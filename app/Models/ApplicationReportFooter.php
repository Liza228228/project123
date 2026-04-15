<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationReportFooter extends Model
{
    protected $fillable = [
        'name',
        'font_size',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaultSettings(): array
    {
        return [
            'font_family' => 'Times New Roman, Times, serif',
            'font_size' => 14,
            'chairman_align' => 'left',
            'chairman_label' => 'Председатель',
            'chairman_sig_caption' => '(подпись)',
            'chairman_name_caption' => '(расшифровка подписи)',
            'members_label' => 'Члены комиссии',
            'members_count' => 3,
            'members_align' => 'left',
            'member_sig_caption' => '(подпись)',
            'member_name_caption' => '(расшифровка подписи)',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mergedSettings(): array
    {
        $fontSize = max(8, min(36, (int) ($this->font_size ?? 14)));

        return array_replace_recursive(static::defaultSettings(), [
            'font_size' => $fontSize,
        ]);
    }
}
