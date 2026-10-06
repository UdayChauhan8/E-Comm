<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::active();

        // Filter by gender: ?gender=Men
        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        // Filter by style category: ?style=Running
        if ($request->filled('style')) {
            $query->where('style_category', $request->style);
        }

        // Search by title: ?search=nike
        if ($request->filled('search')) {
            $query->where('title', 'like', '%'.$request->search.'%');
        }

        // Sort: ?sort=price_asc or ?sort=price_desc or ?sort=newest
        match ($request->sort) {
            'price_asc' => $query->orderBy('price', 'asc'),
            'price_desc' => $query->orderBy('price', 'desc'),
            'newest' => $query->latest(),
            default => $query->latest(),
        };

        // Only select fields needed for listing (NOT all columns)
        // This is faster — don't fetch description, colors, sizes etc. for list view
        $products = $query
            ->select(['id', 'title', 'slug', 'price', 'thumbnail', 'style_category', 'gender'])
            ->paginate(12);

        return response()->json([
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }
}
