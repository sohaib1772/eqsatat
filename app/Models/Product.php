<?php

namespace App\Models;

use App\Models\ActivitiesLog;
use App\Models\ReceiptProduct;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Class Product
 *
 * Represents a product entity in the system, including details like pricing, quantity,
 * origin, and category associations. Supports relationships with receipts, history tracking, and caching.
 */
class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * These fields can be updated or created using mass assignment.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'selling_price',
        'dolar_buying_price',
        'installment_price',
        'dollar_exchange',
        'quantity',
        'user_id',
        'origin_id',
        'category_id',
    ];

    /**
     * The attributes that should be cast to specific data types.
     *
     * Ensures proper handling of values such as currency and identifiers.
     *
     * @var array
     */
    protected $casts = [
        'selling_price'        => 'float',
        'dolar_buying_price'   => 'float',
        'dollar_exchange'      => 'integer',
        'installment_price'    => 'integer',
        'quantity'             => 'integer',
        'user_id'              => 'integer',
        'origin_id'            => 'integer',
        'category_id'          => 'integer',
    ];

    /**
     * Calculate the buying price based on exchange rate.
     *
     * @return float
     */
    public function getCalculatedBuyingPrice()
    {
        return $this->dolar_buying_price * $this->dollar_exchange;
    }

    /**
     * Determine the selling price based on receipt type.
     *
     * @param string $type
     * @return float
     */
    public function getSellingPriceForReceiptType($type)
    {
        return ($type === 'اقساط' || $type === 'دين') ? $this->installment_price : $this->selling_price;
    }

    /**
     * Relationship: A product can have many activity logs.
     *
     * This allows tracking actions performed on the product.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function activities()
    {
        return $this->morphMany(ActivitiesLog::class, 'type');
    }

    /**
     * Relationship: A product has multiple historical records.
     *
     * Tracks changes and events related to the product over time.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function productHistory()
    {
        return $this->hasMany(ProductHistory::class);
    }

    /**
     * Relationship: A product can appear in many receipt products.
     *
     * Links products with receipts for tracking transactions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function receiptProducts()
    {
        return $this->hasMany(ReceiptProduct::class);
    }

    /**
     * Relationship: A product belongs to a user.
     *
     * Associates a product with the user who created it.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship: A product belongs to an origin.
     *
     * Links a product to its origin, such as manufacturer or country.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function origin()
    {
        return $this->belongsTo(ProductOrigin::class, 'origin_id');
    }

    /**
     * Relationship: A product belongs to a category.
     *
     * Associates a product with a category for classification.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    /**
     * Scope: Filter products based on certain criteria.
     *
     * Enables flexible filtering based on attributes like name.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filteringData Associative array of filtering criteria.
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeFilterBy($query, array $filteringData)
    {
        if (isset($filteringData['name'])) {
            $query->where('name', 'LIKE', "%{$filteringData['name']}%");
        }

        return $query;
    }

    /**
     * Boot method for the Product model.
     *
     * Handles caching logic when products are created, updated, or deleted.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::created(function ($product) {
            static::clearProductCache();
            Log::info("تم إنشاء منتج جديد ({$product->id}) وتم حذف كاش المنتجات.");
        });

        static::updated(function ($product) {
            static::clearProductCache();
            Log::info("تم تحديث المنتج ({$product->id}) وتم حذف كاش المنتجات.");
        });

        static::deleted(function ($product) {
            static::clearProductCache();
            Log::info("تم حذف المنتج ({$product->id}) وتم حذف كاش المنتجات.");
        });
    }

    /**
     * Clear product-related cache.
     *
     * Ensures updated data is retrieved instead of cached versions.
     *
     * @return void
     */
    protected static function clearProductCache()
    {
        $cacheKeys = Cache::get('all_products_keys', []);
        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
        Cache::forget('all_products_keys');
    }
}
