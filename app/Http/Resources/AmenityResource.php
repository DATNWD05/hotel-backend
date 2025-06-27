<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AmenityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id ?? '0', // Trả về category_id trực tiếp, mặc định '0' nếu NULL
            'code' => $this->code ?? 'UNKNOWN', // Thêm code với giá trị mặc định
            'name' => $this->name,
            'description' => $this->description ?? '-', // Thêm description
            'icon' => $this->icon ?? '', // Thêm icon
            'price' => $this->price ?? 0, // Thêm price với giá trị mặc định 0
            'default_quantity' => $this->default_quantity ?? 1, // Thêm default_quantity với giá trị mặc định 1
            'status' => $this->status ?? 'Không xác định', // Thêm status
            'category' => $this->category ? [
                'id' => $this->category->id ?? '0',
                'name' => $this->category->name ?? 'Không xác định',
            ] : null, // Trả về category với xử lý NULL
            'created_at' => $this->created_at ? $this->created_at->toDateTimeString() : null,
            'updated_at' => $this->updated_at ? $this->updated_at->toDateTimeString() : null,
        ];
    }
}
