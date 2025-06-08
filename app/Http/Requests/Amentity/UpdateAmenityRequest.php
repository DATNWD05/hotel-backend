<?php

namespace App\Http\Requests\Amentity;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAmenityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $amenityId = $this->route('amenity'); // route parameter {amenity}

        return [
            'category_id'      => 'required|exists:amenity_categories,id',
            'code'             => [
                'required',
                'string',
                'max:50',
                Rule::unique('amenities', 'code')->ignore($amenityId),
            ],
            'name'             => 'required|string|max:100',
            'description'      => 'nullable|string',
            'icon'             => 'nullable|string|max:255',
            'price'            => 'required|numeric|min:0',
            'default_quantity' => 'required|integer|min:0',
            'status'           => 'required|in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required'      => 'Danh mục là bắt buộc.',
            'category_id.exists'        => 'Danh mục được chọn không tồn tại.',
            'code.required'             => 'Mã là bắt buộc.',
            'code.string'               => 'Mã phải là một chuỗi ký tự.',
            'code.max'                  => 'Mã không được vượt quá 50 ký tự.',
            'code.unique'               => 'Mã này đã tồn tại, vui lòng chọn mã khác.',
            'name.required'             => 'Tên là bắt buộc.',
            'name.string'               => 'Tên phải là một chuỗi ký tự.',
            'name.max'                  => 'Tên không được vượt quá 100 ký tự.',
            'description.string'        => 'Mô tả phải là một chuỗi ký tự.',
            'icon.string'               => 'Biểu tượng phải là một chuỗi ký tự.',
            'icon.max'                  => 'Biểu tượng không được vượt quá 255 ký tự.',
            'price.required'            => 'Giá là bắt buộc.',
            'price.numeric'             => 'Giá phải là một số.',
            'price.min'                 => 'Giá không được nhỏ hơn 0.',
            'default_quantity.required' => 'Số lượng mặc định là bắt buộc.',
            'default_quantity.integer'  => 'Số lượng mặc định phải là một số nguyên.',
            'default_quantity.min'      => 'Số lượng mặc định không được nhỏ hơn 0.',
            'status.required'           => 'Trạng thái là bắt buộc.',
            'status.in'                 => 'Trạng thái phải là "active" hoặc "inactive".',
        ];
    }
}
