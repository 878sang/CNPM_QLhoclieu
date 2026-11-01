<?php

namespace App\Http\Requests;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('course'));
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                Rule::exists('users', 'id')->where('role_id', Role::where('name', 'student')->value('id'))->where('status', 'active'),
                Rule::unique('enrollments', 'user_id')->where('course_id', $this->route('course')->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Vui lòng chọn học viên.',
            'user_id.exists' => 'Học viên không hợp lệ hoặc đã bị khóa.',
            'user_id.unique' => 'Học viên đã có trong khóa học.',
        ];
    }
}
