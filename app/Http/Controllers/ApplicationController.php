<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\MaterialStockMovement;
use App\Models\ApplicationStatus;
use App\Models\Equipment;
use App\Models\MeasurementUnit;
use App\Models\Subdivision;
use App\Models\TransportOption;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ApplicationChangeRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isSiteForeman = $user?->hasRoleId(4) ?? false;
        $search = trim((string) $request->input('q', ''));
        $allowedPerPage = [10, 15, 20, 25, 30, 35, 40, 45, 50];
        $perPage = (int) $request->integer('per_page', 20);
        if (! in_array($perPage, $allowedPerPage, true)) {
            $perPage = 20;
        }
        $equipmentFilter = (string) $request->input('equipment_filter', 'all');
        $allowedFilters = ['all', 'has_approved', 'has_not_approved', 'fully_approved', 'on_approval'];
        if (! in_array($equipmentFilter, $allowedFilters, true)) {
            $equipmentFilter = 'all';
        }

        $foremen = User::query()
            ->where('role_id', 4)
            ->orderBy('surname')
            ->orderBy('name')
            ->get(['id', 'surname', 'name', 'patronymic']);
        $selectedForemanId = null;
        if ($isSiteForeman && $user) {
            $selectedForemanId = (int) $user->id;
        } else {
            $candidateForemanId = (int) $request->integer('foreman_user_id');
            if ($candidateForemanId > 0 && $foremen->contains('id', $candidateForemanId)) {
                $selectedForemanId = $candidateForemanId;
            }
        }

        $applicationsQuery = Application::listingQuery($request);
        if ($selectedForemanId !== null) {
            $applicationsQuery->where('user_id', $selectedForemanId);
        }

        $sortState = $this->resolveIndexSortState($request);
        $this->applyIndexSorting($applicationsQuery, $sortState);

        $applications = $applicationsQuery
            ->with(['subdivision', 'responsibleUser', 'items.equipment', 'user', 'approvedBy', 'sourceApplication', 'transportOption', 'applicationStatus'])
            ->paginate($perPage)
            ->withQueryString();

        return view('applications.index', compact('applications', 'search', 'equipmentFilter', 'isSiteForeman', 'foremen', 'selectedForemanId', 'perPage', 'sortState'));
    }

    public function create(Request $request): View
    {
        $this->authorizeCanCreateApplications($request);

        $subdivisions = $this->availableSubdivisionsForCreate($request);
        $equipment = Equipment::orderBy('name')->get();
        $users = User::query()
            ->where('role_id', 4)
            ->orderBy('surname')
            ->orderBy('name')
            ->get();
        $prefill = null;
        $transportOptions = TransportOption::query()
            ->orderBy('name')
            ->get();

        $warehousesBySubdivision = $this->warehousesBySubdivisionForUi();
        $subdivisionIdsByForeman = $this->subdivisionIdsByForemanForUi();
        $measurementMeta = $this->measurementMetaForUi();

        return view('applications.create', compact('subdivisions', 'equipment', 'users', 'prefill', 'transportOptions', 'warehousesBySubdivision', 'subdivisionIdsByForeman', 'measurementMeta'));
    }

    public function repeat(Request $request, Application $application): View
    {
        $this->authorizeCanRepeatApplications($request);

        $application->load(['items']);
        $subdivisions = $this->availableSubdivisionsForCreate($request);
        $equipment = Equipment::orderBy('name')->get();
        $users = User::query()
            ->where('role_id', 4)
            ->orderBy('surname')
            ->orderBy('name')
            ->get();
        $prefill = [
            'source_application_id' => $application->id,
            'subdivision_id' => $subdivisions->contains('id', $application->subdivision_id) ? $application->subdivision_id : null,
            'responsible_user_id' => $application->responsible_user_id,
            'transport_option_id' => $application->transport_option_id,
            'desired_delivery_date' => now()->toDateString(),
            'items' => $application->items->map(fn (ApplicationItem $item): array => [
                'equipment_id' => $item->equipment_id ?? '',
                'equipment_name' => $item->equipment_name ?? '',
                'quantity' => $item->quantity,
                'size_value' => $item->size_value ?? '',
                'measurement_type' => $item->measurement_type ?? 'piece',
                'quantity_unit' => $item->quantity_unit ?? 'шт',
            ])->all(),
        ];
        $transportOptions = TransportOption::query()
            ->orderBy('name')
            ->get();

        $warehousesBySubdivision = $this->warehousesBySubdivisionForUi();
        $subdivisionIdsByForeman = $this->subdivisionIdsByForemanForUi();
        $measurementMeta = $this->measurementMetaForUi();

        return view('applications.create', compact('subdivisions', 'equipment', 'users', 'prefill', 'transportOptions', 'warehousesBySubdivision', 'subdivisionIdsByForeman', 'measurementMeta'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeCanCreateApplications($request);

        $isSiteForeman = $request->user()->hasRoleId(4);
        $allowedSubdivisionIds = $this->availableSubdivisionsForCreate($request)->pluck('id')->map(fn ($id) => (int) $id);

        $validated = $request->validate([
            'subdivision_id' => ['required', 'exists:subdivisions,id'],
            'source_application_id' => ['nullable', 'exists:applications,id'],
            'responsible_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role_id', 4),
            ],
            'desired_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.equipment_id' => ['nullable', 'exists:equipment,id'],
            'items.*.equipment_name' => ['nullable', 'string', 'max:255'],
            'items.*.size_value' => ['nullable', 'string', 'max:120'],
            'items.*.measurement_type' => ['nullable', Rule::in(array_keys($this->measurementUnitsMap()))],
            'items.*.quantity_unit' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'transport_option_id' => ['nullable', 'exists:transport_options,id'],
            'commercial_offer' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ], [
            'desired_delivery_date.after_or_equal' => 'Желаемая дата поставки не может быть в прошлом.',
            'items.min' => 'Добавьте хотя бы одну позицию оборудования.',
            'commercial_offer.mimes' => 'Поддерживаемые форматы: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG.',
            'commercial_offer.max' => 'Максимальный размер файла: 10 МБ.',
        ]);

        if (! $allowedSubdivisionIds->contains((int) $validated['subdivision_id'])) {
            throw ValidationException::withMessages([
                'subdivision_id' => 'Вы не можете создать заявку для этого подразделения.',
            ]);
        }
        $this->validateSubdivisionAllowedForResponsibleUser(
            (int) $validated['subdivision_id'],
            isset($validated['responsible_user_id']) ? (int) $validated['responsible_user_id'] : null
        );

        $equipmentCatalogNames = Equipment::query()
            ->pluck('name')
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->filter()
            ->flip();
        foreach ($validated['items'] as $index => $item) {
            $typeId = $item['equipment_id'] ?? null;
            $name = trim((string) ($item['equipment_name'] ?? ''));
            if (! empty($typeId) || $name === '') {
                continue;
            }
            if ($equipmentCatalogNames->has(mb_strtolower($name))) {
                throw ValidationException::withMessages([
                    "items.{$index}.equipment_name" => 'Такое оборудование уже есть в списке. ',
                ]);
            }
        }
        $this->validateMeasurementPairs($validated['items']);

        $hasValidItem = collect($validated['items'])->contains(fn (array $item) => ! empty($item['equipment_id'] ?? null) || ! empty(trim($item['equipment_name'] ?? ''))
        );
        $hasCommercialOffer = $request->hasFile('commercial_offer');
        if (! $hasValidItem && ! $hasCommercialOffer) {
            return back()->withErrors(['equipment' => 'Укажите оборудование: выберите из списка или введите вручную.'])->withInput();
        }

        $validated['user_id'] = $request->user()->id;
        if ($isSiteForeman) {
            $validated['responsible_user_id'] = $request->user()->id;
        } elseif (empty($validated['responsible_user_id'])) {
            $validated['responsible_user_id'] = $request->user()->id;
        }
        $commercialOfferPath = null;
        if ($request->hasFile('commercial_offer')) {
            $file = $request->file('commercial_offer');
            $storageDisk = 'public';
            $storageDir = 'commercial-offers';

            // Явно создаем отдельную папку для коммерческих предложений.
            Storage::disk($storageDisk)->makeDirectory($storageDir);

            $commercialOfferPath = $file->store($storageDir, $storageDisk);
        }

        $application = Application::create([
            'subdivision_id' => $validated['subdivision_id'],
            'source_application_id' => $validated['source_application_id'] ?? null,
            'responsible_user_id' => $validated['responsible_user_id'],
            'transport_option_id' => $validated['transport_option_id'] ?? null,
            'desired_delivery_date' => $validated['desired_delivery_date'],
            'user_id' => $validated['user_id'],
            'commercial_offer_path' => $commercialOfferPath,
            'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::CODE_PENDING),
        ]);

        foreach ($validated['items'] as $item) {
            $typeId = $item['equipment_id'] ?? null;
            $name = trim($item['equipment_name'] ?? '');
            if (empty($typeId) && $name === '') {
                continue;
            }
            $normalized = $this->normalizeItemPayload($item, $typeId ? Equipment::query()->find((int) $typeId)?->name : null);
            $application->items()->create([
                'equipment_id' => $typeId ?: null,
                'equipment_name' => $typeId ? null : $normalized['equipment_name'],
                'base_name' => $normalized['base_name'],
                'size_value' => $normalized['size_value'],
                'quantity' => $normalized['quantity'],
                'measurement_type' => $normalized['measurement_type'],
                'quantity_unit' => $normalized['quantity_unit'],
                'raw_input' => $normalized['raw_input'],
                'is_checked' => false,
                'reason_not_selected' => null,
            ]);
        }

        return redirect()->route('applications.index')
            ->with('status', 'Заявка успешно создана.');
    }

    public function show(Application $application): View
    {
        $application->load([
            'subdivision.warehouses',
            'responsibleUser',
            'user',
            'approvedBy',
            'items.equipment',
            'sourceApplication',
            'transportOption',
            'applicationStatus',
            'latestEditHistory.user.role',
        ]);

        $mainWarehouse = $this->resolveMainWarehouseForAccounting();
        $issuedByItemId = [];
        $remainingByItemId = [];

        foreach ($application->items as $item) {
            if (! $item->is_checked) {
                continue;
            }

            $issued = $this->issuedQuantityForApplicationItem($application->id, (int) $item->id);
            $issuedByItemId[(int) $item->id] = $issued;
            $remainingByItemId[(int) $item->id] = max(0.0, (float) $item->quantity - $issued);
        }

        return view('applications.show', compact('application', 'mainWarehouse', 'issuedByItemId', 'remainingByItemId'));
    }

    public function issueStock(Request $request, Application $application): RedirectResponse
    {
        if (! $request->user()?->hasAnyRoleId([1, 2])) {
            abort(403, 'Списание со склада по заявке доступно только директору и начальнику отдела снабжения.');
        }

        $application->load('items');
        $mainWarehouse = $this->resolveMainWarehouseForAccounting();
        if (! $mainWarehouse) {
            throw ValidationException::withMessages([
                'stock' => 'Не найден основной склад "Администрация". Назначьте склад основным.',
            ]);
        }

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $rows = collect($validated['items'] ?? [])
            ->map(fn ($row, $key) => [
                'item_id' => (int) $key,
                'quantity' => isset($row['quantity']) ? (float) $row['quantity'] : 0.0,
            ])
            ->filter(fn (array $row) => $row['quantity'] > 0);

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'stock' => 'Укажите хотя бы одну позицию для списания.',
            ]);
        }

        DB::transaction(function () use ($rows, $application, $mainWarehouse, $request, $validated) {
            foreach ($rows as $row) {
                $item = $application->items->firstWhere('id', $row['item_id']);
                if (! $item) {
                    throw ValidationException::withMessages([
                        'stock' => 'Обнаружена некорректная позиция заявки.',
                    ]);
                }

                if (! $item->is_checked) {
                    throw ValidationException::withMessages([
                        'stock' => 'Можно списывать только согласованные позиции.',
                    ]);
                }

                if (! $item->equipment_id) {
                    throw ValidationException::withMessages([
                        'stock' => 'Для списания позиция должна быть из справочника оборудования.',
                    ]);
                }

                $alreadyIssued = $this->issuedQuantityForApplicationItem($application->id, (int) $item->id);
                $remaining = max(0.0, (float) $item->quantity - $alreadyIssued);
                if ($row['quantity'] > $remaining + 0.0005) {
                    throw ValidationException::withMessages([
                        'stock' => 'Нельзя списать больше остатка по позиции заявки.',
                    ]);
                }

                $warehouseBalance = $this->warehouseEquipmentBalance((int) $item->equipment_id, (int) $mainWarehouse->id);
                if ($warehouseBalance < $row['quantity'] - 0.0005) {
                    throw ValidationException::withMessages([
                        'stock' => 'Недостаточно остатка на складе "Администрация" для одной из позиций.',
                    ]);
                }

                MaterialStockMovement::query()->create([
                    'equipment_id' => (int) $item->equipment_id,
                    'warehouse_id' => (int) $mainWarehouse->id,
                    'type' => 'issue',
                    'quantity' => $row['quantity'],
                    'unit_price' => null,
                    'happened_at' => now(),
                    'document_ref' => $this->issueDocumentRef($application->id, (int) $item->id),
                    'counterparty' => 'Заявка №'.$application->id.' / '.$application->subdivision?->name,
                    'comment' => trim((string) ($validated['comment'] ?? '')) ?: null,
                    'created_by_user_id' => $request->user()->id,
                ]);
            }
        });

        return redirect()
            ->route('applications.show', $application)
            ->with('status', 'Списание оборудования по заявке сохранено.');
    }

    public function viewCommercialOffer(Application $application): BinaryFileResponse
    {
        $path = $this->resolveCommercialOfferPath($application);
        if (! $path) {
            abort(404, 'Файл коммерческого предложения не найден.');
        }

        return response()->file($path);
    }

    public function downloadCommercialOffer(Application $application): BinaryFileResponse
    {
        $path = $this->resolveCommercialOfferPath($application);
        if (! $path) {
            abort(404, 'Файл коммерческого предложения не найден.');
        }

        $name = basename($path);

        return response()->download($path, $name);
    }

    public function edit(Request $request, Application $application): View
    {
        $this->authorizeCanEditApplications($request);

        $subdivisions = Subdivision::orderBy('name')->get();
        $equipment = Equipment::orderBy('name')->get();
        $users = User::query()
            ->where('role_id', 4)
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        $transportOptions = TransportOption::query()
            ->orderBy('name')
            ->get();

        $application->load(['items.equipment', 'applicationStatus']);

        $warehousesBySubdivision = $this->warehousesBySubdivisionForUi();
        $subdivisionIdsByForeman = $this->subdivisionIdsByForemanForUi();
        $measurementMeta = $this->measurementMetaForUi();

        return view('applications.edit', compact('application', 'subdivisions', 'equipment', 'users', 'transportOptions', 'warehousesBySubdivision', 'subdivisionIdsByForeman', 'measurementMeta'));
    }

    public function update(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeCanEditApplications($request);

        $isSiteForeman = $request->user()->hasRoleId(4);
        $allowedSubdivisionIds = $this->availableSubdivisionsForCreate($request)->pluck('id')->map(fn ($id) => (int) $id);
        $application->load(['items.equipment', 'applicationStatus']);

        $shouldRecordManagementEdit = $request->user()->hasAnyRoleId($this->managementEditorRoleIds());
        $snapshotBefore = $shouldRecordManagementEdit ? ApplicationChangeRecorder::snapshot($application) : null;

        $validated = $request->validate([
            'subdivision_id' => ['required', 'exists:subdivisions,id'],
            'responsible_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role_id', 4),
            ],
            'management_change_reason' => ['nullable', 'string', 'max:500'],
            'desired_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => [
                'nullable',
                'integer',
                Rule::exists('application_items', 'id')->where('application_id', $application->id),
            ],
            'items.*.equipment_id' => ['nullable', 'exists:equipment,id'],
            'items.*.equipment_name' => ['nullable', 'string', 'max:255'],
            'items.*.size_value' => ['nullable', 'string', 'max:120'],
            'items.*.measurement_type' => ['nullable', Rule::in(array_keys($this->measurementUnitsMap()))],
            'items.*.quantity_unit' => ['nullable', 'string', 'max:20'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'transport_option_id' => ['nullable', 'exists:transport_options,id'],
        ], [
            'desired_delivery_date.after_or_equal' => 'Желаемая дата поставки не может быть в прошлом.',
            'items.min' => 'Добавьте хотя бы одну позицию оборудования.',
        ]);

        if ($isSiteForeman && ! $allowedSubdivisionIds->contains((int) $validated['subdivision_id'])) {
            throw ValidationException::withMessages([
                'subdivision_id' => 'Вы не можете изменить заявку для этого подразделения.',
            ]);
        }

        $this->validateSubdivisionAllowedForResponsibleUser(
            (int) $validated['subdivision_id'],
            isset($validated['responsible_user_id']) ? (int) $validated['responsible_user_id'] : null
        );

        $equipmentCatalogNames = Equipment::query()
            ->pluck('name')
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->filter()
            ->flip();
        foreach ($validated['items'] as $index => $row) {
            $typeId = $row['equipment_id'] ?? null;
            $name = trim((string) ($row['equipment_name'] ?? ''));
            if (! empty($typeId) || $name === '') {
                continue;
            }
            if ($equipmentCatalogNames->has(mb_strtolower($name))) {
                throw ValidationException::withMessages([
                    "items.{$index}.equipment_name" => 'Такое оборудование уже есть в списке.',
                ]);
            }
        }
        $this->validateMeasurementPairs($validated['items']);

        $itemIdsInRequest = collect($validated['items'])->pluck('item_id')->filter()->map(fn ($id) => (int) $id);
        if ($itemIdsInRequest->count() !== $itemIdsInRequest->unique()->count()) {
            throw ValidationException::withMessages([
                'equipment' => 'Дублирование позиций в форме.',
            ]);
        }

        $seenUnapprovedIds = [];
        $toCreate = [];

        foreach ($validated['items'] as $index => $row) {
            $itemId = isset($row['item_id']) ? (int) $row['item_id'] : null;
            $typeId = $row['equipment_id'] ?? null;
            $typeId = $typeId !== null && $typeId !== '' ? (int) $typeId : null;
            $name = trim((string) ($row['equipment_name'] ?? ''));
            $qty = (int) ($row['quantity'] ?? 1);

            if ($itemId) {
                $existing = $application->items->firstWhere('id', $itemId);
                if (! $existing) {
                    throw ValidationException::withMessages([
                        'equipment' => 'Некорректная позиция заявки.',
                    ]);
                }

                if ($existing->is_checked) {
                    $existingTypeId = $existing->equipment_id !== null ? (int) $existing->equipment_id : null;
                    if (
                        $typeId !== $existingTypeId
                        || $name !== trim((string) ($existing->equipment_name ?? ''))
                        || $qty !== (int) $existing->quantity
                    ) {
                        throw ValidationException::withMessages([
                            'equipment' => 'Согласованное оборудование нельзя изменять.',
                        ]);
                    }

                    continue;
                }

                if ($typeId === null && $name === '') {
                    throw ValidationException::withMessages([
                        "items.{$index}.equipment_id" => 'Укажите оборудование или удалите строку.',
                    ]);
                }

                $seenUnapprovedIds[] = $itemId;

                continue;
            }

            if ($typeId === null && $name === '') {
                continue;
            }

            $toCreate[] = [
                'equipment_id' => $typeId,
                'equipment_name' => $typeId ? null : $name,
                'quantity' => $qty,
            ];
        }

        $approvedCount = $application->items->where('is_checked', true)->count();
        $linesWithEquipment = $approvedCount + count($seenUnapprovedIds) + count($toCreate);
        if ($linesWithEquipment < 1) {
            return back()->withErrors(['equipment' => 'Укажите оборудование: выберите из списка или введите вручную.'])->withInput();
        }

        $submittedItemIds = $itemIdsInRequest->values()->all();

        DB::transaction(function () use ($application, $validated, $toCreate, $request, $isSiteForeman, $submittedItemIds) {
            $responsibleUserId = $validated['responsible_user_id'] ?? null;
            if ($isSiteForeman) {
                $responsibleUserId = $request->user()->id;
            }
            $existingApprovedByUserId = $application->approved_by_user_id;

            $application->update([
                'subdivision_id' => $validated['subdivision_id'],
                'responsible_user_id' => $responsibleUserId,
                'transport_option_id' => $validated['transport_option_id'] ?? null,
                'desired_delivery_date' => $validated['desired_delivery_date'],
            ]);

            $application->items()
                ->where('is_checked', false)
                ->whereNotIn('id', $submittedItemIds)
                ->delete();

            foreach ($validated['items'] as $row) {
                $itemId = isset($row['item_id']) ? (int) $row['item_id'] : null;
                if (! $itemId) {
                    continue;
                }

                $existing = $application->items()->where('id', $itemId)->first();
                if (! $existing || $existing->is_checked) {
                    continue;
                }

                $typeId = $row['equipment_id'] ?? null;
                $typeId = $typeId !== null && $typeId !== '' ? (int) $typeId : null;
                $name = trim((string) ($row['equipment_name'] ?? ''));
                $normalized = $this->normalizeItemPayload($row, $typeId ? Equipment::query()->find($typeId)?->name : null);

                $existing->update([
                    'equipment_id' => $typeId ?: null,
                    'equipment_name' => $typeId ? null : $normalized['equipment_name'],
                    'base_name' => $normalized['base_name'],
                    'size_value' => $normalized['size_value'],
                    'quantity' => $normalized['quantity'],
                    'measurement_type' => $normalized['measurement_type'],
                    'quantity_unit' => $normalized['quantity_unit'],
                    'raw_input' => $normalized['raw_input'],
                ]);
            }

            foreach ($toCreate as $payload) {
                $normalized = $this->normalizeItemPayload($payload, $payload['equipment_id'] ? Equipment::query()->find((int) $payload['equipment_id'])?->name : null);
                $application->items()->create([
                    'equipment_id' => $payload['equipment_id'] ?: null,
                    'equipment_name' => $payload['equipment_id'] ? null : $normalized['equipment_name'],
                    'base_name' => $normalized['base_name'],
                    'size_value' => $normalized['size_value'],
                    'quantity' => $normalized['quantity'],
                    'measurement_type' => $normalized['measurement_type'],
                    'quantity_unit' => $normalized['quantity_unit'],
                    'raw_input' => $normalized['raw_input'],
                    'is_checked' => false,
                    'reason_not_selected' => null,
                ]);
            }

            $application->refresh();
            $application->load('items');
            $approvalPayload = Application::aggregateApprovalPayloadFromItems($application->items);
            $approvedStatusId = ApplicationStatus::idFor(ApplicationStatus::CODE_APPROVED);

            $application->update([
                'application_status_id' => $approvalPayload['application_status_id'],
                'approval_rejection_reason' => $approvalPayload['approval_rejection_reason'],
                'approved_by_user_id' => $approvalPayload['application_status_id'] === $approvedStatusId
                    ? $existingApprovedByUserId
                    : null,
            ]);
        });

        if ($shouldRecordManagementEdit && $snapshotBefore !== null) {
            $application->refresh();
            $application->load(['subdivision', 'responsibleUser', 'transportOption', 'items.equipment']);
            $equipmentLines = ApplicationChangeRecorder::equipmentDiff($snapshotBefore, $application);
            $equipmentChange = $equipmentLines === [] ? '' : implode("\n", $equipmentLines);
            $changeReason = trim((string) ($validated['management_change_reason'] ?? ''));
            if ($equipmentChange !== '' || $changeReason !== '') {
                DB::transaction(function () use ($application, $request, $equipmentChange, $changeReason) {
                    $application->editHistories()->create([
                        'user_id' => $request->user()->id,
                        'edited_at' => now(),
                        'equipment_change' => $equipmentChange !== '' ? $equipmentChange : null,
                        'change_reason' => $changeReason !== '' ? $changeReason : null,
                    ]);
                });
            }
        }

        return redirect()->to(route('applications.show', $application).'#approval-form')
            ->with('status', 'Заявка успешно обновлена.');
    }

    public function saveApproval(Request $request, Application $application): RedirectResponse
    {
        if (! $request->user()->hasAnyRoleId($this->managementEditorRoleIds())) {
            abort(403, 'Согласование доступно только директору, техническому директору и начальнику отдела снабжения.');
        }

        $application->load('items');

        if ($application->items->isEmpty()) {
            return redirect()->route('applications.show', $application)
                ->with('status', 'Нет позиций для согласования.');
        }

        $itemsInput = $request->input('items', []);
        $errors = [];

        foreach ($application->items as $item) {
            $row = $itemsInput[(string) $item->id] ?? $itemsInput[$item->id] ?? null;
            if (! is_array($row)) {
                $errors["items.{$item->id}.is_checked"] = 'Отсутствуют данные по позиции.';

                continue;
            }
            $checkedRaw = $row['is_checked'] ?? '0';
            $isChecked = $checkedRaw === '1' || $checkedRaw === 1 || $checkedRaw === true;
            if (! $isChecked) {
                $reason = trim((string) ($row['reason_not_selected'] ?? ''));
                if ($reason === '') {
                    $errors["items.{$item->id}.reason_not_selected"] = 'Укажите причину не согласования.';
                } elseif (mb_strlen($reason) > 500) {
                    $errors["items.{$item->id}.reason_not_selected"] = 'Причина не может быть длиннее 500 символов.';
                }
            }
        }

        if ($errors !== []) {
            return redirect()->route('applications.show', $application)
                ->withErrors($errors)
                ->withInput();
        }

        DB::transaction(function () use ($application, $itemsInput, $request) {
            foreach ($application->items as $item) {
                $row = $itemsInput[(string) $item->id] ?? $itemsInput[$item->id];
                $checkedRaw = $row['is_checked'] ?? '0';
                $isChecked = $checkedRaw === '1' || $checkedRaw === 1 || $checkedRaw === true;
                $item->update([
                    'is_checked' => $isChecked,
                    'reason_not_selected' => $isChecked ? null : trim((string) ($row['reason_not_selected'] ?? '')),
                ]);
            }

            $application->refresh();
            $application->load('items');
            $payload = Application::aggregateApprovalPayloadFromItems($application->items);
            $application->update([
                'application_status_id' => $payload['application_status_id'],
                'approval_rejection_reason' => $payload['approval_rejection_reason'],
                'approved_by_user_id' => $request->user()->id,
            ]);
        });

        return redirect()->route('applications.show', $application)
            ->with('status', 'Согласование по позициям сохранено.');
    }

    private function authorizeCanCreateApplications(Request $request): void
    {
        $allowed = $request->user() && $request->user()->hasAnyRoleId($this->createEditApplicationRoleIds());

        if (! $allowed) {
            abort(403, 'Создание заявок разрешено только директору, техническому директору, начальнику отдела снабжения и мастеру участка.');
        }
    }

    private function authorizeCanEditApplications(Request $request): void
    {
        $allowed = $request->user() && $request->user()->hasAnyRoleId($this->createEditApplicationRoleIds());

        if (! $allowed) {
            abort(403, 'Редактирование заявок разрешено директору, техническому директору, начальнику отдела снабжения и мастеру участка.');
        }
    }

    private function authorizeCanRepeatApplications(Request $request): void
    {
        if (! $request->user() || ! $request->user()->hasRoleId(4)) {
            abort(403, 'Создание повторной заявки разрешено только мастеру участка.');
        }
    }

    private function availableSubdivisionsForCreate(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return Subdivision::query()->whereRaw('1 = 0')->get();
        }

        if ($user->hasRoleId(4)) {
            return $user->assignedSubdivisions()->orderBy('name')->get();
        }

        return Subdivision::query()->orderBy('name')->get();
    }

    private function resolveCommercialOfferPath(Application $application): ?string
    {
        $relativePath = trim((string) ($application->commercial_offer_path ?? ''));
        if ($relativePath === '') {
            return null;
        }

        if (Storage::disk('public')->exists($relativePath)) {
            return Storage::disk('public')->path($relativePath);
        }

        if (Storage::exists($relativePath)) {
            return Storage::path($relativePath);
        }

        return null;
    }

    /**
     * Склады по подразделению (для подсказки в формах): строки «Нет» из справочника привязаны к «Да» через warehouses.subdivision_id.
     *
     * @return array<string, list<array{code: string, name: string}>>
     */
    private function warehousesBySubdivisionForUi(): array
    {
        return Warehouse::query()
            ->whereNotNull('subdivision_id')
            ->orderBy('name')
            ->get(['subdivision_id', 'code', 'name'])
            ->groupBy(fn (Warehouse $w): string => (string) $w->subdivision_id)
            ->map(fn ($group) => $group->map(fn (Warehouse $w): array => [
                'code' => $w->code,
                'name' => $w->name,
            ])->values()->all())
            ->all();
    }

    /**
     * Привязки «мастер участка -> подразделения» для UI-фильтра подразделений.
     *
     * @return array<string, list<string>>
     */
    private function subdivisionIdsByForemanForUi(): array
    {
        $map = [];
        $foremen = User::query()
            ->where('role_id', 4)
            ->with(['assignedSubdivisions:id'])
            ->get(['id']);

        foreach ($foremen as $foreman) {
            $map[(string) $foreman->id] = $foreman->assignedSubdivisions
                ->pluck('id')
                ->map(fn ($id): string => (string) $id)
                ->values()
                ->all();
        }

        return $map;
    }

    /**
     * @return list<int>
     */
    private function managementEditorRoleIds(): array
    {
        return [1, 6, 2];
    }

    /**
     * @return list<int>
     */
    private function createEditApplicationRoleIds(): array
    {
        return [1, 6, 2, 4];
    }

    private function validateSubdivisionAllowedForResponsibleUser(int $subdivisionId, ?int $responsibleUserId): void
    {
        if (! $responsibleUserId) {
            return;
        }

        $isAssigned = DB::table('foreman_subdivision_user')
            ->where('foreman_user_id', $responsibleUserId)
            ->where('subdivision_id', $subdivisionId)
            ->exists();

        if (! $isAssigned) {
            throw ValidationException::withMessages([
                'subdivision_id' => 'Выбранное подразделение не назначено выбранному мастеру участка.',
            ]);
        }
    }

    private function resolveMainWarehouseForAccounting(): ?Warehouse
    {
        $primary = Warehouse::query()
            ->where('is_primary', true)
            ->orderBy('id')
            ->first();

        if ($primary) {
            return $primary;
        }

        return Warehouse::query()
            ->whereRaw('LOWER(name) like ?', ['%администрац%'])
            ->orderBy('id')
            ->first();
    }

    private function issueDocumentRef(int $applicationId, int $itemId): string
    {
        return 'APP:'.$applicationId.':ITEM:'.$itemId;
    }

    private function issuedQuantityForApplicationItem(int $applicationId, int $itemId): float
    {
        $sum = MaterialStockMovement::query()
            ->where('type', 'issue')
            ->where('document_ref', $this->issueDocumentRef($applicationId, $itemId))
            ->sum('quantity');

        return (float) $sum;
    }

    private function warehouseEquipmentBalance(int $equipmentId, int $warehouseId): float
    {
        $sum = MaterialStockMovement::query()
            ->where('equipment_id', $equipmentId)
            ->where('warehouse_id', $warehouseId)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'issue' THEN -quantity ELSE quantity END), 0) as balance")
            ->value('balance');

        return (float) $sum;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{equipment_name:?string,base_name:string,size_value:?string,quantity:int,measurement_type:string,quantity_unit:string,raw_input:?string}
     */
    private function normalizeItemPayload(array $row, ?string $equipmentName = null): array
    {
        $rawName = trim((string) ($row['equipment_name'] ?? ''));
        $rawQty = (int) ($row['quantity'] ?? 1);
        $quantity = max(1, $rawQty);
        $measurementType = (string) ($row['measurement_type'] ?? 'piece');
        if (! array_key_exists($measurementType, $this->measurementUnitsMap())) {
            $measurementType = 'piece';
        }
        $quantityUnit = trim((string) ($row['quantity_unit'] ?? ''));
        $quantityUnit = $quantityUnit !== '' ? mb_substr($quantityUnit, 0, 20) : $this->defaultUnitForType($measurementType);
        $sizeValue = trim((string) ($row['size_value'] ?? ''));
        $sizeValue = $sizeValue !== '' ? mb_substr($sizeValue, 0, 120) : null;
        $baseName = '';
        $rawInput = $rawName !== '' ? $rawName : null;

        if ($equipmentName !== null && trim($equipmentName) !== '') {
            $baseName = trim($equipmentName);

            return [
                'equipment_name' => null,
                'base_name' => $baseName,
                'size_value' => $measurementType === 'clothing_size' ? $sizeValue : null,
                'quantity' => $quantity,
                'measurement_type' => $measurementType,
                'quantity_unit' => $quantityUnit,
                'raw_input' => $rawInput,
            ];
        }

        [$parsedName, $parsedSize, $parsedQty, $parsedUnit] = $this->parseFreeEquipmentText($rawName);
        if ($parsedQty !== null && $quantity === 1) {
            $quantity = max(1, (int) round($parsedQty));
        }
        if ($parsedUnit !== null && trim((string) ($row['quantity_unit'] ?? '')) === '') {
            $quantityUnit = $parsedUnit;
            if (in_array($parsedUnit, $this->measurementUnitsMap()['mass'] ?? [], true)) {
                $measurementType = 'mass';
            } elseif (in_array($parsedUnit, $this->measurementUnitsMap()['length'] ?? [], true)) {
                $measurementType = 'length';
            } else {
                $measurementType = 'piece';
            }
        }

        $baseName = $parsedName !== '' ? $parsedName : $rawName;
        if (($sizeValue === null || $sizeValue === '') && $parsedSize !== '') {
            $sizeValue = $parsedSize;
        }

        if ($measurementType !== 'clothing_size') {
            $sizeValue = null;
        }

        return [
            'equipment_name' => $rawName !== '' ? $rawName : null,
            'base_name' => $baseName !== '' ? $baseName : '—',
            'size_value' => $sizeValue,
            'quantity' => $quantity,
            'measurement_type' => $measurementType,
            'quantity_unit' => $quantityUnit,
            'raw_input' => $rawInput,
        ];
    }

    /**
     * @return array{0:string,1:string,2:?float,3:?string}
     */
    private function parseFreeEquipmentText(string $text): array
    {
        $clean = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        if ($clean === '') {
            return ['', '', null, null];
        }

        $qty = null;
        $unit = null;
        $namePart = $clean;

        if (preg_match('/^(.*)\s+(\d+(?:[.,]\d+)?)\s*(м|метр|метра|метров|шт|штука|штуки|штук|кг|тонна|тонны|тонн|л)\.?$/ui', $clean, $m)) {
            $namePart = trim((string) $m[1]);
            $qty = (float) str_replace(',', '.', (string) $m[2]);
            $rawUnit = mb_strtolower(trim((string) $m[3]));
            $unit = match ($rawUnit) {
                'м', 'метр', 'метра', 'метров' => 'м',
                'шт', 'штука', 'штуки', 'штук' => 'шт',
                'кг' => 'кг',
                'тонна', 'тонны', 'тонн' => 'т',
                'л' => 'л',
                default => 'шт',
            };
        }

        $tokens = preg_split('/\s+/u', $namePart) ?: [];
        if (count($tokens) === 0) {
            return ['', '', $qty, $unit];
        }

        $baseName = (string) array_shift($tokens);
        $size = trim(implode(' ', $tokens));

        return [$baseName, $size, $qty, $unit];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function validateMeasurementPairs(array $items): void
    {
        $map = $this->measurementUnitsMap();
        foreach ($items as $idx => $row) {
            $type = (string) ($row['measurement_type'] ?? 'piece');
            if (! array_key_exists($type, $map)) {
                throw ValidationException::withMessages([
                    "items.{$idx}.measurement_type" => 'Некорректный тип единицы измерения.',
                ]);
            }
            $unit = trim((string) ($row['quantity_unit'] ?? ''));
            if ($unit === '') {
                continue;
            }
            if (! in_array($unit, $map[$type], true)) {
                throw ValidationException::withMessages([
                    "items.{$idx}.quantity_unit" => 'Единица измерения не соответствует выбранному типу.',
                ]);
            }

            if ($type === 'clothing_size') {
                $size = trim((string) ($row['size_value'] ?? ''));
                if ($size === '') {
                    throw ValidationException::withMessages([
                        "items.{$idx}.size_value" => 'Для типа «Размер одежды» укажите размер (например, 48, M, XL).',
                    ]);
                }
            }
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function measurementUnitsMap(): array
    {
        $rows = MeasurementUnit::query()
            ->with('unitType:id,code')
            ->orderBy('unit_type_id')
            ->orderBy('id')
            ->get(['unit_type_id', 'code']);

        $map = [];
        foreach ($rows as $row) {
            $type = (string) ($row->unitType?->code ?? '');
            if ($type === '') {
                continue;
            }
            $code = trim((string) $row->code);
            if ($code === '') {
                continue;
            }
            $map[$type] ??= [];
            $map[$type][] = $code;
        }

        if ($map === []) {
            return [
                'piece' => ['шт'],
                'mass' => ['г', 'кг', 'т'],
                'length' => ['мм', 'см', 'м', 'км'],
                'clothing_size' => ['разм'],
            ];
        }

        return $map;
    }

    /**
     * @return array{typeOptions: array<string, string>, unitsByType: array<string, array<int, string>>}
     */
    private function measurementMetaForUi(): array
    {
        $typeOptions = [];
        $unitsByType = $this->measurementUnitsMap();
        $types = \App\Models\UnitType::query()
            ->orderBy('id')
            ->get(['code', 'name']);

        foreach ($types as $type) {
            $code = (string) $type->code;
            if ($code === '' || ! isset($unitsByType[$code])) {
                continue;
            }
            $typeOptions[$code] = (string) $type->name;
        }

        if ($typeOptions === []) {
            $typeOptions = [
                'piece' => 'Штучные',
                'mass' => 'Масса',
                'length' => 'Длина',
                'clothing_size' => 'Размер одежды',
            ];
        }

        return [
            'typeOptions' => $typeOptions,
            'unitsByType' => $unitsByType,
        ];
    }

    private function defaultUnitForType(string $type): string
    {
        $map = $this->measurementUnitsMap();

        return $map[$type][0] ?? 'шт';
    }

    /**
     * @return array{primary_field:string,primary_direction:string,secondary_field:?string,secondary_direction:string}
     */
    private function resolveIndexSortState(Request $request): array
    {
        $allowedFields = $this->indexAllowedSortFields();
        $allowedDirections = ['asc', 'desc'];

        $primaryField = (string) $request->input('sort_primary_field', 'created_at');
        if (! array_key_exists($primaryField, $allowedFields)) {
            $primaryField = 'created_at';
        }

        $primaryDirection = strtolower((string) $request->input('sort_primary_direction', 'desc'));
        if (! in_array($primaryDirection, $allowedDirections, true)) {
            $primaryDirection = 'desc';
        }

        $secondaryField = trim((string) $request->input('sort_secondary_field', ''));
        $secondaryField = $secondaryField !== '' && array_key_exists($secondaryField, $allowedFields)
            ? $secondaryField
            : null;
        if ($secondaryField === $primaryField) {
            $secondaryField = null;
        }

        $secondaryDirection = strtolower((string) $request->input('sort_secondary_direction', 'asc'));
        if (! in_array($secondaryDirection, $allowedDirections, true)) {
            $secondaryDirection = 'asc';
        }

        return [
            'primary_field' => $primaryField,
            'primary_direction' => $primaryDirection,
            'secondary_field' => $secondaryField,
            'secondary_direction' => $secondaryDirection,
        ];
    }

    /**
     * @param array{primary_field:string,primary_direction:string,secondary_field:?string,secondary_direction:string} $sortState
     */
    private function applyIndexSorting($applicationsQuery, array $sortState): void
    {
        $allowedFields = $this->indexAllowedSortFields();
        $applied = [];

        $primaryField = $sortState['primary_field'];
        $applicationsQuery->orderBy($allowedFields[$primaryField], $sortState['primary_direction']);
        $applied[] = $primaryField;

        if ($sortState['secondary_field'] !== null) {
            $secondaryField = $sortState['secondary_field'];
            if (! in_array($secondaryField, $applied, true)) {
                $applicationsQuery->orderBy($allowedFields[$secondaryField], $sortState['secondary_direction']);
                $applied[] = $secondaryField;
            }
        }

        foreach (['created_at'] as $fallbackField) {
            if (! in_array($fallbackField, $applied, true)) {
                $applicationsQuery->orderBy($allowedFields[$fallbackField], 'desc');
            }
        }

        $applicationsQuery->orderBy('id', 'desc');
    }

    /**
     * @return array<string, string>
     */
    private function indexAllowedSortFields(): array
    {
        return [
            'created_at' => 'created_at',
            'desired_delivery_date' => 'desired_delivery_date',
            'subdivision' => 'subdivision_id',
            'responsible' => 'responsible_user_id',
            'author' => 'user_id',
            'approved_by' => 'approved_by_user_id',
            'status' => 'application_status_id',
        ];
    }
}
