@php
    /** @var array<string, mixed> $s */
    $ff = $s['font_family'];
    $fontSizePt = (int) ($s['font_size'] ?? 14);
    $count = max(1, min(12, (int) ($s['members_count'] ?? 3)));
    $chairJust = match ($s['chairman_align'] ?? 'left') {
        'right' => 'flex-end',
        'center' => 'center',
        default => 'flex-start',
    };
    $memJust = match ($s['members_align'] ?? 'left') {
        'right' => 'flex-end',
        'center' => 'center',
        default => 'flex-start',
    };
@endphp
<div class="act-document-footer act-footer-block" style="font-family: {{ $ff }}; font-size: {{ $fontSizePt }}pt;">
    <div style="text-align: {{ $s['chairman_align'] }};">
        @if(trim((string) ($s['chairman_label'] ?? '')) !== '')
            <div style="font-weight:600; margin-bottom:0.35rem;">{{ $s['chairman_label'] }}</div>
        @endif
        <div class="act-sig-row" style="justify-content: {{ $chairJust }};">
            <span class="act-sig-line"></span>
            <span class="act-sig-line"></span>
        </div>
        <div class="act-sig-row" style="justify-content: {{ $chairJust }}; margin-top:0.15rem;">
            <span class="act-cap">{{ $s['chairman_sig_caption'] }}</span>
            <span class="act-cap">{{ $s['chairman_name_caption'] }}</span>
        </div>
    </div>

    <div class="act-member-block" style="text-align: {{ $s['members_align'] }};">
        @if(trim((string) ($s['members_label'] ?? '')) !== '')
            <div style="font-weight:600; margin-bottom:0.35rem;">{{ $s['members_label'] }}</div>
        @endif
        @for($i = 0; $i < $count; $i++)
            <div class="act-sig-row" style="justify-content: {{ $memJust }};">
                <span class="act-sig-line"></span>
                <span class="act-sig-line"></span>
            </div>
            <div class="act-sig-row" style="justify-content: {{ $memJust }}; margin-top:0.15rem; margin-bottom:0.65rem;">
                <span class="act-cap">{{ $s['member_sig_caption'] }}</span>
                <span class="act-cap">{{ $s['member_name_caption'] }}</span>
            </div>
        @endfor
    </div>
</div>
