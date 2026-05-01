<?php
namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UploadScormCoursesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
             'start_date' => 'required|date',
             'expire_at'  => 'required|date|after_or_equal:start_date',
             'title' => 'required|string|max:255',
             'category_id' => 'required',
             'course_type' => 'required',
             'course_payment_type' => 'required',
             'teacher_id' => 'required|exists:users,id',
             'price' => $this->course_payment_type === 'Paid' ? 'required|numeric|min:1' : 'nullable|numeric',
             'scorm_package' => 'required|file|mimes:zip|max:51200'
        ];
    }
}
