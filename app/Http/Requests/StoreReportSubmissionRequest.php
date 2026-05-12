<?php

namespace App\Http\Requests;

use App\Models\RequestLayout;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'signer_1_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'signer_2_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'signer_3_user_id' => ['nullable', 'integer', 'exists:users,id'],
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
            'signer_1_user_id' => 'подпись №1',
            'signer_2_user_id' => 'подпись №2',
            'signer_3_user_id' => 'подпись №3',
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $layoutId = (int) $this->route('requestLayout')?->id;
            if ($layoutId <= 0) {
                return;
            }
            $layout = RequestLayout::query()->find($layoutId);
            if (! $layout) {
                return;
            }
            $schema = is_array($layout->schema) ? $layout->schema : [];
            $signatureSlotsCount = RequestLayout::resolvedSignatureSlotsCount($schema);
            if ($signatureSlotsCount === 0) {
                return;
            }
            $signatureRoles = is_array($schema['signature_roles'] ?? null) ? $schema['signature_roles'] : [];

            for ($slot = 1; $slot <= $signatureSlotsCount; $slot++) {
                $expectedRoleId = (int) ($signatureRoles[$slot] ?? $signatureRoles[(string) $slot] ?? 0);
                $selectedUserId = (int) $this->input('signer_'.$slot.'_user_id', 0);

                if ($selectedUserId <= 0) {
                    $validator->errors()->add('signer_'.$slot.'_user_id', 'Выберите подписанта №'.$slot.'.');

                    continue;
                }
                if ($expectedRoleId <= 0) {
                    continue;
                }
                $selectedUser = User::query()->find($selectedUserId);
                if (! $selectedUser || (int) $selectedUser->role_id !== $expectedRoleId) {
                    $validator->errors()->add('signer_'.$slot.'_user_id', 'Подписант №'.$slot.' должен соответствовать выбранной роли.');

                    continue;
                }
                $auth = $this->user();
                if ($auth instanceof User
                    && $expectedRoleId === 4
                    && ! User::boilerChiefMaySelectForemanAsSigner($auth, $selectedUser)) {
                    $validator->errors()->add(
                        'signer_'.$slot.'_user_id',
                        'Для начальника котельной доступны только мастера участка из его подразделений.'
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function fieldValues(RequestLayout $layout): array
    {
        $allowed = [];
        foreach ($layout->schema['fields'] ?? [] as $field) {
            $fk = isset($field['key']) ? trim((string) $field['key']) : '';
            if ($fk !== '') {
                $allowed[$fk] = true;
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
