<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderActionEvent;
use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
