<?php

namespace App\Models;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Class Customer
 *
 * Represents a customer entity with relationships to receipts, debts, and activities.
 * Includes caching mechanisms to optimize retrieval and storage of customer-related data.
 */
class Customer extends Model
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * These attributes can be filled when creating or updating a record.
     *
     * @var array<string>
     */
    protected $fillable = [
        'name',         // Customer's full name
        'phone',        // Contact phone number
        'notes',        // Additional notes about the customer
        'sponsor_name', // Name of the sponsor (if any)
        'sponsor_phone', // Sponsor's phone number
        'Record_id',    // Associated record identifier
        'Page_id',      // Associated page identifier
        'status',       // Customer's status (e.g., 'Old' or 'New')
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'name'          => 'string',
        'phone'         => 'integer',
        'notes'         => 'string',
        'sponsor_name'  => 'string',
        'sponsor_phone' => 'integer',
        'Record_id'     => 'string',
        'Page_id'       => 'string',
    ];

    /**
     * Defines the relationship between a customer and receipts.
     *
     * Each customer may have multiple receipts.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }

    /**
     * Defines the polymorphic relationship for activity logs.
     *
     * This allows tracking various activities related to the customer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function activities()
    {
        return $this->morphMany(ActivitiesLog::class, 'type');
    }

    /**
     * Defines the relationship between a customer and debts.
     *
     * Each customer may have multiple debts.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function debts()
    {
        return $this->hasMany(Debt::class);
    }

    /**
     * The booting method of the model.
     *
     * This ensures that cache data is properly cleared upon creation, update, or deletion.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($customer) {
            self::clearCustomerCache();
            Log::info("تم إنشاء زبون جديد ({$customer->id}) وتم حذف كاش الزبائن.");
        });

        static::updated(function ($customer) {
            self::clearCustomerCache();
            Log::info("تم تحديث الزبون ({$customer->id}) وتم حذف كاش الزبائن.");
        });

        static::deleted(function ($customer) {
            self::clearCustomerCache();
            Log::info("تم حذف الزبون ({$customer->id}) وتم حذف كاش الزبائن.");
        });
    }

    /**
     * Clears cached customer data.
     *
     * Ensures that old cached records do not interfere with updated or deleted customer entries.
     *
     * @return void
     */
    protected static function clearCustomerCache()
    {
        $cacheKeys = Cache::get('all_customers_keys', []);
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
        Cache::forget('all_customers_keys');
    }

    /**
     * Mapping constants for customer types.
     *
     * Used in the mutator to convert numerical status values into readable text.
     */
    const TYPE_MAP = [
        0 => 'قديم',  // Old customer
        1 => 'جديد',  // New customer
    ];

    /**
     * Mutator for the 'status' attribute.
     *
     * Converts stored integer values into user-friendly text, and vice versa.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    public function status(): Attribute
    {
        return Attribute::make(
            get: fn($value) => self::TYPE_MAP[$value] ?? 'Unknown',
            set: fn($value) => array_search($value, self::TYPE_MAP) ?? $value
        );
    }

    /**
     * Scope to filter customers by optional criteria.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filteringData
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterBy($query, array $filteringData)
    {
        if (isset($filteringData['name'])) {
            $searchTerm = strtolower($filteringData['name']);
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$searchTerm}%"]);
        }
        if (isset($filteringData['phone'])) {
            $query->where('phone', $filteringData['phone']);
        }

        if (isset($filteringData['status'])) {
            $status = array_search($filteringData['status'], self::TYPE_MAP);
            if ($status !== false) {
                $query->where('status', $status);
            }
        }

        return $query;
    }


    public function notificationLog()
    {
        return $this->hasMany(NotificationLog::class);
    }

    /**
     * Defines the relationship between a customer and amount receipts.
     *
     * Each customer may have multiple amount receipts (general payments).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function amountReceipts()
    {
        return $this->hasMany(AmountReceipt::class);
    }
}
