<?php

namespace App\Services;

use Exception;
use App\Models\AmountReceipt;
use App\Models\ActivitiesLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AmountReceiptService extends Service
{
    /**
     * Get all amount receipts.
     */
    public function getAllAmountReceipts(array $filteringData)
    {
        try {
            $page = request('page', 1);
            $cacheKey = 'amount_receipts_' . $page . '_' . md5(json_encode($filteringData));
            
            // Record cache key for clearing later
            $cacheKeys = Cache::get('all_receipts_keys', []);
            if (!in_array($cacheKey, $cacheKeys)) {
                $cacheKeys[] = $cacheKey;
                Cache::put('all_receipts_keys', $cacheKeys, now()->addHours(120));
            }

            $receipts = Cache::remember($cacheKey, now()->addMinutes(60), function () use ($filteringData) {
                return AmountReceipt::with(['user:id,name', 'customer:id,name'])
                    ->when(isset($filteringData['customer_id']), function ($query) use ($filteringData) {
                        $query->where('customer_id', $filteringData['customer_id']);
                    })
                    ->when(isset($filteringData['receipt_number']), function ($query) use ($filteringData) {
                        $query->where('receipt_number', 'like', "%" . $filteringData['receipt_number'] . "%" );
                    })
                    ->orderByDesc('receipt_date')
                    ->paginate(10);
            });

            return $this->successResponse('تم استرجاع وصولات المبالغ بنجاح', 200, $receipts);
        } catch (Exception $e) {
            Log::error('خطأ في استرجاع وصولات المبالغ: ' . $e->getMessage());
            return $this->errorResponse('حدث خطأ أثناء استرجاع وصولات المبالغ.');
        }
    }

    /**
     * Create a new amount receipt.
     */
    public function createAmountReceipt(array $data)
    {
        DB::beginTransaction();

        try {
            $data['user_id'] = Auth::id();
            $receipt = AmountReceipt::create($data);

            ActivitiesLog::create([
                'user_id'     => Auth::id(),
                'description' => 'تم استلام مبلغ ' . $receipt->amount . ' من الزبون ' . $receipt->customer->name . ' (وصل رقم ' . $receipt->id . ')',
                'type_id'     => $receipt->id,
                'type_type'   => AmountReceipt::class,
            ]);

            DB::commit();
            return $this->successResponse('تم إنشاء وصل استلام المبلغ بنجاح', 200, $receipt);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error in createAmountReceipt: ' . $e->getMessage());
            return $this->errorResponse('حدث خطأ أثناء إنشاء وصل استلام المبلغ: ' . $e->getMessage());
        }
    }

    /**
     * Update an amount receipt.
     */
    public function updateAmountReceipt(AmountReceipt $receipt, array $data)
    {
        DB::beginTransaction();

        try {
            $receipt->update($data);

            ActivitiesLog::create([
                'user_id'     => Auth::id(),
                'description' => 'تم تحديث وصل استلام المبلغ رقم ' . $receipt->id . ' للزبون ' . $receipt->customer->name,
                'type_id'     => $receipt->id,
                'type_type'   => AmountReceipt::class,
            ]);

            DB::commit();
            return $this->successResponse('تم تحديث وصل استلام المبلغ بنجاح', 200, $receipt);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error in updateAmountReceipt: ' . $e->getMessage());
            return $this->errorResponse('حدث خطأ أثناء تحديث وصل استلام المبلغ.');
        }
    }

    /**
     * Delete an amount receipt.
     */
    public function deleteAmountReceipt(AmountReceipt $receipt)
    {
        DB::beginTransaction();

        try {
            ActivitiesLog::create([
                'user_id'     => Auth::id(),
                'description' => 'تم حذف وصل استلام المبلغ رقم ' . $receipt->id . ' للزبون ' . $receipt->customer->name,
                'type_id'     => $receipt->id,
                'type_type'   => AmountReceipt::class,
            ]);

            $receipt->delete();

            DB::commit();
            return $this->successResponse('تم حذف وصل استلام المبلغ بنجاح', 200);
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Error in deleteAmountReceipt: ' . $e->getMessage());
            return $this->errorResponse('حدث خطأ أثناء حذف وصل استلام المبلغ.');
        }
    }
}
