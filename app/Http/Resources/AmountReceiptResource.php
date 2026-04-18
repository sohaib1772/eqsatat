<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AmountReceiptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'receipt_number'   => $this->receipt_number,
            'customer_id'      => $this->customer_id,
            'customer_name'    => $this->customer->name,
            'user_id'          => $this->user_id,
            'user_name'        => $this->user->name,
            'amount'           => $this->amount,
            'remaining_amount' => $this->remaining_amount,
            'receipt_date'     => $this->receipt_date->format('Y-m-d'),
            'notes'            => $this->notes,
            'created_at'       => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
