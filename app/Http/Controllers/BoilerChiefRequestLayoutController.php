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
use App\Models\Role;
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
            'users' => User::query()->with('role')->orderBy('surname')->orderBy('name')->limit(500)->get(),
            'applications' => $this->reportEquipmentApplications($request->user()),
            'warehouseBalances' => $this->reportWarehouseBalances($request->user()),
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
        foreach ([1, 2, 3] as $slot) {
            $key = 'signer_'.$slot.'_user_id';
            if ($request->filled($key)) {
                $values[$key] = (int) $request->input($key);
            }
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
        $signatureSlotsCount = (int) ($schema['signature_slots_count'] ?? 0);
        if ($signatureSlotsCount <= 0) {
            $signatureSlotsCount = match ($preset) {
                'three_signers' => 3,
                'two_signers' => 2,
                default => 1,
            };
        }
        $signatureSlotsCount = max(1, min(3, $signatureSlotsCount));
        $signatureRoles = [];
        $rawSignatureRoles = $schema['signature_roles'] ?? [];
        if (is_array($rawSignatureRoles)) {
            for ($slot = 1; $slot <= $signatureSlotsCount; $slot++) {
                $roleId = (int) ($rawSignatureRoles[$slot] ?? $rawSignatureRoles[(string) $slot] ?? 0);
                if ($roleId > 0) {
                    $signatureRoles[$slot] = $roleId;
                }
            }
        }
        $signatureRoleNames = [];
        if ($signatureRoles !== []) {
            $roles = Role::query()
                ->whereIn('id', array_values($signatureRoles))
                ->pluck('name', 'id');
            foreach ($signatureRoles as $slot => $roleId) {
                $signatureRoleNames[$slot] = (string) ($roles[$roleId] ?? '');
            }
        }

        return response()->json([
            'id' => $requestLayout->id,
            'title' => $requestLayout->title,
            'fields' => $fields,
            'pdf_footer_preset' => $preset !== '' ? $preset : 'one_signer_author',
            'signature_slots_count' => $signatureSlotsCount,
            'signature_roles' => $signatureRoles,
            'signature_role_names' => $signatureRoleNames,
        ]);
    }

    public function foremanFillIndex(Request $request): View
    {
        $this->assertSiteForeman($request->user());

        $layouts = RequestLayout::query()
            ->with(['approver:id,surname,name,patronymic'])
            ->orderByDesc('updated_at')
            ->get();

        return view('applications.installation-act-layout-fill-index', compact('layouts'));
    }

    public function foremanFill(Request $request, RequestLayout $requestLayout): View
    {
        $this->assertSiteForeman($request->user());

        return view('boiler-chief.request-layouts.fill', [
            'layout' => $requestLayout,
            'users' => User::query()->with('role')->orderBy('surname')->orderBy('name')->limit(500)->get(),
            'applications' => $this->reportEquipmentApplications($request->user()),
            'warehouseBalances' => $this->reportWarehouseBalances($request->user()),
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
     * @return array{users: \Illuminate\Database\Eloquent\Collection<int, User>, departments: \Illuminate\Database\Eloquent\Collection<int, Department>, roles: \Illuminate\Database\Eloquent\Collection<int, Role>}
     */
    private function layoutFormContext(Request $request): array
    {
        $this->syncDepartmentsFromSubdivisionsIfNeeded();

        return [
            'users' => User::query()->orderBy('surname')->orderBy('name')->limit(500)->get(),
            'departments' => Department::query()->orderBy('name')->get(),
            'roles' => Role::query()->orderBy('name')->get(),
            'documentHeaderLayouts' => DocumentHeaderLayout::query()
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
        if (! $user || ! $user->hasRoleId(7)) {
            abort(403);
        }
    }

    private function assertSiteForeman(?User $user): void
    {
        if (! $user || ! $user->hasAnyRoleId([1, 2, 3, 4, 6, 7])) {
            abort(403, 'Доступ разрешён только мастеру участка, начальнику котельной, директору, техническому директору, начальнику отдела снабжения или бухгалтеру.');
        }
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
            $subdivisionIds = $user->assignedSubdivisions()->pluck('subdivisions.id');
            $query->whereIn('subdivision_id', $subdivisionIds);
        } elseif ($user->hasRoleId(7)) {
            $subdivisionIds = $user->boilerChiefSubdivisions()->pluck('subdivisions.id');
            $query->whereIn('subdivision_id', $subdivisionIds);
        } elseif ($user->hasRoleId(3)) {
            // Бухгалтер может использовать оборудование из всех заявок.
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
        } elseif ($user->hasRoleId(3)) {
            // Бухгалтер видит все склады и остатки.
        } else {
            return collect();
        }

        return $rows->get()
            ->groupBy('warehouse_id')
            ->map(function ($group) {
                $first = $group->first();
                $equipment = $group->map(function ($row) {
                    $quantity = number_format((float) ($row->balance ?? 0), 3, '.', ' ');
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
