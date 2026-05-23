<?php

namespace App\Support;

use App\Models\RequestLayout;
use Barryvdh\DomPDF\Facade\Pdf;

final class RequestLayoutPdfExporter
{
    public function __construct(
        private readonly RequestLayoutDocumentBuilder $builder
    ) {}

    public function outputBinary(RequestLayout $layout, array $values, ?int $submissionId = null): string
    {
        $pdf = Pdf::loadView('boiler-chief.request-layouts.pdf', $this->viewData($layout, $values))
            ->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    public function suggestedFileName(?int $submissionId = null): string
    {
        return 'zajavka-'.($submissionId ?? now()->format('YmdHis')).'.pdf';
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function viewData(RequestLayout $layout, array $values): array
    {
        $layout->load(['approver', 'divisionAssigner', 'documentHeaderLayout']);
        $parts = $this->builder->pdfParts($layout, $values);
        $structuredHeaderHtml = $parts['structuredHeaderHtml'] ?? null;
        $showHeader = trim($parts['headerText']) !== '' || ($structuredHeaderHtml ?? '') !== '';

        return [
            'layoutTitle' => $layout->title,
            'documentTitle' => $parts['documentTitle'],
            'showHeading' => trim($parts['headingText']) !== '',
            'showHeader' => $showHeader,
            'showFooter' => trim($parts['footerLeftText']) !== '' || trim($parts['signatureText']) !== '',
            'headingHtml' => $this->pdfPlainToHtml($parts['headingText']),
            'structuredHeaderHtml' => $structuredHeaderHtml,
            'headerHtml' => $structuredHeaderHtml !== null && $structuredHeaderHtml !== ''
                ? $structuredHeaderHtml
                : $this->pdfPlainToHtml($parts['headerText']),
            'bodyHtml' => $this->builder->bodyHtmlForPdf($parts['bodyText']),
            'footerLeftHtml' => $this->pdfPlainToHtml($parts['footerLeftText']),
            'signatureHtml' => $this->pdfPlainToHtml($parts['signatureText']),
            'pdfHeaderAlign' => $parts['pdfHeaderAlign'],
            'pdfBodyAlign' => $parts['pdfBodyAlign'],
            'pdfFooterLeftAlign' => $parts['pdfFooterLeftAlign'],
            'pdfFooterRightAlign' => $parts['pdfFooterRightAlign'],
            'headerUsesStructuredLayout' => $structuredHeaderHtml !== null && $structuredHeaderHtml !== '',
            'presentationTitleSizePt' => $parts['presentationHeadingSizePt'] ?? 15,
            'presentationSubtitleSizePt' => $parts['presentationSubtitleSizePt'] ?? 12,
        ];
    }

    private function pdfPlainToHtml(?string $plain): string
    {
        $plain = (string) ($plain ?? '');
        if ($plain === '') {
            return '';
        }

        $withBr = nl2br(e($plain), false);

        return str_replace(["\r\n", "\r", "\n"], '', $withBr);
    }
}
