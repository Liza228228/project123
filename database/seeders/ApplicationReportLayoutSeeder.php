<?php

namespace Database\Seeders;

use App\Models\ApplicationReportFooter;
use App\Models\ApplicationReportHeader;
use Illuminate\Database\Seeder;

class ApplicationReportLayoutSeeder extends Seeder
{
    public function run(): void
    {
        if (ApplicationReportHeader::query()->exists()) {
            return;
        }

        ApplicationReportHeader::query()->create([
            'name' => 'Пример: акт (как образец)',
            'settings' => array_replace_recursive(ApplicationReportHeader::defaultSettings(), [
                'org_name' => 'ООО «Вера»',
                'approval_position' => 'Генеральный директор',
                'approval_name' => 'Потапов А.А.',
                'title' => 'Акт установки материальных ценностей',
                'date_text' => '«23» декабря 2019 г.',
                'city_text' => 'г. Воронеж',
            ]),
        ]);

        ApplicationReportFooter::query()->create([
            'name' => 'Пример: подписи комиссии',
            'settings' => ApplicationReportFooter::defaultSettings(),
        ]);
    }
}
