<?php

namespace App\Http\Requests\AmountReceiptRequest;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateAmountReceiptData extends FormRequest
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
            'receipt_number'   => 'sometimes|required|unique:amount_receipts,receipt_number,' . $this->route('amountReceipt')->id,
            'customer_id'      => 'sometimes|required|exists:customers,id',
            'amount'           => 'sometimes|required|integer|min:1',
            'receipt_date'     => 'sometimes|required|date|before_or_equal:now',
            'remaining_amount' => 'sometimes|required|integer',
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
