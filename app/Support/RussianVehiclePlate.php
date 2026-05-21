<?php

namespace App\Support;

final class RussianVehiclePlate
{
    /** Буквы, допустимые на российских госномерах (кириллица). */
    public const CYRILLIC_LETTERS = 'АВЕКМНОРСТУХ';

    private const LATIN_TO_CYRILLIC = [
        'A' => 'А',
        'B' => 'В',
        'E' => 'Е',
        'K' => 'К',
        'M' => 'М',
        'H' => 'Н',
        'O' => 'О',
        'P' => 'Р',
        'C' => 'С',
        'T' => 'Т',
        'Y' => 'У',
        'X' => 'Х',
    ];

    /**
     * Стандартный госномер: буква + 3 цифры + 2 буквы + код региона (2–3 цифры), напр. А123ВС77.
     */
    public static function isValid(string $plate): bool
    {
        $plate = self::normalize($plate);
        if ($plate === '') {
            return false;
        }

        $letters = self::CYRILLIC_LETTERS;

        return (bool) preg_match(
            '/^['.$letters.']\d{3}['.$letters.']{2}\d{2,3}$/u',
            $plate
        );
    }

    public static function normalize(string $raw): string
    {
        $s = mb_strtoupper(trim($raw));
        $s = (string) preg_replace('/[\s\-]+/u', '', $s);
        if ($s === '') {
            return '';
        }

        $out = '';
        $len = mb_strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $ch = mb_substr($s, $i, 1);
            if (isset(self::LATIN_TO_CYRILLIC[$ch])) {
                $ch = self::LATIN_TO_CYRILLIC[$ch];
            }
            $out .= $ch;
        }

        return mb_substr($out, 0, 9);
    }

    /**
     * @return list<string|object>
     */
    public static function validationRules(): array
    {
        $letters = self::CYRILLIC_LETTERS;

        return [
            'required',
            'string',
            'max:9',
            'regex:/^['.$letters.']\d{3}['.$letters.']{2}\d{2,3}$/u',
        ];
    }

    public static function validationMessage(): string
    {
        return 'Укажите госномер в формате А123ВС77 (буквы А, В, Е, К, М, Н, О, Р, С, Т, У, Х).';
    }

    /** Для поля ввода: «А 123 ВС 77». */
    public static function formatWithSpaces(string $raw): string
    {
        $plate = self::normalize($raw);
        if ($plate === '') {
            return '';
        }

        $parts = [
            mb_substr($plate, 0, 1),
            mb_substr($plate, 1, 3),
            mb_substr($plate, 4, 2),
            mb_substr($plate, 6),
        ];

        return implode(' ', array_filter($parts, fn (string $p): bool => $p !== ''));
    }
}
