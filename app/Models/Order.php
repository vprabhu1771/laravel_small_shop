<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_id',
        'order_date',
        'total_amount',
        'order_status',
        'payment_method'
    ];

    protected $dates = ['order_date'];

    public static function boot()
    {
        parent::boot();
        static::creating(function ($order) {
            $datePart = now()->format('Ymd');
            $sequentialPart = str_pad(static::max('order_number') % 1000 + 1, 3, '0', STR_PAD_LEFT);
            $order->order_number = $datePart . $sequentialPart;
            $order->order_date = now(); // Corrected this line to set the order date instead of the order number
        });

        // Adding an event listener for the "created" event
        static::created(function ($order) {

            // dd($order);

            // Record initial order status and payment method when an order is created
            $history = new OrderHistory([
                'order_id' => $order->id,
                'order_status' => $order->order_status,     
                'payment_method' => $order->payment_method, // Record payment method           
            ]);
            
            $order->history()->save($history);                        

        });

        

        // Adding an event listener for the "updating" event
        static::updating(function ($order) {
            $changedAttributes = $order->getDirty();

            // dd($changedAttributes);

            if (array_key_exists('order_status', $changedAttributes) 
            || array_key_exists('payment_method', $changedAttributes)
            ) {
                $order->recordStatusChange($changedAttributes);
            }
        });
    }

    protected function recordStatusChange($changedAttributes)
    {
        $history = new OrderHistory([
            'order_id' => $this->id,
            'order_status' => $this->order_status, // Use the current status
            'payment_method' => $this->payment_method, // Use the current payment method
        ]);

        $this->history()->save($history);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'order_items');
    }

    public function calculaterSGST(): float
    {
        return $this->total_amount * 0.06; // Corrected the calculation
    }

    public function calculaterCGST(): float
    {
        return $this->total_amount * 0.06; // Corrected the calculation
    }

    public function calculate_SGST_CGST(): float 
    {
        return $this->calculaterSGST() + $this->calculaterCGST();
    }

    public function total_amount_with_SGST_CGST(): float
    {
        // $total_amount = $this->total_amout;

        // $total_amount += $this->calculaterSGST();

        // $total_amount += $this->calculaterCGST();

        // return $total_amount;

        return $this->total_amount + $this->calculaterSGST() + $this->calculaterCGST();
    }

    public function getTotalAmountInWords(): string
    {
        $numberToWords = new NumberToWords();
        $transformer = $numberToWords->getNumberTransformer('en');
        return $transformer->toWords($this->total_amount);
    }

    public function convertTaxAmountInWords(): string
    {
        $numberToWords = new NumberToWords();
        $transformer = $numberToWords->getNumberTransformer('en');
        $taxAmount = $this->calculaterSGST() + $this->calculaterCGST(); // Calculated tax amount
        return $transformer->toWords($taxAmount);
    }

    public function history()
    {
        return $this->hasMany(OrderHistory::class, 'order_id');
    }
}
