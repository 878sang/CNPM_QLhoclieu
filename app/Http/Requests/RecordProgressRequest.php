<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('student');
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'position' => ['required', 'integer', 'min:0', 'max:86400'],
            'duration' => ['required', 'integer', 'min:1', 'max:86400'],
        ];
    }
}
