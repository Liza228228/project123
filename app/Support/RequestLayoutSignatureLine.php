<?php

// вспомогательная логика
namespace App\Support;

final class RequestLayoutSignatureLine
{
    public const MARK = '_______________';

    public static function mark(): string
    {
        return self::MARK;
    }

    public static function withLabel(string $label): string
    {
        $label = trim($label);

        return self::MARK."\u{00A0}\u{00A0}\u{00A0}".$label;
    }
}
