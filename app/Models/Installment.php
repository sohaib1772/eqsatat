<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Installment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * This array defines the fields that can be mass-assigned when creating or updating an installment.
     * This protects against mass-assignment vulnerabilities.
     *
     * @var array
     */
    protected $fillable = [
        'receipt_product_id',
        'pay_cont',
        'installment',
        'first_pay',
        'installment_type',
        'status',
    ];

    /**
     * Casts for attributes.
     *
     * This array specifies the data types for certain attributes.
     * These are used to automatically cast values to the specified types when retrieving data from the database.
     * For example, 'receipt_product_id' is cast to an integer.
     *
     * @var array
     * @documented
     */
    protected $casts = [
        'receipt_product_id' => 'integer',  // Cast the receipt_product_id as integer
        'pay_cont' => 'integer',            // Cast the pay_cont as integer
        'first_pay' => 'float',           // Cast the first_pay as float
        'installment' => 'float',         // Cast the installment as float
    ];

    /**
     * Relationship: An installment belongs to a receipt product.
     *
     * This method defines the relationship where each installment is associated with one receipt product.
     * It is used to retrieve the corresponding ReceiptProduct for a given installment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function receiptProduct()
    {
        return $this->belongsTo(ReceiptProduct::class, 'receipt_product_id');
    }

    /**
     * Relationship: An installment can have many installment payments.
     *
     * This method defines the relationship where each installment can have multiple payments associated with it.
     * It is used to retrieve all installment payments related to a given installment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function InstallmentPayments()
    {
        return $this->hasMany(InstallmentPayment::class);
    }
    public function lastInstallmentPayments(): HasOne
    {
        return $this->hasOne(InstallmentPayment::class)->latestOfMany('id');
    }
    /**
     * Mapping of installment types to human-readable values.
     *
     * This map is used to convert the numeric values stored in the database into human-readable strings.
     * It maps the installment type (e.g., 0, 1, 2) to the corresponding value in Arabic (Weekly, Daily, Monthly).
     *
     * 0 => 'اسبوعي' (Weekly)
     * 1 => 'يومي' (Daily)
     * 2 => 'شهري' (Monthly)
     */
    const TYPE_MAP = [
        0 => 'اسبوعي',   // Weekly installment
        1 => 'يومي',     // Daily installment
        2 => 'شهري',     // Monthly installment
    ];

    /**
     * Accessor for the installment type.
     *
     * This method defines an accessor for the installment type field.
     * It converts the stored integer value into a human-readable string (e.g., 1 becomes "يومي" or "Daily").
     *
     * @param mixed $value The stored value in the database.
     * @param array $attributes The full set of attributes for the model.
     * @return string The human-readable installment type.
     */
    public function installmentType(): Attribute
    {
        return Attribute::make(
            // Get the human-readable installment type based on the stored value
            get: fn ($value, $attributes) => self::TYPE_MAP[$attributes['installment_type']] ?? 'Unknown',

            // Set the installment type based on the human-readable value
            set: fn ($value) => array_search($value, self::TYPE_MAP) !== false ? array_search($value, self::TYPE_MAP) : null
        );
    }

    /**
     * Mapping for installment status.
     *
     * This map is used to convert numeric status values into human-readable strings.
     * It helps translate status values such as 'Paid' (مسدد) and 'Pending payment' (قيد التسديد) from the database.
     *
     * 0 => 'مسدد' (Paid)
     * 1 => 'قيد التسديد' (Pending payment)
     */
    const STATUS_MAP = [
        0 => 'مسدد',    // Paid
        1 => 'قيد التسديد', // Pending payment
    ];

    /**
     * Accessor for the status field.
     *
     * This method defines an accessor for the status field.
     * It converts the stored numeric value into a human-readable string (e.g., 0 becomes "مسدد" or "Paid").
     *
     * @param mixed $value The stored value in the database.
     * @param array $attributes The full set of attributes for the model.
     * @return string The human-readable status.
     */
    public function status(): Attribute
    {
        return Attribute::make(
            // Get the human-readable status based on the stored value
            get: fn ($value, $attributes) => self::STATUS_MAP[$attributes['status']] ?? 'Unknown',

            // Set the status based on the human-readable value
            set: fn ($value) => array_search($value, self::STATUS_MAP) !== false ? array_search($value, self::STATUS_MAP) : null
        );
    }
}
