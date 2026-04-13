<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationReportHeader extends Model
{
    protected $fillable = [
        'name',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultSettings(): array
    {
        return [
            'font_family' => 'Times New Roman, Times, serif',
            'title_font_family' => '',
            'org_align' => 'left',
            'org_name' => '',
            'org_caption' => '(наименование организации, учреждения)',
            'approval_align' => 'right',
            'approval_label' => 'УТВЕРЖДАЮ:',
            'approval_position' => '',
            'approval_name' => '',
            'approval_position_caption' => '(должность)',
            'approval_name_caption' => '(подпись, расшифровка подписи)',
            'title' => '',
            'title_align' => 'center',
            'title_font_pt' => 14,
            'date_text' => '',
            'city_text' => '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mergedSettings(): array
    {
        return array_replace_recursive(static::defaultSettings(), $this->settings ?? []);
    }
}
