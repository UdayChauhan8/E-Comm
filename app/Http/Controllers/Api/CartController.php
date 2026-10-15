<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /**
     * Get the user's cart, items, and calculate the total.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Get or create the user's cart
        /** @var Cart $cart */
        $cart = $user->cart()->firstOrCreate([]);

        // Load items with their associated products
        $cart->load([
            'items' => function ($query) {
                $query->where('status', 'active')->with('product');
            }
        ]);

        // Calculate total of active items only
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

        // 1. Ensure product is active
        $product = Product::active()->findOrFail($validated['product_id']);

        // 2. Validate Color against available colors (case-insensitive check)
        $availableColors = $product->colors ?? [];
        $colorMatch = collect($availableColors)->first(function ($c) use ($validated) {
            return strcasecmp($c, $validated['color']) === 0;
        });

        if (!$colorMatch) {
            return response()->json([
                'message' => 'The selected color is not available for this product.',
                'available_colors' => $availableColors,
            ], 422);
        }

        // 3. Validate Size against available sizes
        $availableSizes = $product->sizes ?? [];
        $sizeMatch = collect($availableSizes)->first(function ($s) use ($validated) {
            return (string) $s === (string) $validated['size'];
        });

        if (!$sizeMatch) {
            return response()->json([
                'message' => 'The selected size is not available for this product.',
                'available_sizes' => $availableSizes,
            ], 422);
        }

        // Use the exact canonical casing from the database
        $validated['color'] = $colorMatch;
        $validated['size'] = (string) $sizeMatch;

        /** @var User $user */
        $user = $request->user();
        /** @var Cart $cart */
        $cart = $user->cart()->firstOrCreate([]);

        // 4. Check if this exact item (same product, color, size) is already in the cart
        $cartItem = $cart->items()->where([
            'product_id' => $validated['product_id'],
            'color' => $validated['color'],
            'size' => $validated['size'],
            'status' => 'active',
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
        /** @var Cart $cart */
        $cart = $item->cart;

        // Ensure the item belongs to the authenticated user's cart
        if ($cart->user_id !== $request->user()->id) {
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
        /** @var Cart $cart */
        $cart = $item->cart;

        if ($cart->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $item->delete();

        return response()->json([
            'message' => 'Item removed from cart',
        ]);
    }
}
