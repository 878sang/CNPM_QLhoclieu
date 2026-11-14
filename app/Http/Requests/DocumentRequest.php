<?php

namespace App\Http\Requests;

use App\Models\Chapter;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($this->route('course')) {
            return $user->can('update', $this->route('course'));
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        $course = Course::find($this->input('course_id'));

        return $course ? $user->can('update', $course) : false;
    }

    protected function prepareForValidation(): void
    {
        $title = trim((string) $this->input('title'));
        if ($title === '' && $this->hasFile('file')) {
            $orig = $this->file('file')->getClientOriginalName();
            $title = pathinfo($orig, PATHINFO_FILENAME) ?: $orig;
        }

        $type = $this->input('type', 'file');
        if ($type === 'file' && $this->hasFile('file')) {
            $extension = strtolower($this->file('file')->getClientOriginalExtension());
            if (in_array($extension, ['mp4', 'webm', 'ogg', 'mkv', 'mov'], true)) {
                $type = 'video';
            }
        }

        $this->merge([
            'title' => $title !== '' ? $title : ($this->filled('url') ? $this->input('url') : 'Tài liệu'),
            'type' => $type,
            'allow_view' => $this->boolean('allow_view'),
            'allow_download' => $this->boolean('allow_download'),
            'lesson_id' => $this->input('lesson_id') ?: null,
            'url' => $this->filled('url') ? trim((string) $this->input('url')) : null,
        ]);
    }

    public function rules(): array
    {
        $course = $this->route('course') ?? Course::find($this->input('course_id'));
        $document = $this->route('document');
        $type = $this->input('type', 'file');

        $rules = [
            'type' => ['required', 'in:file,video,link'],
            'title' => ['nullable', 'string', 'max:255'],
            'allow_view' => ['boolean'],
            'allow_download' => ['boolean'],
            'redirect_to' => ['nullable', 'string'],
        ];

        if (! $this->route('course')) {
            $rules['course_id'] = ['required', 'exists:courses,id'];
        }

        $rules['lesson_id'] = [
            'nullable',
            Rule::exists('lessons', 'id')->when($course, function ($query) use ($course) {
                return $query->whereIn(
                    'chapter_id',
                    Chapter::where('course_id', $course->id)->select('id')
                );
            }),
        ];

        if ($type === 'link') {
            $rules['url'] = ['required', 'url', 'max:2048'];
        } elseif ($type === 'video') {
            if ($this->hasFile('file')) {
                $rules['file'] = ['file', 'extensions:mp4,webm,ogg,mkv,mov', 'max:204800']; // 200MB
            } elseif (! $document && ! $this->filled('url')) {
                $rules['url'] = ['required_without:file', 'url', 'max:2048'];
            } elseif ($this->filled('url')) {
                $rules['url'] = ['url', 'max:2048'];
            }
        } else {
            // type == 'file'
            $fileRule = $document ? ['nullable', 'file'] : ['required', 'file'];
            $fileRule[] = 'extensions:pdf,doc,docx,ppt,pptx,xls,xlsx,txt';
            $fileRule[] = 'max:20480'; // 20MB
            $rules['file'] = $fileRule;
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Vui lòng nhập tên tài liệu.',
            'title.max' => 'Tên tài liệu không được vượt quá 255 ký tự.',
            'type.required' => 'Vui lòng chọn loại tài liệu.',
            'type.in' => 'Loại tài liệu không hợp lệ.',
            'file.required' => 'Vui lòng chọn tệp tài liệu.',
            'file.mimes' => 'Tài liệu chỉ nhận định dạng PDF, Word, PowerPoint, Excel hoặc video MP4/WebM.',
            'file.max' => 'Tệp vượt quá dung lượng cho phép (tài liệu tối đa 20 MB, video tối đa 200 MB).',
            'file.uploaded' => 'Tải tệp lên thất bại. Vui lòng thử lại.',
            'file.extensions' => 'Định dạng tệp không được hỗ trợ. Vui lòng chọn tệp PDF, Word (.doc, .docx), PowerPoint (.ppt, .pptx), Excel (.xls, .xlsx) hoặc TXT.',
            'file.mimes' => 'Định dạng tệp không được hỗ trợ. Vui lòng chọn tệp PDF, Word (.doc, .docx), PowerPoint (.ppt, .pptx), Excel (.xls, .xlsx) hoặc TXT.',
            'file.max' => 'Dung lượng tệp vượt quá giới hạn cho phép (tối đa 20MB cho tài liệu, 500MB cho video).',
            'file.uploaded' => 'Tải tệp lên thất bại. Vui lòng kiểm tra dung lượng và thử lại.',
            'url.required' => 'Vui lòng nhập liên kết (URL).',
            'url.required_without' => 'Vui lòng tải lên tệp video hoặc dán link video.',
            'url.url' => 'Địa chỉ liên kết (URL) không hợp lệ.',
            'lesson_id.exists' => 'Bài học không thuộc khóa học này.',
            'course_id.required' => 'Vui lòng chọn khóa học cho tài liệu.',
            'course_id.exists' => 'Khóa học được chọn không tồn tại trong hệ thống.',
        ];
    }
}
