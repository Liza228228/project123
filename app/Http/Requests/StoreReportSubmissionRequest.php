<?php

namespace App\Http\Requests;

use App\Models\RequestLayout;
use Illuminate\Foundation\Http\FormRequest;

class StoreReportSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'values' => ['required', 'array'],
            'values.*' => ['nullable', 'string', 'max:20000'],
            'use_current_date' => ['sometimes', 'boolean'],
            'form_document_date' => ['nullable', 'date'],
            'form_document_number' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'values' => 'поля заявки',
            'values.*' => 'значение поля',
            'use_current_date' => 'использовать текущую дату',
            'form_document_date' => 'дата документа',
            'form_document_number' => 'номер документа',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'values.required' => 'Укажите значения полей заявки.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function fieldValues(RequestLayout $layout): array
    {
        $allowed = [];
        foreach ($layout->schema['fields'] ?? [] as $field) {
            if (! empty($field['key'])) {
                $allowed[(string) $field['key']] = true;
            }
        }

        $raw = $this->input('values', []);
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($allowed as $key => $_) {
            $v = $raw[$key] ?? '';
            $out[$key] = is_string($v) ? $v : (is_scalar($v) ? (string) $v : '');
        }

        return $out;
    }
}
