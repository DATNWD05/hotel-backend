<?php

namespace App\Http\Requests\Departments;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends BaseFormRequest
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
            'name' => ['required', 'string', Rule::unique('departments')->ignore($this->department)],
            'manager_id' => 'nullable|exists:employees,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Tên phòng ban không được để trống.',
            'name.string' => 'Tên phòng ban phải là chuỗi ký tự.',
            'name.unique' => 'Tên phòng ban đã tồn tại.',
            'manager_id.exists' => 'Nhân viên quản lý không tồn tại.',
        ];
    }
}
