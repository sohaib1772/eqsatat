<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Class Debt
 *
 * This model represents debts within the system, storing details such as total debt, remaining balance,
 * and associations with users and customers.
 */
class Debt extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'customer_id',
        'payment_amount',
        'description',
        'remaining_debt',
        'debt_date',
        'user_id'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [

        'customer_id'    => 'integer',
        'payment_amount'  => 'float',
        'remaining_debt' => 'float',
        'debt_date'      => 'date',
        'description' => 'string',
    ];

    /**
     * Defines the relationship between debts and users.
     *
     * Each debt is associated with one user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Defines the relationship between debts and DebtPayments.
     *
     * Each debt is associated with many payments .
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function debtPayments()
    {
        return $this->hasMany(DebtPayment::class);
    }

    /**
     * Defines the relationship between debts and customers.
     *
     * Each debt is associated with a specific customer.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Defines the polymorphic relationship between debts and activity logs.
     *
     * This allows a debt record to have multiple logged activities, tracking actions performed on the debt.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function activities()
    {
        return $this->morphMany(ActivitiesLog::class, 'type');
    }

    /**
     * Automatically clear cache when a debt record is created or updated.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($debt) {
            self::clearCache();
            self::clearCustomerCache();

            Log::info("تم إنشاء سجل دين جديد ({$debt->id}) وتم حذف الكاش.");
        });


        static::updated(function ($debt) {
            self::clearCache();
            self::clearCustomerCache();

            Log::info("تم تحديث سجل الدين ({$debt->id}) وتم حذف الكاش.");
        });


        static::deleted(function ($debt) {
            self::clearCache();
            self::clearCustomerCache();

            Log::info("تم حذف سجل الدين ({$debt->id}) وتم حذف الكاش.");
        });
    }
    protected static function clearCustomerCache()
    {
        $cacheKeys = Cache::get('all_customers_keys', []);
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
        Cache::forget('all_customers_keys');
    }
    protected static function clearCache()
    {
        $cacheKeys = Cache::get('all_agents_keys', []);
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
        Cache::forget('all_agents_keys');
    }

    /**
     * Scope function to filter debts based on certain criteria.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array<string, mixed> $filteringData
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterBy($query, array $filteringData)
    {
        // Filter by customer name if provided
        if (!empty($filteringData['name'])) {
            $query->whereHas('customer', function ($q) use ($filteringData) {
                $q->where('name', 'LIKE', "%{$filteringData['name']}%");
            });
        }

        // Filter by receipt number if provided
        if (!empty($filteringData['receipt_number'])) {
            $query->where('receipt_number', $filteringData['receipt_number']);
        }

        return $query;
    }
}
