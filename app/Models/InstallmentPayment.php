<?php

namespace App\Models;

use App\Models\User;
use App\Models\Installment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Class InstallmentPayment
 *
 * Represents a payment made towards an installment.
 *
 * @property int $id
 * @property int $installment_id
 * @property int $user_id
 * @property int $amount
 * @property \Illuminate\Support\Carbon $payment_date
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 *
 * Relationships:
 * @property Installment $installment
 * @property User $user
 */
class InstallmentPayment extends Model
{
    use HasFactory;

    /**
     * Mass assignable attributes.
     *
     * @var array<string>
     */
    protected $fillable = [
        'installment_id',
        'user_id',
        'payment_date',
        'amount',
    ];

    /**
     * Attribute casting.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'installment_id' => 'integer',
        'user_id' => 'integer',
        'payment_date' => 'date',
        'amount' => 'float',
    ];

    /**
     * Polymorphic relationship for activity logs.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function activities()
    {
        return $this->morphMany(ActivitiesLog::class, 'type');
    }

    /**
     * Relationship: InstallmentPayment belongs to an Installment.
     *
     * @return BelongsTo
     */
    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }

    /**
     * Relationship: InstallmentPayment belongs to a User.
     *
     * @return BelongsTo
     */
 public function user()
{
    return $this->belongsTo(User::class, 'user_id', 'id');
}


    /**
     * Boot method to hook into model events.
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($installmentPayment) {
            self::clearCustomerCache();
            Log::info("كاش الزبائن تم حذفه بعد إضافة دفعة.");
        });

        static::updated(function ($installmentPayment) {
            self::clearCustomerCache();
            Log::info("كاش الزبائن تم حذفه بعد تعديل دفعة.");
        });

        static::deleted(function ($installmentPayment) {
            self::clearCustomerCache();
            Log::info("كاش الزبائن تم حذفه بعد حذف دفعة.");
        });
    }

    /**
     * Clears customer cache.
     *
     * @return void
     */
    protected static function clearCustomerCache(): void
    {
        $cacheKeys = Cache::get('all_customers_keys', []);
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
        Cache::forget('all_customers_keys');
    }
}
