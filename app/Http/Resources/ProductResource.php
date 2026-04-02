<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'selling_price' => $this->selling_price,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'installment_price' => $this->installment_price,
            'dolar_buying_price' => $this->dolar_buying_price,
            'origin' => $this->origin->name ?? null,
            'origin_id' => $this->origin->id ?? null,
            'category' => $this->category->name ?? null,
            'category_id' => $this->category->id ?? null,
            'user_name' => $this->user->name ?? null,
            'user_id' => $this->user->id ?? null,
            'created_at' => $this->created_at->format('Y-m-d '),
            "dollar_exchange"=>$this->dollar_exchange,
        ];

        if (array_key_exists('last_selling_price', $this->getAttributes())) {
            $data['last_selling_price'] = $this->last_selling_price;
        }

        return $data;
    }
}
