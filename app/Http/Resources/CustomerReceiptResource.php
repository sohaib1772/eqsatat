<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerReceiptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'receipt_number' => $this->receipt_number,
            'total_price' => $this->total_price,
            'user_name' => $this->user->name ?? null,
            'notes' => $this->notes,
            'type' => $this->type,
            'discount_amount' => $this->discount_amount ?? 0,
            'paid_amount' => $this->paid_amount ?? 0,
            'receipt_date' => $this->receipt_date->format('Y-m-d'),
            'user_id' => $this->user_id,
            'customer_name' => $this->customer->name,
        ];
    }
}
