<?php

namespace App\Http\Requests\AmenityCategory;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAmenityCategoryRequest extends BaseFormRequest
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
        $categoryId = $this->route('amenity_category'); // route parameter {amenity_category}

        return [
            'name'        => [
                'required',
                'string',
                'max:100',
                Rule::unique('amenity_categories', 'name')->ignore($categoryId),
            ],
            'description' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên là bắt buộc.',
            'name.string'   => 'Tên phải là một chuỗi ký tự.',
            'name.max'      => 'Tên không được vượt quá 100 ký tự.',
            'name.unique'   => 'Tên này đã tồn tại, vui lòng chọn tên khác.',
            'description.string' => 'Mô tả phải là một chuỗi ký tự.',
        ];
    }
}
