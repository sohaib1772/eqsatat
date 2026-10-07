<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReceiptProduct extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'receipt_id',
        'product_id',
        'quantity',
        'selling_price',
        'buying_price',
        'description',
    ];
    /**
     * Casts for attributes.
     *
     * @var array
     * @documented
     */
    protected $casts = [
        'receipt_id' => 'integer',
        'product_id' => 'integer',
        'quantity' => 'integer',
        'selling_price' => 'float',
        'buying_price' => 'float',
        'description' => 'string',
    ];

    /**
     * Relationship: A ReceiptProduct belongs to a Receipt.
     *
     * This function defines the relationship between the `ReceiptProduct` model
     * and the `Receipt` model. Each receipt product is associated with a specific receipt.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }

    /**
     * Relationship: A ReceiptProduct belongs to a Product.
     *
     * This function defines the relationship between the `ReceiptProduct` model
     * and the `Product` model. Each receipt product is associated with a specific product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Relationship: A ReceiptProduct can have one Installment.
     *
     * This function defines the relationship between the `ReceiptProduct` model
     * and the `Installment` model. Each receipt product may have one associated installment
     * (e.g., if the payment is in installments).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function installment()
    {
        return $this->hasOne(Installment::class);
    }

    /**
 * Get the total price for this receipt product.
 *
 * @return float
 */
    public function getTotalPriceAttribute(): float
    {
        return $this->selling_price * $this->quantity;
    }

}
