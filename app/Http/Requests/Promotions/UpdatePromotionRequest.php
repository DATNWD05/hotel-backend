<?php

namespace App\Http\Requests\Promotions;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePromotionRequest extends BaseFormRequest
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
        $id = $this->route('promotion')->id;
        return [
            'code'           => "required|string|unique:promotions,code,{$id}",
            'description'    => 'nullable|string',
            'discount_type'  => 'required|in:percent,amount',
            'discount_value' => 'required|numeric|min:0',
            'start_date'     => 'required|date',
            'end_date'       => 'required|date|after_or_equal:start_date',
            'usage_limit'    => 'required|integer|min:1',
            'is_active'      => 'sometimes|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required'           => 'Mã khuyến mãi là bắt buộc.',
            'code.string'             => 'Mã khuyến mãi phải là chuỗi ký tự.',
            'code.unique'             => 'Mã khuyến mãi đã tồn tại, vui lòng chọn mã khác.',
            'description.string'      => 'Mô tả phải là chuỗi ký tự.',
            'discount_type.required'  => 'Loại giảm giá là bắt buộc.',
            'discount_type.in'        => 'Loại giảm giá phải là "percent" hoặc "amount".',
            'discount_value.required' => 'Giá trị giảm giá là bắt buộc.',
            'discount_value.numeric'  => 'Giá trị giảm giá phải là số.',
            'discount_value.min'      => 'Giá trị giảm giá không được nhỏ hơn 0.',
            'start_date.required'     => 'Ngày bắt đầu là bắt buộc.',
            'start_date.date'         => 'Ngày bắt đầu không đúng định dạng.',
            'end_date.required'       => 'Ngày kết thúc là bắt buộc.',
            'end_date.date'           => 'Ngày kết thúc không đúng định dạng.',
            'end_date.after_or_equal' => 'Ngày kết thúc phải sau hoặc bằng ngày bắt đầu.',
            'usage_limit.required'    => 'Giới hạn sử dụng là bắt buộc.',
            'usage_limit.integer'     => 'Giới hạn sử dụng phải là số nguyên.',
            'usage_limit.min'         => 'Giới hạn sử dụng phải lớn hơn hoặc bằng 1.',
            'is_active.boolean'       => 'Trường “is_active” phải là true hoặc false.'
        ];
    }
}