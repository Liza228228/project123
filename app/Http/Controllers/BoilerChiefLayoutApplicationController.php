<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLayoutApplicationRequest;
use App\Http\Requests\UpdateLayoutApplicationRequest;
use App\Models\Application;
use App\Models\RequestLayout;
use App\Models\RequestSubmission;
use App\Models\User;
use App\Support\RequestLayoutDocumentBuilder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BoilerChiefLayoutApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $submissions = RequestSubmission::query()
            ->with(['requestLayout', 'creator'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('boiler-chief.layout-applications.index', [
            'submissions' => $submissions,
        ]);
    }

    public function create(Request $request): View
    {
        $layouts = RequestLayout::query()
            ->orderBy('title')
            ->get();

        $users = User::query()
            ->with(['role', 'assignedSubdivisions:id'])
            ->orderBy('surname')
            ->orderBy('name')
            ->limit(500)
            ->get();
        $applications = $this->applicationsForLayoutInsertion($request->user());
        $layoutSchemasById = $layouts->mapWithKeys(
            fn (RequestLayout $layout): array => [$layout->id => $layout->clientFillPayload()]
        )->all();

        return view('boiler-chief.layout-applications.create', [
            'layouts' => $layouts,
            'users' => $users,
            'applications' => $applications,
            'layoutSchemasById' => $layoutSchemasById,
            'layoutViewerContext' => User::layoutReportViewerContext($request->user()),
        ]);
    }

    public function edit(Request $request, RequestSubmission $submission): View
    {
        $this->authorizeSubmission($submission, $request->user());
        $submission->loadMissing('requestLayout');
        $layout = $submission->requestLayout;
        if (! $layout instanceof RequestLayout) {
            abort(404);
        }

        $layouts = collect([$layout]);
        $users = User::query()
            ->with(['role', 'assignedSubdivisions:id'])
            ->orderBy('surname')
            ->orderBy('name')
            ->limit(500)
            ->get();
        $applications = $this->applicationsForLayoutInsertion($request->user());
        $layoutSchemasById = [$layout->id => $layout->clientFillPayload()];

        $data = is_array($submission->data) ? $submission->data : [];
        $formDocumentDate = '';
        $doc = trim((string) ($data['_document_date'] ?? ''));
        if ($doc !== '' && preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/u', $doc, $m)) {
            $formDocumentDate = $m[3].'-'.$m[2].'-'.$m[1];
        }

        return view('boiler-chief.layout-applications.create', [
            'layouts' => $layouts,
            'users' => $users,
            'applications' => $applications,
            'layoutSchemasById' => $layoutSchemasById,
            'layoutViewerContext' => User::layoutReportViewerContext($request->user()),
            'editingSubmission' => $submission,
            'initialSubmissionPayload' => $data,
            'formDocumentDate' => $formDocumentDate,
        ]);
    }

    /**
     * Та же логика подстановки заявок, что в {@see BoilerChiefRequestLayoutController::reportEquipmentApplications}.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Application>
     */
    private function applicationsForLayoutInsertion(?User $user)
    {
        if (! $user) {
            return collect();
        }
        $query = Application::query()
            ->with(['subdivision:id,name', 'items'])
            ->orderByDesc('id')
            ->limit(300);

        if ($user->hasRoleId(4)) {
            $subdivisionIds = $user->assignedSubdivisions()->pluck('subdivisions.id');
            $query->whereIn('subdivision_id', $subdivisionIds);
        } elseif ($user->hasRoleId(7)) {
            $subdivisionIds = $user->boilerChiefSubdivisions()->pluck('subdivisions.id');
            $query->whereIn('subdivision_id', $subdivisionIds);
        } elseif ($user->hasRoleId(3) || $user->hasAnyRoleId(User::MANAGEMENT_EDITOR_ROLE_IDS)) {
            // бухгалтер, директор, ТД, снабжение — все заявки
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query->get();
    }

    public function store(
        StoreLayoutApplicationRequest $request,
        RequestLayoutDocumentBuilder $builder
    ): SymfonyResponse {
        $layout = $request->layout();
        $values = $this->layoutApplicationValuesFromRequest($request, $layout);

        $submission = RequestSubmission::query()->create([
            'data' => $values,
            'created_by' => $request->user()->id,
            'layout_structure_id' => $layout->id,
        ]);

        return $this->streamPdfResponse($layout, $values, $builder, $submission->id);
    }

    public function update(
        UpdateLayoutApplicationRequest $request,
        RequestSubmission $submission,
        RequestLayoutDocumentBuilder $builder
    ): SymfonyResponse {
        $this->authorizeSubmission($submission, $request->user());
        $submission->loadMissing('requestLayout');
        $layout = $submission->requestLayout;
        if (! $layout instanceof RequestLayout) {
            abort(404);
        }

        $values = $this->layoutApplicationValuesFromRequest($request, $layout);
        $submission->update(['data' => $values]);

        return $this->streamPdfResponse($layout, $values, $builder, $submission->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function layoutApplicationValuesFromRequest(StoreLayoutApplicationRequest $request, RequestLayout $layout): array
    {
        $values = $request->fieldValues($layout);

        foreach ([1, 2, 3] as $i) {
            $k = 'signer_'.$i.'_user_id';
            if ($request->filled($k)) {
                $values[$k] = (int) $request->input($k);
            }
        }

        if ($request->boolean('use_current_date')) {
            $values['_document_date'] = now()->format('d.m.Y');
        } elseif ($request->filled('form_document_date')) {
            $values['_document_date'] = $request->date('form_document_date')->format('d.m.Y');
        }

        return $values;
    }

    public function pdf(
        Request $request,
        RequestSubmission $submission,
        RequestLayoutDocumentBuilder $builder
    ): SymfonyResponse {
        $this->authorizeSubmission($submission, $request->user());

        $layout = $submission->requestLayout;
        if (! $layout instanceof RequestLayout) {
            abort(404);
        }

        $values = is_array($submission->data) ? $submission->data : [];

        return $this->streamPdfResponse($layout, $values, $builder, $submission->id);
    }

    public function destroy(Request $request, RequestSubmission $submission): RedirectResponse
    {
        $this->authorizeSubmission($submission, $request->user());
        $submission->delete();

        return redirect()->route('boiler-chief.layout-applications.index')
            ->with('status', 'Заявка по макету удалена.');
    }

    private function streamPdfResponse(
        RequestLayout $layout,
        array $values,
        RequestLayoutDocumentBuilder $builder,
        ?int $submissionId = null
    ): SymfonyResponse {
        $layout->load(['approver', 'divisionAssigner', 'documentHeaderLayout']);
        $parts = $builder->pdfParts($layout, $values);
        $structuredHeaderHtml = $parts['structuredHeaderHtml'] ?? null;
        $showHeader = trim($parts['headerText']) !== '' || ($structuredHeaderHtml ?? '') !== '';

        $viewData = [
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
        ];

        $pdf = Pdf::loadView('boiler-chief.request-layouts.pdf', $viewData)->setPaper('a4', 'portrait');

        $fileName = 'zajavka-'.($submissionId ?? now()->format('YmdHis')).'.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
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

    private function authorizeSubmission(RequestSubmission $submission, ?User $user): void
    {
        if (! $user) {
            abort(403);
        }
        $submission->loadMissing('requestLayout');
        if (! $submission->requestLayout) {
            abort(404);
        }
        if (! $user->hasAnyRoleId(User::LAYOUT_APPLICATION_REPORT_ROLE_IDS)) {
            abort(403);
        }
    }
}
