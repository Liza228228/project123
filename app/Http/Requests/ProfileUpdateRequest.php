<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /** Длины колонок в таблице `users` (string N). */
    private const MAX_NAME_PART = 45;

    private const MAX_EMAIL = 50;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'surname' => ['required', 'string', 'max:'.self::MAX_NAME_PART],
            'name' => ['required', 'string', 'max:'.self::MAX_NAME_PART],
            'patronymic' => ['required', 'string', 'max:'.self::MAX_NAME_PART],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:'.self::MAX_EMAIL,
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxPerson = (string) self::MAX_NAME_PART;
        $maxMail = (string) self::MAX_EMAIL;

        return [
            'surname.required' => 'Укажите фамилию.',
            'surname.max' => 'В фамилии слишком много символов (не более '.$maxPerson.').',
            'name.required' => 'Укажите имя.',
            'name.max' => 'В имени слишком много символов (не более '.$maxPerson.').',
            'patronymic.required' => 'Укажите отчество.',
            'patronymic.max' => 'В отчестве слишком много символов (не более '.$maxPerson.').',
            'email.required' => 'Укажите адрес электронной почты.',
            'email.email' => 'Введите корректный адрес электронной почты.',
            'email.max' => 'В адресе почты слишком много символов (не более '.$maxMail.').',
            'email.unique' => 'Этот адрес уже используется.',
        ];
    }
}
