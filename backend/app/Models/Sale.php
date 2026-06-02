<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number', 'customer_id', 'branch_id', 'user_id',
        'sale_date', 'payment_status', 'delivery_status',
        'subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'notes'
    ];

    protected $casts = [
        'sale_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($sale) {
            $sale->invoice_number = self::generateInvoiceNumber();
        });
    }

    public static function generateInvoiceNumber()
    {
        $year = date('Y');
        $month = date('m');
        $lastSale = self::whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->orderBy('id', 'desc')
            ->first();
        
        $number = $lastSale ? intval(substr($lastSale->invoice_number, -4)) + 1 : 1;
        
        return sprintf('INV/%s/%s/%04d', $year, $month, $number);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class, 'reference_id')
            ->where('payment_type', 'sale');
    }

    public function receivable()
    {
        return $this->hasOne(Receivable::class);
    }

    public function addPayment($amount, $method, $referenceNumber = null)
    {
        DB::transaction(function () use ($amount, $method, $referenceNumber) {
            Payment::create([
                'payment_number' => Payment::generateNumber(),
                'payment_type' => 'sale',
                'reference_id' => $this->id,
                'customer_id' => $this->customer_id,
                'amount' => $amount,
                'payment_method' => $method,
                'payment_date' => now(),
                'reference_number' => $referenceNumber,
            ]);

            $totalPaid = $this->payments()->sum('amount');
            
            if ($totalPaid >= $this->total_amount) {
                $this->update(['payment_status' => 'paid']);
                if ($this->receivable) {
                    $this->receivable->update(['status' => 'paid']);
                }
            } else if ($totalPaid > 0) {
                $this->update(['payment_status' => 'partial']);
                if ($this->receivable) {
                    $this->receivable->update(['status' => 'partial']);
                }
            }
        });
    }
}