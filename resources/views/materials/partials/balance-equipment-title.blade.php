@php
    $unitCode = trim((string) ($unitCode ?? '')) ?: 'шт';
    $measurementTypeCode = trim((string) ($measurementTypeCode ?? ''));
    $isClothing = $measurementTypeCode === \App\Support\PieceQuantity::CLOTHING_MEASUREMENT_TYPE;
    $sizeLabel = $isClothing && ! \App\Support\PieceQuantity::isClothingUnitCode($unitCode) ? $unitCode : null;
@endphp
{{ $equipmentName }}
@if($isClothing && $sizeLabel)
    <span class="text-black/55 dark:text-white/55">(размер {{ $sizeLabel }})</span>
@elseif(! $isClothing)
    <span class="text-black/55 dark:text-white/55">({{ $unitCode }})</span>
@endif
