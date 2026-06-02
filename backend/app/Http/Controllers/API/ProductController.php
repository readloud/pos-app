<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStock;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();

        // Search
        if ($request->has("search")) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where("name", "like", "%{$search}%")
                  ->orWhere("sku", "like", "%{$search}%")
                  ->orWhere("barcode", "like", "%{$search}%");
            });
        }

        // Filter by category
        if ($request->has("category")) {
            $query->where("category", $request->category);
        }

        // Filter by stock
        if ($request->has("in_stock")) {
            $query->whereHas("stock", function($q) use ($request) {
                $q->where("quantity_on_hand", ">", 0);
            });
        }

        $products = $query->with(["stock" => function($q) use ($request) {
            $q->where("branch_id", $request->user()->branch_id ?? 1);
        }])->paginate(20);

        return response()->json([
            "success" => true,
            "data" => $products
        ]);
    }

    public function show($id)
    {
        $product = Product::with("stock")->findOrFail($id);

        return response()->json([
            "success" => true,
            "data" => $product
        ]);
    }

    public function search(Request $request)
    {
        $request->validate([
            "query" => "required|string|min:2"
        ]);

        $products = Product::where("name", "like", "%{$request->query}%")
            ->orWhere("sku", "like", "%{$request->query}%")
            ->orWhere("barcode", "like", "%{$request->query}%")
            ->limit(10)
            ->get();

        return response()->json([
            "success" => true,
            "data" => $products
        ]);
    }

    public function getByBarcode($barcode)
    {
        $product = Product::where("barcode", $barcode)->first();

        if (!$product) {
            return response()->json([
                "success" => false,
                "message" => "Product not found"
            ], 404);
        }

        return response()->json([
            "success" => true,
            "data" => $product
        ]);
    }
}
