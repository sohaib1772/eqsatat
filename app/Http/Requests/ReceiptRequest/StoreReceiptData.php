<?php

namespace App\Http\Requests\ReceiptRequest;

use Illuminate\Support\Facades\Log;
use App\Rules\FirstInstallmentAmountValid;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Rules\AvailableQuantityStore;

class StoreReceiptData extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'receipt_number' => 'required|integer|unique:receipts,receipt_number',
            'type' => 'required|in:اقساط,نقدي',
            'total_price' => 'required|integer',
            'notes' => 'nullable|string',
            'receipt_date' => 'nullable|date|before_or_equal:now',

            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.description' => 'nullable|string|max:255',
            'products.*.selling_price' => 'nullable|integer',
            'products.*.quantity' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $productId = $this->input("products.{$index}.product_id");

                    $rule = new AvailableQuantityStore((int)$productId);
                    if (!$rule->passes($attribute, $value)) {
                        $fail($rule->message());
                    }
                }
            ],


            'products.*.pay_cont' => 'required_if:type,اقساط|integer|min:0',
            'products.*.installment' => 'required_if:type,اقساط|integer|min:0',
            'products.*.installment_type' => 'required_if:type,اقساط|in:يومي,شهري,اسبوعي',

            'products.*.first_pay' => [
                'required_if:type,اقساط',
                'integer',
                'min:0',
                function ($attribute, $value, $fail) {
                    // فقط إذا كان النوع "اقساط"
                    if ($this->input('type') !== 'اقساط') {
                        return;
                    }

                    $index = explode('.', $attribute)[1];
                    $productId = $this->input("products.{$index}.product_id");
                    $quantity = $this->input("products.{$index}.quantity");

                    $rule = new FirstInstallmentAmountValid((int) $productId, (int) $quantity);
                    if (!$rule->passes($attribute, $value)) {
                        $fail($rule->message());
                    }
                }
            ],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => 'error',
            'message' => 'فشل التحقق من صحة البيانات',
            'errors' => $validator->errors(),
        ], 422));
    }
}
