<?php

namespace App\Models;

use App\Models\ReceiptProduct;
use App\Models\InstallmentPayment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Receipt extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * This array defines the fields that can be mass-assigned when creating or updating a receipt.
     * This protects against mass-assignment vulnerabilities by specifying which fields are allowed.
     *
     * @var array
     */
    protected $fillable = [
        'customer_id',   // ID of the customer associated with the receipt
        'receipt_number', // Unique identifier for the receipt
        'type',           // Type of receipt (installment or cash or debt)
        'total_price',    // Total price of the receipt
        'discount',       // Discount applied to the receipt
        'paid',           // Amount paid at the time of receipt creation
        'receipt_date',   // Date the receipt was created
        'user_id',        // ID of the user who created the receipt
        'notes',          // Additional notes related to the receipt
    ];

    /**
     * Casts for attributes.
     *
     * This array specifies how the attributes should be cast when retrieving from or saving to the database.
     * The `receipt_date` is cast as a datetime in 'Y-m-d' format, while other fields are cast to their respective types.
     *
     * @var array
     * @documented
     */
    protected $casts = [
        'customer_id'      => 'integer',  // Cast customer_id as an integer
        'receipt_number'   => 'integer',  // Cast receipt_number as an integer
        'total_price'      => 'integer',  // Cast total_price as an integer
        'discount'         => 'integer',  // Cast discount as an integer
        'paid'             => 'integer',  // Cast paid as an integer
        'notes'            => 'string',   // Cast notes as a string
        'receipt_date'     => 'datetime:Y-m-d', // Cast receipt_date as a datetime in 'Y-m-d' format
    ];

    /**
     * Relationship: A Receipt belongs to a Customer.
     *
     * This function defines the relationship between the `Receipt` model and the `Customer` model.
     * A receipt is linked to a specific customer, and we can access the customer details via this relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relationship: A Receipt belongs to a User.
     *
     * This function defines the relationship between the `Receipt` model and the `User` model.
     * A receipt is created by a specific user, and we can access the user details via this relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: A Receipt has many ReceiptProducts.
     *
     * This function defines the one-to-many relationship between the `Receipt` model and the `ReceiptProduct` model.
     * A receipt can contain multiple receipt products, and we can access all the products via this relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function receiptProducts()
    {
        return $this->hasMany(ReceiptProduct::class);
    }

    /**
     * A map for receipt types: either installment ('اقساط') or cash ('نقدي').
     *
     * This constant array maps numeric values to human-readable receipt types.
     * - 0 represents 'اقساط' (Installment)
     * - 1 represents 'نقدي' (Cash)
     */
    const TYPE_MAP = [
        0 => 'اقساط',  // Installment payment type
        1 => 'نقدي',   // Cash payment type
        2 => 'دين',    // Debt payment type
    ];

    /**
     * Relationship: A Receipt can have many ActivitiesLog entries.
     *
     * This function defines a polymorphic relationship between the `Receipt` model and the `ActivitiesLog` model.
     * It allows the receipt to be associated with multiple activity logs, which can store details of actions taken on the receipt.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function activities()
    {
        return $this->morphMany(ActivitiesLog::class, 'type');
    }

    /**
     * Mutator for the 'type' attribute.
     *
     * This method defines an accessor and mutator for the 'type' attribute.
     * The accessor converts the numeric 'type' value (0 or 1) into a human-readable string ('اقساط' or 'نقدي').
     * The mutator converts the string back to its corresponding numeric value before saving it to the database.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    public function type(): Attribute
    {
        return Attribute::make(
            // Get the string representation of the type
            get: fn($value) => self::TYPE_MAP[$value] ?? 'Unknown',  // Convert numeric to string representation

            // Set the integer value for type
            set: fn($value) => array_search($value, self::TYPE_MAP)  // Convert string back to its corresponding integer value
        );
    }

    /**
     * Boot method for the model.
     *
     * This method is automatically called when an instance of the model is booted.
     * In this case, we are defining actions to take after a receipt is created, updated, or deleted.
     * - Clear the cache of receipts each time a receipt is created, updated, or deleted.
     * - Log an informational message each time a receipt is created, updated, or deleted.
     */

    /**
     * Boot method for the model.
     *
     * This method is automatically called when an instance of the model is booted.
     * In this case, we are defining actions to take after a receipt is created, updated, or deleted.
     * - Clear the cache of receipts each time a receipt is created, updated, or deleted.
     * - Log an informational message each time a receipt is created, updated, or deleted.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($receipt) {
            self::clearCache();
            Log::info("وتم حذف كاش الزبائن.");
            Log::info("تم إنشاء فاتورة جديدة ({$receipt->id}) وتم حذف كاش الفواتير.");
        });

        static::updated(function ($receipt) {
            self::clearCache();
            Log::info("وتم حذف كاش الزبائن.");
            Log::info("تم تحديث الفاتورة ({$receipt->id}) وتم حذف كاش الفواتير.");
        });

        static::deleted(function ($receipt) {
            self::clearCache();
            Log::info("وتم حذف كاش الزبائن.");
            Cache::forget('all_receipts_keys');

            Log::info("تم حذف الفاتورة ({$receipt->id}) وتم حذف كاش الفواتير.");
        });
    }

    protected static function clearCache()
    {
        $cacheKeys = Cache::get('all_customers_keys', []);
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
        Cache::forget('all_customers_keys');
        $cacheKeys = Cache::get('all_receipts_keys', []);
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
        Cache::forget('all_receipts_keys');
    }
    /**
     * Scope to filter receipts by specific parameters.
     *
     * This scope allows you to filter receipts based on different filtering criteria.
     * For example, you can filter receipts by customer name or receipt number.
     * This helps in building more dynamic queries.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filteringData The filtering data (e.g., customer name, receipt number).
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterBy($query, array $filteringData)
    {
        // Filter by customer name if provided
        if (isset($filteringData['name'])) {
            $query->whereHas('customer', function ($q) use ($filteringData) {
                $q->where('name', 'LIKE', "%{$filteringData['name']}%");
            });
        }

        // Filter by receipt number if provided
        if (isset($filteringData['receipt_number'])) {
            $query->where('receipt_number', $filteringData['receipt_number']);
        }

        return $query;
    }
}
