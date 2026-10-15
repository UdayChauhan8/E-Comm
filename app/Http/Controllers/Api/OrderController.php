<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderActionEvent;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class OrderController extends Controller
{
    // ---------------------------------------------------------------
    // GET /api/orders
    // Show the authenticated user's order history
    // Loads items and payments so the response is complete
    // ---------------------------------------------------------------
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()
            ->orders()
            ->with(['items.product', 'payments']) // eager load so no N+1 query problem
            ->latest()
            ->get();

        return response()->json($orders);
    }

    // ---------------------------------------------------------------
    // GET /api/orders/{order}
    // Show one specific order (must belong to the authenticated user)
    // ---------------------------------------------------------------
    public function show(Request $request, Order $order): JsonResponse
    {
        // Security check: user can only view their own orders
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $order->load(['items.product', 'payments']);

        return response()->json($order);
    }

    // ---------------------------------------------------------------
    // POST /api/checkout
    // Step 1 of payment: user selects cart items, we create the order
    // and a Razorpay payment session
    //
    // Request body:
    //   { "cart_item_ids": [3, 7] }
    //
    // Response:
    //   {
    //     "order_id": 5,
    //     "payment_id": 3,
    //     "razorpay_order_id": "order_TmZn5spttaWh9X",
    //     "amount": 5700.00,
    //     "currency": "INR",
    //     "key": "rzp_test_..."
    //   }
    // ---------------------------------------------------------------
    public function checkout(Request $request): JsonResponse
    {
        // Validate that the frontend sent an array of cart item IDs
        $request->validate([
            'cart_item_ids' => ['required', 'array', 'min:1'],
            'cart_item_ids.*' => ['integer', 'exists:cart_items,id'],
        ]);

        /** @var User $user */
        $user = $request->user();

        // Get the user's cart
        /** @var Cart|null $cart */
        $cart = $user->cart()->first();
        if (!$cart) {
            return response()->json(['message' => 'You have no cart'], 400);
        }

        // Fetch only the SELECTED active items from the user's cart
        // The ->where('status', 'active') ensures we don't re-buy already-ordered items
        $selectedItems = $cart->items()
            ->with('product')
            ->where('status', 'active')
            ->whereIn('id', $request->cart_item_ids)
            ->get();

        if ($selectedItems->isEmpty()) {
            return response()->json(['message' => 'No valid items selected'], 400);
        }

        // Calculate money amounts
        $subtotal = $selectedItems->sum(fn($item) => $item->quantity * $item->product->price);
        $tax = 0;      // add tax logic here later if needed
        $discount = 0;      // add coupon logic here later if needed
        $total = $subtotal - $discount + $tax;

        // Guard: Razorpay requires minimum ₹1.00 (= 100 paise)
        $amountInPaise = (int) round($total * 100);
        if ($amountInPaise < 100) {
            return response()->json(['message' => 'Order amount must be at least ₹1.00'], 400);
        }

        // === CREATE OUR LOCAL ORDER ===
        // We create the order BEFORE calling Razorpay.
        // If Razorpay fails, we can track that the user tried.
        $order = $user->orders()->create([
            'reference' => 'ORD-' . strtoupper(uniqid()),  // e.g. "ORD-1728878400ABCD"
            'currency' => 'INR',
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total_amount' => $total,
            'status' => 'pending',
        ]);

        // === CREATE ORDER ITEMS ===
        // One row per product — this is our permanent record of what was bought
        foreach ($selectedItems as $item) {
            $order->items()->create([
                'product_id' => $item->product_id,
                'color' => $item->color,
                'size' => $item->size,
                'quantity' => $item->quantity,
                'unit_price' => $item->product->price,                   // snapshot!
                'subtotal' => $item->quantity * $item->product->price, // snapshot!
                'status' => 'pending',
            ]);
        }

        // === CREATE OUR LOCAL PAYMENT RECORD ===
        // Status is 'pending' because the user hasn't paid yet.
        // gateway_order_id is null for now — we'll fill it after calling Razorpay.
        $payment = $order->payments()->create([
            'payment_reference' => 'PAY-' . strtoupper(uniqid()),
            'gateway' => 'razorpay',
            'currency' => 'INR',
            'amount' => $total,
            'status' => 'pending',
            'attempted_at' => now(),
        ]);

        // === CALL RAZORPAY API ===
        // We wrap this in try/catch because the Razorpay server might be down
        $key = (string) config('services.razorpay.key');
        $secret = (string) config('services.razorpay.secret');
        $api = new Api($key, $secret);

        try {
            /** @var \Razorpay\Api\Order $orderResource */
            $orderResource = $api->__get('order');

            $razorpayOrder = $orderResource->create([
                // uniqid() is microsecond-based, so even 2 requests in same second get different values
                'receipt' => 'rcpt_' . $user->id . '_' . uniqid(),
                'amount' => $amountInPaise,
                'currency' => 'INR',
                'payment_capture' => 1,   // 1 = auto-capture money immediately after payment
                // notes are stored on Razorpay's Dashboard — useful for support
                'notes' => [
                    'user_id' => (string) $user->id,
                    'user_email' => $user->email,
                    'our_order_ref' => $order->reference,
                    'our_payment_ref' => $payment->payment_reference,
                ],
            ]);
        } catch (\Exception $e) {
            // Razorpay was unreachable or returned an error
            // We mark our payment as failed and return a clean error
            $payment->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);

            // 502 = "my server is fine, but an upstream service I depend on failed"
            return response()->json([
                'message' => 'Payment gateway error. Please try again.',
            ], 502);
        }

        // === SAVE RAZORPAY'S ORDER ID INTO OUR PAYMENT ROW ===
        // Now our payment record knows which Razorpay order it corresponds to
        $payment->update([
            'gateway_order_id' => $razorpayOrder['id'],
        ]);

        // Return everything the frontend needs to open the Razorpay widget
        return response()->json([
            'order_id' => $order->id,
            'payment_id' => $payment->id,
            'razorpay_order_id' => $razorpayOrder['id'],
            'amount' => $total,
            'currency' => 'INR',
            'key' => $key, // public key — safe to send to browser
        ]);
    }

    // ---------------------------------------------------------------
    // POST /api/payment/verify
    // Step 2 of payment: user paid in Razorpay widget, now we verify
    //
    // Request body (sent by Razorpay's JavaScript SDK automatically):
    //   {
    //     "razorpay_order_id":   "order_TmZn5spttaWh9X",
    //     "razorpay_payment_id": "pay_TmP2abc123xyz",
    //     "razorpay_signature":  "abc123def456..."
    //   }
    // ---------------------------------------------------------------
    public function verifyPayment(Request $request): JsonResponse
    {
        $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        // === FIND THE PAYMENT RECORD ===
        // We look up by gateway_order_id (Razorpay's ID) AND the order must belong to
        // the authenticated user. This prevents User B from verifying User A's payment.
        $payment = Payment::whereHas('order', function ($q) use ($request) {
            $q->where('user_id', $request->user()->id);
        })
            ->where('gateway_order_id', $request->razorpay_order_id)
            ->first();

        if (!$payment) {
            return response()->json(['message' => 'Payment not found'], 404);
        }

        // === IDEMPOTENCY CHECK ===
        // If this payment was already verified (status = captured), don't process again.
        // This prevents the "hit verify twice, get 2 emails" bug we fixed before.
        if ($payment->status === 'captured') {
            return response()->json([
                'message' => 'This payment has already been verified and confirmed.',
            ], 400);
        }

        $key = (string) config('services.razorpay.key');
        $secret = (string) config('services.razorpay.secret');
        $api = new Api($key, $secret);

        try {
            // === CRYPTOGRAPHIC SIGNATURE VERIFICATION ===
            // Razorpay generates a signature using:
            //   HMAC_SHA256( razorpay_order_id + "|" + razorpay_payment_id , YOUR_SECRET )
            //
            // We recalculate the same hash on our server.
            // If our hash matches the one Razorpay sent → payment is genuine.
            // If they don't match → someone tampered with the request → reject it.
            //
            // This is THE most important security check. Without it, anyone could
            // fake a payment by just sending a POST request to this endpoint.
            /** @var \Razorpay\Api\Utility $utility */
            $utility = $api->__get('utility');
            $utility->verifyPaymentSignature([
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature,
            ]);

        } catch (SignatureVerificationError $e) {
            // Signature mismatch = fraud attempt or corrupted data
            // Mark the payment as failed so we have a record
            $payment->update([
                'status' => 'failed',
                'failure_reason' => 'Signature verification failed: ' . $e->getMessage(),
            ]);

            return response()->json(['message' => 'Invalid payment signature.'], 400);
        }

        // === SIGNATURE PASSED — UPDATE RECORDS ===
        // Now we know the payment is genuine. Update all three layers:

        // 1. Update the payment row
        $payment->update([
            'gateway_payment_id' => $request->razorpay_payment_id,  // Razorpay's payment ID
            'status' => 'captured',
            'captured_at' => now(),
        ]);

        // 2. Update the order status from 'pending' to 'processing'
        //    (money received, now preparing the order for shipping)
        $order = $payment->order;
        $order->update(['status' => 'processing']);

        // 3. Mark the specific cart items as 'ordered' so they disappear from the active cart
        //    Other cart items (not part of this order) remain 'active'
        $purchasedProductIds = $order->items->pluck('product_id')->toArray();
        /** @var Cart|null $cart */
        $cart = $request->user()->cart;
        if ($cart) {
            $cart->items()
                ->where('status', 'active')
                ->whereIn('product_id', $purchasedProductIds)
                ->update(['status' => 'ordered']);
        }

        // 4. Send confirmation email (load items and products for the email template)
        $order->load('items.product');
        OrderActionEvent::dispatch($order, $request->user(), 'paid');

        return response()->json([
            'message' => 'Payment verified successfully.',
            'order_reference' => $order->reference,
            'amount_paid' => $payment->amount,
        ]);
    }
}