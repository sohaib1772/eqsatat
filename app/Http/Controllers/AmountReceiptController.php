<?php

namespace App\Http\Controllers;

use App\Models\AmountReceipt;
use Illuminate\Http\JsonResponse;
use App\Services\AmountReceiptService;
use App\Http\Resources\AmountReceiptResource;
use App\Http\Requests\AmountReceiptRequest\StoreAmountReceiptData;
use App\Http\Requests\AmountReceiptRequest\UpdateAmountReceiptData;
use Illuminate\Http\Request;

class AmountReceiptController extends Controller
{
    protected AmountReceiptService $amountReceiptService;

    public function __construct(AmountReceiptService $amountReceiptService)
    {
        $this->amountReceiptService = $amountReceiptService;
    }

    /**
     * Display a listing of amount receipts.
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->amountReceiptService->getAllAmountReceipts($request->all());

        return $result['status'] === 200
            ? $this->paginated($result["data"], AmountReceiptResource::class, $result['message'], $result['status'])
            : $this->error(null, $result['message'], $result['status']);
    }

    /**
     * Store a newly created amount receipt.
     */
    public function store(StoreAmountReceiptData $request): JsonResponse
    {
        $result = $this->amountReceiptService->createAmountReceipt($request->validated());

        return $result['status'] === 200
            ? $this->success(new AmountReceiptResource($result['data']), $result['message'], $result['status'])
            : $this->error(null, $result['message'], $result['status']);
    }

    /**
     * Display the specified amount receipt.
     */
    public function show(AmountReceipt $amountReceipt): JsonResponse
    {
        $amountReceipt->load(['user', 'customer']);
        return $this->success(new AmountReceiptResource($amountReceipt), 'تم استرجاع الوصل بنجاح');
    }

    /**
     * Update the specified amount receipt.
     */
    public function update(UpdateAmountReceiptData $request, AmountReceipt $amountReceipt): JsonResponse
    {
        $result = $this->amountReceiptService->updateAmountReceipt($amountReceipt, $request->validated());

        return $result['status'] === 200
            ? $this->success(new AmountReceiptResource($result['data']), $result['message'], $result['status'])
            : $this->error(null, $result['message'], $result['status']);
    }

    /**
     * Remove the specified amount receipt from storage.
     */
    public function destroy(AmountReceipt $amountReceipt): JsonResponse
    {
        $result = $this->amountReceiptService->deleteAmountReceipt($amountReceipt);

        return $result['status'] === 200
            ? $this->success(null, $result['message'], $result['status'])
            : $this->error(null, $result['message'], $result['status']);
    }
}
