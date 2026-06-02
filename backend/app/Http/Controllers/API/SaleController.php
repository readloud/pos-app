<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Cart;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Receivable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index(Request $request)
    {
        $sales = Sale::with(["customer", "user", "branch"])
            ->where("branch_id", $request->user()->branch_id)
            ->orderBy("created_at", "desc")
            ->paginate(20);

        return response()->json([
            "success" => true,
            "data" => $sales
        ]);
    }

    public function show($id)
    {
        $sale = Sale::with(["customer", "user", "branch", "items.product"])
            ->findOrFail($id);

        return response()->json([
            "success" => true,
            "data" => $sale
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            "customer_id" => "nullable|exists:customers,id",
            "payment_method" => "required|in:cash,bank_transfer,credit_card,e_wallet",
            "items" => "required|array|min:1",
            "items.*.product_id" => "required|exists:products,id",
            "items.*.quantity" => "required|numeric|min:1",
            "items.*.unit_price" => "required|numeric"
        ]);

        DB::beginTransaction();

        try {
            // Calculate totals
            $subtotal = 0;
            foreach ($request->items as $item) {
                $subtotal += $item["quantity"] * $item["unit_price"];
            }

            $discount = $request->discount ?? 0;
            $tax = $request->tax ?? 0;
            $total = $subtotal - $discount + $tax;

            // Generate invoice number
            $invoiceNumber = "INV-" . date("Ymd") . "-" . str_pad(Sale::count() + 1, 4, "0", STR_PAD_LEFT);

            // Create sale
            $sale = Sale::create([
                "invoice_number" => $invoiceNumber,
                "customer_id" => $request->customer_id,
                "branch_id" => $request->user()->branch_id,
                "user_id" => $request->user()->id,
                "sale_date" => now(),
                "payment_status" => $request->payment_method === "cash" ? "paid" : "pending",
                "delivery_status" => "pending",
                "subtotal" => $subtotal,
                "discount_amount" => $discount,
                "tax_amount" => $tax,
                "total_amount" => $total,
                "notes" => $request->notes
            ]);

            // Create sale items and update stock
            foreach ($request->items as $item) {
                SaleItem::create([
                    "sale_id" => $sale->id,
                    "product_id" => $item["product_id"],
                    "quantity" => $item["quantity"],
                    "unit_price" => $item["unit_price"],
                    "discount" => $item["discount"] ?? 0,
                    "subtotal" => $item["quantity"] * $item["unit_price"]
                ]);

                // Update stock
                $stock = ProductStock::where([
                    "product_id" => $item["product_id"],
                    "branch_id" => $request->user()->branch_id
                ])->first();

                if ($stock) {
                    $stock->decrement("quantity_on_hand", $item["quantity"]);
                }
            }

            // Create receivable if not paid in full
            if ($sale->payment_status !== "paid") {
                Receivable::create([
                    "sale_id" => $sale->id,
                    "customer_id" => $request->customer_id,
                    "amount_due" => $total,
                    "due_date" => now()->addDays(30),
                    "status" => "pending"
                ]);
            }

            // Clear cart
            Cart::where("user_id", $request->user()->id)
                ->where("status", "active")
                ->delete();

            DB::commit();

            return response()->json([
                "success" => true,
                "message" => "Sale completed successfully",
                "data" => $sale->load("items.product")
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                "success" => false,
                "message" => "Failed to process sale: " . $e->getMessage()
            ], 500);
        }
    }

    public function invoice($id)
    {
        $sale = Sale::with(["customer", "user", "branch", "items.product"])
            ->findOrFail($id);

        return response()->json([
            "success" => true,
            "data" => $sale
        ]);
    }
}
