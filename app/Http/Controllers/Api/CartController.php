<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Get the user's cart, items, and calculate the total.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        
        // Get or create the user's cart
        $cart = $user->cart()->firstOrCreate([]);
        
        // Load items with their associated products
        $cart->load('items.product');

        // Calculate total cart value
        $total = $cart->items->sum(function ($item) {
            return $item->quantity * $item->product->price;
        });

        return response()->json([
            'cart' => $cart,
            'total' => round($total, 2),
        ]);
    }

    /**
     * Add a product to the cart.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'color' => ['required', 'string'],
            'size' => ['required', 'string'],
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        // Ensure product is active
        $product = Product::active()->findOrFail($validated['product_id']);

        /** @var \App\Models\User $user */
        $user = $request->user();
        $cart = $user->cart()->firstOrCreate([]);

        // Check if this exact item (same product, color, size) is already in the cart
        $cartItem = $cart->items()->where([
            'product_id' => $validated['product_id'],
            'color' => $validated['color'],
            'size' => $validated['size'],
        ])->first();

        if ($cartItem) {
            // Increment quantity if it already exists
            $cartItem->increment('quantity', $validated['quantity']);
        } else {
            // Create new cart item
            $cart->items()->create($validated);
        }

        return response()->json([
            'message' => 'Item added to cart',
        ]);
    }

    /**
     * Update cart item quantity.
     */
    public function update(Request $request, CartItem $item): JsonResponse
    {
        // Ensure the item belongs to the authenticated user's cart
        if ($item->cart->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $item->update($validated);

        return response()->json([
            'message' => 'Cart item updated',
        ]);
    }

    /**
     * Remove an item from the cart.
     */
    public function destroy(Request $request, CartItem $item): JsonResponse
    {
        if ($item->cart->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $item->delete();

        return response()->json([
            'message' => 'Item removed from cart',
        ]);
    }
}