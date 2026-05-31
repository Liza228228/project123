<?php

// начальные данные для базы
namespace Database\Seeders;

use App\Models\DocumentHeaderLayout;
use App\Models\RequestLayout;
use App\Support\ReportLayoutCommercialProposal;
use Illuminate\Database\Seeder;

class InstallationActRequestLayoutSeeder extends Seeder
{
    public const TITLE = 'Акт установки';

    public function run(): void
    {
        ReportLayoutCommercialProposal::purgeStoredLayouts();

        $this->call(InstallationActDocumentHeaderLayoutSeeder::class);

        $headerLayout = DocumentHeaderLayout::query()
            ->where('title', InstallationActDocumentHeaderLayoutSeeder::TITLE)
            ->first();

        $footerLeft = <<<'TXT'
Члены комиссии:
Мастер участка _________________ (подпись) _________________ {{signer_1_fio}}
Начальник котельной _________________ (подпись) _________________ {{signer_2_fio}}
TXT;

        $body = <<<'TXT'
Комиссией, назначенной приказом № {{номер_приказа}} от «{{дата_приказа}}» г. составлен настоящий акт о том, что следующие перечисленные запасные (составные) части установлены на объект:

{{таблица_запчастей}}

В результате установки были получены:
1. Материалы, непригодные для дальнейшего использования (отходы);
{{отходы_непригодные}}

2. пригодные для дальнейшей эксплуатации запчасти (узлы, детали) (подлежат оприходованию на баланс):
{{запчасти_пригодные}}
TXT;

        $heading = <<<'TXT'
от «{{дата_акта}}» г.

Наименование объекта {{наименование_объекта}}
TXT;

        foreach (['Акт установки запасных ', 'Акт установки запасных'] as $legacyTitle) {
            RequestLayout::query()
                ->where('title', $legacyTitle)
                ->each(fn (RequestLayout $layout) => $layout->delete());
        }

        RequestLayout::query()->updateOrCreate(
            ['title' => self::TITLE],
            [
                'schema' => [
                    'document_title' => '',
                    'heading_template' => $heading,
                    'body_template' => $body,
                    'header_template' => '',
                    'footer_left_template' => $footerLeft,
                    'signature_template' => '',
                    'presentation_heading_size_pt' => 14,
                    'presentation_subtitle_size_pt' => 12,
                    'needs_statement_header' => true,
                    'pdf_header_align' => 'right',
                    'pdf_body_align' => 'left',
                    'pdf_footer_left_align' => 'left',
                    'pdf_footer_right_align' => 'right',
                    'pdf_footer_preset' => null,
                    'signature_slots_count' => 2,
                    'signature_roles' => [
                        1 => 4,
                        2 => 7,
                    ],
                    'footer_stamp' => false,
                    'category' => 'installation-act',
                    'executor_mode' => 'user',
                    'executor_user_id' => null,
                    'flags' => [
                        'needs_coordinator' => false,
                        'requires_print' => false,
                    ],
                    'fields' => [
                        [
                            'key' => 'дата_акта',
                            'label' => 'Дата акта',
                            'type' => 'date',
                        ],
                        [
                            'key' => 'наименование_объекта',
                            'label' => 'Наименование объекта',
                            'type' => 'text',
                            'simple_input' => true,
                        ],
                        [
                            'key' => 'номер_приказа',
                            'label' => 'Номер приказа',
                            'type' => 'text',
                            'simple_input' => true,
                        ],
                        [
                            'key' => 'дата_приказа',
                            'label' => 'Дата приказа',
                            'type' => 'date',
                        ],
                        [
                            'key' => 'таблица_запчастей',
                            'label' => 'Установленные запасные (составные) части',
                            'type' => 'table',
                            'table_columns' => [
                                '№ п/п',
                                'Наименование запасных (составных) частей',
                                'Кол-во (шт.)',
                                'Примечание',
                            ],
                        ],
                        [
                            'key' => 'отходы_непригодные',
                            'label' => 'Материалы, непригодные для дальнейшего использования (отходы)',
                            'type' => 'textarea',
                        ],
                        [
                            'key' => 'запчасти_пригодные',
                            'label' => 'Запчасти, пригодные для дальнейшей эксплуатации',
                            'type' => 'textarea',
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
