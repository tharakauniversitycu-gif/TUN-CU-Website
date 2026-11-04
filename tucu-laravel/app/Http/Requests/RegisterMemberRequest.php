<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('year_of_study') && is_string($this->year_of_study)) {
            preg_match('/\d+/', $this->year_of_study, $matches);
            if (! empty($matches)) {
                $this->merge(['year_of_study' => (int) $matches[0]]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'full_name'           => ['required', 'string', 'max:255'],
            'email'               => ['required', 'email', 'max:255', 'unique:members,email'],
            'password'            => ['required', 'string', 'min:6', 'confirmed'],
            'reg_number'          => ['nullable', 'string', 'max:50', 'unique:members,reg_number'],
            'phone'               => ['nullable', 'string', 'max:20'],
            'course'              => ['nullable', 'string', 'max:255'],
            'course_duration'     => ['nullable', 'integer', 'in:4,5'],
            'year_of_study'       => ['nullable', 'integer', 'between:0,5'],
            'membership_category' => ['nullable', 'string', 'in:full,special,associate'],
            'ministry_ids'        => ['nullable', 'array', 'max:3'],
            'ministry_ids.*'      => ['integer', 'exists:ministries,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'          => 'This email is already registered.',
            'reg_number.unique'     => 'This registration number is already in use.',
            'password.confirmed'    => 'Password confirmation does not match.',
            'password.min'          => 'Password must be at least 6 characters.',
            'ministry_ids.max'      => 'You can select a maximum of 3 ministries.',
            'ministry_ids.*.exists' => 'One or more selected ministries are invalid.',
        ];
    }
}
