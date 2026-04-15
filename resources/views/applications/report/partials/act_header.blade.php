@php
    /** @var array<string, mixed> $s */
    $ff = $s['font_family'];
    $titleFf = ($s['title_font_family'] ?? '') !== '' ? $s['title_font_family'] : $ff;
    $titlePt = (int) ($s['title_font_pt'] ?? 14);
    $fontSizePt = (int) ($s['font_size'] ?? $titlePt);
    $dateTextRaw = trim((string) ($s['date_text'] ?? ''));
    $dateTextDisplay = $dateTextRaw;
    if ($dateTextRaw !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTextRaw) === 1) {
        try {
            $dateTextDisplay = \Illuminate\Support\Carbon::parse($dateTextRaw)->locale('ru')->translatedFormat('«d» F Y г.');
        } catch (\Throwable $e) {
            $dateTextDisplay = $dateTextRaw;
        }
    }
@endphp
<div class="act-document-header" style="font-family: {{ $ff }}; font-size: {{ $fontSizePt }}pt;">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:1.25rem; margin-bottom:1rem;">
        <div style="flex:1; min-width:0; text-align: {{ $s['org_align'] }};">
            @if(trim((string) ($s['org_name'] ?? '')) !== '')
                <div style="font-weight:600;">{{ $s['org_name'] }}</div>
            @endif
            @if(trim((string) ($s['org_caption'] ?? '')) !== '')
                <div class="act-cap">{{ $s['org_caption'] }}</div>
            @endif
        </div>
        <div style="flex:1; min-width:0; text-align: {{ $s['approval_align'] }};">
            @if(trim((string) ($s['approval_label'] ?? '')) !== '')
                <div style="font-weight:600;">{{ $s['approval_label'] }}</div>
            @endif
            @if(trim((string) ($s['approval_position'] ?? '')) !== '')
                <div style="margin-top:0.35rem;">{{ $s['approval_position'] }}</div>
            @endif
            @if(trim((string) ($s['approval_position_caption'] ?? '')) !== '')
                <div class="act-cap">{{ $s['approval_position_caption'] }}</div>
            @endif
            @if(trim((string) ($s['approval_name'] ?? '')) !== '')
                <div style="margin-top:0.5rem;">{{ $s['approval_name'] }}</div>
            @endif
            @if(trim((string) ($s['approval_name_caption'] ?? '')) !== '')
                <div class="act-cap">{{ $s['approval_name_caption'] }}</div>
            @endif
        </div>
    </div>

    @if(trim((string) ($s['title'] ?? '')) !== '')
        <div style="text-align: {{ $s['title_align'] }}; font-family: {{ $titleFf }}; font-size: {{ $titlePt }}pt; font-weight:700; margin: 0.75rem 0 1rem;">
            {{ $s['title'] }}
        </div>
    @endif

    @if(trim((string) ($s['date_text'] ?? '')) !== '' || trim((string) ($s['city_text'] ?? '')) !== '')
        <div style="display:flex; justify-content:space-between; gap:1rem; margin-bottom:0.5rem;">
            <span>{{ $dateTextDisplay }}</span>
            <span>{{ $s['city_text'] }}</span>
        </div>
    @endif
</div>
