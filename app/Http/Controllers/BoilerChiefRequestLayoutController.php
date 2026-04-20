<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBoilerChiefRequestLayoutRequest;
use App\Http\Requests\StoreReportSubmissionRequest;
use App\Http\Requests\UpdateBoilerChiefRequestLayoutRequest;
use App\Models\Department;
use App\Models\DocumentHeaderLayout;
use App\Models\RequestLayout;
use App\Models\Subdivision;
use App\Models\User;
use App\Support\RequestLayoutDocumentBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class BoilerChiefRequestLayoutController extends Controller
{
    public function index(Request $request): View
    {
        $layouts = RequestLayout::query()
            ->where('user_assigner_id', $request->user()->id)
            ->with(['documentHeaderLayout'])
            ->orderByDesc('updated_at')
            ->get();

        return view('boiler-chief.request-layouts.index', compact('layouts'));
    }

    public function create(Request $request): View
    {
        return view('boiler-chief.request-layouts.create', $this->layoutFormContext($request));
    }

    public function store(StoreBoilerChiefRequestLayoutRequest $request): RedirectResponse
    {
        $payload = $request->layoutPayload();
        RequestLayout::query()->create([
            'title' => $payload['title'],
            'schema' => $payload['schema'],
            'has_header' => $payload['has_header'],
            'type' => $payload['type'],
            'version' => $payload['version'],
            'approver_id' => $payload['approver_id'],
            'division_assigner_id' => $payload['division_assigner_id'],
            'document_header_layout_id' => $payload['document_header_layout_id'],
            'user_assigner_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('boiler-chief.request-layouts.index')
            ->with('status', 'Макет сохранён.');
    }

    public function edit(Request $request, RequestLayout $requestLayout): View
    {
        $this->assertOwner($requestLayout, $request->user());

        return view('boiler-chief.request-layouts.edit', array_merge(
            ['layout' => $requestLayout],
            $this->layoutFormContext($request)
        ));
    }

    public function update(UpdateBoilerChiefRequestLayoutRequest $request, RequestLayout $requestLayout): RedirectResponse
    {
        $this->assertOwner($requestLayout, $request->user());
        $payload = $request->layoutPayload();
        $requestLayout->update([
            'title' => $payload['title'],
            'schema' => $payload['schema'],
            'has_header' => $payload['has_header'],
            'type' => $payload['type'],
            'version' => $payload['version'],
            'approver_id' => $payload['approver_id'],
            'division_assigner_id' => $payload['division_assigner_id'],
            'document_header_layout_id' => $payload['document_header_layout_id'],
        ]);

        return redirect()
            ->route('boiler-chief.request-layouts.index')
            ->with('status', 'Макет обновлён.');
    }

    public function destroy(Request $request, RequestLayout $requestLayout): RedirectResponse
    {
        $this->assertOwner($requestLayout, $request->user());
        $requestLayout->delete();

        return redirect()
            ->route('boiler-chief.request-layouts.index')
            ->with('status', 'Макет удалён.');
    }

    public function fill(Request $request, RequestLayout $requestLayout): View
    {
        $this->assertOwner($requestLayout, $request->user());

        return view('boiler-chief.request-layouts.fill', [
            'layout' => $requestLayout,
            'allowEditLayout' => true,
            'backRoute' => route('boiler-chief.request-layouts.index'),
            'backLabel' => 'К списку макетов заявок',
            'closeRoute' => route('boiler-chief.request-layouts.index'),
            'cancelRoute' => route('boiler-chief.request-layouts.index'),
            'formAction' => route('boiler-chief.request-layouts.filled-pdf', $requestLayout),
        ]);
    }

    /**
     * Сформировать PDF по заполненной заявке без сохранения в БД (журнал удалён).
     */
    public function downloadFilledPdf(
        StoreReportSubmissionRequest $request,
        RequestLayout $requestLayout,
        RequestLayoutDocumentBuilder $builder
    ): Response {
        $this->assertOwner($requestLayout, $request->user());
        $values = $request->fieldValues($requestLayout);
        if ($request->boolean('use_current_date')) {
            $values['_document_date'] = now()->format('d.m.Y');
        } elseif ($request->filled('form_document_date')) {
            $values['_document_date'] = $request->date('form_document_date')->format('d.m.Y');
        }
        $values['_document_number'] = trim((string) $request->input('form_document_number', ''));

        $layout = $requestLayout;
        $layout->load(['approver', 'divisionAssigner', 'documentHeaderLayout']);
        $parts = $builder->pdfParts($layout, $values);
        $structuredHeaderHtml = $parts['structuredHeaderHtml'] ?? null;
        $showHeader = trim($parts['headerText']) !== '' || ($structuredHeaderHtml ?? '') !== '';
        $pdf = Pdf::loadView('boiler-chief.request-layouts.pdf', [
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
            'bodyHtml' => $builder->bodyHtmlForPdf($parts['bodyText']),
            'footerLeftHtml' => $this->pdfPlainToHtml($parts['footerLeftText']),
            'signatureHtml' => $this->pdfPlainToHtml($parts['signatureText']),
            'pdfHeaderAlign' => $parts['pdfHeaderAlign'],
            'pdfBodyAlign' => $parts['pdfBodyAlign'],
            'pdfFooterLeftAlign' => $parts['pdfFooterLeftAlign'],
            'pdfFooterRightAlign' => $parts['pdfFooterRightAlign'],
            'headerUsesStructuredLayout' => $structuredHeaderHtml !== null && $structuredHeaderHtml !== '',
            'presentationTitleSizePt' => $parts['presentationHeadingSizePt'] ?? 15,
            'presentationSubtitleSizePt' => $parts['presentationSubtitleSizePt'] ?? 12,
        ])->setPaper('a4', 'portrait');

        $fileName = 'zajavka-'.now()->format('YmdHis').'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    public function layoutSchemaJson(Request $request, RequestLayout $requestLayout): JsonResponse
    {
        $this->assertOwner($requestLayout, $request->user());
        $schema = is_array($requestLayout->schema) ? $requestLayout->schema : [];

        $fields = [];
        foreach ($schema['fields'] ?? [] as $row) {
            if (! is_array($row) || empty($row['key'])) {
                continue;
            }
            $fields[] = [
                'key' => (string) $row['key'],
                'label' => (string) ($row['label'] ?? $row['key']),
                'type' => (string) ($row['type'] ?? 'text'),
                'choices' => isset($row['choices']) && is_array($row['choices']) ? array_values($row['choices']) : [],
            ];
        }

        $preset = isset($schema['pdf_footer_preset']) ? trim((string) $schema['pdf_footer_preset']) : '';

        return response()->json([
            'id' => $requestLayout->id,
            'title' => $requestLayout->title,
            'fields' => $fields,
            'pdf_footer_preset' => $preset !== '' ? $preset : 'one_signer_author',
        ]);
    }

    public function foremanFillIndex(Request $request): View
    {
        $this->assertSiteForeman($request->user());

        $layouts = RequestLayout::query()
            ->with(['userAssigner:id,surname,name,patronymic'])
            ->orderByDesc('updated_at')
            ->get();

        return view('applications.installation-act-layout-fill-index', compact('layouts'));
    }

    public function foremanFill(Request $request, RequestLayout $requestLayout): View
    {
        $this->assertSiteForeman($request->user());

        return view('boiler-chief.request-layouts.fill', [
            'layout' => $requestLayout,
            'allowEditLayout' => false,
            'backRoute' => route('applications.installation-act.layout-fill.index'),
            'backLabel' => 'К списку макетов заявок',
            'closeRoute' => route('applications.installation-act.layout-fill.index'),
            'cancelRoute' => route('applications.installation-act.layout-fill.index'),
            'formAction' => route('applications.installation-act.layout-fill.pdf', $requestLayout),
        ]);
    }

    public function foremanDownloadFilledPdf(
        StoreReportSubmissionRequest $request,
        RequestLayout $requestLayout,
        RequestLayoutDocumentBuilder $builder
    ): Response {
        $this->assertSiteForeman($request->user());

        return $this->downloadFilledPdf($request, $requestLayout, $builder);
    }

    /**
     * @return array{users: \Illuminate\Database\Eloquent\Collection<int, User>, departments: \Illuminate\Database\Eloquent\Collection<int, Department>}
     */
    private function layoutFormContext(Request $request): array
    {
        $this->syncDepartmentsFromSubdivisionsIfNeeded();

        return [
            'users' => User::query()->orderBy('surname')->orderBy('name')->limit(500)->get(),
            'departments' => Department::query()->orderBy('name')->get(),
            'documentHeaderLayouts' => DocumentHeaderLayout::query()
                ->where('user_assigner_id', $request->user()->id)
                ->orderBy('title')
                ->get(),
        ];
    }

    /**
     * Подтягивает подразделения из subdivisions в departments (по названию),
     * чтобы список для division_assigner_id не был пустым даже без отдельного сидера.
     */
    private function syncDepartmentsFromSubdivisionsIfNeeded(): void
    {
        if (! Schema::hasTable('subdivisions')) {
            return;
        }

        foreach (Subdivision::query()->orderBy('name')->get() as $subdivision) {
            $name = trim((string) $subdivision->name);
            if ($name === '') {
                continue;
            }
            Department::query()->firstOrCreate(['name' => $name]);
        }
    }

    private function assertOwner(RequestLayout $layout, ?User $user): void
    {
        if (! $user || (int) $layout->user_assigner_id !== (int) $user->id) {
            abort(403);
        }
    }

    private function assertSiteForeman(?User $user): void
    {
        if (! $user || ! $user->hasRoleId(4)) {
            abort(403, 'Доступ разрешён только мастеру участка.');
        }
    }

    /**
     * Текст макета → безопасный HTML для DomPDF: переносы строк только через &lt;br&gt;,
     * без «двойных» интервалов (nl2br оставляет символы перевода строки; вместе с white-space: pre-wrap это давало лишний перенос).
     */
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
