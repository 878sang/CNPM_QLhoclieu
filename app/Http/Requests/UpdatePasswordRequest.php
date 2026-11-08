<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return ['current_password' => ['required', 'current_password'], 'password' => ['required', 'confirmed', Password::defaults()]];
    }

    public function messages(): array
    {
        return ['current_password.current_password' => 'Mật khẩu hiện tại không đúng.', 'password.confirmed' => 'Mật khẩu xác nhận không khớp.'];
    }
}
