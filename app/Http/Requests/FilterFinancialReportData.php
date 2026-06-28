<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Log;

class FilterFinancialReportData extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ];
    }

    /**
     * Prepare input before validation.
     * Automatically completes missing date parts (year-only, year-month).
     */
    public function prepareForValidation()
    {
        foreach (['start_date', 'end_date'] as $dateField) {
            if (!empty($this->$dateField)) {
                // If only the year is provided, append "-01-01"
                if (preg_match('/^\d{4}$/', $this->$dateField)) {
                    $this->merge([$dateField => $this->$dateField . '-01-01']);
                }
                // If year and month are provided, append "-01"
                elseif (preg_match('/^\d{4}-\d{2}$/', $this->$dateField)) {
                    $this->merge([$dateField => $this->$dateField . '-01']);
                }
            }
        }
    }

    /**
     * Handle a failed validation attempt.
     * Logs failed attempts and throws a validation exception.
     *
     * @param \Illuminate\Validation\Validator $validator
     * @return void
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
