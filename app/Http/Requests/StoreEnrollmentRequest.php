<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User $user */
        $user = $this->user();

        return $user && $user->can('update', $this->route('course'));
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'array',
                'min:1',
            ],
            'user_id.*' => [
                'integer',
                'distinct',
                Rule::exists('users', 'id')->where('role_id', Role::where('name', 'student')->value('id'))->where('status', 'active'),
                Rule::unique('enrollments', 'user_id')->where('course_id', $this->route('course')->id),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('user_id') && ! is_array($this->input('user_id'))) {
            $this->merge(['user_id' => [$this->input('user_id')]]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->has('user_id.0') && ! $validator->errors()->has('user_id')) {
                $validator->errors()->add('user_id', $validator->errors()->first('user_id.0'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Vui lòng chọn học viên.',
            'user_id.array' => 'Vui lòng chọn ít nhất một học viên.',
            'user_id.min' => 'Vui lòng chọn ít nhất một học viên.',
            'user_id.exists' => 'Học viên không hợp lệ hoặc đã bị khóa.',
            'user_id.*.exists' => 'Học viên không hợp lệ hoặc đã bị khóa.',
            'user_id.*.unique' => 'Một học viên đã có trong khóa học.',
        ];
    }
}
