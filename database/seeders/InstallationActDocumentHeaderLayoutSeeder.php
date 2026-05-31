<?php

// начальные данные для базы
namespace Database\Seeders;

use App\Models\DocumentHeaderLayout;
use Illuminate\Database\Seeder;

class InstallationActDocumentHeaderLayoutSeeder extends Seeder
{
    public const TITLE = 'Акт установки — шапка';

    public function run(): void
    {
        DocumentHeaderLayout::query()->updateOrCreate(
            ['title' => self::TITLE],
            [
                'schema' => [
                    'blocks' => [
                        [
                            'align' => 'right',
                            'bold' => false,
                            'font_family' => 'times_new_roman',
                            'font_size_pt' => 11,
                            'lines' => [
                                [
                                    'text' => 'Приложение к учетной политике',
                                    'from_application' => false,
                                    'source_key' => '',
                                    'fio_case' => 'nominative',
                                ],
                            ],
                        ],
                        [
                            'align' => 'center',
                            'bold' => true,
                            'font_family' => 'times_new_roman',
                            'font_size_pt' => 14,
                            'lines' => [
                                [
                                    'text' => 'АКТ установки',
                                    'from_application' => false,
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
