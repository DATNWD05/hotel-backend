<?php

namespace App\Http\Resources;

use App\Http\Resources\AmenityResource;

use Illuminate\Http\Resources\Json\JsonResource;

class AmenityCategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            "description" => $this->description,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
            // Nếu cần load relationship 'amenities':
            'amenities'  => AmenityResource::collection($this->whenLoaded('amenities')),
        ];
    }
}
