<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku', 'barcode', 'name', 'description', 'category',
        'unit_of_measure', 'purchase_price', 'selling_price',
        'tax_rate', 'min_stock', 'is_active', 'image'
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function stocks()
    {
        return $this->hasMany(ProductStock::class);
    }

    public function stockInBranch($branchId)
    {
        return $this->stocks()
            ->where('branch_id', $branchId)
            ->first();
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }
}