<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\Receivable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers
     */
    public function index(Request $request)
    {
        $query = Customer::query();
        
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
        
        if ($request->has('has_credit')) {
            if ($request->boolean('has_credit')) {
                $query->where('credit_limit', '>', 0);
            } else {
                $query->where('credit_limit', 0);
            }
        }
        
        $customers = $query->orderBy('name')
            ->paginate($request->get('per_page', 15));
            
        // Add outstanding balance for each customer
        $customers->getCollection()->transform(function($customer) {
            $customer->outstanding_balance = $customer->receivables()
                ->where('status', '!=', 'paid')
                ->sum(DB::raw('amount_due - amount_paid'));
            return $customer;
        });
        
        return response()->json($customers);
    }
    
    /**
     * Store a newly created customer
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|unique:customers',
            'address' => 'nullable|string',
            'credit_limit' => 'numeric|min:0',
            'credit_days' => 'integer|min:0'
        ]);
        
        $customer = Customer::create([
            'code' => $this->generateCustomerCode(),
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'address' => $request->address,
            'credit_limit' => $request->credit_limit ?? 0,
            'credit_days' => $request->credit_days ?? 0,
            'is_active' => true
        ]);
        
        return response()->json([
            'message' => 'Customer created successfully',
            'data' => $customer
        ], 201);
    }
    
    /**
     * Display the specified customer
     */
    public function show($id)
    {
        $customer = Customer::with(['sales', 'receivables'])->findOrFail($id);
        
        // Calculate statistics
        $totalSales = $customer->sales()->sum('total_amount');
        $totalPaid = $customer->payments()->sum('amount');
        $outstanding = $customer->receivables()
            ->where('status', '!=', 'paid')
            ->sum(DB::raw('amount_due - amount_paid'));
            
        $recentSales = $customer->sales()
            ->with('branch')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
            
        return response()->json([
            'data' => $customer,
            'statistics' => [
                'total_sales' => $totalSales,
                'total_paid' => $totalPaid,
                'outstanding_balance' => $outstanding,
                'total_transactions' => $customer->sales()->count()
            ],
            'recent_sales' => $recentSales
        ]);
    }
    
    /**
     * Update the specified customer
     */
    public function update(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);
        
        $request->validate([
            'name' => 'string|max:255',
            'phone' => 'string|max:20',
            'email' => 'email|unique:customers,email,' . $id,
            'credit_limit' => 'numeric|min:0',
            'credit_days' => 'integer|min:0',
            'is_active' => 'boolean'
        ]);
        
        $customer->update($request->only([
            'name', 'email', 'phone', 'address', 'credit_limit', 'credit_days', 'is_active'
        ]));
        
        return response()->json([
            'message' => 'Customer updated successfully',
            'data' => $customer
        ]);
    }
    
    /**
     * Remove the specified customer
     */
    public function destroy($id)
    {
        $customer = Customer::findOrFail($id);
        
        // Check if customer has any transactions
        if ($customer->sales()->exists() || $customer->receivables()->exists()) {
            return response()->json([
                'message' => 'Cannot delete customer with existing transactions'
            ], 422);
        }
        
        $customer->delete();
        
        return response()->json([
            'message' => 'Customer deleted successfully'
        ]);
    }
    
    /**
     * Get customer's receivable aging
     */
    public function aging($id)
    {
        $customer = Customer::findOrFail($id);
        
        $receivables = $customer->receivables()
            ->with('sale')
            ->where('status', '!=', 'paid')
            ->get()
            ->map(function($receivable) {
                $daysOverdue = now()->diffInDays($receivable->due_date, false);
                $outstanding = $receivable->amount_due - $receivable->amount_paid;
                
                $aging = 'current';
                if ($daysOverdue > 0) {
                    if ($daysOverdue <= 30) $aging = '1-30 days';
                    else if ($daysOverdue <= 60) $aging = '31-60 days';
                    else if ($daysOverdue <= 90) $aging = '61-90 days';
                    else $aging = '>90 days';
                }
                
                return [
                    'invoice_number' => $receivable->sale->invoice_number,
                    'date' => $receivable->sale->sale_date,
                    'due_date' => $receivable->due_date,
                    'amount_due' => $receivable->amount_due,
                    'amount_paid' => $receivable->amount_paid,
                    'outstanding' => $outstanding,
                    'days_overdue' => max(0, $daysOverdue),
                    'aging' => $aging
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
            'customer' => $customer,
            'summary' => $summary,
            'details' => $receivables
        ]);
    }
    
    /**
     * Get customer payment history
     */
    public function paymentHistory($id, Request $request)
    {
        $customer = Customer::findOrFail($id);
        
        $payments = $customer->payments()
            ->with(['sale'])
            ->orderBy('payment_date', 'desc')
            ->paginate($request->get('per_page', 20));
            
        return response()->json($payments);
    }
    
    /**
     * Generate customer code
     */
    private function generateCustomerCode()
    {
        $lastCustomer = Customer::orderBy('id', 'desc')->first();
        $number = $lastCustomer ? intval(substr($lastCustomer->code, 3)) + 1 : 1;
        
        return 'CUS' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Export customers to CSV
     */
    public function export()
    {
        $customers = Customer::all();
        
        $filename = 'customers_' . date('Y-m-d_His') . '.csv';
        $handle = fopen('php://temp', 'w');
        
        fputcsv($handle, ['Code', 'Name', 'Email', 'Phone', 'Credit Limit', 'Credit Days', 'Status']);
        
        foreach ($customers as $customer) {
            fputcsv($handle, [
                $customer->code,
                $customer->name,
                $customer->email,
                $customer->phone,
                $customer->credit_limit,
                $customer->credit_days,
                $customer->is_active ? 'Active' : 'Inactive'
            ]);
        }
        
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        
        return response($csv, 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}