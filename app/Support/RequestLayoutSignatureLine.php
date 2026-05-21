<?php

namespace App\Support;

final class RequestLayoutSignatureLine
{
    /** Линия под подпись в превью макета и в PDF. */
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
