<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBoilerChiefRequestLayoutRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('approver_id') && $this->input('approver_id') === '') {
            $merge['approver_id'] = null;
        }
        if ($this->has('division_assigner_id') && $this->input('division_assigner_id') === '') {
            $merge['division_assigner_id'] = null;
        }
        if ($this->has('executor_user_id') && $this->input('executor_user_id') === '') {
            $merge['executor_user_id'] = null;
        }
        if ($this->has('document_header_layout_id') && $this->input('document_header_layout_id') === '') {
            $merge['document_header_layout_id'] = null;
        }
        if (! $this->has('executor_mode')) {
            $merge['executor_mode'] = 'user';
            $merge['executor_user_id'] = $this->user()?->id;
            $merge['needs_coordinator'] = false;
            $merge['requires_print'] = false;
            $merge['approver_id'] = null;
        }
        if (! $this->filled('pdf_footer_preset')) {
            $merge['pdf_footer_preset'] = 'one_signer_author';
        }
        if (! $this->filled('signature_slots_count')) {
            $merge['signature_slots_count'] = $this->defaultSignatureSlotsCount(
                (string) $this->input('pdf_footer_preset', 'one_signer_author')
            );
        }
        $signatureRoles = $this->input('signature_roles', []);
        if (! is_array($signatureRoles)) {
            $signatureRoles = [];
        }
        $normalizedSignatureRoles = [];
        foreach ([1, 2, 3] as $slot) {
            $rawRoleId = $signatureRoles[$slot] ?? $signatureRoles[(string) $slot] ?? null;
            if ($rawRoleId === '' || $rawRoleId === null) {
                $normalizedSignatureRoles[$slot] = null;

                continue;
            }
            $normalizedSignatureRoles[$slot] = (int) $rawRoleId;
        }
        $merge['signature_roles'] = $normalizedSignatureRoles;
        if ((string) $this->input('executor_mode') === 'user') {
            $merge['division_assigner_id'] = null;
        }
        if ((string) $this->input('executor_mode') === 'department') {
            $merge['executor_user_id'] = null;
        }
        if (is_array($this->input('fields'))) {
            $fields = [];
            foreach ($this->input('fields') as $i => $row) {
                if (! is_array($row)) {
                    $fields[$i] = $row;

                    continue;
                }
                if (array_key_exists('key', $row)) {
                    $row['key'] = $this->sanitizeRequestLayoutFieldKey((string) $row['key']);
                }
                $fields[$i] = $row;
            }
            $merge['fields'] = $fields;
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * Нормализует ключ поля: обрезка, одиночные пробелы, только буквы/цифры/пробел/«_».
     */
    private function sanitizeRequestLayoutFieldKey(string $key): string
    {
        $key = trim(preg_replace('/\s+/u', ' ', $key));
        if ($key === '') {
            return '';
        }
        $key = preg_replace('/[^\p{L}\p{N}_\s]/u', '', $key);
        $key = trim(preg_replace('/\s+/u', ' ', $key));
        if ($key === '') {
            return '';
        }
        if (mb_strlen($key) > 64) {
            $key = mb_substr($key, 0, 64);
        }

        return $key;
    }

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
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:128'],
            'document_title' => ['nullable', 'string', 'max:255'],
            'heading_template' => ['nullable', 'string', 'max:50000'],
            'pdf_header_align' => ['nullable', 'string', 'in:left,center,right'],
            'pdf_body_align' => ['nullable', 'string', 'in:left,center,right,justify'],
            'pdf_footer_left_align' => ['nullable', 'string', 'in:left,center'],
            'pdf_footer_right_align' => ['nullable', 'string', 'in:left,center,right'],
            'header_template' => ['nullable', 'string', 'max:50000'],
            'footer_left_template' => ['nullable', 'string', 'max:50000'],
            'signature_template' => ['nullable', 'string', 'max:50000'],
            'body_template' => ['required', 'string', 'max:50000'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*.key' => ['required', 'string', 'max:64', 'regex:/^[\p{L}\p{N}_][\p{L}\p{N}_\s]*$/u'],
            'fields.*.label' => ['nullable', 'string', 'max:255'],
            'fields.*.type' => ['required', 'string', 'in:text,number,textarea,date,address'],
            'needs_statement_header' => ['sometimes', 'boolean'],
            'presentation_heading_size_pt' => ['nullable', 'integer', 'min:8', 'max:36'],
            'presentation_subtitle_size_pt' => ['nullable', 'integer', 'min:8', 'max:28'],
            'pdf_footer_preset' => ['nullable', 'string', 'in:one_signer_author,two_signers,three_signers,classic_split'],
            'signature_slots_count' => ['nullable', 'integer', 'min:1', 'max:3'],
            'signature_roles' => ['nullable', 'array'],
            'signature_roles.*' => ['nullable', 'integer', 'exists:roles,id'],
            'footer_stamp' => ['sometimes', 'boolean'],
            'needs_coordinator' => ['sometimes', 'boolean'],
            'requires_print' => ['sometimes', 'boolean'],
            'executor_mode' => ['nullable', 'string', 'in:user,department'],
            'executor_user_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::requiredIf((string) ($this->input('executor_mode') ?? 'user') === 'user'),
            ],
            'division_assigner_id' => [
                'nullable',
                'integer',
                'exists:departments,id',
                Rule::requiredIf((string) ($this->input('executor_mode') ?? 'user') === 'department'),
            ],
            'layout_type' => ['nullable', 'string', 'max:32'],
            'layout_version' => ['nullable', 'integer', 'min:1', 'max:999999'],
            'approver_id' => [
                'nullable',
                'integer',
                'exists:users,id',
                Rule::requiredIf($this->boolean('needs_coordinator')),
            ],
            'document_header_layout_id' => [
                'nullable',
                'integer',
                Rule::exists('document_header_layouts', 'id'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'fields.*.key.regex' => 'Ключ поля: буква, цифра или «_» в начале, далее буквы, цифры, пробелы и «_»; не длиннее 64 символов.',
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'название макета',
            'category' => 'категория',
            'document_title' => 'краткое название в PDF',
            'heading_template' => 'блок заголовка PDF',
            'header_template' => 'служебный блок',
            'footer_left_template' => 'нижний блок слева',
            'signature_template' => 'блок подписи',
            'body_template' => 'основной текст',
            'pdf_header_align' => 'выравнивание служебного блока',
            'pdf_body_align' => 'выравнивание основного текста',
            'pdf_footer_left_align' => 'выравнивание нижнего блока слева',
            'pdf_footer_right_align' => 'выравнивание нижнего блока справа',
            'fields' => 'поля заявки',
            'fields.*.key' => 'ключ поля',
            'fields.*.label' => 'подпись поля',
            'fields.*.type' => 'тип поля',
            'needs_coordinator' => 'нужен согласующий',
            'requires_print' => 'требуется печать',
            'executor_mode' => 'режим ответственного',
            'executor_user_id' => 'сотрудник-исполнитель',
            'division_assigner_id' => 'подразделение-исполнитель',
            'layout_type' => 'тип макета',
            'layout_version' => 'версия макета',
            'approver_id' => 'утверждающий',
            'document_header_layout_id' => 'макет шапки документа',
            'signature_slots_count' => 'количество подписей',
            'signature_roles' => 'роли подписей',
            'signature_roles.*' => 'роль подписи',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $fields = $this->input('fields', []);
            if (! is_array($fields)) {
                return;
            }
            $keys = [];
            foreach ($fields as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $k = isset($row['key']) ? (string) $row['key'] : '';
                if ($k === '') {
                    continue;
                }
                if (isset($keys[$k])) {
                    $validator->errors()->add('fields', 'Ключи полей должны быть уникальными (дубликат: '.$k.').');

                    return;
                }
                $keys[$k] = true;
            }
            $slotCount = (int) ($this->input('signature_slots_count') ?? 0);
            $slotCount = max(1, min(3, $slotCount));
            $signatureRoles = $this->input('signature_roles', []);
            if (! is_array($signatureRoles)) {
                $signatureRoles = [];
            }
            for ($slot = 1; $slot <= $slotCount; $slot++) {
                $roleId = (int) ($signatureRoles[$slot] ?? $signatureRoles[(string) $slot] ?? 0);
                if ($roleId <= 0) {
                    $validator->errors()->add('signature_roles.'.$slot, 'Выберите роль для подписи №'.$slot.'.');
                }
            }
        });
    }

    /**
     * @return array{
     *     title: string,
     *     schema: array<string, mixed>,
     *     has_header: bool,
     *     type: string,
     *     version: int,
     *     approver_id: int|null,
     *     division_assigner_id: int|null,
     *     document_header_layout_id: int|null
     * }
     */
    public function layoutPayload(): array
    {
        $validated = $this->validated();
        $fields = [];
        foreach ($validated['fields'] as $row) {
            $key = trim((string) $row['key']);
            $label = isset($row['label']) ? trim((string) $row['label']) : '';
            if ($label === '') {
                $label = $key;
            }
            $fields[] = [
                'key' => $key,
                'label' => $label,
                'type' => $row['type'],
            ];
        }

        $version = max(1, (int) ($validated['layout_version'] ?? 1));
        $executorMode = (string) ($validated['executor_mode'] ?? 'user');
        $executorUserId = $executorMode === 'user' ? (int) ($validated['executor_user_id'] ?? 0) : null;
        if ($executorUserId === 0) {
            $executorUserId = null;
        }
        $divisionId = $executorMode === 'department'
            ? (isset($validated['division_assigner_id']) ? (int) $validated['division_assigner_id'] : null)
            : null;

        $documentHeaderLayoutId = isset($validated['document_header_layout_id'])
            ? (int) $validated['document_header_layout_id']
            : null;
        if ($documentHeaderLayoutId === 0) {
            $documentHeaderLayoutId = null;
        }

        $preset = isset($validated['pdf_footer_preset']) ? trim((string) $validated['pdf_footer_preset']) : '';
        $footerStamp = $this->boolean('footer_stamp');
        $signatureSlotsCount = max(1, min(3, (int) ($validated['signature_slots_count'] ?? $this->defaultSignatureSlotsCount($preset))));
        $signatureRoles = [];
        $rolesInput = $validated['signature_roles'] ?? [];
        if (is_array($rolesInput)) {
            for ($slot = 1; $slot <= $signatureSlotsCount; $slot++) {
                $roleId = (int) ($rolesInput[$slot] ?? $rolesInput[(string) $slot] ?? 0);
                if ($roleId > 0) {
                    $signatureRoles[$slot] = $roleId;
                }
            }
        }
        if ($preset !== '') {
            [$footerLeft, $signature] = $this->footerTemplatesFromPreset($preset, $footerStamp);
        } else {
            $footerLeft = (string) ($validated['footer_left_template'] ?? '');
            $signature = (string) ($validated['signature_template'] ?? '');
        }

        $presentationHeadingPt = (int) ($validated['presentation_heading_size_pt'] ?? 15);
        if ($presentationHeadingPt < 8) {
            $presentationHeadingPt = 15;
        }
        if ($presentationHeadingPt > 36) {
            $presentationHeadingPt = 36;
        }
        $presentationSubtitlePt = (int) ($validated['presentation_subtitle_size_pt'] ?? 12);
        if ($presentationSubtitlePt < 8) {
            $presentationSubtitlePt = 12;
        }
        if ($presentationSubtitlePt > 28) {
            $presentationSubtitlePt = 12;
        }

        $needsStatementHeader = $this->boolean('needs_statement_header');

        return [
            'title' => $validated['title'],
            'schema' => [
                'fields' => $fields,
                'body_template' => $validated['body_template'],
                'header_template' => $documentHeaderLayoutId !== null
                    ? ''
                    : (string) ($validated['header_template'] ?? ''),
                'footer_left_template' => $footerLeft,
                'signature_template' => $signature,
                'document_title' => trim((string) ($validated['document_title'] ?? '')) ?: 'ЗАЯВКА',
                'heading_template' => (string) ($validated['heading_template'] ?? ''),
                'presentation_heading_size_pt' => $presentationHeadingPt,
                'presentation_subtitle_size_pt' => $presentationSubtitlePt,
                'pdf_footer_preset' => $preset !== '' ? $preset : null,
                'signature_slots_count' => $signatureSlotsCount,
                'signature_roles' => $signatureRoles,
                'footer_stamp' => $footerStamp,
                'needs_statement_header' => $needsStatementHeader,
                'pdf_header_align' => $this->normalizedPdfAlign(
                    $validated['pdf_header_align'] ?? null,
                    ['left', 'center', 'right'],
                    'right'
                ),
                'pdf_body_align' => $this->normalizedPdfAlign(
                    $validated['pdf_body_align'] ?? null,
                    ['left', 'center', 'right', 'justify'],
                    'center'
                ),
                'pdf_footer_left_align' => $this->normalizedPdfAlign(
                    $validated['pdf_footer_left_align'] ?? null,
                    ['left', 'center'],
                    'left'
                ),
                'pdf_footer_right_align' => $this->normalizedPdfAlign(
                    $validated['pdf_footer_right_align'] ?? null,
                    ['left', 'center', 'right'],
                    'right'
                ),
                'category' => isset($validated['category']) ? trim((string) $validated['category']) : '',
                'executor_mode' => $executorMode,
                'executor_user_id' => $executorUserId,
                'flags' => [
                    'needs_coordinator' => $this->boolean('needs_coordinator'),
                    'requires_print' => $this->boolean('requires_print'),
                ],
            ],
            'has_header' => $needsStatementHeader || $this->boolean('needs_coordinator'),
            'type' => ! empty($validated['layout_type']) ? (string) $validated['layout_type'] : 'pdf',
            'version' => $version,
            'approver_id' => $validated['approver_id'] ?? null,
            'division_assigner_id' => $divisionId,
            'document_header_layout_id' => $documentHeaderLayoutId,
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function footerTemplatesFromPreset(string $preset, bool $stamp): array
    {
        $mp = $stamp ? "\n\nМ.П." : '';

        return match ($preset) {
            'one_signer_author' => [
                "{{фио}}\n\nДата: {{document_date}}",
                '________________ / {{signatory_print_name}}'.$mp,
            ],
            'two_signers' => [
                " {{signer_1_fio}}\n {{signer_2_fio}}\n\nДата: {{document_date}}",
                '________________ / {{signatory_print_name}}'.$mp,
            ],
            'three_signers' => [
                " {{signer_1_fio}}\n {{signer_2_fio}}\n {{signer_3_fio}}\n\nДата: {{document_date}}",
                '________________'.$mp,
            ],
            'classic_split' => [
                "{{фио}}\n\n{{текст}}\n\nДата: {{document_date}}",
                '________________ / {{signatory_print_name}}'.$mp,
            ],
            default => [
                "{{фио}}\n\nДата: {{document_date}}",
                '________________ / {{signatory_print_name}}'.$mp,
            ],
        };
    }

    /**
     * @param  list<string>  $allowed
     */
    private function normalizedPdfAlign(mixed $value, array $allowed, string $default): string
    {
        $v = strtolower(trim((string) ($value ?? '')));

        return in_array($v, $allowed, true) ? $v : $default;
    }

    private function defaultSignatureSlotsCount(string $preset): int
    {
        return match (trim($preset)) {
            'three_signers' => 3,
            'two_signers' => 2,
            default => 1,
        };
    }
}
