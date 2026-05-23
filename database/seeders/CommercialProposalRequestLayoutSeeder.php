<?php

namespace Database\Seeders;

use App\Models\DocumentHeaderLayout;
use App\Models\RequestLayout;
use App\Support\ReportLayoutCommercialProposal;
use App\Support\RequestLayoutSignatureLine;
use Illuminate\Database\Seeder;

class CommercialProposalRequestLayoutSeeder extends Seeder
{
    public const TITLE = 'Коммерческое предложение';

    /** Устаревшее название макета — удаляется при повторном сиде. */
    private const LEGACY_TITLE = 'Коммерческое предложение (смета видеонаблюдения)';

    public function run(): void
    {
        RequestLayout::query()
            ->where('title', self::LEGACY_TITLE)
            ->delete();

        $this->call(CommercialProposalDocumentHeaderLayoutSeeder::class);

        $headerLayout = DocumentHeaderLayout::query()
            ->where('title', CommercialProposalDocumentHeaderLayoutSeeder::TITLE)
            ->first();

        $heading = '';

        $body = <<<'HTML'
<p style="text-align:center;font-style:italic;margin:12px 0 6px 0;"><strong>Оборудование</strong></p>
{{таблица_оборудование}}

<table cellpadding="4" cellspacing="0" style="width:100%;margin-top:18px;font-size:11px;">
<tr>
<td style="text-align:right;padding-right:12px;">Итого оборудование</td>
<td style="text-align:right;width:120px;">{{итого_оборудование}}</td>
</tr>
<tr>
<td style="text-align:right;padding-right:12px;"><strong>Итого вся смета:</strong></td>
<td style="text-align:right;width:120px;"><strong>{{итого_вся_смета}}</strong></td>
</tr>
</table>
HTML;

        $footerLeft = "Дата: {{document_date}}\n";
        $signature = RequestLayoutSignatureLine::mark();

        RequestLayout::query()->updateOrCreate(
            ['title' => self::TITLE],
            [
                'schema' => [
                    'document_title' => '',
                    'heading_template' => $heading,
                    'body_template' => $body,
                    'header_template' => '',
                    'footer_left_template' => $footerLeft,
                    'signature_template' => $signature,
                    'presentation_heading_size_pt' => 12,
                    'presentation_subtitle_size_pt' => 11,
                    'needs_statement_header' => false,
                    'pdf_header_align' => 'center',
                    'pdf_body_align' => 'left',
                    'pdf_footer_left_align' => 'left',
                    'pdf_footer_right_align' => 'right',
                    'pdf_footer_preset' => 'one_signer_author',
                    'signature_slots_count' => 0,
                    'signature_roles' => [],
                    'footer_stamp' => false,
                    'category' => ReportLayoutCommercialProposal::CATEGORY,
                    'allow_application_equipment_insert' => false,
                    'executor_mode' => 'user',
                    'executor_user_id' => null,
                    'flags' => [
                        'needs_coordinator' => false,
                        'requires_print' => false,
                    ],
                    'fields' => [
                        [
                            'key' => 'подразделение',
                            'label' => 'Склад',
                            'type' => 'subdivision_warehouse',
                        ],
                        [
                            'key' => 'адрес',
                            'label' => 'Адрес объекта',
                            'type' => 'text',
                        ],
                        [
                            'key' => 'таблица_оборудование',
                            'label' => 'Оборудование',
                            'type' => 'table',
                            'table_mode' => ReportLayoutCommercialProposal::TABLE_MODE,
                            'table_columns' => ReportLayoutCommercialProposal::TABLE_COLUMNS,
                        ],
                        [
                            'key' => 'итого_оборудование',
                            'label' => 'Итого оборудование',
                            'type' => 'text',
                            'readonly' => true,
                        ],
                        [
                            'key' => 'итого_вся_смета',
                            'label' => 'Итого вся смета',
                            'type' => 'text',
                            'readonly' => true,
                        ],
                    ],
                ],
                'has_header' => true,
                'type' => 'pdf',
                'version' => 1,
                'approver_id' => null,
                'division_assigner_id' => null,
                'document_header_layout_id' => $headerLayout?->id,
            ]
        );
    }
}
