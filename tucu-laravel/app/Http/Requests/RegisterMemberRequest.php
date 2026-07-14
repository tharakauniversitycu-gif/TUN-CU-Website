<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'     => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', 'unique:members,email'],
            'password'      => ['required', 'string', 'min:6', 'confirmed'],
            'reg_number'    => ['nullable', 'string', 'max:50', 'unique:members,reg_number'],
            'phone'         => ['nullable', 'string', 'max:20'],
            'course'        => ['nullable', 'string', 'max:255'],
            'year_of_study' => ['nullable', 'string', 'max:50'],
            'ministry'      => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique'       => 'This email is already registered.',
            'reg_number.unique'  => 'This registration number is already in use.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min'       => 'Password must be at least 6 characters.',
        ];
    }
}
