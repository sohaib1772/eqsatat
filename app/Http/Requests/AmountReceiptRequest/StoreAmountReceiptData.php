<?php

namespace App\Http\Requests\AmountReceiptRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreAmountReceiptData extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'receipt_number'   => 'required|unique:amount_receipts,receipt_number',
            'customer_id'      => 'required|exists:customers,id',
            'amount'           => 'required|integer|min:1',
            'receipt_date'     => 'required|date|before_or_equal:now',
            'remaining_amount' => 'required|integer',
            'notes'            => 'nullable|string',
        ];
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'فشل التحقق من صحة البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
