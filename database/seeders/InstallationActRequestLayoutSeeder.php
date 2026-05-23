<?php

namespace Database\Seeders;

use App\Models\DocumentHeaderLayout;
use App\Models\RequestLayout;
use Illuminate\Database\Seeder;

class InstallationActRequestLayoutSeeder extends Seeder
{
    public const TITLE = 'Акт установки запасных ';

    public function run(): void
    {
        $this->call(InstallationActDocumentHeaderLayoutSeeder::class);

        $headerLayout = DocumentHeaderLayout::query()
            ->where('title', InstallationActDocumentHeaderLayoutSeeder::TITLE)
            ->first();

        $footerLeft = <<<'TXT'
Председатель комиссии _________________ (должность) _________________ (подпись) _________________ {{signer_1_fio}}

Члены комиссии:
Мастер участка _________________ (подпись) _________________ {{signer_2_fio}}
Начальник котельной _________________ (подпись) _________________ {{signer_3_fio}}
TXT;

        $body = <<<'TXT'
Комиссией, назначенной приказом № {{номер_приказа}} от «{{приказ_день}}» {{приказ_месяц}} {{приказ_год}} г. составлен настоящий акт о том, что следующие перечисленные запасные (составные) части установлены на объект:

{{таблица_запчастей}}

Установка запасных (составных) частей произведена в рамках проведения: {{вид_работ}} (текущего ремонта, капитального ремонта, дооборудования, модернизации — нужное подчеркнуть).

В результате установки были получены:
1. Материалы, непригодные для дальнейшего использования (отходы);
{{отходы_непригодные}}

2. пригодные для дальнейшей эксплуатации запчасти (узлы, детали) (подлежат оприходованию на баланс):
{{запчасти_пригодные}}
(указать наименование и количество)

3. драгметаллы и/или лом в количестве (кг):
{{драгметаллы_лом}}
(указать количество полученного лома)

4. отходы, подлежащие обязательной утилизации специализированной организацией (подлежат учету на забалансовом счете 02.4)
{{отходы_утилизация}}
(указать наименование и количество отходов в единицах измерения)
TXT;

        $heading = <<<'TXT'
от «{{дата_день}}» {{дата_месяц}} {{дата_год}} г.

Наименование объекта {{наименование_объекта}}
Инвентарный номер {{инвентарный_номер}}
TXT;

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
                    'signature_slots_count' => 3,
                    'signature_roles' => [
                        1 => 1,
                        2 => 4,
                        3 => 7,
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
                            'key' => 'дата_день',
                            'label' => 'День даты акта',
                            'type' => 'text',
                        ],
                        [
                            'key' => 'дата_месяц',
                            'label' => 'Месяц даты акта',
                            'type' => 'text',
                        ],
                        [
                            'key' => 'дата_год',
                            'label' => 'Год даты акта',
                            'type' => 'text',
                        ],
                        [
                            'key' => 'наименование_объекта',
                            'label' => 'Наименование объекта',
                            'type' => 'text',
                        ],
                        [
                            'key' => 'инвентарный_номер',
                            'label' => 'Инвентарный номер',
                            'type' => 'text',
                        ],
                        [
                            'key' => 'номер_приказа',
                            'label' => 'Номер приказа о комиссии',
                            'type' => 'text',
                        ],
                        [
                            'key' => 'приказ_день',
                            'label' => 'День приказа о комиссии',
                            'type' => 'text',
                        ],
                        [
                            'key' => 'приказ_месяц',
                            'label' => 'Месяц приказа о комиссии',
                            'type' => 'text',
                        ],
                        [
                            'key' => 'приказ_год',
                            'label' => 'Год приказа о комиссии',
                            'type' => 'text',
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
                            'key' => 'вид_работ',
                            'label' => 'Вид работ (текущий/капитальный ремонт, дооборудование, модернизация)',
                            'type' => 'text',
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
                        [
                            'key' => 'драгметаллы_лом',
                            'label' => 'Драгметаллы и/или лом (кг)',
                            'type' => 'text',
                        ],
                        [
                            'key' => 'отходы_утилизация',
                            'label' => 'Отходы, подлежащие обязательной утилизации',
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
