<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBoilerChiefRequestLayoutRequest;
use App\Http\Requests\StoreReportSubmissionRequest;
use App\Http\Requests\UpdateBoilerChiefRequestLayoutRequest;
use App\Models\Application;
use App\Models\Department;
use App\Models\DocumentHeaderLayout;
use App\Models\MaterialStockMovement;
use App\Models\MaterialStockMovementType;
use App\Models\RequestLayout;
use App\Models\RequestSubmission;
use App\Models\Role;
use App\Models\Subdivision;
use App\Models\User;
use App\Support\ListingPerPage;
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
            ->with(['documentHeaderLayout'])
            ->orderByDesc('updated_at')
            ->get();

        $canDesignReportLayouts = $request->user()?->hasAnyRoleId(User::REPORT_LAYOUT_DESIGNER_ROLE_IDS) ?? false;

        return view('boiler-chief.request-layouts.index', compact('layouts', 'canDesignReportLayouts'));
    }

    public function create(Request $request): View
    {
        return view('boiler-chief.request-layouts.create', $this->layoutFormContext($request, null));
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
        ]);

        return redirect()
            ->route('boiler-chief.request-layouts.index')
            ->with('status', 'Макет сохранён.');
    }

    public function edit(Request $request, RequestLayout $requestLayout): View
    {
        $this->assertReportLayoutDesigner($requestLayout, $request->user());

        return view('boiler-chief.request-layouts.edit', array_merge(
            ['layout' => $requestLayout],
            $this->layoutFormContext($request, $requestLayout)
        ));
    }

    public function update(UpdateBoilerChiefRequestLayoutRequest $request, RequestLayout $requestLayout): RedirectResponse
    {
        $this->assertReportLayoutDesigner($requestLayout, $request->user());
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
        $this->assertReportLayoutDesigner($requestLayout, $request->user());
        $requestLayout->delete();

        return redirect()
            ->route('boiler-chief.request-layouts.index')
            ->with('status', 'Макет удалён.');
    }

    public function fill(Request $request, RequestLayout $requestLayout): View
    {
        $this->assertLayoutReportPdfFill($request->user());
        $user = $request->user();
        $isDesigner = $user instanceof User && $user->hasAnyRoleId(User::REPORT_LAYOUT_DESIGNER_ROLE_IDS);
        $isBoilerChief = $user instanceof User && $user->hasRoleId(7);

        return view('boiler-chief.request-layouts.fill', [
            'layout' => $requestLayout,
            'users' => User::query()->with('role')->orderBy('surname')->orderBy('name')->limit(500)->get(),
            'applications' => $this->reportEquipmentApplications($request->user()),
            'warehouseBalances' => $this->reportWarehouseBalances($request->user()),
            'allowEditLayout' => $isDesigner,
            'backRoute' => ($isDesigner || $isBoilerChief)
                ? route('boiler-chief.request-layouts.index')
                : route('boiler-chief.layout-applications.index'),
            'backLabel' => ($isDesigner || $isBoilerChief) ? 'К списку макетов отчетов' : 'Отчеты по макетам',
            'closeRoute' => ($isDesigner || $isBoilerChief)
                ? route('boiler-chief.request-layouts.index')
                : route('boiler-chief.layout-applications.index'),
            'cancelRoute' => ($isDesigner || $isBoilerChief)
                ? route('boiler-chief.request-layouts.index')
                : route('boiler-chief.layout-applications.index'),
            'formAction' => route('boiler-chief.request-layouts.filled-pdf', $requestLayout),
        ]);
    }

    /**
     * Сформировать PDF по заполненному отчету без сохранения в БД (журнал удалён).
     */
    public function downloadFilledPdf(
        StoreReportSubmissionRequest $request,
        RequestLayout $requestLayout,
        RequestLayoutDocumentBuilder $builder
    ): Response {
        $this->assertLayoutReportPdfFill($request->user());
        $values = $this->valuesFromReportSubmissionRequest($request, $requestLayout);

        return $this->pdfResponseForLayout($requestLayout, $values, $builder, 'zajavka-'.now()->format('YmdHis').'.pdf');
    }

    public function layoutSchemaJson(Request $request, RequestLayout $requestLayout): JsonResponse
    {
        $this->assertReportLayoutDesigner($requestLayout, $request->user());

        return response()->json($requestLayout->clientFillPayload());
    }

    /**
     * JSON схемы для формы «Отчеты по макетам» / заполнения отчёта: те же роли, что для заполнения макета по акту.
     */
    public function layoutSchemaJsonForReportFillers(Request $request, RequestLayout $requestLayout): JsonResponse
    {
        $this->assertSiteForeman($request->user());

        return response()->json($requestLayout->clientFillPayload());
    }

    /** JSON схемы для модального «Новый отчёт» в каталоге макетов (роли каталога, без middleware «Заявки»). */
    public function layoutFillSchemaJsonForCatalog(Request $request, RequestLayout $requestLayout): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->hasAnyRoleId(User::REPORT_LAYOUT_CATALOG_VIEWER_ROLE_IDS)) {
            abort(403, 'Загрузка схемы макета вам недоступна.');
        }

        return response()->json($requestLayout->clientFillPayload());
    }

    public function foremanFillIndex(Request $request): View
    {
        $user = $request->user();
        $this->assertSiteForeman($user);

        $layouts = RequestLayout::query()
            ->with(['approver:id,surname,name,patronymic'])
            ->orderByDesc('updated_at')
            ->get();

        $parent = $user instanceof User
            ? $this->installationActParentLink($user)
            : ['href' => route('dashboard'), 'label' => 'Главная'];

        return view('applications.installation-act-layout-fill-index', [
            'layouts' => $layouts,
            'layoutFillParentHref' => $parent['href'],
            'layoutFillParentLabel' => $parent['label'],
        ]);
    }

    public function foremanSubmissionsIndex(Request $request): View
    {
        $user = $request->user();
        $this->assertSiteForeman($user);

        $parent = $user instanceof User
            ? $this->installationActParentLink($user)
            : ['href' => route('dashboard'), 'label' => 'Главная'];

        $pagination = ListingPerPage::fromRequest($request);
        $submissions = RequestSubmission::query()
            ->with(['requestLayout:id,title'])
            ->where('created_by', (int) ($user?->id ?? 0))
            ->orderByDesc('id')
            ->paginate($pagination['perPage'])
            ->withQueryString();

        return view('applications.installation-act-layout-fill-submissions', [
            'submissions' => $submissions,
            'layoutFillParentHref' => $parent['href'],
            'layoutFillParentLabel' => $parent['label'],
            'perPage' => $pagination['perPage'],
            'allowedPerPage' => $pagination['allowedPerPage'],
            'defaultPerPage' => $pagination['defaultPerPage'],
        ]);
    }

    public function foremanFill(Request $request, RequestLayout $requestLayout): View
    {
        $this->assertSiteForeman($request->user());

        return view('applications.installation-act-layout-fill-rich', [
            'layout' => $requestLayout,
            'users' => User::query()->with(['role', 'assignedSubdivisions:id'])->orderBy('surname')->orderBy('name')->limit(500)->get(),
            'applications' => $this->reportEquipmentApplications($request->user()),
            'allowEditLayout' => false,
            'backRoute' => route('applications.installation-act.layout-fill.index'),
            'backLabel' => 'К списку макетов отчетов',
            'closeRoute' => route('applications.installation-act.layout-fill.index'),
            'cancelRoute' => route('applications.installation-act.layout-fill.index'),
            'formAction' => route('applications.installation-act.layout-fill.pdf', $requestLayout),
            'layoutViewerContext' => User::layoutReportViewerContext($request->user()),
        ]);
    }

    public function foremanDownloadFilledPdf(
        StoreReportSubmissionRequest $request,
        RequestLayout $requestLayout,
        RequestLayoutDocumentBuilder $builder
    ): Response {
        $this->assertSiteForeman($request->user());
        $values = $this->valuesFromReportSubmissionRequest($request, $requestLayout);
        $submission = RequestSubmission::query()->create([
            'data' => $values,
            'created_by' => (int) $request->user()->id,
            'layout_structure_id' => (int) $requestLayout->id,
        ]);

        return $this->pdfResponseForLayout($requestLayout, $values, $builder, 'zajavka-'.$submission->id.'.pdf');
    }

    public function foremanSubmissionPdf(
        Request $request,
        RequestSubmission $submission,
        RequestLayoutDocumentBuilder $builder
    ): Response {
        $user = $request->user();
        $this->assertSiteForeman($user);

        if (! $user || (int) $submission->created_by !== (int) $user->id) {
            abort(403);
        }

        $layout = $submission->requestLayout;
        if (! $layout instanceof RequestLayout) {
            abort(404);
        }
        $values = is_array($submission->data) ? $submission->data : [];

        return $this->pdfResponseForLayout($layout, $values, $builder, 'zajavka-'.$submission->id.'.pdf');
    }

    /**
     * Собирает values для PDF из формы заполнения макета (rich + simple).
     *
     * @return array<string, mixed>
     */
    private function valuesFromReportSubmissionRequest(StoreReportSubmissionRequest $request, RequestLayout $requestLayout): array
    {
        $values = $request->fieldValues($requestLayout);

        if ($request->boolean('use_current_date')) {
            $values['_document_date'] = now()->format('d.m.Y');
        } elseif ($request->filled('form_document_date')) {
            $values['_document_date'] = $request->date('form_document_date')->format('d.m.Y');
        }

        foreach ([1, 2, 3] as $slot) {
            $key = 'signer_'.$slot.'_user_id';
            if ($request->filled($key)) {
                $values[$key] = (int) $request->input($key);
            }
        }

        $values['_document_number'] = trim((string) $request->input('form_document_number', ''));

        return $values;
    }

    /**
     * Формирует PDF response для макета.
     *
     * @param  array<string, mixed>  $values
     */
    private function pdfResponseForLayout(
        RequestLayout $layout,
        array $values,
        RequestLayoutDocumentBuilder $builder,
        string $fileName
    ): Response {
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

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    /**
     * @return array{
     *     users: \Illuminate\Database\Eloquent\Collection<int, User>,
     *     departments: \Illuminate\Database\Eloquent\Collection<int, Department>,
     *     roles: \Illuminate\Database\Eloquent\Collection<int, Role>,
     *     documentHeaderLayouts: \Illuminate\Database\Eloquent\Collection<int, DocumentHeaderLayout>,
     *     documentHeaderLayoutPreviewHtmlById: array<string, string>
     * }
     */
    private function layoutFormContext(Request $request, ?RequestLayout $wizardLayout = null): array
    {
        $this->syncDepartmentsFromSubdivisionsIfNeeded();

        $documentHeaderLayouts = DocumentHeaderLayout::query()
            ->orderBy('title')
            ->get();

        return [
            'users' => User::query()->orderBy('surname')->orderBy('name')->limit(500)->get(),
            'departments' => Department::query()->orderBy('name')->get(),
            'roles' => Role::query()->orderBy('name')->get(),
            'documentHeaderLayouts' => $documentHeaderLayouts,
            'documentHeaderLayoutPreviewHtmlById' => $this->documentHeaderLayoutPreviewHtmlById(
                $documentHeaderLayouts,
                $wizardLayout,
                $request
            ),
        ];
    }

    /**
     * HTML шапки по каждому макету (черновик без данных заявки) для предпросмотра в мастере.
     *
     * @param  \Illuminate\Support\Collection<int, DocumentHeaderLayout>  $headerLayouts
     * @return array<string, string>
     */
    private function documentHeaderLayoutPreviewHtmlById($headerLayouts, ?RequestLayout $wizardLayout, Request $request): array
    {
        $builder = app(RequestLayoutDocumentBuilder::class);
        $proxy = $wizardLayout ?? RequestLayout::make([
            'schema' => [
                'fields' => [],
                'executor_mode' => 'user',
                'executor_user_id' => (int) ($request->user()?->id ?? 0),
            ],
        ]);

        $out = [];
        foreach ($headerLayouts as $headerLayout) {
            $out[(string) $headerLayout->id] = $builder->renderStructuredDocumentHeaderHtml(
                $headerLayout,
                $proxy,
                []
            );
        }

        return $out;
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

    private function assertReportLayoutDesigner(RequestLayout $layout, ?User $user): void
    {
        if (! $user || ! $user->hasAnyRoleId(User::REPORT_LAYOUT_DESIGNER_ROLE_IDS)) {
            abort(403);
        }
    }

    /** PDF и формы заполнения отчёта по макету — все роли. */
    private function assertLayoutReportPdfFill(?User $user): void
    {
        if (! $user || ! $user->hasAnyRoleId(User::REPORT_LAYOUT_FILL_ROLE_IDS)) {
            abort(403, 'Заполнение отчётов недоступно для вашей роли.');
        }
    }

    private function assertSiteForeman(?User $user): void
    {
        $this->assertLayoutReportPdfFill($user);
    }

    /**
     * Куда вести «назад» из списка макетов для заполнения: бухгалтер не имеет доступа к загрузке акта, только к просмотру актов.
     *
     * @return array{href: string, label: string}
     */
    private function installationActParentLink(User $user): array
    {
        if ($user->hasRoleId(3)) {
            return [
                'href' => route('applications.installation-act.browse'),
                'label' => 'Акты по заявкам',
            ];
        }

        if ($user->hasAnyRoleId([1, 6, 2, 4, 7])) {
            return [
                'href' => route('applications.installation-act.upload'),
                'label' => 'Акт установки',
            ];
        }

        return [
            'href' => route('dashboard'),
            'label' => 'Главная',
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Application>
     */
    private function reportEquipmentApplications(?User $user)
    {
        if (! $user) {
            return collect();
        }
        $query = Application::query()
            ->with(['subdivision:id,name', 'items'])
            ->orderByDesc('id')
            ->limit(300);

        if ($user->hasRoleId(4)) {
            $query->forSiteForemanAccess($user);
        } elseif ($user->hasRoleId(7)) {
            $subdivisionIds = $user->boilerChiefSubdivisions()->pluck('subdivisions.id');
            $query->whereIn('subdivision_id', $subdivisionIds);
        } elseif ($user->hasRoleId(3) || $user->hasAnyRoleId(User::MANAGEMENT_EDITOR_ROLE_IDS)) {
            // Бухгалтер, директор, ТД и снабжение — все заявки для подстановки в PDF (без сохранения заявки в БД).
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query->get();
    }

    /**
     * Остатки оборудования по складам для вставки в отчет.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, label: string, equipment: array<int, array{name: string, quantity: string, line: string}>}>
     */
    private function reportWarehouseBalances(?User $user)
    {
        if (! $user) {
            return collect();
        }

        $rows = MaterialStockMovement::query()
            ->join('warehouses', 'warehouses.id', '=', 'material_stock_movements.warehouse_id')
            ->join('equipment', 'equipment.id', '=', 'material_stock_movements.equipment_id')
            ->leftJoin('measurement_units', 'measurement_units.id', '=', 'equipment.measurement_unit_id')
            ->join('material_stock_movement_types as msm_types', 'msm_types.id', '=', 'material_stock_movements.material_stock_movement_type_id')
            ->selectRaw('warehouses.id as warehouse_id')
            ->selectRaw('warehouses.name as warehouse_name')
            ->selectRaw('warehouses.subdivision_id as subdivision_id')
            ->selectRaw('equipment.name as equipment_name')
            ->selectRaw("COALESCE(measurement_units.code, 'шт') as unit_code")
            ->selectRaw('SUM(CASE WHEN msm_types.name = ? THEN -material_stock_movements.quantity ELSE material_stock_movements.quantity END) as balance', [MaterialStockMovementType::NAME_ISSUE])
            ->groupBy('warehouses.id', 'warehouses.name', 'warehouses.subdivision_id', 'equipment.name', 'measurement_units.code')
            ->havingRaw('SUM(CASE WHEN msm_types.name = ? THEN -material_stock_movements.quantity ELSE material_stock_movements.quantity END) > 0.0005', [MaterialStockMovementType::NAME_ISSUE])
            ->orderBy('warehouses.name')
            ->orderBy('equipment.name');

        if ($user->hasRoleId(4)) {
            $subdivisionIds = $user->assignedSubdivisions()->pluck('subdivisions.id');
            $rows->whereIn('warehouses.subdivision_id', $subdivisionIds);
        } elseif ($user->hasRoleId(7)) {
            $subdivisionIds = $user->boilerChiefSubdivisions()->pluck('subdivisions.id');
            $rows->whereIn('warehouses.subdivision_id', $subdivisionIds);
        } elseif ($user->hasRoleId(3) || $user->hasAnyRoleId(User::MANAGEMENT_EDITOR_ROLE_IDS)) {
            // Бухгалтер, директор, ТД и снабжение — остатки по всем складам.
        } else {
            return collect();
        }

        return $rows->get()
            ->groupBy('warehouse_id')
            ->map(function ($group) {
                $first = $group->first();
                $equipment = $group->map(function ($row) {
                    $quantity = \App\Support\PieceQuantity::formatForDisplay(
                        (float) ($row->balance ?? 0),
                        (string) ($row->unit_code ?? 'шт')
                    );
                    $line = trim(((string) ($row->equipment_name ?? '')).' x '.$quantity.' '.((string) ($row->unit_code ?? 'шт')));

                    return [
                        'name' => (string) ($row->equipment_name ?? ''),
                        'quantity' => trim($quantity.' '.((string) ($row->unit_code ?? 'шт'))),
                        'line' => $line,
                    ];
                })->values()->all();

                return [
                    'id' => (int) ($first->warehouse_id ?? 0),
                    'label' => (string) ($first->warehouse_name ?? 'Склад'),
                    'equipment' => $equipment,
                ];
            })
            ->values();
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
