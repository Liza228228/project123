<?php

namespace App\Http\Requests;

use App\Models\RequestLayout;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLayoutApplicationRequest extends FormRequest
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
            'request_layout_id' => ['required', 'integer', 'exists:request_layout,id'],
            'recipient_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'signer_1_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'signer_2_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'signer_3_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'values' => ['required', 'array'],
            'values.*' => ['nullable', 'string', 'max:100000'],
            'use_current_date' => ['sometimes', 'boolean'],
            'form_document_date' => ['required_if:use_current_date,0', 'nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $id = (int) $this->input('request_layout_id', 0);
            if ($id <= 0) {
                return;
            }
            $layout = RequestLayout::query()->find($id);
            if (! $layout) {
                return;
            }
            if ((int) $layout->user_assigner_id !== (int) $this->user()?->id) {
                $validator->errors()->add('request_layout_id', 'Нет доступа к выбранному макету.');
                return;
            }

            $schema = is_array($layout->schema) ? $layout->schema : [];
            $signatureSlotsCount = (int) ($schema['signature_slots_count'] ?? 0);
            if ($signatureSlotsCount <= 0) {
                $preset = trim((string) ($schema['pdf_footer_preset'] ?? ''));
                $signatureSlotsCount = match ($preset) {
                    'three_signers' => 3,
                    'two_signers' => 2,
                    default => 1,
                };
            }
            $signatureSlotsCount = max(1, min(3, $signatureSlotsCount));
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
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'request_layout_id' => 'макет',
            'recipient_user_id' => 'получатель',
            'signer_1_user_id' => '',
            'signer_2_user_id' => '',
            'signer_3_user_id' => '',
            'values' => 'поля заявки',
            'values.*' => 'значение поля',
            'use_current_date' => 'использовать текущую дату',
            'form_document_date' => 'дата документа',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'values.required' => 'Укажите значения полей заявки.',
            'form_document_date.required_if' => 'Если отключена текущая дата, укажите дату документа.',
        ];
    }

    public function layout(): RequestLayout
    {
        /** @var RequestLayout $l */
        $l = RequestLayout::query()->findOrFail((int) $this->validated('request_layout_id'));

        return $l;
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
