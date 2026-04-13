<?php

namespace App\Support;

final class ReportFontChoices
{
    /**
     * @return array<string, string> value => label
     */
    public static function options(): array
    {
        return [
            'Times New Roman, Times, serif' => 'Times New Roman',
            'Arial, Helvetica, sans-serif' => 'Arial',
            'Calibri, Candara, Segoe UI, sans-serif' => 'Calibri',
            'Georgia, serif' => 'Georgia',
            'Garamond, Palatino Linotype, serif' => 'Garamond',
            'Courier New, Courier, monospace' => 'Courier New',
        ];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_keys(self::options());
    }
}
