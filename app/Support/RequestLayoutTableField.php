<?php

// вспомогательная логика
namespace App\Support;

final class RequestLayoutTableField
{
    public const MAX_COLUMNS = 12;

    public const MAX_ROWS = 30;
    public static function definitionFromField(array $field): array
    {
        $key = trim((string) ($field['key'] ?? ''));
        $label = trim((string) ($field['label'] ?? $key));
        $columns = [];
        foreach ($field['table_columns'] ?? [] as $col) {
            $col = trim((string) $col);
            if ($col !== '') {
                $columns[] = $col;
            }
        }
        if ($columns === []) {
            $columns = ['Столбец 1'];
        }

        return [
            'key' => $key,
            'label' => $label !== '' ? $label : $key,
            'columns' => $columns,
        ];
    }
    public static function rowCountFromRaw(mixed $raw): int
    {
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }

        if (! is_array($raw)) {
            return 1;
        }

        return max(1, min(self::MAX_ROWS, count($raw)));
    }
    public static function sanitizeColumns(array $columns): array
    {
        $out = [];
        foreach ($columns as $col) {
            $col = trim((string) $col);
            if ($col === '') {
                continue;
            }
            $out[] = mb_strlen($col) > 120 ? mb_substr($col, 0, 120) : $col;
            if (count($out) >= self::MAX_COLUMNS) {
                break;
            }
        }

        return $out !== [] ? $out : ['Столбец 1'];
    }
    public static function decodeValues(mixed $raw, int $rowCount, int $colCount): array
    {
        $rows = [];
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }
        if (! is_array($raw)) {
            $raw = [];
        }
        for ($r = 0; $r < $rowCount; $r++) {
            $rowIn = $raw[$r] ?? [];
            if (! is_array($rowIn)) {
                $rowIn = [];
            }
            $row = [];
            for ($c = 0; $c < $colCount; $c++) {
                $cell = $rowIn[$c] ?? $rowIn[(string) $c] ?? '';
                $row[] = is_scalar($cell) || $cell === null ? trim((string) $cell) : '';
            }
            $rows[] = $row;
        }

        return $rows;
    }
    public static function encodeValues(mixed $raw): string
    {
        if (is_string($raw) && $raw !== '') {
            return $raw;
        }
        if (! is_array($raw)) {
            return '[]';
        }

        $normalized = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $line = [];
            foreach ($row as $cell) {
                $line[] = is_scalar($cell) || $cell === null ? trim((string) $cell) : '';
            }
            $normalized[] = $line;
        }

        return json_encode($normalized, JSON_UNESCAPED_UNICODE) ?: '[]';
    }
    public static function toPdfHtml(array $field, array $rows): string
    {
        $def = self::definitionFromField($field);
        $columns = $def['columns'];
        $colCount = count($columns);
        if ($colCount === 0) {
            return '';
        }

        $html = '<table border="1" cellpadding="6" cellspacing="0" style="width:100%;border-collapse:collapse;margin:12px 0;font-size:11px;">';
        $html .= '<thead><tr>';
        foreach ($columns as $heading) {
            $html .= '<th style="background-color:#f5f5f5;font-weight:bold;text-align:center;">'
                .e($heading).'</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($rows as $row) {
            $html .= '<tr>';
            for ($c = 0; $c < $colCount; $c++) {
                $cell = $row[$c] ?? '';
                $html .= '<td style="vertical-align:top;">'.nl2br(e($cell), false).'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }
    public static function normalizeFieldValueFromRequest(array $field, mixed $raw): string
    {
        $def = self::definitionFromField($field);
        $colCount = count($def['columns']);
        $rowCount = self::rowCountFromRaw($raw);

        if (is_array($raw)) {
            return self::encodeValues(self::decodeValues($raw, $rowCount, $colCount));
        }

        return self::encodeValues(self::decodeValues($raw, $rowCount, $colCount));
    }
}
