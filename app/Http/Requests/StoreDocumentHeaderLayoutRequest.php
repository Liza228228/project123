<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentHeaderLayoutRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $raw = $this->input('blocks_json');
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $this->merge(['blocks' => $decoded]);
            }
        }
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
            'blocks' => ['required', 'array', 'min:1', 'max:3'],
            'blocks.*.align' => ['required', 'string', 'in:left,center,right'],
            'blocks.*.bold' => ['sometimes', 'boolean'],
            'blocks.*.font_family' => ['required', 'string', 'in:times_new_roman,arial,dejavu_sans,dejavu_serif,georgia'],
            'blocks.*.font_size_pt' => ['required', 'integer', 'min:8', 'max:24'],
            'blocks.*.lines' => ['required', 'array', 'min:1'],
            'blocks.*.lines.*.text' => ['nullable', 'string', 'max:5000'],
            'blocks.*.lines.*.from_application' => ['sometimes', 'boolean'],
            'blocks.*.lines.*.source_key' => ['nullable', 'string', 'in:coordinator_name,representative_name,signatory_print_name,recipient_name,signer_1_name,signer_2_name,signer_3_name,signer_1_fio,signer_2_fio,signer_3_fio'],
            'blocks.*.lines.*.fio_case' => ['nullable', 'string', 'in:nominative,genitive,dative,ablative'],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'название макета шапки',
            'blocks' => 'блоки шапки',
        ];
    }

    /**
     * @return array{title: string, schema: array{blocks: list<array<string, mixed>>}}
     */
    public function payload(): array
    {
        $validated = $this->validated();
        $blocks = [];
        foreach ($validated['blocks'] as $block) {
            $lines = [];
            foreach ($block['lines'] as $line) {
                $lines[] = [
                    'text' => (string) ($line['text'] ?? ''),
                    'from_application' => ! empty($line['from_application']),
                    'source_key' => (string) ($line['source_key'] ?? ''),
                    'fio_case' => (string) ($line['fio_case'] ?? 'nominative'),
                ];
            }
            $blocks[] = [
                'align' => $block['align'],
                'bold' => ! empty($block['bold']),
                'font_family' => $block['font_family'],
                'font_size_pt' => (int) $block['font_size_pt'],
                'lines' => $lines,
            ];
        }

        return [
            'title' => $validated['title'],
            'schema' => ['blocks' => $blocks],
        ];
    }
}
