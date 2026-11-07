<?php

namespace App\Http\Requests;

use App\Models\Chapter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('course'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'allow_view' => $this->boolean('allow_view'),
            'allow_download' => $this->boolean('allow_download'),
            'lesson_id' => $this->input('lesson_id') ?: null,
        ]);
    }

    public function rules(): array
    {
        $course = $this->route('course');

        return [
            'title' => ['required', 'string', 'max:255'],
            'file' => [$this->route('document') ? 'nullable' : 'required', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx', 'max:20480'],
            'lesson_id' => ['nullable', Rule::exists('lessons', 'id')->whereIn('chapter_id', Chapter::where('course_id', $course->id)->select('id'))],
            'allow_view' => ['boolean'],
            'allow_download' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tên tài liệu.',
            'title.max' => 'Tên tài liệu không được vượt quá 255 ký tự.',
            'file.required' => 'Vui lòng chọn tệp tài liệu.',
            'file.mimes' => 'Tài liệu chỉ nhận định dạng PDF, Word, PowerPoint hoặc Excel.',
            'file.max' => 'Tài liệu không được vượt quá 20 MB.',
            'file.uploaded' => 'Tải tệp lên thất bại. Vui lòng thử lại.',
            'lesson_id.exists' => 'Bài học không thuộc khóa học này.',
        ];
    }
}
