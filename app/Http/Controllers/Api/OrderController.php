<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderActionEvent;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;
use Razorpay\Api\Utility;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = $request->user()->orders()->latest()->get();

        return response()->json($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_name' => ['required', 'string', 'max:255'],
            'order_number' => ['required', 'string', 'max:255', 'unique:orders'],
            'amount' => ['required', 'numeric', 'min:0.01'],
        ]);

        /** @var Order $order */
        $order = $request->user()->orders()->create($validated);

        OrderActionEvent::dispatch($order, $request->user(), 'created');

        return response()->json([
            'message' => 'Order created successfully',
            'order' => $order,
        ], 201);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($order);
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'product_name' => ['sometimes', 'string', 'max:255'],
            'order_number' => ['sometimes', 'string', 'max:255', 'unique:orders,order_number,'.$order->id],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
        ]);

        $order->update($validated);

        OrderActionEvent::dispatch($order, $request->user(), 'updated');

        return response()->json([
            'message' => 'Order updated successfully',
            'order' => $order,
        ]);
    }

    public function destroy(Request $request, Order $order): JsonResponse
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        OrderActionEvent::dispatch($order, $request->user(), 'deleted');

        $order->delete();

        return response()->json(['message' => 'Order deleted successfully']);
    }

    public function checkout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var Cart|null $cart */
        $cart = $user->cart()->with('items.product')->first();
        if (! $cart || $cart->items->isEmpty()) {
            return response()->json(['message' => 'Your cart is empty'], 400);
        }
        // 1. Calculate the total in INR (Rupees)
        $totalAmount = $cart->items->sum(function ($item) {
            return $item->quantity * $item->product->price;
        });
        // Razorpay expects the amount in PAISE (smallest currency unit). So multiply by 100.
        $amountInPaise = (int) round($totalAmount * 100);
        // 2. Initialize Razorpay API
        $key = (string) config('services.razorpay.key');
        $secret = (string) config('services.razorpay.secret');
        $api = new Api($key, $secret);

        /** @var \Razorpay\Api\Order $orderResource */
        $orderResource = $api->__get('order');

        // 3. Create an order on Razorpay servers
        $razorpayOrder = $orderResource->create([
            'receipt' => 'rcpt_'.$user->id.'_'.time(),
            'amount' => $amountInPaise,
            'currency' => 'INR',
            'payment_capture' => 1, // Auto capture payment
        ]);
        // 4. Save the order in our local database as "Pending"
        $localOrder = $user->orders()->create([
            'product_name' => 'Cart Checkout',
            'order_number' => $razorpayOrder['id'],
            'amount' => $totalAmount,
            'status' => 'pending',
        ]);

        return response()->json([
            'message' => 'Checkout initiated',
            'order_id' => $localOrder->id,
            'razorpay_order_id' => $razorpayOrder['id'],
            'amount' => $totalAmount,
            'currency' => 'INR',
        ]);
    }

    /**
     * Verify the cryptographic signature sent by Razorpay after successful payment
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $key = (string) config('services.razorpay.key');
        $secret = (string) config('services.razorpay.secret');
        $api = new Api($key, $secret);

        try {
            // 1. Find the local order first
            $order = Order::where('order_number', $request->razorpay_order_id)->firstOrFail();

            // 2. CHECK IF ALREADY PAID! If yes, STOP and DO NOT send email!
            if ($order->status === 'paid') {
                return response()->json([
                    'message' => 'Payment for this order has already been completed.',
                ], 400);
            }

            // 3. Verify the signature cryptographically to prevent fraud
            $attributes = [
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature,
            ];

            /** @var Utility $utility */
            $utility = $api->__get('utility');
            $utility->verifyPaymentSignature($attributes);

            // 4. Update the order status to PAID
            $order->update(['status' => 'paid']);

            // 5. Dispatch the email event (only sent once!)
            OrderActionEvent::dispatch($order, $request->user(), 'paid');

            // 6. Empty the user's cart
            /** @var Cart|null $cart */
            $cart = $request->user()->cart;
            $cart?->items()->delete();

            return response()->json([
                'message' => 'Payment successful and verified. Cart cleared.',
            ]);

        } catch (SignatureVerificationError $e) {
            // If the signature is invalid, it means the payment failed or was tampered with
            return response()->json([
                'message' => 'Invalid payment signature.',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
