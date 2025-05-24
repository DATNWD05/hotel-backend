<?php

namespace App\Http\Requests\Employees;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends BaseFormRequest
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
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => ['required', 'email', Rule::unique('employees')->ignore($this->employee)],
            'phone' => 'nullable|string|max:20',
            'department_id' => 'required|exists:departments,id',
            'hire_date' => 'required|date',
            'status' => 'required|in:active,inactive',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'Tên không được để trống.',
            'first_name.string' => 'Tên phải là chuỗi ký tự.',
            'first_name.max' => 'Tên không được dài quá 100 ký tự.',
            'last_name.required' => 'Họ không được để trống.',
            'last_name.string' => 'Họ phải là chuỗi ký tự.',
            'last_name.max' => 'Họ không được dài quá 100 ký tự.',
            'email.required' => 'Email không được để trống.',
            'email.email' => 'Email không đúng định dạng.',
            'email.unique' => 'Email đã tồn tại trong hệ thống.',
            'phone.string' => 'Số điện thoại phải là chuỗi ký tự.',
            'phone.max' => 'Số điện thoại không được dài quá 20 ký tự.',
            'department_id.required' => 'Phòng ban không được để trống.',
            'department_id.exists' => 'Phòng ban không tồn tại.',
            'hire_date.required' => 'Ngày tuyển dụng không được để trống.',
            'hire_date.date' => 'Ngày tuyển dụng không đúng định dạng.',
            'status.required' => 'Trạng thái không được để trống.',
            'status.in' => 'Trạng thái phải là active hoặc inactive.',
        ];
    }
}
