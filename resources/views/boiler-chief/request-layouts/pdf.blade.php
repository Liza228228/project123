<!DOCTYPE html>
<html lang="ru">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta charset="utf-8"/>
    <title>{{ $layoutTitle }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #111;
            line-height: 1.35;
            margin: 0;
            padding: 28px 36px 40px;
        }
        /* nl2br даёт <br>; без pre-wrap переносы только от <br>, без дублирования */
        br {
            line-height: 1.2em;
        }
        .header-block {
            margin: 0 0 14px 0;
            white-space: normal;
            line-height: 1.35;
        }
        .doc-heading {
            text-align: center;
            font-size: 13px;
            font-weight: 700;
            margin: 0 0 12px 0;
            white-space: normal;
            line-height: 1.35;
        }
        .doc-title {
            text-align: center;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 0 0 20px 0;
        }
        .body-main {
            margin: 0 0 28px 0;
            white-space: normal;
            line-height: 1.4;
        }
        .footer-row {
            width: 100%;
            margin-top: 28px;
        }
        .footer-row td {
            vertical-align: bottom;
            padding: 0 8px 0 0;
            white-space: normal;
            line-height: 1.35;
        }
        .footer-row td:last-child {
            padding-right: 0;
            padding-left: 8px;
        }
        .footer-left {
            width: 50%;
        }
        .footer-right {
            width: 50%;
            font-size: 12px;
            line-height: 1.3;
            font-weight: 400;
            text-align: right;
            white-space: nowrap;
            word-break: keep-all;
            overflow-wrap: normal;
        }
        .signature-stack {
            display: inline-block;
            text-align: left;
            white-space: nowrap;
            word-break: keep-all;
            overflow-wrap: normal;
            line-height: 1.35;
            font-weight: 400;
        }
    </style>
</head>
<body>
    @if(! empty($showHeader))
        @if(! empty($headerUsesStructuredLayout))
            <div class="header-block">{!! $headerHtml !!}</div>
        @else
            <div class="header-block" style="text-align: {{ $pdfHeaderAlign ?? 'right' }};">{!! $headerHtml !!}</div>
        @endif
    @endif
    <h1 class="doc-title" style="font-size: {{ $presentationTitleSizePt ?? 15 }}pt;">{{ $documentTitle }}</h1>
    @if(! empty($showHeading))
        <div class="doc-heading" style="font-size: {{ $presentationSubtitleSizePt ?? 12 }}pt;">{!! $headingHtml ?? '' !!}</div>
    @endif
    <div class="body-main" style="text-align: {{ $pdfBodyAlign ?? 'center' }};">{!! $bodyHtml !!}</div>
    @if(! empty($showFooter))
        <table class="footer-row" cellspacing="0" cellpadding="0" width="100%">
            <tr>
                <td class="footer-left" style="text-align: {{ $pdfFooterLeftAlign ?? 'left' }};">{!! $footerLeftHtml ?? '' !!}</td>
                <td class="footer-right">
                    <div class="signature-stack">{!! $signatureHtml ?? '' !!}</div>
                </td>
            </tr>
        </table>
    @endif
</body>
</html>
