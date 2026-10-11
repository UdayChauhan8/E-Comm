<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderActionEvent;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Razorpay\Api\Api;

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
     public function checkout(Request $request): \Illuminate\Http\JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
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
        $api = new Api(env('RAZORPAY_KEY_ID'), env('RAZORPAY_KEY_SECRET'));
        // 3. Create an order on Razorpay servers
        $razorpayOrder = $api->order->create([
            'receipt'         => 'rcpt_' . $user->id . '_' . time(),
            'amount'          => $amountInPaise,
            'currency'        => 'INR',
            'payment_capture' => 1 // Auto capture payment
        ]);
        // 4. Save the order in our local database as "Pending"
        $localOrder = $user->orders()->create([
            'product_name' => 'Cart Checkout', // Simplification for now
            'order_number' => $razorpayOrder['id'], // We store Razorpay's Order ID here
            'amount'       => $totalAmount,
            // (If you have a 'status' column, you would set it to 'pending' here)
        ]);
        return response()->json([
            'message' => 'Checkout initiated',
            'order_id' => $localOrder->id,
            'razorpay_order_id' => $razorpayOrder['id'],
            'amount' => $totalAmount,
            'currency' => 'INR'
        ]);
    }
        /**
     * Verify the cryptographic signature sent by Razorpay after successful payment
     */
    public function verifyPayment(Request $request): \Illuminate\Http\JsonResponse
    {
        $request->validate([
            'razorpay_order_id' => 'required|string',
            'razorpay_payment_id' => 'required|string',
            'razorpay_signature' => 'required|string',
        ]);

        $api = new Api(env('RAZORPAY_KEY_ID'), env('RAZORPAY_KEY_SECRET'));

        try {
            // 1. Verify the signature cryptographically to prevent fraud
            $attributes = [
                'razorpay_order_id'   => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature'  => $request->razorpay_signature
            ];
            
            $api->utility->verifyPaymentSignature($attributes);
            
            // 2. Find the local order
            $order = \App\Models\Order::where('order_number', $request->razorpay_order_id)->firstOrFail();
            
            // (Optional: If you have a status column, update it to 'paid')
            // $order->update(['status' => 'paid']);

            // 3. Dispatch our previously made Order Email Event
            \App\Events\OrderActionEvent::dispatch($order, $request->user(), 'paid');

            // 4. Empty the user's cart since they purchased it
            $request->user()->cart->items()->delete();

            return response()->json([
                'message' => 'Payment successful and verified. Cart cleared.',
            ]);

        } catch (\Razorpay\Api\Errors\SignatureVerificationError $e) {
            // If the signature is invalid, it means the payment failed or was tampered with
            return response()->json([
                'message' => 'Invalid payment signature.',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}
