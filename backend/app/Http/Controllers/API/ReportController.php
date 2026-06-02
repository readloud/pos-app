<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Receivable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * Sales report
     */
    public function salesReport(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'branch_id' => 'nullable|exists:branches,id',
        ]);
        
        $query = Sale::with('branch')
            ->whereBetween('sale_date', [$request->date_from, $request->date_to]);
            
        if ($request->has('branch_id') && $request->branch_id) {
            $query->where('branch_id', $request->branch_id);
        } else if (auth()->user()->branch_id) {
            $query->where('branch_id', auth()->user()->branch_id);
        }
        
        $summary = [
            'total_sales' => $query->count(),
            'total_revenue' => $query->sum('total_amount'),
            'total_tax' => $query->sum('tax_amount'),
            'total_discount' => $query->sum('discount_amount'),
            'average_sale' => $query->avg('total_amount') ?? 0,
        ];
        
        $dailySales = $query->select(
                DB::raw('DATE(sale_date) as date'),
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('SUM(total_amount) as total_amount')
            )
            ->groupBy(DB::raw('DATE(sale_date)'))
            ->orderBy('date')
            ->get();
            
        $paymentMethods = DB::table('payments')
            ->join('sales', 'payments.reference_id', '=', 'sales.id')
            ->where('payments.payment_type', 'sale')
            ->whereBetween('sales.sale_date', [$request->date_from, $request->date_to])
            ->select(
                'payments.payment_method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(payments.amount) as total')
            )
            ->groupBy('payments.payment_method')
            ->get();
            
        return response()->json([
            'summary' => $summary,
            'daily_sales' => $dailySales,
            'payment_methods' => $paymentMethods,
        ]);
    }
    
    /**
     * Top products report
     */
    public function topProducts(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'limit' => 'integer|min:1|max:100',
        ]);
        
        $topProducts = SaleItem::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->whereBetween('sales.sale_date', [$request->date_from, $request->date_to])
            ->select(
                'products.id',
                'products.name',
                'products.sku',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.subtotal) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderBy('total_quantity', 'desc')
            ->limit($request->get('limit', 10))
            ->get();
            
        return response()->json(['data' => $topProducts]);
    }
    
    /**
     * Aging receivables report
     */
    public function agingReceivables(Request $request)
    {
        $receivables = Receivable::with(['customer', 'sale'])
            ->where('status', '!=', 'paid')
            ->get()
            ->map(function ($receivable) {
                $daysOverdue = now()->diffInDays($receivable->due_date, false);
                $aging = 'current';
                
                if ($daysOverdue > 0) {
                    if ($daysOverdue <= 30) $aging = '1-30 days';
                    else if ($daysOverdue <= 60) $aging = '31-60 days';
                    else if ($daysOverdue <= 90) $aging = '61-90 days';
                    else $aging = '>90 days';
                }
                
                return [
                    'customer_name' => $receivable->customer->name,
                    'invoice_number' => $receivable->sale->invoice_number,
                    'due_date' => $receivable->due_date,
                    'amount_due' => $receivable->amount_due,
                    'amount_paid' => $receivable->amount_paid,
                    'outstanding' => $receivable->amount_due - $receivable->amount_paid,
                    'days_overdue' => max(0, $daysOverdue),
                    'aging' => $aging,
                ];
            });
            
        $summary = [
            'total_outstanding' => $receivables->sum('outstanding'),
            'current' => $receivables->where('aging', 'current')->sum('outstanding'),
            'days_1_30' => $receivables->where('aging', '1-30 days')->sum('outstanding'),
            'days_31_60' => $receivables->where('aging', '31-60 days')->sum('outstanding'),
            'days_61_90' => $receivables->where('aging', '61-90 days')->sum('outstanding'),
            'days_90_plus' => $receivables->where('aging', '>90 days')->sum('outstanding'),
        ];
        
        return response()->json([
            'summary' => $summary,
            'details' => $receivables,
        ]);
    }
    
    /**
     * Stock report
     */
    public function stockReport(Request $request)
    {
        $query = Product::with(['stocks' => function($q) {
            $q->where('branch_id', auth()->user()->branch_id);
        }]);
        
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%')
                ->orWhere('sku', 'like', '%' . $request->search . '%');
        }
        
        $products = $query->paginate($request->get('per_page', 20));
        
        $products->getCollection()->transform(function ($product) {
            $stock = $product->stocks->first();
            $product->current_stock = $stock ? $stock->quantity_on_hand : 0;
            $product->minimum_stock = $stock ? $stock->minimum_stock : $product->min_stock;
            $product->stock_value = ($product->current_stock * $product->purchase_price);
            return $product;
        });
        
        return response()->json([
            'data' => $products,
            'summary' => [
                'total_products' => $products->total(),
                'total_stock_value' => $products->getCollection()->sum('stock_value'),
                'low_stock_products' => $products->getCollection()->filter(function($p) {
                    return $p->current_stock <= $p->minimum_stock;
                })->count(),
            ]
        ]);
    }
}