<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return ['rejection_reason' => ['required', 'string', 'max:1000']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rejection_reason.required' => 'Vui lòng nhập lý do từ chối.',
            'rejection_reason.max' => 'Lý do từ chối không được vượt quá 1000 ký tự.',
        ];
    }
}
