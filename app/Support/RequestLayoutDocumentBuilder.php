<?php

namespace App\Support;

use App\Models\DocumentHeaderLayout;
use App\Models\RequestLayout;
use App\Models\User;
use App\Services\DadataAddressService;
use DOMDocument;
use DOMElement;
use DOMNode;
use Throwable;

final class RequestLayoutDocumentBuilder
{
    /** @var array<string, array<string, mixed>> */
    private array $cleanNameCache = [];

    public function __construct(
        private readonly DadataAddressService $dadataAddressService
    ) {}

    /**
     * @param  array{fields?: list<array{key: string, label: string, type: string}>, body_template?: string, header_template?: string, footer_left_template?: string, signature_template?: string, document_title?: string, executor_mode?: string, executor_user_id?: int|null}  $schema
     * @param  array<string, mixed>  $values
     */
    public function mergeBodyTemplate(array $schema, array $values, ?RequestLayout $layout = null): string
    {
        $template = (string) ($schema['body_template'] ?? '');

        return $this->mergeTemplateString($template, $schema, $values, $layout);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function mergedBodyForHtml(array $schema, array $values, ?RequestLayout $layout = null): string
    {
        return e($this->mergeBodyTemplate($schema, $values, $layout));
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{
     *     documentTitle: string,
     *     headingText: string,
     *     headerText: string,
     *     structuredHeaderHtml: string|null,
     *     bodyText: string,
     *     footerLeftText: string,
     *     signatureText: string,
     *     pdfHeaderAlign: string,
     *     pdfBodyAlign: string,
     *     pdfFooterLeftAlign: string,
     *     pdfFooterRightAlign: string
     * }
     */
    public function pdfParts(RequestLayout $layout, array $values): array
    {
        $layout->loadMissing(['documentHeaderLayout', 'approver', 'divisionAssigner']);
        $schema = is_array($layout->schema) ? $layout->schema : [];
        $title = trim((string) ($schema['document_title'] ?? ''));

        $heading = (string) ($schema['heading_template'] ?? '');
        $body = (string) ($schema['body_template'] ?? '');
        $footerLeft = (string) ($schema['footer_left_template'] ?? '');
        $signature = (string) ($schema['signature_template'] ?? '');

        $structuredHeaderHtml = null;
        $headerLayoutModel = $layout->documentHeaderLayout;
        if ($headerLayoutModel instanceof DocumentHeaderLayout) {
            $headerPlain = $this->structuredDocumentHeaderPlain($headerLayoutModel, $layout, $values);
            $structuredHeaderHtml = $this->renderStructuredDocumentHeaderHtml($headerLayoutModel, $layout, $values);
        } else {
            $header = (string) ($schema['header_template'] ?? '');
            $headerPlain = $this->mergeTemplateString($header, $schema, $values, $layout);
        }

        $mergedFooterLeft = $this->removeSignerArtifactsFromText(
            $this->normalizeInlineSignerGroups(
                $this->cleanupSignerLabelPrefixes(
                    $this->mergeTemplateString($footerLeft, $schema, $values, $layout)
                )
            )
        );
        $mergedSignature = $this->normalizeSignerLines(
            $this->cleanupSignerLabelPrefixes(
                $this->mergeTemplateString($signature, $schema, $values, $layout)
            )
        );

        $signerColumn = $this->buildStrictSignerColumn($values);
        if ($signerColumn !== '') {
            $mergedSignature = $signerColumn;
        }

        return [
            'documentTitle' => $title,
            'headingText' => $this->mergeTemplateString($heading, $schema, $values, $layout),
            'headerText' => $headerPlain,
            'structuredHeaderHtml' => $structuredHeaderHtml,
            'bodyText' => $this->mergeTemplateString($body, $schema, $values, $layout),
            'footerLeftText' => $mergedFooterLeft,
            'signatureText' => $mergedSignature,
            'pdfHeaderAlign' => $this->pdfAlign($schema, 'pdf_header_align', 'right', ['left', 'center', 'right']),
            'pdfBodyAlign' => $this->pdfAlign($schema, 'pdf_body_align', 'center', ['left', 'center', 'right', 'justify']),
            'pdfFooterLeftAlign' => $this->pdfAlign($schema, 'pdf_footer_left_align', 'left', ['left', 'center']),
            'pdfFooterRightAlign' => $this->pdfAlign($schema, 'pdf_footer_right_align', 'right', ['left', 'center', 'right']),
            'presentationHeadingSizePt' => max(8, min(36, (int) ($schema['presentation_heading_size_pt'] ?? 15))),
            'presentationSubtitleSizePt' => max(8, min(28, (int) ($schema['presentation_subtitle_size_pt'] ?? 12))),
        ];
    }

    /**
     * Плоский текст шапки из конструктора блоков (для предпросмотра).
     *
     * @param  array<string, mixed>  $values
     */
    public function structuredDocumentHeaderPlain(DocumentHeaderLayout $headerLayout, RequestLayout $layout, array $values): string
    {
        $schema = is_array($layout->schema) ? $layout->schema : [];
        $blocks = $headerLayout->schema['blocks'] ?? [];
        if (! is_array($blocks)) {
            return '';
        }
        $blockChunks = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            $lines = $block['lines'] ?? [];
            if (! is_array($lines)) {
                continue;
            }
            $lineTexts = [];
            foreach ($lines as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $text = (string) ($line['text'] ?? '');
                $fromApp = ! empty($line['from_application']);
                if ($fromApp) {
                    $sourceKey = trim((string) ($line['source_key'] ?? ''));
                    $fioCase = trim((string) ($line['fio_case'] ?? 'nominative'));
                    $lineTexts[] = $sourceKey !== ''
                        ? $this->resolveHeaderApplicationLineText($sourceKey, $fioCase, $schema, $values, $layout)
                        : $this->mergeTemplateString($text, $schema, $values, $layout);
                } else {
                    $lineTexts[] = $text;
                }
            }
            if ($lineTexts !== []) {
                $blockChunks[] = implode("\n", $lineTexts);
            }
        }

        return implode("\n\n", $blockChunks);
    }

    /**
     * HTML шапки из блоков (шрифт, выравнивание, жирность по блоку).
     *
     * @param  array<string, mixed>  $values
     */
    public function renderStructuredDocumentHeaderHtml(DocumentHeaderLayout $headerLayout, RequestLayout $layout, array $values): string
    {
        $schema = is_array($layout->schema) ? $layout->schema : [];
        $blocks = $headerLayout->schema['blocks'] ?? [];
        if (! is_array($blocks) || $blocks === []) {
            return '';
        }
        $out = '';
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            $align = $this->normalizeBlockAlign($block['align'] ?? 'center');
            $bold = ! empty($block['bold']);
            $size = (int) ($block['font_size_pt'] ?? 12);
            if ($size < 8) {
                $size = 8;
            }
            if ($size > 24) {
                $size = 24;
            }
            $fontStack = $this->pdfFontStack((string) ($block['font_family'] ?? 'times_new_roman'));
            $weight = $bold ? '700' : '400';
            $lines = $block['lines'] ?? [];
            if (! is_array($lines)) {
                continue;
            }
            $inner = '';
            foreach ($lines as $line) {
                if (! is_array($line)) {
                    continue;
                }
                $text = (string) ($line['text'] ?? '');
                $fromApp = ! empty($line['from_application']);
                if ($fromApp) {
                    $sourceKey = trim((string) ($line['source_key'] ?? ''));
                    $fioCase = trim((string) ($line['fio_case'] ?? 'nominative'));
                    $merged = $sourceKey !== ''
                        ? $this->resolveHeaderApplicationLineText($sourceKey, $fioCase, $schema, $values, $layout)
                        : $this->mergeTemplateString($text, $schema, $values, $layout);
                } else {
                    $merged = $text;
                }
                $inner .= '<div style="margin:0 0 2px 0;">'.$this->plainToPdfInnerHtml($merged).'</div>';
            }
            if ($inner === '') {
                continue;
            }
            $out .= '<div style="text-align:'.$align.';font-weight:'.$weight.';font-size:'.$size.'pt;font-family:'.$fontStack.';margin:0 0 10px 0;">'.$inner.'</div>';
        }

        return $out;
    }

    private function normalizeBlockAlign(mixed $align): string
    {
        $a = strtolower(trim((string) $align));

        return in_array($a, ['left', 'center', 'right'], true) ? $a : 'center';
    }

    private function pdfFontStack(string $key): string
    {
        $k = strtolower(trim($key));

        return match ($k) {
            'arial', 'dejavu_sans' => 'DejaVu Sans, sans-serif',
            'times_new_roman', 'georgia', 'dejavu_serif' => 'DejaVu Serif, serif',
            default => 'DejaVu Serif, serif',
        };
    }

    /**
     * Переносы → &lt;br&gt;, как в PDF-шаблоне (без pre-wrap).
     */
    private function plainToPdfInnerHtml(string $plain): string
    {
        $plain = (string) $plain;
        if ($plain === '') {
            return '';
        }
        $withBr = nl2br(e($plain), false);

        return str_replace(["\r\n", "\r", "\n"], '', $withBr);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @param  list<string>  $allowed
     */
    private function pdfAlign(array $schema, string $key, string $default, array $allowed): string
    {
        $v = strtolower(trim((string) ($schema[$key] ?? '')));

        return in_array($v, $allowed, true) ? $v : $default;
    }

    /**
     * @param  array{fields?: list<array{key?: string}>, body_template?: string}  $schema
     * @param  array<string, mixed>  $values
     */
    private function mergeTemplateString(string $template, array $schema, array $values, ?RequestLayout $layout = null): string
    {
        $map = $this->buildSubstitutionMap($schema, $values, $layout);

        return preg_replace_callback('/\{\{\s*([^}]+?)\s*\}\}/u', function (array $m) use ($map): string {
            $resolved = $this->resolvePlaceholder(trim($m[1]), $map);

            return $resolved !== null ? $resolved : $m[0];
        }, $template) ?? $template;
    }

    /**
     * Подставить значение по ключу поля: точное совпадение, затем без учёта регистра (для кириллицы/латиницы).
     *
     * @param  array<string, string>  $map
     */
    private function resolvePlaceholder(string $rawKey, array $map): ?string
    {
        $key = trim($rawKey);
        if ($key === '') {
            return null;
        }

        if (array_key_exists($key, $map)) {
            return (string) $map[$key];
        }

        $lower = mb_strtolower($key, 'UTF-8');
        foreach ($map as $mapKey => $value) {
            if (! is_string($mapKey) && ! is_int($mapKey)) {
                continue;
            }
            if (mb_strtolower((string) $mapKey, 'UTF-8') === $lower) {
                return is_scalar($value) || $value === null ? (string) $value : '';
            }
        }

        $norm = $this->normalizePlaceholderKey($key);
        if ($norm !== '') {
            foreach ($map as $mapKey => $value) {
                if (! is_string($mapKey) && ! is_int($mapKey)) {
                    continue;
                }
                if ($this->normalizePlaceholderKey((string) $mapKey) === $norm) {
                    return is_scalar($value) || $value === null ? (string) $value : '';
                }
            }
        }

        // Старые макеты с плейсхолдером {{фио}} без поля в схеме/форме — не выводим сырой токен в PDF.
        if (mb_strtolower($key, 'UTF-8') === 'фио') {
            return '';
        }

        return null;
    }

    /**
     * Совпадение ключей при опечатках в шаблоне: пробелы вокруг «_» (например «введите_ оборудование» vs «введите_оборудование»).
     */
    private function normalizePlaceholderKey(string $key): string
    {
        $normalized = str_replace('&nbsp;', '', $key);
        $normalized = preg_replace('/[\x{200B}\x{200C}\x{200D}\x{FEFF}]/u', '', $normalized) ?? $normalized;

        return preg_replace('/[\h\p{Z}\x{00A0}\x{202F}]+/u', '', $normalized) ?? $normalized;
    }

    /**
     * Системные плейсхолдеры (основные имена + устаревшие синонимы для старых макетов).
     *
     * Основные: coordinator_name, representative_prefix, representative_name,
     * signatory_print_name, subdivision_name, document_date, report_date,
     * document_number, report_number.
     * Синонимы: approver_fio, executor_line1, executor_line2, signatory_fio, department_name.
     *
     * @param  array{fields?: list<array{key?: string}>, executor_mode?: string, executor_user_id?: int|null}  $schema
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function buildSubstitutionMap(array $schema, array $values, ?RequestLayout $layout): array
    {
        $map = [];
        foreach ($schema['fields'] ?? [] as $field) {
            $k = isset($field['key']) ? trim((string) $field['key']) : '';
            if ($k === '') {
                continue;
            }
            if (($field['type'] ?? '') === 'table') {
                $def = RequestLayoutTableField::definitionFromField($field);
                $raw = $values[$k] ?? '';
                $rowCount = RequestLayoutTableField::rowCountFromRaw($raw);
                $rows = RequestLayoutTableField::decodeValues(
                    $raw,
                    $rowCount,
                    count($def['columns'])
                );
                $map[$k] = RequestLayoutTableField::toPdfHtml($field, $rows);

                continue;
            }
            $raw = $values[$k] ?? '';
            $map[$k] = is_scalar($raw) || $raw === null ? (string) $raw : '';
        }

        // Раньше ключи, начинающиеся с цифры, сохранялись как «поле N» — даём подстановку и для {{N}}.
        foreach (array_keys($map) as $mk) {
            if (! is_string($mk)) {
                continue;
            }
            if (preg_match('/^поле\h+(\p{N}+)$/u', $mk, $m)) {
                $short = (string) ($m[1] ?? '');
                if ($short !== '' && ! array_key_exists($short, $map)) {
                    $map[$short] = $map[$mk];
                }
            }
        }

        $recipientName = trim((string) ($values['recipient_name'] ?? ''));
        if ($recipientName !== '') {
            $map['recipient_name'] = $recipientName;
            $map['получатель'] = $recipientName;
        }

        for ($i = 1; $i <= 3; $i++) {
            $uid = (int) ($values['signer_'.$i.'_user_id'] ?? 0);
            $rawSignerName = '';
            if ($uid > 0) {
                $signer = User::query()->find($uid);
                $rawSignerName = $signer instanceof User ? $signer->fullName() : '';
            } else {
                $rawSignerName = trim((string) ($values['signer_'.$i.'_fio'] ?? ''));
            }
            $map['signer_'.$i.'_name'] = $rawSignerName;
            $shortSignerName = $this->formatSignerShortName($rawSignerName);
            $noBreakSignerName = preg_replace('/\s+/u', "\u{00A0}", $shortSignerName) ?? $shortSignerName;
            $signatureLine = RequestLayoutSignatureLine::mark();
            $signatureDelimiter = "\u{00A0}\u{00A0}\u{00A0}";
            $map['signer_'.$i.'_signature'] = $rawSignerName !== '' ? $signatureLine.$signatureDelimiter.$noBreakSignerName : '';
            $map['signer_'.$i.'_fio'] = $map['signer_'.$i.'_signature'];
        }

        $docDate = (string) ($values['_document_date'] ?? $values['document_date'] ?? '');
        if ($docDate === '' && $layout !== null) {
            $docDate = now()->format('d.m.Y');
        }
        $map['document_date'] = $docDate;
        $map['report_date'] = $docDate;
        $map['contract_date'] = $docDate;

        $docNumber = trim((string) ($values['_document_number'] ?? $values['document_number'] ?? ''));
        $map['document_number'] = $docNumber;
        $map['report_number'] = $docNumber;
        $map['contract_number'] = $docNumber;

        if ($layout === null) {
            return $this->withReservedPlaceholderAliases($map, [
                'coordinator_name' => '',
                'representative_prefix' => '',
                'representative_name' => '',
                'signatory_print_name' => '',
                'subdivision_name' => '',
            ]);
        }

        $layout->loadMissing(['approver', 'divisionAssigner']);

        $approver = $layout->approver;
        $coordinatorName = $approver instanceof User ? $approver->fullName() : '';

        $mode = (string) ($schema['executor_mode'] ?? 'department');
        $executorUserId = (int) ($schema['executor_user_id'] ?? 0);
        $executorUser = $executorUserId > 0 ? User::query()->find($executorUserId) : null;
        $deptName = trim((string) ($layout->divisionAssigner?->name ?? ''));

        if ($mode === 'user' && $executorUser instanceof User) {
            $prefix = 'от сотрудника';
            $repName = $executorUser->fullName();
            $signatory = $repName;
        } else {
            $prefix = 'от подразделения';
            $repName = $deptName;
            $signatory = $deptName !== '' ? $deptName : $coordinatorName;
        }

        return $this->withReservedPlaceholderAliases($map, [
            'coordinator_name' => $coordinatorName,
            'representative_prefix' => $prefix,
            'representative_name' => $repName,
            'signatory_print_name' => $signatory,
            'subdivision_name' => $deptName,
        ]);
    }

    /**
     * @param  array<string, string>  $reserved  canonical keys only
     * @return array<string, string>
     */
    private function withReservedPlaceholderAliases(array $map, array $reserved): array
    {
        $map['coordinator_name'] = $reserved['coordinator_name'] ?? '';
        $map['representative_prefix'] = $reserved['representative_prefix'] ?? '';
        $map['representative_name'] = $reserved['representative_name'] ?? '';
        $map['signatory_print_name'] = $reserved['signatory_print_name'] ?? '';
        $map['subdivision_name'] = $reserved['subdivision_name'] ?? '';

        $map['approver_fio'] = $map['coordinator_name'];
        $map['executor_line1'] = $map['representative_prefix'];
        $map['executor_line2'] = $map['representative_name'];
        $map['signatory_fio'] = $map['signatory_print_name'];
        $map['department_name'] = $map['subdivision_name'];

        return $map;
    }

    /**
     * Удаляет префиксы "ФИО N:" перед строкой подписи, если в шаблоне они были добавлены вручную.
     * Пример: "ФИО 1: ____________ / Иванов И.И." -> "____________ / Иванов И.И."
     */
    private function cleanupSignerLabelPrefixes(string $text): string
    {
        return preg_replace(
            '/(?:^|\R)\h*ФИО\h*\d+\h*:\h*(?=_{3,})/u',
            '',
            $text
        ) ?? $text;
    }

    /**
     * Принудительно переносит каждую подпись на отдельную строку,
     * даже если в шаблоне плейсхолдеры подписей были поставлены подряд.
     */
    private function normalizeSignerLines(string $text): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $normalized = preg_replace_callback(
            '/_{2,}\h*\/\h*([^\r\n]+)/u',
            function (array $m): string {
                $name = trim((string) ($m[1] ?? ''));
                if ($name === '') {
                    return RequestLayoutSignatureLine::mark();
                }

                $short = $this->formatSignerShortName($name);
                $shortNoBreak = preg_replace('/\s+/u', "\u{00A0}", $short) ?? $short;

                return RequestLayoutSignatureLine::withLabel($shortNoBreak);
            },
            $normalized
        ) ?? $normalized;
        $normalized = preg_replace('/([^\n])\h*(?=_{3,})/u', '$1'."\n", $normalized) ?? $normalized;
        $normalized = preg_replace(
            '/([А-ЯЁA-Z][А-ЯЁA-Zа-яёa-z\-]+\h+[А-ЯЁA-Z]\.[А-ЯЁA-Z]\.)(?=\h+[А-ЯЁA-Z][А-ЯЁA-Zа-яёa-z\-]+\h+[А-ЯЁA-Z]\.[А-ЯЁA-Z]\.)/u',
            '$1'."\n",
            $normalized
        ) ?? $normalized;
        $normalized = preg_replace('/^\h*[-–—‑−‒﹘﹣]{1,100}\h*$/um', '', $normalized) ?? $normalized;
        $normalized = preg_replace("/\n{3,}/", "\n\n", $normalized) ?? $normalized;

        $normalized = $this->rebuildSignerBlockFromDetectedNames($normalized);

        return str_replace("\n", PHP_EOL, trim($normalized));
    }

    /**
     * Собирает подписи в стабильную колонку из signer_1..3.
     */
    private function buildSignerColumnFromValues(array $values, string $fallbackText): string
    {
        $lines = [];
        for ($i = 1; $i <= 3; $i++) {
            $raw = trim((string) ($values['signer_'.$i.'_fio'] ?? ''));
            if ($raw === '') {
                continue;
            }
            $short = $this->formatSignerShortName($raw);
            if ($short === '') {
                continue;
            }
            $noBreak = preg_replace('/\s+/u', "\u{00A0}", $short) ?? $short;
            $lines[] = RequestLayoutSignatureLine::withLabel($noBreak);
        }

        if ($lines === []) {
            return '';
        }

        $column = implode(PHP_EOL, $lines);
        if (preg_match('/(?:^|\R)\h*М\.\h*П\.?\h*$/u', $fallbackText)) {
            $column .= PHP_EOL.'М.П.';
        }

        return $column;
    }

    /**
     * Жестко формирует колонку подписей как на эталоне:
     * "__________ Фамилия И.О." по одной на строку.
     *
     * @param  array<string, mixed>  $values
     */
    private function buildStrictSignerColumn(array $values): string
    {
        $lines = [];
        $seen = [];

        for ($i = 1; $i <= 3; $i++) {
            $raw = '';
            $uid = (int) ($values['signer_'.$i.'_user_id'] ?? 0);
            if ($uid > 0) {
                $user = User::query()->find($uid);
                if ($user instanceof User) {
                    $raw = trim($user->fullName());
                }
            }

            if ($raw === '') {
                $raw = trim((string) ($values['signer_'.$i.'_fio'] ?? ''));
            }
            if ($raw === '') {
                continue;
            }

            $short = $this->formatSignerShortName($raw);
            if ($short === '') {
                continue;
            }

            if (isset($seen[$short])) {
                continue;
            }
            $seen[$short] = true;

            $shortNoBreak = preg_replace('/\s+/u', "\u{00A0}", $short) ?? $short;
            $lines[] = RequestLayoutSignatureLine::withLabel($shortNoBreak);
        }

        $recipientRaw = trim((string) ($values['recipient_name'] ?? $values['получатель'] ?? ''));
        if ($recipientRaw !== '') {
            $recipientShort = $this->formatSignerShortName($recipientRaw);
            if ($recipientShort !== '' && ! isset($seen[$recipientShort])) {
                $recipientNoBreak = preg_replace('/\s+/u', "\u{00A0}", $recipientShort) ?? $recipientShort;
                $lines[] = RequestLayoutSignatureLine::withLabel($recipientNoBreak);
            }
        }

        return $lines === [] ? '' : implode(PHP_EOL, $lines);
    }

    /**
     * Убирает подписи из произвольного блока, чтобы не дублировались рядом с колонкой.
     */
    private function removeSignerArtifactsFromText(string $text): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $normalized = preg_replace('/^.*_{3,}.*$/um', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/^\h*[А-ЯЁA-Z][\p{L}\-]+\h+[А-ЯЁA-Z]\.[А-ЯЁA-Z]\.\h*$/um', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/^\h*ФИО\h*\d+\h*:?.*$/um', '', $normalized) ?? $normalized;
        $normalized = preg_replace("/\n{3,}/", "\n\n", $normalized) ?? $normalized;

        return trim(str_replace("\n", PHP_EOL, $normalized));
    }

    /**
     * Разделяет подряд идущие подписи в одной строке на отдельные строки,
     * не затрагивая прочий текст (например, дату).
     */
    private function normalizeInlineSignerGroups(string $text): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $text);
        $normalized = preg_replace(
            '/(_{3,}\h+[А-ЯЁA-Z][\p{L}\-]+\h+[А-ЯЁA-Z]\.[А-ЯЁA-Z]\.)\h+(?=_{3,}\h+[А-ЯЁA-Z][\p{L}\-]+\h+[А-ЯЁA-Z]\.[А-ЯЁA-Z]\.)/u',
            '$1'."\n",
            $normalized
        ) ?? $normalized;

        return str_replace("\n", PHP_EOL, $normalized);
    }

    private function rebuildSignerBlockFromDetectedNames(string $text): string
    {
        $stamp = '';
        if (preg_match('/(?:^|\n)\h*(М\.\h*П\.?)\h*$/u', $text, $m)) {
            $stamp = preg_replace('/\s+/u', '', (string) ($m[1] ?? '')) ?? '';
        }

        $detectedNames = [];

        if (preg_match_all('/[А-ЯЁA-Z][\p{L}\-]+\h+[А-ЯЁA-Z]\.[А-ЯЁA-Z]\./u', $text, $m1)) {
            foreach (($m1[0] ?? []) as $raw) {
                $name = trim((string) $raw);
                if ($name !== '') {
                    $detectedNames[] = $name;
                }
            }
        }

        if (preg_match_all('/[А-ЯЁA-Z][\p{L}\-]+\h+[А-ЯЁA-Z][\p{L}\-]+\h+[А-ЯЁA-Z][\p{L}\-]+/u', $text, $m2)) {
            foreach (($m2[0] ?? []) as $raw) {
                $short = $this->formatSignerShortName((string) $raw);
                if ($short !== '') {
                    $detectedNames[] = $short;
                }
            }
        }

        $uniqueNames = [];
        foreach ($detectedNames as $name) {
            if (! in_array($name, $uniqueNames, true)) {
                $uniqueNames[] = $name;
            }
        }

        if ($uniqueNames === []) {
            return $text;
        }

        $lines = [];
        foreach ($uniqueNames as $name) {
            $noBreakName = preg_replace('/\s+/u', "\u{00A0}", $name) ?? $name;
            $lines[] = RequestLayoutSignatureLine::withLabel($noBreakName);
        }

        $result = implode("\n", $lines);
        if ($stamp !== '') {
            $result .= "\n".$stamp;
        }

        return $result;
    }

    private function formatSignerShortName(string $fullName): string
    {
        $parts = preg_split('/\s+/u', trim($fullName), -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($parts) || $parts === []) {
            return '';
        }

        $surname = (string) array_shift($parts);
        if ($parts === []) {
            return $surname;
        }

        $initials = [];
        foreach ($parts as $part) {
            $letter = mb_substr((string) $part, 0, 1, 'UTF-8');
            if ($letter !== '') {
                $initials[] = mb_strtoupper($letter, 'UTF-8').'.';
            }
        }

        if ($initials === []) {
            return $surname;
        }

        return $surname.' '.implode('', $initials);
    }

    /**
     * Подстановка значения ФИО из данных заявки в выбранном падеже для строки шапки.
     *
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $values
     */
    private function resolveHeaderApplicationLineText(
        string $sourceKey,
        string $fioCase,
        array $schema,
        array $values,
        ?RequestLayout $layout
    ): string {
        $map = $this->buildSubstitutionMap($schema, $values, $layout);
        $raw = trim((string) ($map[$sourceKey] ?? ''));
        if ($raw === '') {
            return '';
        }

        return $this->inflectFioByCase($raw, $fioCase);
    }

    private function inflectFioByCase(string $fullName, string $fioCase): string
    {
        $case = strtolower(trim($fioCase));
        if ($case === '' || $case === 'nominative') {
            return $fullName;
        }

        if (! in_array($case, ['genitive', 'dative', 'ablative'], true)) {
            return $fullName;
        }

        $cacheKey = mb_strtolower(trim($fullName), 'UTF-8');
        if ($cacheKey === '') {
            return $fullName;
        }

        if (! array_key_exists($cacheKey, $this->cleanNameCache)) {
            try {
                $this->cleanNameCache[$cacheKey] = $this->dadataAddressService->cleanName($fullName);
            } catch (Throwable) {
                $this->cleanNameCache[$cacheKey] = [];
            }
        }

        $payload = $this->cleanNameCache[$cacheKey];
        $field = match ($case) {
            'genitive' => 'result_genitive',
            'dative' => 'result_dative',
            'ablative' => 'result_ablative',
            default => 'result',
        };

        $resolved = trim((string) ($payload[$field] ?? ''));
        if ($resolved !== '') {
            return $resolved;
        }

        $fallback = trim((string) ($payload['result'] ?? ''));

        return $fallback !== '' ? $fallback : $fullName;
    }

    /**
     * Текст тела документа после подстановок: plain → nl2br+e, иначе безопасный HTML для DomPDF.
     */
    public function bodyHtmlForPdf(string $mergedBody): string
    {
        $mergedBody = (string) $mergedBody;
        if (trim($mergedBody) === '') {
            return '';
        }
        if (preg_match('/<[a-z][\s\S]*?>/i', $mergedBody)) {
            return $this->stripInlineTextAlign($this->sanitizePdfHtml($mergedBody));
        }

        $withBr = nl2br(e($mergedBody), false);

        return str_replace(["\r\n", "\r", "\n"], '', $withBr);
    }

    /**
     * Убирает инлайн-выравнивание из текста тела PDF, чтобы работало выравнивание,
     * выбранное в настройках макета (left/center/justify).
     */
    private function stripInlineTextAlign(string $html): string
    {
        if ($html === '') {
            return '';
        }

        $withoutAlignAttr = preg_replace('/\s+align="(?:left|center|right|justify)"/iu', '', $html) ?? $html;
        $withoutTextAlign = preg_replace('/text-align\s*:\s*(?:left|center|right|justify)\s*;?/iu', '', $withoutAlignAttr) ?? $withoutAlignAttr;
        $cleanupEmptyStyle = preg_replace('/\sstyle="\s*;?\s*"/iu', '', $withoutTextAlign) ?? $withoutTextAlign;
        $cleanupStyleSeparators = preg_replace('/style="([^"]*?)\s*;+\s*"/iu', 'style="$1"', $cleanupEmptyStyle) ?? $cleanupEmptyStyle;

        return $cleanupStyleSeparators;
    }

    /**
     * Безопасный HTML для DomPDF. strip_tags удаляет атрибуты style — выравнивание терялось;
     * здесь сохраняются только безопасные inline-стили и нужные теги.
     */
    private function sanitizePdfHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        /** @var array<string, true> */
        $allowed = [
            'p' => true, 'br' => true, 'hr' => true, 'b' => true, 'strong' => true,
            'i' => true, 'em' => true, 'u' => true, 'div' => true, 'span' => true,
            'ul' => true, 'ol' => true, 'li' => true, 'h1' => true, 'h2' => true, 'h3' => true, 'h4' => true, 'font' => true,
            'table' => true, 'thead' => true, 'tbody' => true, 'tr' => true, 'td' => true, 'th' => true,
        ];

        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><div id="__pdf_body_root">'.$html.'</div></body></html>';
        if (! @$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            return e(strip_tags($html));
        }
        $root = $dom->getElementById('__pdf_body_root');
        if (! $root instanceof DOMElement) {
            return '';
        }

        foreach (iterator_to_array($root->childNodes) as $child) {
            $this->sanitizePdfDomRecursive($child, $allowed);
        }

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }

        return $out;
    }

    /**
     * @param  array<string, true>  $allowed
     */
    private function sanitizePdfDomRecursive(DOMNode $node, array $allowed): void
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            return;
        }
        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }

        /** @var DOMElement $el */
        $el = $node;

        $tag = strtolower($el->tagName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'link', 'meta'], true)) {
            $el->parentNode?->removeChild($el);

            return;
        }

        foreach (iterator_to_array($el->childNodes) as $child) {
            $this->sanitizePdfDomRecursive($child, $allowed);
        }

        if (! isset($allowed[$tag])) {
            $this->unwrapPdfElement($el);

            return;
        }

        $this->stripPdfElementAttributes($el, $tag);
    }

    private function unwrapPdfElement(DOMElement $el): void
    {
        $parent = $el->parentNode;
        if (! $parent) {
            return;
        }
        while ($el->firstChild) {
            $parent->insertBefore($el->firstChild, $el);
        }
        $parent->removeChild($el);
    }

    private function stripPdfElementAttributes(DOMElement $el, string $tag): void
    {
        $styleTags = ['div', 'span', 'font', 'p', 'li', 'h1', 'h2', 'h3', 'h4', 'table', 'td', 'th', 'tr'];
        $remove = [];
        foreach (iterator_to_array($el->attributes ?? []) as $attr) {
            $name = strtolower($attr->name);
            if ($name === 'style' && in_array($tag, $styleTags, true)) {
                $clean = $this->sanitizePdfStyle($attr->value);
                if ($clean !== '') {
                    $el->setAttribute('style', $clean);
                } else {
                    $el->removeAttribute('style');
                }

                continue;
            }
            if ($name === 'align' && in_array($tag, ['div', 'p'], true) && preg_match('/^(left|center|right|justify)$/i', $attr->value)) {
                continue;
            }
            if ($tag === 'font' && in_array($name, ['color', 'face', 'size'], true)) {
                continue;
            }
            if (in_array($tag, ['table', 'td', 'th', 'tr'], true) && in_array($name, ['border', 'cellpadding', 'cellspacing', 'colspan', 'rowspan'], true)) {
                continue;
            }
            $remove[] = $attr->name;
        }
        foreach ($remove as $n) {
            $el->removeAttribute($n);
        }
    }

    private function sanitizePdfStyle(string $style): string
    {
        $style = trim($style);
        if ($style === '') {
            return '';
        }
        $parts = array_filter(array_map('trim', explode(';', $style)));
        $out = [];
        foreach ($parts as $part) {
            if (preg_match('/^(text-align|font-size|font-family|font-weight|text-decoration|line-height|width|border|border-collapse|padding)\s*:\s*.+$/iu', $part)) {
                $out[] = $part;
            }
        }

        return implode('; ', $out);
    }
}
