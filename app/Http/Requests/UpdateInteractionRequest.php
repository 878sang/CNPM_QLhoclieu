<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInteractionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $interaction = $this->route('interaction');
        $user = $this->user();

        return $user && $interaction->user_id === $user->id
            && ($user->can('update', $interaction->course)
                || ($interaction->lesson?->status === 'published'
                    && $interaction->status !== 'hidden'
                    && $interaction->course->enrollments()->where('user_id', $user->id)->where('status', 'active')->exists()));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('content'))) {
            $this->merge(['content' => trim($this->input('content'))]);
        }
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Vui lòng nhập nội dung bình luận.',
            'content.max' => 'Bình luận không được vượt quá 2000 ký tự.',
        ];
    }
}
