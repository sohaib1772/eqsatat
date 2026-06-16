<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $name = $this->name;
        $isDuplicate = \Illuminate\Support\Facades\Cache::remember('cust_dup_' . md5($this->name), 60, function() {
            return \App\Models\Customer::where('name', $this->name)->count() > 1;
        });

        if ($isDuplicate) {
            $suffix = $this->status;
            if ($this->phone) {
                $suffix .= ' - ' . $this->phone;
            }
            $name .= ' (' . $suffix . ')';
        }

        return [
            'id' => $this->id,
            'name' => $name,
            'phone' => $this->phone,
        'sponsor_name' => $this->sponsor_name,
        'Record_id' => $this->Record_id,
        'Page_id' => $this->Page_id,
        'notes' => $this->notes,
        'status' => $this->status,
        'created_at' => $this->created_at->format('Y-m-d'),
        'sponsor_phone' => $this->sponsor_phone,


'lastest_payment_date' => $this->lastest_payment_date
    ? \Carbon\Carbon::parse($this->lastest_payment_date)->format('Y-m-d')
    : null,

        'total_remaining' => $this->total_remaining,
        'days_since_last_payment' => (integer)$this->days_since_last_payment, // << الجديد
    ];
}

}
