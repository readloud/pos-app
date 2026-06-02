<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Product;
use App\Models\Receivable;
use App\Models\ProductStock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard statistics
     */
    public function stats(Request $request)
    {
        $branchId = $request->user()->branch_id;
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        
        // Today's sales
        $todaySales = Sale::where('branch_id', $branchId)
            ->whereDate('sale_date', $today)
            ->sum('total_amount');
            
        // Yesterday's sales for comparison
        $yesterdaySales = Sale::where('branch_id', $branchId)
            ->whereDate('sale_date', $yesterday)
            ->sum('total_amount');
            
        $salesGrowth = $yesterdaySales > 0 
            ? (($todaySales - $yesterdaySales) / $yesterdaySales) * 100 
            : 0;
            
        // Total transactions today
        $totalTransactions = Sale::where('branch_id', $branchId)
            ->whereDate('sale_date', $today)
            ->count();
            
        // Low stock count
        $lowStockCount = ProductStock::where('branch_id', $branchId)
            ->whereRaw('quantity_on_hand <= minimum_stock')
            ->count();
            
        // Outstanding receivables
        $outstandingReceivables = Receivable::whereHas('sale', function($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            })
            ->where('status', '!=', 'paid')
            ->sum(DB::raw('amount_due - amount_paid'));
            
        // Last 7 days sales for chart
        $dailySales = Sale::where('branch_id', $branchId)
            ->whereBetween('sale_date', [now()->subDays(6), now()])
            ->select(
                DB::raw('DATE(sale_date) as date'),
                DB::raw('SUM(total_amount) as total_amount'),
                DB::raw('COUNT(*) as transaction_count')
            )
            ->groupBy(DB::raw('DATE(sale_date)'))
            ->orderBy('date')
            ->get();
            
        // Top products
        $topProducts = Sale::join('sale_items', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->where('sales.branch_id', $branchId)
            ->whereBetween('sales.sale_date', [now()->subDays(30), now()])
            ->select(
                'products.id',
                'products.name',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.subtotal) as total_revenue')
            )
            ->groupBy('products.id', 'products.name')
            ->orderBy('total_quantity', 'desc')
            ->limit(5)
            ->get();
            
        // Recent sales
        $recentSales = Sale::with('customer')
            ->where('branch_id', $branchId)
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();
            
        // Today's sales by hour
        $salesByHour = Sale::where('branch_id', $branchId)
            ->whereDate('sale_date', $today)
            ->select(
                DB::raw('EXTRACT(HOUR FROM created_at) as hour'),
                DB::raw('SUM(total_amount) as total')
            )
            ->groupBy(DB::raw('EXTRACT(HOUR FROM created_at)'))
            ->orderBy('hour')
            ->get();
            
        // Payment method distribution
        $paymentMethods = DB::table('payments')
            ->join('sales', 'payments.reference_id', '=', 'sales.id')
            ->where('payments.payment_type', 'sale')
            ->where('sales.branch_id', $branchId)
            ->whereDate('payments.payment_date', $today)
            ->select(
                'payments.payment_method',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(payments.amount) as total')
            )
            ->groupBy('payments.payment_method')
            ->get();
            
        // Stock value summary
        $stockValue = ProductStock::where('branch_id', $branchId)
            ->join('products', 'product_stocks.product_id', '=', 'products.id')
            ->select(DB::raw('SUM(quantity_on_hand * purchase_price) as total_value'))
            ->first();
            
        return response()->json([
            'data' => [
                'today_sales' => $todaySales,
                'sales_growth' => round($salesGrowth, 2),
                'total_transactions' => $totalTransactions,
                'low_stock_count' => $lowStockCount,
                'outstanding_receivables' => $outstandingReceivables,
                'stock_value' => $stockValue->total_value ?? 0,
                'daily_sales' => $dailySales,
                'top_products' => $topProducts,
                'recent_sales' => $recentSales,
                'sales_by_hour' => $salesByHour,
                'payment_methods' => $paymentMethods
            ]
        ]);
    }
    
    /**
     * Get quick actions menu
     */
    public function quickActions(Request $request)
    {
        $user = $request->user();
        $actions = [];
        
        // Based on role, provide different quick actions
        if (in_array($user->role->name, ['cashier', 'admin', 'manager'])) {
            $actions[] = [
                'title' => 'New Sale',
                'icon' => 'shopping-cart',
                'link' => '/pos',
                'color' => 'blue'
            ];
        }
        
        if (in_array($user->role->name, ['admin', 'manager'])) {
            $actions[] = [
                'title' => 'Add Product',
                'icon' => 'plus-circle',
                'link' => '/products/create',
                'color' => 'green'
            ];
            
            $actions[] = [
                'title' => 'Stock Transfer',
                'icon' => 'arrows-right-left',
                'link' => '/stock/transfer',
                'color' => 'purple'
            ];
        }
        
        if (in_array($user->role->name, ['admin', 'manager', 'sales'])) {
            $actions[] = [
                'title' => 'View Receivables',
                'icon' => 'currency-dollar',
                'link' => '/receivables',
                'color' => 'yellow'
            ];
        }
        
        return response()->json([
            'data' => $actions
        ]);
    }
    
    /**
     * Get notification center data
     */
    public function notifications(Request $request)
    {
        $branchId = $request->user()->branch_id;
        $notifications = [];
        
        // Low stock alerts
        $lowStockProducts = ProductStock::with('product')
            ->where('branch_id', $branchId)
            ->whereRaw('quantity_on_hand <= minimum_stock')
            ->limit(5)
            ->get();
            
        foreach ($lowStockProducts as $stock) {
            $notifications[] = [
                'type' => 'warning',
                'title' => 'Low Stock Alert',
                'message' => "{$stock->product->name} is running low. Current stock: {$stock->quantity_on_hand}",
                'link' => "/products/{$stock->product_id}",
                'created_at' => now()
            ];
        }
        
        // Overdue receivables
        $overdueReceivables = Receivable::with(['customer', 'sale'])
            ->whereHas('sale', function($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            })
            ->where('status', '!=', 'paid')
            ->where('due_date', '<', now())
            ->limit(5)
            ->get();
            
        foreach ($overdueReceivables as $receivable) {
            $daysOverdue = now()->diffInDays($receivable->due_date);
            $notifications[] = [
                'type' => 'danger',
                'title' => 'Overdue Payment',
                'message' => "Payment from {$receivable->customer->name} is {$daysOverdue} days overdue. Invoice: {$receivable->sale->invoice_number}",
                'link' => "/receivables",
                'created_at' => $receivable->due_date
            ];
        }
        
        // Sort by date
        usort($notifications, function($a, $b) {
            return $b['created_at'] <=> $a['created_at'];
        });
        
        return response()->json([
            'data' => array_slice($notifications, 0, 10),
            'total' => count($notifications)
        ]);
    }
    
    /**
     * Get performance metrics
     */
    public function performance(Request $request)
    {
        $branchId = $request->user()->branch_id;
        $currentMonth = now()->startOfMonth();
        $lastMonth = now()->subMonth()->startOfMonth();
        
        // Current month vs last month
        $currentMonthSales = Sale::where('branch_id', $branchId)
            ->where('sale_date', '>=', $currentMonth)
            ->sum('total_amount');
            
        $lastMonthSales = Sale::where('branch_id', $branchId)
            ->whereBetween('sale_date', [$lastMonth, $currentMonth->copy()->subDay()])
            ->sum('total_amount');
            
        $salesGrowth = $lastMonthSales > 0 
            ? (($currentMonthSales - $lastMonthSales) / $lastMonthSales) * 100 
            : 0;
            
        // Average transaction value
        $avgTransaction = Sale::where('branch_id', $branchId)
            ->whereMonth('sale_date', now()->month)
            ->avg('total_amount');
            
        // Top performing cashiers
        $topCashiers = Sale::with('user')
            ->where('branch_id', $branchId)
            ->whereMonth('sale_date', now()->month)
            ->select(
                'user_id',
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(total_amount) as total_sales')
            )
            ->groupBy('user_id')
            ->orderBy('total_sales', 'desc')
            ->limit(5)
            ->get();
            
        return response()->json([
            'data' => [
                'current_month_sales' => $currentMonthSales,
                'last_month_sales' => $lastMonthSales,
                'sales_growth' => round($salesGrowth, 2),
                'average_transaction' => round($avgTransaction, 2),
                'top_cashiers' => $topCashiers
            ]
        ]);
    }
}