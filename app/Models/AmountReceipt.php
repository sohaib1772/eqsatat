<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AmountReceipt extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'receipt_number',
        'customer_id',
        'user_id',
        'amount',
        'receipt_date',
        'remaining_amount',
        'notes',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'customer_id'      => 'integer',
        'user_id'          => 'integer',
        'amount'           => 'integer',
        'remaining_amount' => 'integer',
        'receipt_date'     => 'date',
    ];

    /**
     * Relationship: AmountReceipt belongs to a Customer.
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Relationship: AmountReceipt belongs to a User.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Polymorphic relationship for activity logs.
     */
    public function activities()
    {
        return $this->morphMany(ActivitiesLog::class, 'type');
    }

    /**
     * Boot method for the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($receipt) {
            self::clearCaches();
            Log::info("تم إنشاء وصل استلام مبلغ جديد ({$receipt->id})");
        });

        static::updated(function ($receipt) {
            self::clearCaches();
            Log::info("تم تحديث وصل استلام مبلغ ({$receipt->id})");
        });

        static::deleted(function ($receipt) {
            self::clearCaches();
            Log::info("تم حذف وصل استلام مبلغ ({$receipt->id})");
        });
    }

    /**
     * Clear relevant caches.
     */
    protected static function clearCaches()
    {
        // Clear customer cache as this might affect shown balances
        $cacheKeys = Cache::get('all_customers_keys', []);
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
        Cache::forget('all_customers_keys');
        
        // Also clear any receipts cache if exists
        $cacheKeys = Cache::get('all_receipts_keys', []);
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
        Cache::forget('all_receipts_keys');
    }
}
