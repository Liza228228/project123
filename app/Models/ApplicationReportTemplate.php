<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationReportTemplate extends Model
{
    protected $fillable = [
        'report_header_id',
        'report_footer_id',
        'main_body_text',
        'main_font_family',
        'table_font_family',
    ];

    public function reportHeader(): BelongsTo
    {
        return $this->belongsTo(ApplicationReportHeader::class, 'report_header_id');
    }

    public function reportFooter(): BelongsTo
    {
        return $this->belongsTo(ApplicationReportFooter::class, 'report_footer_id');
    }

    public static function current(): self
    {
        $row = static::query()->orderBy('id')->first();
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'report_header_id' => null,
            'report_footer_id' => null,
            'main_body_text' => null,
            'main_font_family' => 'Times New Roman, Times, serif',
            'table_font_family' => 'Times New Roman, Times, serif',
        ]);
    }
}
