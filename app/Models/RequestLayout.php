<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RequestLayout extends Model
{
    use SoftDeletes;

    protected $table = 'layout_structures';

    protected $fillable = [
        'title',
        'schema',
        'has_header',
        'type',
        'version',
        'approver_id',
        'division_assigner_id',
        'document_header_layout_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'schema' => 'array',
            'has_header' => 'boolean',
        ];
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function divisionAssigner(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'division_assigner_id');
    }

    public function documentHeaderLayout(): BelongsTo
    {
        return $this->belongsTo(DocumentHeaderLayout::class, 'document_header_layout_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(RequestSubmission::class, 'layout_structure_id');
    }

    /**
     * Число слотов подписей для PDF: 0 — макет без подписей (явно в schema).
     * Если ключ отсутствует (старые макеты), число выводится из пресета подвала (минимум 1).
     */
    public static function resolvedSignatureSlotsCount(?array $schema): int
    {
        $schema = is_array($schema) ? $schema : [];
        if (array_key_exists('signature_slots_count', $schema)) {
            return max(0, min(3, (int) $schema['signature_slots_count']));
        }

        $preset = trim((string) ($schema['pdf_footer_preset'] ?? ''));
        $inferred = match ($preset) {
            'three_signers' => 3,
            'two_signers' => 2,
            default => 1,
        };

        return max(1, min(3, $inferred));
    }

    /**
     * JSON для формы «Заявки по макетам» и GET layout-schema (поля, подписи, пресет подвала).
     *
     * @return array{
     *     id: int,
     *     title: string,
     *     fields: list<array<string, mixed>>,
     *     pdf_footer_preset: string,
     *     signature_slots_count: int,
     *     signature_roles: array<int, int>,
     *     signature_role_names: array<int, string>
     * }
     */
    public function clientFillPayload(): array
    {
        $schema = is_array($this->schema) ? $this->schema : [];

        $fields = [];
        foreach ($schema['fields'] ?? [] as $row) {
            if (! is_array($row) || empty($row['key'])) {
                continue;
            }
            $fields[] = [
                'key' => (string) $row['key'],
                'label' => (string) ($row['label'] ?? $row['key']),
                'type' => (string) ($row['type'] ?? 'text'),
                'choices' => isset($row['choices']) && is_array($row['choices']) ? array_values($row['choices']) : [],
            ];
        }

        $preset = isset($schema['pdf_footer_preset']) ? trim((string) $schema['pdf_footer_preset']) : '';
        $signatureSlotsCount = self::resolvedSignatureSlotsCount($schema);
        $signatureRoles = [];
        $rawSignatureRoles = $schema['signature_roles'] ?? [];
        if (is_array($rawSignatureRoles) && $signatureSlotsCount > 0) {
            for ($slot = 1; $slot <= $signatureSlotsCount; $slot++) {
                $roleId = (int) ($rawSignatureRoles[$slot] ?? $rawSignatureRoles[(string) $slot] ?? 0);
                if ($roleId > 0) {
                    $signatureRoles[$slot] = $roleId;
                }
            }
        }
        $signatureRoleNames = [];
        if ($signatureRoles !== []) {
            $roles = Role::query()
                ->whereIn('id', array_values($signatureRoles))
                ->pluck('name', 'id');
            foreach ($signatureRoles as $slot => $roleId) {
                $signatureRoleNames[$slot] = (string) ($roles[$roleId] ?? '');
            }
        }

        return [
            'id' => (int) $this->id,
            'title' => (string) $this->title,
            'fields' => $fields,
            'pdf_footer_preset' => $preset !== '' ? $preset : 'one_signer_author',
            'signature_slots_count' => $signatureSlotsCount,
            'signature_roles' => $signatureRoles,
            'signature_role_names' => $signatureRoleNames,
        ];
    }
}
