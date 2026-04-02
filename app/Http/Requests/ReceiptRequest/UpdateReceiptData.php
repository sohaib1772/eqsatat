<?php

namespace App\Http\Requests\ReceiptRequest;

use App\Rules\AvailableQuantity;
use Illuminate\Support\Facades\Log;
use App\Rules\AvailableQuantityUpdate;
use App\Rules\FirstInstallmentAmountValid;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateReceiptData extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Adjust authorization logic if needed
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_id'     => 'nullable|exists:customers,id',
            'notes'           => 'nullable|string',
            'receipt_date'    => 'nullable|date|before_or_equal:now',
            'type' => 'required|in:اقساط,نقدي,دين',
            'discount_amount' => 'nullable|integer|min:0',
            'paid_amount' => 'nullable|integer|min:0', 
            'products' => 'nullable|array',
            'products.*.product_id' => 'required|exists:products,id',
            'products.*.description' => 'nullable|string|max:255',
            'products.*.selling_price' => 'nullable|integer:',
            'products.*.quantity' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    $index = explode('.', $attribute)[1];
                    $productId = $this->input("products.{$index}.product_id");
                    $receiptId = $this->route('receipt')->id;
                    $rule = new AvailableQuantityUpdate((int)$productId, (int)$receiptId);
                    if (!$rule->passes($attribute, $value)) {
                        $fail($rule->message());
                    }
                }
            ],

            'products.*.pay_cont' => 'required_if:type,اقساط|nullable|integer|min:0',
            'products.*.installment' => 'required_if:type,اقساط|nullable|integer|min:0',
            'products.*.installment_type' => 'required_if:type,اقساط|nullable|in:يومي,شهري,اسبوعي',
            'products.*.first_pay' => [
                'required_if:type,اقساط',
                'nullable',
                'integer',
                'min:0',
                function ($attribute, $value, $fail) {
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


    /**
     * Handle a failed validation attempt.
     * This method is called when validation fails.
     * Logs failed attempts and throws validation exception.
     * @param \Illuminate\Validation\Validator $validator
     * @return void
     *
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
