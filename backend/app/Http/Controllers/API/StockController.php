<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ProductStock;
use App\Models\StockMutation;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index(Request $request)
    {
        $stocks = ProductStock::with(["product", "branch"])
            ->where("branch_id", $request->user()->branch_id)
            ->paginate(20);

        return response()->json([
            "success" => true,
            "data" => $stocks
        ]);
    }

    public function lowStock(Request $request)
    {
        $lowStocks = ProductStock::with("product")
            ->where("branch_id", $request->user()->branch_id)
            ->whereRaw("quantity_on_hand <= minimum_stock")
            ->get();

        return response()->json([
            "success" => true,
            "data" => $lowStocks
        ]);
    }
}
