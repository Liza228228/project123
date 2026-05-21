@php
    $qty = (float) ($quantity ?? 0);
    $unitCode = trim((string) ($unitCode ?? '')) ?: 'шт';
    $measurementTypeCode = trim((string) ($measurementTypeCode ?? ''));
    $suffix = \App\Support\PieceQuantity::quantitySuffix($unitCode, $measurementTypeCode);
@endphp
<span class="tabular-nums {{ $class ?? '' }}">{{ \App\Support\PieceQuantity::formatForDisplay($qty, $unitCode, $measurementTypeCode) }} {{ $suffix }}</span>
