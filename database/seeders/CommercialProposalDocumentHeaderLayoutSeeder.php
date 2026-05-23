<?php

namespace Database\Seeders;

use App\Models\DocumentHeaderLayout;
use Illuminate\Database\Seeder;

class CommercialProposalDocumentHeaderLayoutSeeder extends Seeder
{
    public const TITLE = 'Коммерческое предложение — шапка';

    public function run(): void
    {
        DocumentHeaderLayout::query()->updateOrCreate(
            ['title' => self::TITLE],
            [
                'schema' => [
                    'blocks' => [
                        [
                            'align' => 'center',
                            'bold' => true,
                            'font_family' => 'times_new_roman',
                            'font_size_pt' => 14,
                            'lines' => [
                                [
                                    'text' => 'Коммерческое предложение',
                                    'from_application' => false,
                                    'source_key' => '',
                                    'fio_case' => 'nominative',
                                ],
                            ],
                        ],
                        [
                            'align' => 'right',
                            'bold' => false,
                            'font_family' => 'times_new_roman',
                            'font_size_pt' => 11,
                            'lines' => [
                                [
                                    'text' => 'Подразделение и склад: {{подразделение}}',
                                    'from_application' => true,
                                    'source_key' => '',
                                    'fio_case' => 'nominative',
                                ],
                                [
                                    'text' => 'Адрес: {{адрес}}',
                                    'from_application' => true,
                                    'source_key' => '',
                                    'fio_case' => 'nominative',
                                ],
                            ],
                        ],
                    ],
                ],
            ]
        );
    }
}
