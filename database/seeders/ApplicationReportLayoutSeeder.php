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
            'font_size' => 14,
        ]);

        ApplicationReportFooter::query()->create([
            'name' => 'Пример: подписи комиссии',
            'font_size' => 14,
        ]);
    }
}
