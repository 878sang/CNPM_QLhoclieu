<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['login' => ['required', 'string'], 'password' => ['required', 'string']];
    }

    public function messages(): array
    {
        return ['login.required' => 'Vui lòng nhập tên đăng nhập hoặc email.', 'password.required' => 'Vui lòng nhập mật khẩu.'];
    }
}
