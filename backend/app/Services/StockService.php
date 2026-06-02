<?php

namespace App\Services;

use App\Models\ProductStock;
use App\Models\StockMutation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockService
{
    /**
     * Update stock with transaction and locking
     */
    public function updateStock($productId, $branchId, $quantity, $type, $reference = null, $notes = null)
    {
        return DB::transaction(function () use ($productId, $branchId, $quantity, $type, $reference, $notes) {
            // Lock the stock record for update
            $stock = ProductStock::where('product_id', $productId)
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->first();
            
            if (!$stock) {
                $stock = ProductStock::create([
                    'product_id' => $productId,
                    'branch_id' => $branchId,
                    'quantity_on_hand' => 0,
                    'reserved_quantity' => 0,
                ]);
            }
            
            $oldQuantity = $stock->quantity_on_hand;
            $newQuantity = $oldQuantity;
            
            switch ($type) {
                case 'in':
                    $newQuantity = $oldQuantity + $quantity;
                    break;
                case 'out':
                    if ($oldQuantity < $quantity) {
                        throw new \Exception("Insufficient stock. Available: {$oldQuantity}, Requested: {$quantity}");
                    }
                    $newQuantity = $oldQuantity - $quantity;
                    break;
                case 'adjust':
                    $newQuantity = $quantity;
                    break;
            }
            
            $stock->quantity_on_hand = $newQuantity;
            $stock->save();
            
            // Create mutation log
            StockMutation::create([
                'product_id' => $productId,
                'branch_id' => $branchId,
                'type' => $type,
                'quantity' => $quantity,
                'stock_before' => $oldQuantity,
                'stock_after' => $newQuantity,
                'reference_type' => $reference ? get_class($reference) : null,
                'reference_id' => $reference ? $reference->id : null,
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);
            
            // Check for low stock alert
            if ($newQuantity <= $stock->minimum_stock) {
                $this->sendLowStockAlert($productId, $branchId, $newQuantity);
            }
            
            return $stock;
        });
    }
    
    /**
     * Reserve stock for pending orders
     */
    public function reserveStock($productId, $branchId, $quantity)
    {
        return DB::transaction(function () use ($productId, $branchId, $quantity) {
            $stock = ProductStock::where('product_id', $productId)
                ->where('branch_id', $branchId)
                ->lockForUpdate()
                ->first();
                
            if (!$stock || $stock->quantity_on_hand - $stock->reserved_quantity < $quantity) {
                throw new \Exception("Insufficient available stock for reservation");
            }
            
            $stock->reserved_quantity += $quantity;
            $stock->save();
            
            return $stock;
        });
    }
    
    private function sendLowStockAlert($productId, $branchId, $currentStock)
    {
        // Implement notification logic
        Log::warning("Low stock alert", [
            'product_id' => $productId,
            'branch_id' => $branchId,
            'current_stock' => $currentStock
        ]);
    }
}