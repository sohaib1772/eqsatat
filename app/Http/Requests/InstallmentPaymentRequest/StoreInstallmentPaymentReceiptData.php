<?php

namespace App\Http\Requests\InstallmentPaymentRequest;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use App\Rules\StoreValidInstallmentReceiptAmount;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreInstallmentPaymentReceiptData extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', new StoreValidInstallmentReceiptAmount(Customer::findOrFail($this->route('id')))],
        ];
    }    /**
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
