<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Payable;
use App\Models\Product;
use App\Models\ProductStock;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseController extends Controller
{
    protected $stockService;
    
    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }
    
    /**
     * Display a listing of purchases
     */
    public function index(Request $request)
    {
        $query = Purchase::with(['supplier', 'branch', 'user'])
            ->where('branch_id', $request->user()->branch_id);
            
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('date_from')) {
            $query->whereDate('order_date', '>=', $request->date_from);
        }
        
        if ($request->has('date_to')) {
            $query->whereDate('order_date', '<=', $request->date_to);
        }
        
        $purchases = $query->orderBy('id', 'desc')
            ->paginate($request->get('per_page', 15));
            
        return response()->json($purchases);
    }
    
    /**
     * Store a newly created purchase order
     */
    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'expected_date' => 'nullable|date|after:order_date',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:1',
            'items.*.unit_price' => 'required|numeric|min:0'
        ]);
        
        DB::beginTransaction();
        
        try {
            $subtotal = collect($request->items)->sum(function($item) {
                return $item['quantity'] * $item['unit_price'];
            });
            
            $tax = $subtotal * 0.11; // 11% VAT
            $total = $subtotal + $tax;
            
            $purchase = Purchase::create([
                'po_number' => $this->generatePONumber(),
                'supplier_id' => $request->supplier_id,
                'branch_id' => $request->user()->branch_id,
                'user_id' => $request->user()->id,
                'order_date' => $request->order_date,
                'expected_date' => $request->expected_date,
                'status' => 'ordered',
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'total_amount' => $total,
                'notes' => $request->notes
            ]);
            
            foreach ($request->items as $item) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'received_quantity' => 0,
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['quantity'] * $item['unit_price']
                ]);
            }
            
            // Create payable
            Payable::create([
                'purchase_id' => $purchase->id,
                'supplier_id' => $request->supplier_id,
                'amount' => $total,
                'amount_paid' => 0,
                'due_date' => now()->addDays(30),
                'status' => 'pending'
            ]);
            
            DB::commit();
            
            return response()->json([
                'message' => 'Purchase order created successfully',
                'data' => $purchase->load('items', 'supplier')
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Receive goods from purchase order
     */
    public function receive(Request $request, $id)
    {
        $purchase = Purchase::with('items')->findOrFail($id);
        
        $request->validate([
            'items' => 'required|array',
            'items.*.purchase_item_id' => 'required|exists:purchase_items,id',
            'items.*.received_quantity' => 'required|numeric|min:0'
        ]);
        
        DB::beginTransaction();
        
        try {
            foreach ($request->items as $itemData) {
                $purchaseItem = PurchaseItem::findOrFail($itemData['purchase_item_id']);
                
                if ($itemData['received_quantity'] > $purchaseItem->quantity - $purchaseItem->received_quantity) {
                    throw new \Exception("Received quantity exceeds ordered quantity");
                }
                
                $purchaseItem->received_quantity += $itemData['received_quantity'];
                $purchaseItem->save();
                
                // Update stock
                $this->stockService->updateStock(
                    $purchaseItem->product_id,
                    $purchase->branch_id,
                    $itemData['received_quantity'],
                    'in',
                    $purchase,
                    "Purchase Order #{$purchase->po_number}"
                );
            }
            
            // Check if all items received
            $allReceived = $purchase->items->every(function($item) {
                return $item->received_quantity >= $item->quantity;
            });
            
            if ($allReceived) {
                $purchase->status = 'received';
                $purchase->save();
            }
            
            DB::commit();
            
            return response()->json([
                'message' => 'Goods received successfully',
                'data' => $purchase->load('items')
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to receive goods',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Display the specified purchase
     */
    public function show($id)
    {
        $purchase = Purchase::with(['items.product', 'supplier', 'branch', 'user', 'payable'])
            ->findOrFail($id);
            
        return response()->json([
            'data' => $purchase
        ]);
    }
    
    /**
     * Update purchase order
     */
    public function update(Request $request, $id)
    {
        $purchase = Purchase::findOrFail($id);
        
        if ($purchase->status !== 'draft') {
            return response()->json([
                'message' => 'Only draft orders can be updated'
            ], 422);
        }
        
        $request->validate([
            'expected_date' => 'nullable|date',
            'notes' => 'nullable|string'
        ]);
        
        $purchase->update($request->only(['expected_date', 'notes']));
        
        return response()->json([
            'message' => 'Purchase order updated successfully',
            'data' => $purchase
        ]);
    }
    
    /**
     * Cancel purchase order
     */
    public function cancel($id)
    {
        $purchase = Purchase::findOrFail($id);
        
        if (!in_array($purchase->status, ['draft', 'ordered'])) {
            return response()->json([
                'message' => 'Only draft or ordered purchases can be cancelled'
            ], 422);
        }
        
        $purchase->status = 'cancelled';
        $purchase->save();
        
        return response()->json([
            'message' => 'Purchase order cancelled successfully'
        ]);
    }
    
    /**
     * Generate PO number
     */
    private function generatePONumber()
    {
        $year = date('Y');
        $month = date('m');
        $lastPO = Purchase::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();
        
        $number = $lastPO ? intval(substr($lastPO->po_number, -4)) + 1 : 1;
        
        return sprintf('PO/%s/%s/%04d', $year, $month, $number);
    }
    
    /**
     * Get purchase reports
     */
    public function reports(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from'
        ]);
        
        $query = Purchase::whereBetween('order_date', [$request->date_from, $request->date_to])
            ->where('branch_id', $request->user()->branch_id);
            
        $summary = [
            'total_purchases' => $query->count(),
            'total_amount' => $query->sum('total_amount'),
            'received_count' => (clone $query)->where('status', 'received')->count(),
            'pending_count' => (clone $query)->where('status', 'ordered')->count(),
            'cancelled_count' => (clone $query)->where('status', 'cancelled')->count(),
        ];
        
        $bySupplier = $query->selectRaw('suppliers.name, SUM(purchases.total_amount) as total')
            ->join('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->groupBy('suppliers.id', 'suppliers.name')
            ->get();
            
        return response()->json([
            'summary' => $summary,
            'by_supplier' => $bySupplier
        ]);
    }
}