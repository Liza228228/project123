<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLayoutApplicationRequest;
use App\Http\Requests\UpdateLayoutApplicationRequest;
use App\Models\RequestLayout;
use App\Models\RequestSubmission;
use App\Models\User;
use App\Support\ListingPerPage;
use App\Support\LayoutApplicationCatalog;
use App\Support\ReportLayoutCommercialProposal;
use App\Support\ReportLayoutEquipmentApplications;
use App\Support\RequestLayoutDocumentBuilder;
use App\Support\RequestLayoutPdfExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class BoilerChiefLayoutApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $pagination = ListingPerPage::fromRequest($request);
        $submissions = RequestSubmission::query()
            ->ownedBy($user ?? 0)
            ->with([
                'requestLayout' => fn ($query) => $query->withTrashed(),
                'creator',
            ])
            ->orderByDesc('id')
            ->paginate($pagination['perPage'])
            ->withQueryString();

        $layouts = LayoutApplicationCatalog::layoutsForFillCatalog();

        return view('boiler-chief.layout-applications.index', [
            'submissions' => $submissions,
            'layouts' => $layouts,
            'perPage' => $pagination['perPage'],
            'allowedPerPage' => $pagination['allowedPerPage'],
            'defaultPerPage' => $pagination['defaultPerPage'],
        ]);
    }

    public function create(Request $request): View
    {
        $layouts = LayoutApplicationCatalog::layoutsForFillCatalog();

        $users = User::query()
            ->with(['role', 'assignedSubdivisions:id'])
            ->orderBy('surname')
            ->orderBy('name')
            ->limit(500)
            ->get();
        $applicationOptions = ReportLayoutEquipmentApplications::clientOptionsForUser($request->user());
        $layoutSchemasById = $layouts->mapWithKeys(
            fn (RequestLayout $layout): array => [$layout->id => $layout->clientFillPayload()]
        )->all();

        return view('boiler-chief.layout-applications.create', [
            'layouts' => $layouts,
            'users' => $users,
            'applicationOptions' => $applicationOptions,
            'layoutSchemasById' => $layoutSchemasById,
            'layoutViewerContext' => User::layoutReportViewerContext($request->user()),
            'measurementMeta' => ReportLayoutCommercialProposal::measurementMetaForUi(),
            'subdivisionWarehouseOptions' => ReportLayoutCommercialProposal::subdivisionWarehouseOptionsForUser($request->user()),
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
        $applicationOptions = ReportLayoutEquipmentApplications::clientOptionsForUser($request->user());
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
            'applicationOptions' => $applicationOptions,
            'layoutSchemasById' => $layoutSchemasById,
            'layoutViewerContext' => User::layoutReportViewerContext($request->user()),
            'measurementMeta' => ReportLayoutCommercialProposal::measurementMetaForUi(),
            'subdivisionWarehouseOptions' => ReportLayoutCommercialProposal::subdivisionWarehouseOptionsForUser($request->user()),
            'editingSubmission' => $submission,
            'initialSubmissionPayload' => $data,
            'formDocumentDate' => $formDocumentDate,
        ]);
    }

    public function store(
        StoreLayoutApplicationRequest $request,
        RequestLayoutDocumentBuilder $builder,
        RequestLayoutPdfExporter $exporter
    ): SymfonyResponse {
        $layout = $request->layout();
        $values = $this->layoutApplicationValuesFromRequest($request, $layout);

        $submission = RequestSubmission::query()->create([
            'data' => $values,
            'created_by' => $request->user()->id,
            'layout_structure_id' => $layout->id,
        ]);

        return $this->streamPdfResponse($layout, $values, $exporter, $submission->id);
    }

    public function update(
        UpdateLayoutApplicationRequest $request,
        RequestSubmission $submission,
        RequestLayoutDocumentBuilder $builder,
        RequestLayoutPdfExporter $exporter
    ): SymfonyResponse {
        $this->authorizeSubmission($submission, $request->user());
        $submission->loadMissing('requestLayout');
        $layout = $submission->requestLayout;
        if (! $layout instanceof RequestLayout) {
            abort(404);
        }

        $values = $this->layoutApplicationValuesFromRequest($request, $layout);
        $submission->update(['data' => $values]);

        return $this->streamPdfResponse($layout, $values, $exporter, $submission->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function layoutApplicationValuesFromRequest(StoreLayoutApplicationRequest $request, RequestLayout $layout): array
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
        RequestLayoutPdfExporter $exporter
    ): SymfonyResponse {
        $this->authorizeSubmission($submission, $request->user());

        $layout = $submission->requestLayout;
        if (! $layout instanceof RequestLayout) {
            abort(404);
        }

        $values = is_array($submission->data) ? $submission->data : [];

        return $this->streamPdfResponse($layout, $values, $exporter, $submission->id);
    }

    public function destroy(Request $request, RequestSubmission $submission): RedirectResponse
    {
        $this->authorizeSubmission($submission, $request->user(), requireLayout: false);
        $submission->delete();

        return redirect()->route('boiler-chief.layout-applications.index')
            ->with('status', 'Отчет по макету удален.');
    }

    private function streamPdfResponse(
        RequestLayout $layout,
        array $values,
        RequestLayoutPdfExporter $exporter,
        ?int $submissionId = null
    ): SymfonyResponse {
        $fileName = $exporter->suggestedFileName($submissionId);

        return response($exporter->outputBinary($layout, $values, $submissionId), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    private function authorizeSubmission(
        RequestSubmission $submission,
        ?User $user,
        bool $requireLayout = true,
    ): void {
        if (! $user) {
            abort(403);
        }
        if (! $user->hasAnyRoleId(User::REPORT_LAYOUT_FILL_ROLE_IDS)) {
            abort(403);
        }
        if ((int) $submission->created_by !== (int) $user->id) {
            abort(403, 'Нет доступа к этому отчёту.');
        }
        if (! $requireLayout) {
            return;
        }
        $submission->loadMissing(['requestLayout' => fn ($query) => $query->withTrashed()]);
        if (! $submission->requestLayout) {
            abort(404);
        }
    }
}
