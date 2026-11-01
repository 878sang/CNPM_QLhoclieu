<?php

namespace App\Http\Requests;

use App\Models\Course;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $course = $this->route('course');

        return $course
            ? $this->user()->can('update', $course)
            : $this->user()->can('create', Course::class);
    }

    public function rules(): array
    {
        $rules = [
            'subject_id' => ['required', 'exists:subjects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'status' => ['required', 'in:draft,public,archived'],
        ];

        if ($this->user()->hasRole('admin')) {
            $rules['teacher_id'] = ['required', Rule::exists('users', 'id')->where('role_id', Role::where('name', 'teacher')->value('id'))];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'subject_id.required' => 'Vui lòng chọn môn học.',
            'subject_id.exists' => 'Môn học không tồn tại.',
            'teacher_id.required' => 'Vui lòng chọn giảng viên.',
            'teacher_id.exists' => 'Giảng viên không hợp lệ.',
            'title.required' => 'Vui lòng nhập tên khóa học.',
            'title.max' => 'Tên khóa học không được vượt quá 255 ký tự.',
            'cover_image.image' => 'Ảnh bìa phải là tệp hình ảnh.',
            'cover_image.mimes' => 'Ảnh bìa chỉ nhận định dạng JPG, PNG hoặc WEBP.',
            'cover_image.max' => 'Ảnh bìa không được vượt quá 2 MB.',
            'status.required' => 'Vui lòng chọn trạng thái.',
            'status.in' => 'Trạng thái không hợp lệ.',
        ];
    }
}
