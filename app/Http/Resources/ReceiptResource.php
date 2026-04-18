<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\ReceiptProductResource;

class ReceiptResource extends JsonResource
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
            'notes' => $this->notes,
            'type' => $this->type,
            'receipt_date' => $this->receipt_date->format('Y-m-d '),
            'user_id' => $this->user_id,
            'user_name' => $this->user->name,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer->name,
            'first_pay' => $this->first_pay,
        ];
    }
}
