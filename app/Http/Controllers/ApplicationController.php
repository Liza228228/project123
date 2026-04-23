<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationInstallationActPhoto;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\CompanyDeliveryVehicle;
use App\Models\Equipment;
use App\Models\MaterialStockMovement;
use App\Models\MeasurementUnit;
use App\Models\Subdivision;
use App\Models\TransportOption;
use App\Models\UnitType;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ApplicationController extends Controller
{
    private const BOILER_CHIEF_ROLE_ID = 7;

    private const ACCOUNTANT_ROLE_ID = 3;


    public function index(Request $request): View
    {
        $user = $request->user();
        $this->syncCompletionArchiveForEligibleApplications();
        $isSiteForeman = $user?->hasRoleId(4) ?? false;
        $isBoilerChief = $user?->hasRoleId(self::BOILER_CHIEF_ROLE_ID) ?? false;
        $search = trim((string) $request->input('q', ''));
        $allowedPerPage = [10, 15, 20, 25, 30, 35, 40, 45, 50];
        $perPage = (int) $request->integer('per_page', 20);
        if (! in_array($perPage, $allowedPerPage, true)) {
            $perPage = 20;
        }
        $equipmentFilter = (string) $request->input('equipment_filter', 'all');
        $allowedFilters = ['all', 'has_approved', 'has_not_approved', 'fully_approved', 'on_approval', 'needs_custom_equipment_order'];
        if (! in_array($equipmentFilter, $allowedFilters, true)) {
            $equipmentFilter = 'all';
        }

        $foremen = User::query()
            ->where('role_id', 4)
            ->orderBy('surname')
            ->orderBy('name')
            ->get(['id', 'surname', 'name', 'patronymic']);
        $selectedForemanId = null;
        if (! $isSiteForeman && ! $isBoilerChief) {
            $candidateForemanId = (int) $request->integer('foreman_user_id');
            if ($candidateForemanId > 0 && $foremen->contains('id', $candidateForemanId)) {
                $selectedForemanId = $candidateForemanId;
            }
        }

        $archiveFilter = Application::archiveFilterFromRequest($request);

        $applicationsQuery = Application::listingQuery($request);

        if ($user?->hasRoleId(self::ACCOUNTANT_ROLE_ID)) {
            $applicationsQuery->withCount('installationActPhotos');
        }

        if ($user?->hasAnyRoleId([1, 2, 6, 3])) {
            $applicationsQuery->where(function ($outer) {
                $outer->whereDoesntHave('user', function ($q) {
                    $q->where('role_id', 4);
                })->orWhereNotNull('applications.boiler_chief_stage_completed_at');
            });
        }

        if ($isBoilerChief && $user) {
            $chiefSubIds = $user->boilerChiefSubdivisions()->pluck('subdivisions.id');
            $applicationsQuery->whereIn('subdivision_id', $chiefSubIds);
        }

        if ($isSiteForeman && $user) {
            $assignedSubdivisionIds = $user->assignedSubdivisions()
                ->pluck('subdivisions.id')
                ->map(fn ($id): int => (int) $id);
            $applicationsQuery->whereIn('subdivision_id', $assignedSubdivisionIds);
        } elseif ($selectedForemanId !== null) {
            $applicationsQuery->where('user_id', $selectedForemanId);
        }

        $sortState = $this->resolveIndexSortState($request);
        $this->applyIndexSorting($applicationsQuery, $sortState);

        $applications = $applicationsQuery
            ->with([
                'subdivision',
                'responsibleUser',
                'items.equipment.measurementUnit.unitType',
                'items.manualDetail',
                'user',
                'approvedBy',
                'sourceApplication',
                'transportOption',
                'applicationStatus',
            ])
            ->paginate($perPage)
            ->withQueryString();

        $customEquipmentPendingOrderCount = 0;
        if ($user?->hasAnyRoleId($this->customEquipmentOrderingRoleIds())) {
            $customEquipmentPendingOrderCount = ApplicationItem::queryPendingCustomEquipmentOrder()->count();
        }

        return view('applications.index', compact('applications', 'search', 'equipmentFilter', 'isSiteForeman', 'isBoilerChief', 'foremen', 'selectedForemanId', 'perPage', 'sortState', 'archiveFilter', 'customEquipmentPendingOrderCount'));
    }

    /**
     * Снабжение / руководство: заявки, по которым есть своё оборудование (ещё не оприходовано на основной склад).
     */
    public function customEquipmentToOrder(Request $request): View
    {
        if (! $request->user()?->hasAnyRoleId($this->customEquipmentOrderingRoleIds())) {
            abort(403, 'Раздел доступен только директору и начальнику отдела снабжения.');
        }

        $sortDate = (string) $request->input('sort_date', 'desc');
        if (! in_array($sortDate, ['desc', 'asc'], true)) {
            $sortDate = 'desc';
        }

        $applicationsQuery = Application::query()
            ->whereNull('archived_at')
            ->whereHas('items', function ($q): void {
                $q->whereNull('equipment_id')
                    ->where('is_checked', true)
                    ->where(function ($w): void {
                        $w->whereNull('custom_equipment_supply_status_id')
                            ->orWhereIn('custom_equipment_supply_status_id', [
                                ApplicationItem::CUSTOM_SUPPLY_ACCEPTED_ID,
                                ApplicationItem::CUSTOM_SUPPLY_ORDERED_ID,
                                ApplicationItem::CUSTOM_SUPPLY_IN_TRANSIT_ID,
                            ]);
                    });
            })
            ->with(['subdivision', 'user']);

        if ($sortDate === 'asc') {
            $applicationsQuery->orderBy('created_at')->orderBy('id');
        } else {
            $applicationsQuery->orderByDesc('created_at')->orderByDesc('id');
        }

        $applications = $applicationsQuery
            ->paginate(30)
            ->withQueryString();

        return view('applications.custom-equipment-to-order', compact('applications', 'sortDate'));
    }

    /**
     * Форма по заявке: что заказать (часть или всё) и отметки «Заказано» / «На складе».
     */
    public function customEquipmentOrderForm(Request $request, Application $application): View
    {
        if (! $request->user()?->hasAnyRoleId($this->customEquipmentOrderingRoleIds())) {
            abort(403, 'Форма доступна только директору и начальнику отдела снабжения.');
        }

        $this->authorizeViewApplication($request, $application);

        if ($application->archived_at !== null) {
            abort(404);
        }

        $application->load(['items.equipment.measurementUnit.unitType', 'items.manualDetail', 'subdivision', 'user']);

        $toOrder = $application->items->filter(fn (ApplicationItem $i) => $i->canMarkCustomSupplyOrdered())->sortBy('id');
        $toWarehouse = $application->items->filter(fn (ApplicationItem $i) => $i->canMarkCustomSupplyOnWarehouse())->sortBy('id');

        return view('applications.custom-equipment-order-form', compact('application', 'toOrder', 'toWarehouse'));
    }

    public function markCustomEquipmentOrderedBulk(Request $request, Application $application): RedirectResponse
    {
        if (! $request->user()->hasAnyRoleId($this->customEquipmentOrderingRoleIds())) {
            abort(403);
        }

        $this->authorizeViewApplication($request, $application);

        if ($application->archived_at !== null) {
            return redirect()->route('applications.custom-equipment-order', $application)
                ->withErrors(['custom_supply' => 'Заявка в архиве.']);
        }

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', Rule::exists('application_items', 'id')->where('application_id', $application->id)],
        ], [
            'item_ids.required' => 'Отметьте хотя бы одну позицию.',
            'item_ids.min' => 'Отметьте хотя бы одну позицию.',
        ]);

        $application->load('items');
        $updated = 0;

        foreach ($validated['item_ids'] as $rawId) {
            $item = $application->items->firstWhere('id', (int) $rawId);
            if (! $item || ! $item->canMarkCustomSupplyOrdered()) {
                continue;
            }
            $item->update(['custom_equipment_supply_status_id' => ApplicationItem::CUSTOM_SUPPLY_ORDERED_ID]);
            $updated++;
        }

        if ($updated === 0) {
            return redirect()->route('applications.custom-equipment-order', $application)
                ->withErrors(['custom_supply' => 'Не выбрано ни одной позиции, которую можно отметить как заказанную.']);
        }

        return redirect()->route('applications.custom-equipment-order', $application)
            ->with('status', 'Отмечено как заказано позиций: '.$updated.'.');
    }

    public function markCustomEquipmentOnWarehouseBulk(Request $request, Application $application): RedirectResponse
    {
        if (! $request->user()->hasAnyRoleId($this->customEquipmentOrderingRoleIds())) {
            abort(403);
        }

        $this->authorizeViewApplication($request, $application);

        if ($application->archived_at !== null) {
            return redirect()->route('applications.custom-equipment-order', $application)
                ->withErrors(['custom_supply' => 'Заявка в архиве.']);
        }

        $validated = $request->validate([
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', Rule::exists('application_items', 'id')->where('application_id', $application->id)],
        ], [
            'item_ids.required' => 'Отметьте хотя бы одну позицию.',
            'item_ids.min' => 'Отметьте хотя бы одну позицию.',
        ]);

        $mainWarehouse = $this->resolveMainWarehouseForAccounting();
        if (! $mainWarehouse) {
            return redirect()->route('applications.custom-equipment-order', $application)
                ->withErrors(['custom_supply' => 'Не найден основной склад «Администрация». Назначьте склад основным.']);
        }

        $application->load('items');
        $processed = 0;

        try {
            DB::transaction(function () use ($request, $application, $mainWarehouse, $validated, &$processed): void {
                foreach ($validated['item_ids'] as $rawId) {
                    $item = $application->items->firstWhere('id', (int) $rawId);
                    if (! $item || ! $item->canMarkCustomSupplyOnWarehouse()) {
                        continue;
                    }
                    $this->processCustomEquipmentOnWarehouseItem($request, $application, $item, $mainWarehouse);
                    $processed++;
                }
            });
        } catch (ValidationException $e) {
            return redirect()->route('applications.custom-equipment-order', $application)
                ->withErrors($e->errors());
        }

        if ($processed === 0) {
            return redirect()->route('applications.custom-equipment-order', $application)
                ->withErrors(['custom_supply' => 'Не выбрано ни одной позиции для прихода на основной склад.']);
        }

        return redirect()->route('applications.custom-equipment-order', $application)
            ->with('status', 'Оприходовано на основной склад «'.$mainWarehouse->name.'», позиций: '.$processed.'.');
    }

    /**
     * Приход на основной склад по позиции со своим названием (общая логика для одной позиции).
     *
     * @throws ValidationException
     */
    private function processCustomEquipmentOnWarehouseItem(Request $request, Application $application, ApplicationItem $item, Warehouse $mainWarehouse): void
    {
        $item->refresh();

        if ($item->equipment_id !== null || ! $item->canMarkCustomSupplyOnWarehouse()) {
            throw ValidationException::withMessages([
                'custom_supply' => 'Позиция уже обработана или недоступна для отметки «На складе».',
            ]);
        }

        $docRef = $this->customReceiptDocumentRef($application->id, (int) $item->id);
        $existingReceipt = MaterialStockMovement::query()
            ->where('document_ref', $docRef)
            ->where('type', 'receipt')
            ->first();

        if ($existingReceipt) {
            $equipment = Equipment::query()->findOrFail((int) $existingReceipt->equipment_id);
        } else {
            $equipment = $this->resolveOrCreateEquipmentForCustomApplicationItem($application, $item);
            MaterialStockMovement::query()->create([
                'equipment_id' => $equipment->id,
                'warehouse_id' => (int) $mainWarehouse->id,
                'type' => 'receipt',
                'quantity' => (float) $item->quantity,
                'unit_price' => null,
                'happened_at' => now(),
                'document_ref' => $docRef,
                'counterparty' => null,
                'comment' => 'Приход по заявке №'.$application->id.' (позиция со своим названием).',
                'created_by_user_id' => $request->user()->id,
            ]);
        }

        $item->update([
            'equipment_id' => $equipment->id,
            'equipment_name' => null,
            'custom_equipment_supply_status_id' => null,
            'base_name' => $equipment->base_name,
            'size_value' => $equipment->size_value,
            'delivery_status_id' => null,
            'delivery_warehouse_id' => null,
            'delivery_marked_by_user_id' => null,
            'delivery_marked_at' => null,
            'custom_target_warehouse_id' => null,
            'custom_foreman_in_transit' => false,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorizeCanCreateApplications($request);

        $subdivisions = $this->availableSubdivisionsForCreate($request);
        $equipment = $this->catalogEquipmentQuery()->orderBy('name')->get();
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

    public function createInstallationActUpload(Request $request): View
    {
        $this->authorizeCanEditApplications($request);

        $applications = $this->applicationsSelectableForInstallationActUpload($request);
        $preselectedApplicationId = (int) old('application_id', (int) $request->query('application_id', 0));
        if ($preselectedApplicationId > 0 && ! $applications->contains(fn (Application $a): bool => (int) $a->id === $preselectedApplicationId)) {
            $preselectedApplicationId = 0;
        }

        $selectedApplication = $preselectedApplicationId > 0
            ? $applications->firstWhere('id', $preselectedApplicationId)
            : null;
        $deliveredWarehouseIssueCandidates = collect();
        if ($selectedApplication instanceof Application) {
            $selectedApplication->loadMissing([
                'items.equipment.measurementUnit.unitType',
                'items.manualDetail',
                'items.deliveryWarehouse.subdivision',
            ]);
            $deliveredWarehouseIssueCandidates = $this->deliveredWarehouseIssueCandidates($selectedApplication);
        }

        return view('applications.installation-act-upload', compact(
            'applications',
            'preselectedApplicationId',
            'selectedApplication',
            'deliveredWarehouseIssueCandidates'
        ));
    }

    /**
     * Бухгалтер: выбор заявки и просмотр акта установки и фото (без полной карточки заявки).
     */
    public function browseInstallationActs(Request $request): View
    {
        $user = $request->user();
        if (! $user || ! $user->hasRoleId(self::ACCOUNTANT_ROLE_ID)) {
            abort(403, 'Раздел доступен только бухгалтеру.');
        }

        $applications = $this->applicationsWithInstallationActForAccountant();
        $selectedId = (int) $request->query('application_id', 0);
        $selectedApplication = null;
        if ($selectedId > 0) {
            $selectedApplication = $applications->firstWhere('id', $selectedId);
            if ($selectedApplication) {
                $selectedApplication->load('installationActPhotos');
            }
        }

        return view('applications.installation-act-browse', compact('applications', 'selectedApplication', 'selectedId'));
    }

    public function storeInstallationActUpload(Request $request): RedirectResponse
    {
        $this->authorizeCanEditApplications($request);

        $validated = $request->validate([
            'application_id' => ['required', 'integer', 'exists:applications,id'],
            'installation_act' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
            'issue_item_ids' => ['nullable', 'array'],
            'issue_item_ids.*' => ['integer'],
        ], [
            'application_id.required' => 'Выберите заявку.',
            'installation_act.required' => 'Загрузите файл акта установки.',
            'installation_act.mimes' => 'Акт установки: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG.',
            'installation_act.max' => 'Максимальный размер файла акта: 10 МБ.',
            'issue_item_ids.array' => 'Выбор оборудования для списания передан некорректно.',
        ]);

        $application = Application::query()->with('items')->findOrFail((int) $validated['application_id']);
        $this->authorizeViewApplication($request, $application);

        $allowedIds = $this->applicationsSelectableForInstallationActUpload($request)->pluck('id');
        if (! $allowedIds->contains($application->id)) {
            throw ValidationException::withMessages([
                'application_id' => 'Эта заявка недоступна для прикрепления акта.',
            ]);
        }

        if (! $application->canUploadInstallationActAndPhotos()) {
            throw ValidationException::withMessages([
                'application_id' => 'Прикрепить акт и фото можно только после полного согласования заявки и доставки всего согласованного оборудования на склады подразделений, куда оно заказывалось (по каждой позиции — статус «Доставлено» на склад получателя).',
            ]);
        }
        if ($application->hasInstallationActEvidence()) {
            throw ValidationException::withMessages([
                'application_id' => 'По этой заявке акт и фото уже загружены. Повторная загрузка недоступна.',
            ]);
        }

        $actFile = $request->file('installation_act');
        if (! $actFile instanceof UploadedFile || ! $actFile->isValid()) {
            $code = $actFile instanceof UploadedFile ? $actFile->getError() : UPLOAD_ERR_NO_FILE;
            throw ValidationException::withMessages([
                'installation_act' => $this->uploadedFileErrorMessage($code, 'акта'),
            ]);
        }

        $photoFiles = $this->normalizeUploadedFilesArray($request->file('installation_act_photos'));
        if ($photoFiles->isEmpty()) {
            throw ValidationException::withMessages([
                'installation_act_photos' => 'Добавьте хотя бы одно фото к акту.',
            ]);
        }
        if ($photoFiles->count() > 30) {
            throw ValidationException::withMessages([
                'installation_act_photos' => 'Не более 30 фотографий за один раз.',
            ]);
        }

        foreach ($photoFiles as $index => $file) {
            if (! $file->isValid()) {
                throw ValidationException::withMessages([
                    "installation_act_photos.{$index}" => $this->uploadedFileErrorMessage($file->getError(), 'фото'),
                ]);
            }
        }

        foreach ($photoFiles as $index => $file) {
            $validator = Validator::make(
                ['photo' => $file],
                ['photo' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,bmp', 'max:10240']],
                [
                    'photo.mimes' => 'Фото: JPG, JPEG, PNG, GIF, WebP, BMP.',
                    'photo.max' => 'Максимальный размер одного фото: 10 МБ.',
                ]
            );
            if ($validator->fails()) {
                throw ValidationException::withMessages([
                    "installation_act_photos.{$index}" => (string) $validator->errors()->first('photo'),
                ]);
            }
        }

        $storageDisk = 'public';
        $installationActsDir = 'installation-acts/'.$application->id;
        $installationPhotosDir = 'installation-act-photos/'.$application->id;
        Storage::disk($storageDisk)->makeDirectory($installationActsDir);
        Storage::disk($storageDisk)->makeDirectory($installationPhotosDir);

        $deliveredCandidates = $this->deliveredWarehouseIssueCandidates($application);
        $selectedIssueItemIds = collect($validated['issue_item_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($deliveredCandidates->isNotEmpty()) {
            if ($selectedIssueItemIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'issue_item_ids' => 'Выберите оборудование, которое нужно списать со склада получателя перед сохранением акта.',
                ]);
            }

            $candidateIds = $deliveredCandidates->pluck('id')->map(fn ($id): int => (int) $id);
            $invalidSelected = $selectedIssueItemIds->first(fn (int $id): bool => ! $candidateIds->contains($id));
            if ($invalidSelected !== null) {
                throw ValidationException::withMessages([
                    'issue_item_ids' => 'Обнаружены некорректные позиции для списания. Обновите страницу и повторите выбор.',
                ]);
            }
        }

        $installationStockSummary = [
            'issued_lines' => 0,
            'warnings' => [],
        ];

        DB::transaction(function () use ($application, $actFile, $photoFiles, $storageDisk, $installationActsDir, $installationPhotosDir, $request, $selectedIssueItemIds, &$installationStockSummary) {
            $application->load('installationActPhotos');
            foreach ($application->installationActPhotos as $photo) {
                $this->deleteStoredPublicDiskFileIfExists($photo->path);
            }
            $application->installationActPhotos()->delete();

            $this->deleteStoredPublicDiskFileIfExists($application->installation_act_path);
            $newActName = $this->safeUploadedOriginalName($actFile, 'act-installation');
            $newActPath = $actFile->storeAs($installationActsDir, $newActName, $storageDisk);
            $application->update(['installation_act_path' => $newActPath]);

            foreach ($photoFiles as $photoFile) {
                $application->installationActPhotos()->create([
                    'path' => $photoFile->store($installationPhotosDir, $storageDisk),
                ]);
            }

            $application->refresh();
            $application->load(['items.equipment.measurementUnit.unitType', 'items.manualDetail']);
            $installationStockSummary = $this->writeOffDeliveredItemsOnRecipientWarehouses(
                $application,
                $request->user(),
                'Списание по акту установки (оборудование смонтировано).',
                $selectedIssueItemIds
            );
        });

        $status = 'Акт установки и фотографии сохранены для заявки №'.$application->id.'.';
        if ($installationStockSummary['issued_lines'] > 0) {
            $status .= ' Со склада получателя списано позиций: '.$installationStockSummary['issued_lines'].'.';
        }
        if ($installationStockSummary['warnings'] !== []) {
            $status .= ' '.implode(' ', $installationStockSummary['warnings']);
        }

        $application->refresh();
        $application->load(['items', 'installationActPhotos']);
        $archiveHint = $this->archiveCompletedApplicationIfReady($application);
        if ($archiveHint !== null) {
            $status .= ' '.$archiveHint;
        }

        return redirect()
            ->route('applications.show', $application)
            ->with('status', $status);
    }

    /**
     * Форма новой заявки с копией позиций. Исходная заявка может быть в архиве выполненных.
     */
    public function repeat(Request $request, Application $application): View
    {
        $this->authorizeCanRepeatApplications($request);
        $this->authorizeViewApplication($request, $application);

        $application->load(['items']);
        $subdivisions = $this->availableSubdivisionsForCreate($request);
        $equipment = $this->catalogEquipmentQuery()->orderBy('name')->get();
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

        if (filled($request->input('transport_option_id'))) {
            throw ValidationException::withMessages([
                'transport_option_id' => 'Способ доставки указывается на этапе «Отметить всё как В пути».',
            ]);
        }

        if (! $allowedSubdivisionIds->contains((int) $validated['subdivision_id'])) {
            throw ValidationException::withMessages([
                'subdivision_id' => 'Вы не можете создать заявку для этого подразделения.',
            ]);
        }
        $this->validateSubdivisionAllowedForResponsibleUser(
            (int) $validated['subdivision_id'],
            isset($validated['responsible_user_id']) ? (int) $validated['responsible_user_id'] : null
        );

        $this->validateMeasurementPairs($validated['items']);
        if ($isSiteForeman) {
            $this->validateCatalogStockAvailabilityForRequestItems($validated['items']);
        }

        $sourceId = isset($validated['source_application_id']) ? (int) $validated['source_application_id'] : null;
        if ($sourceId !== null && $sourceId > 0) {
            $sourceApplication = Application::query()->find($sourceId);
            if ($sourceApplication === null) {
                throw ValidationException::withMessages([
                    'source_application_id' => 'Исходная заявка не найдена.',
                ]);
            }
            // В т.ч. архивные заявки: повторная создаётся как новая запись, права — как на просмотр исходной.
            $this->authorizeViewApplication($request, $sourceApplication);
        }

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
            'transport_option_id' => null,
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
                'custom_equipment_supply_status_id' => $typeId ? null : ApplicationItem::CUSTOM_SUPPLY_PENDING_APPROVAL_ID,
                'delivery_status_id' => null,
                'delivery_warehouse_id' => null,
                'delivery_marked_by_user_id' => null,
                'delivery_marked_at' => null,
            ]);
        }

        $application->refresh();
        $this->applyBoilerChiefAutoGate($application);

        return redirect()->route('applications.index')
            ->with('status', 'Заявка успешно создана.');
    }

    public function show(Request $request, Application $application): View|RedirectResponse
    {
        $this->authorizeViewApplication($request, $application);

        // Автодоводка "выполненных" заявок: списываем доставленные позиции (если еще не списаны)
        // и сразу пытаемся перенести заявку в архив.
        if ($application->archived_at === null) {
            $application->loadMissing(['subdivision', 'items.equipment.measurementUnit.unitType', 'items.manualDetail', 'installationActPhotos']);
            $this->writeOffDeliveredItemsOnRecipientWarehouses(
                $application,
                $request->user(),
                'Автосписание при проверке завершения заявки.'
            );
            if ($application->archiveIfEligible()) {
                return redirect()
                    ->route('applications.show', $application)
                    ->with('status', 'Заявка автоматически перенесена в архив выполненных.');
            }
        }

        $application->load([
            'subdivision.warehouses',
            'responsibleUser',
            'user',
            'approvedBy',
            'items.equipment.measurementUnit.unitType',
            'items.manualDetail',
            'items.deliveryWarehouse',
            'items.deliveryMarkedBy',
            'items.customTargetWarehouse',
            'sourceApplication',
            'transportOption',
            'applicationStatus',
            'installationActPhotos',
        ]);

        $mainWarehouse = $this->resolveMainWarehouseForAccounting();
        $issuedByItemId = [];
        $remainingByItemId = [];

        foreach ($application->items as $item) {
            if (! $item->is_checked) {
                continue;
            }

            if ($item->equipment_id) {
                $issued = $application->totalIssuedQuantityForCatalogItem($item);
            } else {
                $issued = $this->issuedQuantityForApplicationItem($application->id, (int) $item->id);
            }
            $issuedByItemId[(int) $item->id] = $issued;
            $remainingByItemId[(int) $item->id] = max(0.0, (float) $item->quantity - $issued);
        }

        $boilerChiefDeliverySubdivisions = collect();
        if ($request->user()?->hasRoleId(self::BOILER_CHIEF_ROLE_ID)) {
            $boilerChiefDeliverySubdivisions = $request->user()
                ->boilerChiefSubdivisions()
                ->with(['warehouses' => fn ($q) => $q->orderBy('name')])
                ->orderBy('name')
                ->get();
        } elseif ($request->user()?->hasRoleId(4)) {
            $boilerChiefDeliverySubdivisions = $request->user()
                ->assignedSubdivisions()
                ->with(['warehouses' => fn ($q) => $q->orderBy('name')])
                ->orderBy('name')
                ->get();
        }

        $deliveredWarehouseIssueCandidates = $this->deliveredWarehouseIssueCandidates($application);
        $transportOptions = TransportOption::query()
            ->orderBy('name')
            ->get(['id', 'name']);
        $companyDeliveryVehicles = Schema::hasTable('company_delivery_vehicles')
            ? CompanyDeliveryVehicle::query()->orderBy('plate')->get(['id', 'plate', 'label'])
            : collect();

        return view('applications.show', compact(
            'application',
            'mainWarehouse',
            'issuedByItemId',
            'remainingByItemId',
            'boilerChiefDeliverySubdivisions',
            'deliveredWarehouseIssueCandidates',
            'transportOptions',
            'companyDeliveryVehicles'
        ));
    }

    public function issueStock(Request $request, Application $application): RedirectResponse
    {
        if (! $request->user()?->hasAnyRoleId(User::ISSUE_STOCK_FROM_MAIN_ROLE_IDS)) {
            abort(403, 'Списание со склада по заявке доступно директору, техническому директору и начальнику отдела снабжения.');
        }

        $this->authorizeViewApplication($request, $application);

        if ($application->archived_at !== null) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['stock' => 'Заявка в архиве выполненных — списание недоступно.']);
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

        DB::transaction(function () use ($rows, $application, $mainWarehouse, $request, $validated): void {
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

                $alreadyIssued = $application->totalIssuedQuantityForCatalogItem($item);
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

        $application->refresh();
        $application->load(['items', 'installationActPhotos']);
        $archiveHint = $this->archiveCompletedApplicationIfReady($application);
        $status = 'Списание оборудования по заявке сохранено.';
        if ($archiveHint !== null) {
            $status .= ' '.$archiveHint;
        }

        return redirect()
            ->route('applications.show', $application)
            ->with('status', $status);
    }

    public function issueDeliveredWarehouseStock(Request $request, Application $application): RedirectResponse
    {
        if (! $request->user()?->hasAnyRoleId([1, 2, 6, self::BOILER_CHIEF_ROLE_ID])) {
            abort(403, 'Списание со склада поступления доступно директору, техническому директору, начальнику отдела снабжения и начальнику котельной.');
        }

        $this->authorizeViewApplication($request, $application);

        $returnToUpload = $request->boolean('return_to_upload');

        if ($application->archived_at !== null) {
            return $returnToUpload
                ? redirect()->route('applications.installation-act.upload', ['application_id' => $application->id])
                    ->withErrors(['delivered_stock' => 'Заявка в архиве выполненных — списание недоступно.'])
                : redirect()->route('applications.show', $application)
                ->withErrors(['delivered_stock' => 'Заявка в архиве выполненных — списание недоступно.']);
        }

        $validated = $request->validate([
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $comment = trim((string) ($validated['comment'] ?? ''));
        $movementComment = $comment !== ''
            ? $comment
            : 'Списание со склада поступления по заявке (после доставки / монтажа).';

        $summary = DB::transaction(function () use ($application, $request, $movementComment) {
            $application->load(['items.equipment.measurementUnit.unitType', 'items.manualDetail']);

            return $this->writeOffDeliveredItemsOnRecipientWarehouses(
                $application,
                $request->user(),
                $movementComment
            );
        });

        if ($summary['issued_lines'] === 0 && $summary['warnings'] === []) {
            return ($returnToUpload
                ? redirect()->route('applications.installation-act.upload', ['application_id' => $application->id])
                : redirect()->route('applications.show', $application))
                ->withErrors([
                    'delivered_stock' => 'Нет позиций со статусом «Доставлено», которые ещё можно списать со склада получателя.',
                ]);
        }

        $status = $summary['issued_lines'] > 0
            ? 'Со складов поступления списано позиций: '.$summary['issued_lines'].'.'
            : 'Списание со складов поступления не выполнено.';
        if ($summary['warnings'] !== []) {
            $status .= ' '.implode(' ', $summary['warnings']);
        }

        $application->refresh();
        $application->load(['items', 'installationActPhotos']);
        $archiveHint = $this->archiveCompletedApplicationIfReady($application);
        if ($archiveHint !== null) {
            $status .= ' '.$archiveHint;
        }

        return ($returnToUpload
            ? redirect()->route('applications.installation-act.upload', ['application_id' => $application->id])
            : redirect()->route('applications.show', $application))
            ->with('status', $status);
    }

    public function viewCommercialOffer(Request $request, Application $application): BinaryFileResponse
    {
        $this->authorizeViewApplication($request, $application);

        $path = $this->resolveCommercialOfferPath($application);
        if (! $path) {
            abort(404, 'Файл коммерческого предложения не найден.');
        }

        return response()->file($path);
    }

    public function downloadCommercialOffer(Request $request, Application $application): BinaryFileResponse
    {
        $this->authorizeViewApplication($request, $application);

        $path = $this->resolveCommercialOfferPath($application);
        if (! $path) {
            abort(404, 'Файл коммерческого предложения не найден.');
        }

        $name = basename($path);

        return response()->download($path, $name);
    }

    public function viewInstallationAct(Request $request, Application $application): BinaryFileResponse
    {
        $this->authorizeViewInstallationActFiles($request, $application);

        $path = $this->resolveInstallationActPath($application);
        if (! $path) {
            abort(404, 'Файл акта установки не найден.');
        }

        return response()->file($path);
    }

    public function downloadInstallationAct(Request $request, Application $application): BinaryFileResponse
    {
        $this->authorizeViewInstallationActFiles($request, $application);

        $path = $this->resolveInstallationActPath($application);
        if (! $path) {
            abort(404, 'Файл акта установки не найден.');
        }

        $name = basename($path);

        return response()->download($path, $name);
    }

    public function viewInstallationActPhoto(Request $request, Application $application, ApplicationInstallationActPhoto $installationActPhoto): BinaryFileResponse
    {
        $this->authorizeViewInstallationActFiles($request, $application);

        if ((int) $installationActPhoto->application_id !== (int) $application->id) {
            abort(404, 'Фото не найдено.');
        }

        $path = $this->resolveStoredPublicDiskAbsolutePath(trim((string) $installationActPhoto->path));
        if (! $path) {
            abort(404, 'Файл фото не найден.');
        }

        return response()->file($path);
    }

    public function edit(Request $request, Application $application): View|RedirectResponse
    {
        $this->authorizeCanEditApplications($request);
        $this->authorizeViewApplication($request, $application);
        $this->authorizeForemanCanModifyApplication($request, $application);
        if ($this->approvalLockedByDeliveryProgress($application)) {
            return redirect()
                ->route('applications.show', $application)
                ->withErrors(['edit' => 'Заявка уже в доставке/получена — редактирование недоступно.']);
        }

        if ($application->archived_at !== null) {
            return redirect()
                ->route('applications.show', $application)
                ->withErrors(['edit' => 'Заявка в архиве выполненных — редактирование недоступно. Для новой поставки создайте повторную заявку.']);
        }

        $subdivisions = Subdivision::orderBy('name')->get();
        $equipment = $this->catalogEquipmentQuery()->orderBy('name')->get();
        $users = User::query()
            ->where('role_id', 4)
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        $transportOptions = TransportOption::query()
            ->orderBy('name')
            ->get();

        $application->load(['items.equipment.measurementUnit.unitType', 'items.manualDetail', 'applicationStatus']);

        $warehousesBySubdivision = $this->warehousesBySubdivisionForUi();
        $subdivisionIdsByForeman = $this->subdivisionIdsByForemanForUi();
        $measurementMeta = $this->measurementMetaForUi();

        return view('applications.edit', compact('application', 'subdivisions', 'equipment', 'users', 'transportOptions', 'warehousesBySubdivision', 'subdivisionIdsByForeman', 'measurementMeta'));
    }

    public function update(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeCanEditApplications($request);
        $this->authorizeViewApplication($request, $application);
        $this->authorizeForemanCanModifyApplication($request, $application);
        if ($this->approvalLockedByDeliveryProgress($application)) {
            return redirect()
                ->route('applications.show', $application)
                ->withErrors(['edit' => 'Заявка уже в доставке/получена — редактирование недоступно.']);
        }

        if ($application->archived_at !== null) {
            return redirect()
                ->route('applications.show', $application)
                ->withErrors(['edit' => 'Заявка в архиве выполненных — редактирование недоступно. Для новой поставки создайте повторную заявку.']);
        }

        $isSiteForeman = $request->user()->hasRoleId(4);
        $allowedSubdivisionIds = $this->availableSubdivisionsForCreate($request)->pluck('id')->map(fn ($id) => (int) $id);
        $application->load(['items.equipment.measurementUnit.unitType', 'items.manualDetail', 'applicationStatus']);

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

        if (filled($request->input('transport_option_id'))) {
            throw ValidationException::withMessages([
                'transport_option_id' => 'Способ доставки указывается на этапе «Отметить всё как В пути».',
            ]);
        }

        if ($isSiteForeman && ! $allowedSubdivisionIds->contains((int) $validated['subdivision_id'])) {
            throw ValidationException::withMessages([
                'subdivision_id' => 'Вы не можете изменить заявку для этого подразделения.',
            ]);
        }

        $this->validateSubdivisionAllowedForResponsibleUser(
            (int) $validated['subdivision_id'],
            isset($validated['responsible_user_id']) ? (int) $validated['responsible_user_id'] : null
        );

        $this->validateMeasurementPairs($validated['items']);
        if ($isSiteForeman) {
            $this->validateCatalogStockAvailabilityForRequestItems($validated['items']);
        }

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

        $previousSubdivisionId = (int) $application->subdivision_id;

        DB::transaction(function () use ($application, $validated, $toCreate, $request, $isSiteForeman, $submittedItemIds, $previousSubdivisionId) {
            $responsibleUserId = $validated['responsible_user_id'] ?? null;
            if ($isSiteForeman) {
                $responsibleUserId = $request->user()->id;
            }
            $existingApprovedByUserId = $application->approved_by_user_id;

            $application->update([
                'subdivision_id' => $validated['subdivision_id'],
                'responsible_user_id' => $responsibleUserId,
                'transport_option_id' => $application->transport_option_id,
                'desired_delivery_date' => $validated['desired_delivery_date'],
            ]);

            if ((int) $validated['subdivision_id'] !== $previousSubdivisionId) {
                $application->items()->update([
                    'boiler_chief_checked' => false,
                    'reason_boiler_chief_not_selected' => null,
                ]);
                $application->update(['boiler_chief_stage_completed_at' => null]);
            }

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
                    'custom_equipment_supply_status_id' => $typeId ? null : ApplicationItem::CUSTOM_SUPPLY_PENDING_APPROVAL_ID,
                    'boiler_chief_checked' => false,
                    'reason_boiler_chief_not_selected' => null,
                    'delivery_status_id' => null,
                    'delivery_warehouse_id' => null,
                    'delivery_marked_by_user_id' => null,
                    'delivery_marked_at' => null,
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
                    'custom_equipment_supply_status_id' => $payload['equipment_id'] ? null : ApplicationItem::CUSTOM_SUPPLY_PENDING_APPROVAL_ID,
                    'boiler_chief_checked' => false,
                    'reason_boiler_chief_not_selected' => null,
                    'delivery_status_id' => null,
                    'delivery_warehouse_id' => null,
                    'delivery_marked_by_user_id' => null,
                    'delivery_marked_at' => null,
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

            $application->refresh();
            $application->load('items');
            $this->refreshBoilerChiefGateAfterItemChanges($application);
        });

        return redirect()->to(route('applications.show', $application).'#approval-form')
            ->with('status', 'Заявка успешно обновлена.');
    }

    public function saveApproval(Request $request, Application $application): RedirectResponse
    {
        if (! $request->user()->hasAnyRoleId($this->managementEditorRoleIds())) {
            abort(403, 'Согласование доступно только директору, техническому директору и начальнику отдела снабжения.');
        }

        $this->authorizeViewApplication($request, $application);

        if ($application->archived_at !== null) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['approval' => 'Заявка в архиве выполненных — изменить согласование по позициям нельзя.']);
        }

        $application->load('items');

        if ($this->approvalLockedByDeliveryProgress($application)) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['approval' => 'Согласование нельзя изменять после отметки оборудования «В пути» или «Доставлено».']);
        }

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
                $payload = [
                    'is_checked' => $isChecked,
                    'reason_not_selected' => $isChecked ? null : trim((string) ($row['reason_not_selected'] ?? '')),
                ];
                if (! $isChecked) {
                    $payload['delivery_status_id'] = null;
                    $payload['delivery_warehouse_id'] = null;
                    $payload['delivery_marked_by_user_id'] = null;
                    $payload['delivery_marked_at'] = null;
                }
                if ($item->equipment_id === null) {
                    $payload['custom_equipment_supply_status_id'] = $this->customSupplyStatusAfterApprovalToggle(
                        $isChecked,
                        $item
                    );
                } else {
                    $payload['custom_equipment_supply_status_id'] = null;
                }
                $item->update($payload);
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

        $application->refresh();
        $application->load(['items', 'installationActPhotos']);
        $archiveHint = $this->archiveCompletedApplicationIfReady($application);
        $status = 'Согласование по позициям сохранено.';
        if ($archiveHint !== null) {
            $status .= ' '.$archiveHint;
        }

        return redirect()->route('applications.show', $application)
            ->with('status', $status);
    }

    public function saveBoilerChiefApproval(Request $request, Application $application): RedirectResponse
    {
        if (! $request->user()->hasRoleId(self::BOILER_CHIEF_ROLE_ID)) {
            abort(403, 'Согласование на этом этапе доступно только начальнику котельной.');
        }

        $this->authorizeViewApplication($request, $application);

        $application->load('items');

        if ($application->items->isEmpty()) {
            return redirect()->route('applications.show', $application)
                ->with('status', 'Нет позиций для согласования.');
        }

        if ($application->boiler_chief_stage_completed_at !== null) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['boiler_chief' => 'Этап согласования начальником котельной уже завершён.']);
        }

        $itemsInput = $request->input('boiler_items', []);
        $bulkUncheckedReason = trim((string) $request->input('boiler_bulk_unchecked_reason', ''));
        if ($bulkUncheckedReason !== '' && mb_strlen($bulkUncheckedReason) > 500) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['boiler_bulk_unchecked_reason' => 'Общая причина не может быть длиннее 500 символов.'])
                ->withInput();
        }
        $errors = [];

        foreach ($application->items as $item) {
            $row = $itemsInput[(string) $item->id] ?? $itemsInput[$item->id] ?? null;
            if (! is_array($row)) {
                $errors["boiler_items.{$item->id}.boiler_chief_checked"] = 'Отсутствуют данные по позиции.';

                continue;
            }
            $checkedRaw = $row['boiler_chief_checked'] ?? '0';
            $isChecked = $checkedRaw === '1' || $checkedRaw === 1 || $checkedRaw === true;
            if (! $isChecked) {
                $reason = trim((string) ($row['reason_boiler_chief_not_selected'] ?? ''));
                if ($reason === '' && $bulkUncheckedReason !== '') {
                    $reason = $bulkUncheckedReason;
                }
                if ($reason === '') {
                    $errors["boiler_items.{$item->id}.reason_boiler_chief_not_selected"] = 'Укажите причину не согласования.';
                } elseif (mb_strlen($reason) > 500) {
                    $errors["boiler_items.{$item->id}.reason_boiler_chief_not_selected"] = 'Причина не может быть длиннее 500 символов.';
                }
            }
        }

        if ($errors !== []) {
            return redirect()->route('applications.show', $application)
                ->withErrors($errors)
                ->withInput();
        }

        DB::transaction(function () use ($application, $itemsInput, $bulkUncheckedReason) {
            foreach ($application->items as $item) {
                $row = $itemsInput[(string) $item->id] ?? $itemsInput[$item->id];
                $checkedRaw = $row['boiler_chief_checked'] ?? '0';
                $isChecked = $checkedRaw === '1' || $checkedRaw === 1 || $checkedRaw === true;
                $reason = trim((string) ($row['reason_boiler_chief_not_selected'] ?? ''));
                if (! $isChecked && $reason === '' && $bulkUncheckedReason !== '') {
                    $reason = $bulkUncheckedReason;
                }
                $item->update([
                    'boiler_chief_checked' => $isChecked,
                    'reason_boiler_chief_not_selected' => $isChecked ? null : $reason,
                ]);
            }

            $application->refresh();
            $application->load('items');
            $allBoiler = $application->items->every(fn (ApplicationItem $i) => (bool) $i->boiler_chief_checked);
            $application->update([
                'boiler_chief_stage_completed_at' => $allBoiler ? now() : null,
            ]);
        });

        $application->refresh();
        $statusMessage = $application->boiler_chief_stage_completed_at !== null
            ? 'Согласование начальника котельной завершено. Заявка доступна директору, техническому директору и начальнику отдела снабжения для дальнейшего согласования позиций.'
            : 'Согласование начальника котельной сохранено. Пока не все позиции согласованы — заявка не передаётся на следующий этап.';

        return redirect()->route('applications.show', $application)
            ->with('status', $statusMessage);
    }

    public function markApplicationDeliveryInTransit(Request $request, Application $application): RedirectResponse
    {
        if (! $request->user()->hasAnyRoleId($this->managementEditorRoleIds())) {
            abort(403, 'Отметка «В пути» доступна только директору, техническому директору и начальнику отдела снабжения.');
        }

        $this->authorizeViewApplication($request, $application);
        $application->load('items');

        $validated = $request->validate([
            'transport_option_id' => ['required', 'exists:transport_options,id'],
            'delivery_vehicle_plate' => ['nullable', 'string', 'max:30', 'regex:/^[\p{L}\p{N}\s\-]*$/u'],
        ], [
            'transport_option_id.required' => 'Перед отметкой «В пути» укажите способ доставки.',
            'transport_option_id.exists' => 'Выбранный способ доставки не найден.',
            'delivery_vehicle_plate.regex' => 'Номер транспорта: только буквы, цифры, пробел и дефис.',
        ]);

        $eligibleItems = $application->items->filter(fn (ApplicationItem $i) => $i->canMarkDeliveryInTransit());

        if ($eligibleItems->isEmpty()) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['delivery' => 'Нет позиций, которые можно массово отметить как «В пути».']);
        }

        ApplicationItem::query()
            ->whereIn('id', $eligibleItems->pluck('id'))
            ->update([
                'delivery_status_id' => ApplicationItem::DELIVERY_IN_TRANSIT_ID,
                'delivery_warehouse_id' => null,
                'delivery_marked_by_user_id' => null,
                'delivery_marked_at' => null,
            ]);

        $plate = isset($validated['delivery_vehicle_plate'])
            ? trim((string) $validated['delivery_vehicle_plate'])
            : '';
        $application->update([
            'transport_option_id' => (int) $validated['transport_option_id'],
            'delivery_vehicle_plate' => $plate !== '' ? $plate : null,
        ]);

        return redirect()->route('applications.show', $application)
            ->with('status', 'Все подходящие позиции заявки отмечены как «В пути».');
    }

    public function markItemDeliveryDelivered(Request $request, Application $application, ApplicationItem $item): RedirectResponse
    {
        if (! $request->user()->hasAnyRoleId([self::BOILER_CHIEF_ROLE_ID, 4])) {
            abort(403, 'Отметка «Доставлено» доступна только начальнику котельной и мастеру участка.');
        }

        $this->authorizeViewApplication($request, $application);

        if ((int) $item->application_id !== (int) $application->id) {
            abort(404);
        }

        if (! $item->canMarkDeliveryDeliveredByBoilerChief()) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['delivery' => 'Отметка «Доставлено» доступна только для позиций со статусом «В пути».']);
        }

        $application->loadMissing('subdivision');
        $item->loadMissing('application');

        $expectedSubdivisionId = $item->resolvedDeliveryTargetSubdivisionId();
        if ($expectedSubdivisionId === null) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['delivery' => 'Не задано подразделение получения по заявке.']);
        }

        $validated = $request->validate([
            'delivery_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ], [
            'delivery_warehouse_id.required' => 'Выберите склад поступления.',
        ]);

        $allowedSubdivisionIds = $request->user()->hasRoleId(self::BOILER_CHIEF_ROLE_ID)
            ? $request->user()
                ->boilerChiefSubdivisions()
                ->pluck('subdivisions.id')
                ->map(fn ($id) => (int) $id)
            : $request->user()
                ->assignedSubdivisions()
                ->pluck('subdivisions.id')
                ->map(fn ($id) => (int) $id);

        $deliveryWarehouseId = (int) $validated['delivery_warehouse_id'];

        $warehouse = Warehouse::query()->findOrFail($deliveryWarehouseId);
        $warehouseSubdivisionId = (int) ($warehouse->subdivision_id ?? 0);

        if ($warehouseSubdivisionId !== $expectedSubdivisionId) {
            throw ValidationException::withMessages([
                'delivery_warehouse_id' => 'Склад должен относиться к подразделению, указанному в заявке / выбранному мастером участка (не выбирайте другое подразделение).',
            ]);
        }

        if (! $allowedSubdivisionIds->contains($warehouseSubdivisionId)) {
            throw ValidationException::withMessages([
                'delivery_warehouse_id' => 'Вы можете отметить доставку только на склады подразделений, закреплённых за вами.',
            ]);
        }

        DB::transaction(function () use ($request, $application, $item, $deliveryWarehouseId) {
            $item->refresh();

            if (! $item->canMarkDeliveryDeliveredByBoilerChief()) {
                throw ValidationException::withMessages([
                    'delivery' => 'Позиция уже обработана или не находится в статусе «В пути».',
                ]);
            }

            if (! $item->equipment_id) {
                throw ValidationException::withMessages([
                    'delivery' => 'Для отметки «Доставлено» позиция должна быть привязана к оборудованию из справочника.',
                ]);
            }

            $docRef = $this->deliveryReceiptDocumentRef($application->id, (int) $item->id, $deliveryWarehouseId);
            $alreadyReceived = MaterialStockMovement::query()
                ->where('type', 'receipt')
                ->where('document_ref', $docRef)
                ->exists();

            if (! $alreadyReceived) {
                MaterialStockMovement::query()->create([
                    'equipment_id' => (int) $item->equipment_id,
                    'warehouse_id' => $deliveryWarehouseId,
                    'type' => 'receipt',
                    'quantity' => (float) $item->quantity,
                    'unit_price' => null,
                    'happened_at' => now(),
                    'document_ref' => $docRef,
                    'counterparty' => 'Доставка по заявке №'.$application->id,
                    'comment' => 'Поступление на склад получателя по отметке «Доставлено».',
                    'created_by_user_id' => $request->user()->id,
                ]);
            }

            $item->update([
                'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
                'delivery_warehouse_id' => $deliveryWarehouseId,
                'delivery_marked_by_user_id' => $request->user()->id,
                'delivery_marked_at' => now(),
            ]);
        });

        $application->refresh();
        $application->load(['subdivision', 'items', 'installationActPhotos']);
        $archiveHint = $this->archiveCompletedApplicationIfReady($application);

        $status = 'Позиция отмечена как доставленная и оприходована на склад получателя. Остаток на этом складе сохраняется до отдельного списания (по акту установки или иной операции списания со склада поступления).';
        if ($archiveHint !== null) {
            $status .= ' '.$archiveHint;
        }

        return redirect()->route('applications.show', $application)
            ->with('status', $status);
    }

    public function markCustomEquipmentOrdered(Request $request, Application $application, ApplicationItem $item): RedirectResponse
    {
        if (! $request->user()->hasAnyRoleId($this->customEquipmentOrderingRoleIds())) {
            abort(403, 'Отметка «Заказано» доступна только директору и начальнику отдела снабжения.');
        }

        $this->authorizeViewApplication($request, $application);

        if ((int) $item->application_id !== (int) $application->id) {
            abort(404);
        }

        if ($item->equipment_id !== null) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['custom_supply' => 'Для позиций из справочника эта отметка не используется.']);
        }

        if (! $item->canMarkCustomSupplyOrdered()) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['custom_supply' => 'Отметить «Заказано» можно только для согласованной позиции со своим названием, которая ещё не отмечена как заказанная.']);
        }

        $item->update([
            'custom_equipment_supply_status_id' => ApplicationItem::CUSTOM_SUPPLY_ORDERED_ID,
        ]);

        return redirect()->route('applications.show', $application)
            ->with('status', 'Позиция со своим названием отмечена как заказанная.');
    }

    public function markCustomEquipmentSupplyInTransit(Request $request, Application $application, ApplicationItem $item): RedirectResponse
    {
        if (! $request->user()->hasAnyRoleId($this->managementEditorRoleIds())) {
            abort(403, 'Отметка «В пути» доступна только директору, техническому директору и начальнику отдела снабжения.');
        }

        $this->authorizeViewApplication($request, $application);

        if ((int) $item->application_id !== (int) $application->id) {
            abort(404);
        }

        if ($item->equipment_id !== null) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['custom_supply' => 'Для позиций из справочника эта отметка не используется.']);
        }

        if (! $item->canMarkCustomSupplyInTransit()) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['custom_supply' => 'Отметить «В пути» можно только после «Заказано», пока груз ещё не принят на основной склад.']);
        }

        $item->update([
            'custom_equipment_supply_status_id' => ApplicationItem::CUSTOM_SUPPLY_IN_TRANSIT_ID,
        ]);

        return redirect()->route('applications.show', $application)
            ->with('status', 'Позиция отмечена как «В пути» (поставка от поставщика).');
    }

    public function saveCustomItemTargetWarehouse(Request $request, Application $application, ApplicationItem $item): RedirectResponse
    {
        if (! $request->user()->hasRoleId(4)) {
            abort(403, 'Указать склад получения может только мастер участка.');
        }

        $this->authorizeViewApplication($request, $application);
        $this->authorizeForemanCanModifyApplication($request, $application);

        if ((int) $item->application_id !== (int) $application->id) {
            abort(404);
        }

        if (! $item->canSaveCustomTargetWarehouseForForeman()) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['custom_target' => 'Нельзя изменить склад получения для этой позиции.']);
        }

        $validated = $request->validate([
            'custom_target_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
        ], [
            'custom_target_warehouse_id.required' => 'Выберите склад получения.',
        ]);

        $warehouse = Warehouse::query()->findOrFail((int) $validated['custom_target_warehouse_id']);
        if ((int) ($warehouse->subdivision_id ?? 0) !== (int) $application->subdivision_id) {
            throw ValidationException::withMessages([
                'custom_target_warehouse_id' => 'Склад должен относиться к подразделению этой заявки.',
            ]);
        }

        $item->update([
            'custom_target_warehouse_id' => (int) $warehouse->id,
        ]);

        return redirect()->route('applications.show', $application)
            ->with('status', 'Склад получения для позиции со своим названием сохранён.');
    }

    public function markCustomForemanInTransitToTarget(Request $request, Application $application, ApplicationItem $item): RedirectResponse
    {
        if (! $request->user()->hasRoleId(4)) {
            abort(403, 'Эта отметка доступна только мастеру участка.');
        }

        $this->authorizeViewApplication($request, $application);

        if ((int) $item->application_id !== (int) $application->id) {
            abort(404);
        }

        if (! $item->canMarkCustomForemanInTransitToTarget()) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['custom_target' => 'Сначала выберите склад получения; отметить «в пути» можно после того, как снабжение отметило заказ (позиция «Заказано» или «В пути»).']);
        }

        $item->update([
            'custom_foreman_in_transit' => true,
        ]);

        return redirect()->route('applications.show', $application)
            ->with('status', 'Отмечено: оборудование в пути на выбранный склад подразделения.');
    }

    public function markCustomEquipmentOnWarehouse(Request $request, Application $application, ApplicationItem $item): RedirectResponse
    {
        if (! $request->user()->hasAnyRoleId($this->customEquipmentOrderingRoleIds())) {
            abort(403, 'Отметка «На складе» доступна только директору и начальнику отдела снабжения.');
        }

        $this->authorizeViewApplication($request, $application);

        if ((int) $item->application_id !== (int) $application->id) {
            abort(404);
        }

        if ($item->equipment_id !== null) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['custom_supply' => 'Для позиций из справочника эта отметка не используется.']);
        }

        if (! $item->canMarkCustomSupplyOnWarehouse()) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['custom_supply' => 'Сначала отметьте «Заказано»; после прихода на основной склад — «На складе».']);
        }

        $mainWarehouse = $this->resolveMainWarehouseForAccounting();
        if (! $mainWarehouse) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['custom_supply' => 'Не найден основной склад «Администрация». Назначьте склад основным — без этого приход в «Материалы» невозможен.']);
        }

        $statusMessage = '';

        try {
            DB::transaction(function () use ($request, $application, $item, $mainWarehouse, &$statusMessage): void {
                $this->processCustomEquipmentOnWarehouseItem($request, $application, $item, $mainWarehouse);
                $statusMessage = 'Создан приход на основной склад «'.$mainWarehouse->name.'», позиция привязана к справочнику оборудования.';
            });
        } catch (ValidationException $e) {
            return redirect()->route('applications.show', $application)
                ->withErrors($e->errors());
        }

        return redirect()->route('applications.show', $application)
            ->with('status', $statusMessage);
    }

    /**
     * Заявки, к которым пользователь может прикрепить акт установки (совпадает с правами просмотра в списке).
     *
     * @return Collection<int, Application>
     */
    private function applicationsSelectableForInstallationActUpload(Request $request): Collection
    {
        $user = $request->user();
        if (! $user) {
            return collect();
        }

        $query = Application::query()
            ->with('subdivision')
            ->orderByDesc('id');

        if ($user->hasRoleId(4)) {
            $foremanSubdivisionIds = $user->assignedSubdivisions()
                ->pluck('subdivisions.id')
                ->map(fn ($id): int => (int) $id);
            $query->whereIn('subdivision_id', $foremanSubdivisionIds);
        }

        if ($user->hasRoleId(self::BOILER_CHIEF_ROLE_ID)) {
            $chiefSubIds = $user->boilerChiefSubdivisions()->pluck('subdivisions.id');
            $query->whereIn('subdivision_id', $chiefSubIds);
        }

        if ($user->hasAnyRoleId([1, 2, 6, 3])) {
            $query->where(function ($outer) {
                $outer->whereDoesntHave('user', function ($q) {
                    $q->where('role_id', 4);
                })->orWhereNotNull('applications.boiler_chief_stage_completed_at');
            });
        }

        return $query->with(['items', 'installationActPhotos'])
            ->limit(500)
            ->get()
            ->filter(fn (Application $a) => $a->canUploadInstallationActAndPhotos() && ! $a->hasInstallationActEvidence())
            ->values();
    }

    /**
     * @return Collection<int, UploadedFile>
     */
    private function normalizeUploadedFilesArray(mixed $files): Collection
    {
        if ($files === null) {
            return collect();
        }
        if ($files instanceof UploadedFile) {
            return collect([$files]);
        }
        if (! is_array($files)) {
            return collect();
        }

        return collect($files)
            ->values()
            ->filter(fn ($f): bool => $f instanceof UploadedFile);
    }

    private function uploadedFileErrorMessage(int $errorCode, string $label): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE => "Файл «{$label}» больше, чем разрешено в PHP (upload_max_filesize). Уменьшите размер или увеличьте лимит на сервере.",
            UPLOAD_ERR_FORM_SIZE => "Файл «{$label}» больше, чем указано в форме (post_max_size / лимит приложения).",
            UPLOAD_ERR_PARTIAL => "Файл «{$label}» загружен не полностью — повторите отправку.",
            UPLOAD_ERR_NO_FILE => "Файл «{$label}» не был передан.",
            UPLOAD_ERR_NO_TMP_DIR => 'На сервере нет временной папки для загрузки.',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск.',
            UPLOAD_ERR_EXTENSION => 'Расширение PHP прервало загрузку файла.',
            default => "Не удалось загрузить файл «{$label}» (код {$errorCode}).",
        };
    }

    private function safeUploadedOriginalName(UploadedFile $file, string $fallbackPrefix): string
    {
        $original = trim((string) $file->getClientOriginalName());
        $original = str_replace(["\\", '/'], '-', $original);
        $original = preg_replace('/[\x00-\x1F\x7F:*?"<>|]+/u', '', $original) ?? '';
        $original = trim($original, ". \t\n\r\0\x0B");

        $extension = trim((string) $file->getClientOriginalExtension());
        $fallbackExt = trim((string) $file->extension());
        $extension = $extension !== '' ? $extension : $fallbackExt;

        if ($original === '') {
            $stamp = now()->format('Ymd-His');
            return $extension !== '' ? "{$fallbackPrefix}-{$stamp}.{$extension}" : "{$fallbackPrefix}-{$stamp}";
        }

        if ($extension !== '' && ! str_ends_with(mb_strtolower($original), '.'.mb_strtolower($extension))) {
            return $original.'.'.$extension;
        }

        return $original;
    }

    private function authorizeViewInstallationActFiles(Request $request, Application $application): void
    {
        $user = $request->user();
        if ($user && $user->hasRoleId(self::ACCOUNTANT_ROLE_ID) && $application->hasInstallationActEvidence()) {
            return;
        }

        $this->authorizeViewApplication($request, $application);
    }

    /**
     * Заявки с приложенным актом и/или фото — для страницы просмотра бухгалтером.
     *
     * @return Collection<int, Application>
     */
    private function applicationsWithInstallationActForAccountant(): Collection
    {
        return Application::query()
            ->with('subdivision')
            ->where(function ($outer) {
                $outer->where(function ($q) {
                    $q->whereNotNull('installation_act_path')
                        ->where('installation_act_path', '!=', '');
                })->orWhereHas('installationActPhotos');
            })
            ->orderByDesc('id')
            ->limit(500)
            ->get();
    }

    private function authorizeViewApplication(Request $request, Application $application): void
    {
        $user = $request->user();
        if (! $user) {
            abort(403, 'Необходима авторизация.');
        }

        if ($user->hasRoleId(4)) {
            $ids = $user->assignedSubdivisions()->pluck('subdivisions.id');
            if (! $ids->contains((int) $application->subdivision_id)) {
                abort(403, 'Заявка относится к подразделению вне вашей зоны ответственности.');
            }

            return;
        }

        if ($user->hasRoleId(self::BOILER_CHIEF_ROLE_ID)) {
            $ids = $user->boilerChiefSubdivisions()->pluck('subdivisions.id');
            if (! $ids->contains((int) $application->subdivision_id)) {
                abort(403, 'Заявка относится к подразделению вне вашей зоны ответственности.');
            }

            return;
        }

        if ($user->hasAnyRoleId([1, 2, 6, 3])) {
            if ($this->isForemanCreatedApplication($application) && $application->boiler_chief_stage_completed_at === null) {
                abort(403, 'Заявка пока недоступна: сначала её согласует начальник котельной по подразделению.');
            }

            return;
        }

        abort(403, 'Недостаточно прав для просмотра этой заявки.');
    }

    private function applyBoilerChiefAutoGate(Application $application): void
    {
        if (! Schema::hasColumn('applications', 'boiler_chief_stage_completed_at')) {
            return;
        }

        if ($this->isForemanCreatedApplication($application)) {
            return;
        }

        if (! Subdivision::hasBoilerChiefAssigned((int) $application->subdivision_id)) {
            $application->update([
                'boiler_chief_stage_completed_at' => now(),
            ]);
            $application->items()->update([
                'boiler_chief_checked' => true,
                'reason_boiler_chief_not_selected' => null,
            ]);
        }
    }

    private function refreshBoilerChiefGateAfterItemChanges(Application $application): void
    {
        if (! Schema::hasColumn('applications', 'boiler_chief_stage_completed_at')) {
            return;
        }

        if ($this->isForemanCreatedApplication($application)) {
            return;
        }

        if (! Subdivision::hasBoilerChiefAssigned((int) $application->subdivision_id)) {
            $application->update([
                'boiler_chief_stage_completed_at' => now(),
            ]);
            $application->items()->update([
                'boiler_chief_checked' => true,
                'reason_boiler_chief_not_selected' => null,
            ]);

            return;
        }

        $application->load('items');
        $allBoiler = $application->items->every(fn (ApplicationItem $i) => (bool) $i->boiler_chief_checked);
        if (! $allBoiler) {
            $application->update(['boiler_chief_stage_completed_at' => null]);
        }
    }

    private function authorizeCanCreateApplications(Request $request): void
    {
        $allowed = $request->user() && $request->user()->hasAnyRoleId([1, 6, 2, 4]);

        if (! $allowed) {
            abort(403, 'Создание заявок разрешено только директору, техническому директору, начальнику отдела снабжения и мастеру участка.');
        }
    }

    private function authorizeCanEditApplications(Request $request): void
    {
        $allowed = $request->user() && $request->user()->hasAnyRoleId($this->editApplicationRoleIds());

        if (! $allowed) {
            abort(403, 'Редактирование заявок разрешено директору, техническому директору, начальнику отдела снабжения, мастеру участка и начальнику котельной.');
        }
    }

    private function authorizeForemanCanModifyApplication(Request $request, Application $application): void
    {
        $user = $request->user();
        if (! $user || ! $user->hasRoleId(4)) {
            return;
        }

        if ($application->isStatusApproved()) {
            abort(403, 'Заявка полностью согласована — мастер участка не может больше изменять её или добавлять новые позиции.');
        }
    }

    private function authorizeCanRepeatApplications(Request $request): void
    {
        if (! $request->user() || ! $request->user()->hasRoleId(4)) {
            abort(403, 'Создание повторной заявки разрешено только мастеру участка.');
        }
    }

    /**
     * Проверяет, что по выбранному оборудованию из каталога не запрашивают больше остатка на основном складе.
     *
     * @param  array<int, array<string, mixed>>  $items
     *
     * @throws ValidationException
     */
    private function validateCatalogStockAvailabilityForRequestItems(array $items): void
    {
        $requestedByEquipmentId = [];
        foreach ($items as $row) {
            $equipmentIdRaw = $row['equipment_id'] ?? null;
            $equipmentId = $equipmentIdRaw !== null && $equipmentIdRaw !== '' ? (int) $equipmentIdRaw : null;
            if (! $equipmentId || $equipmentId <= 0) {
                continue;
            }

            $qty = (int) ($row['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $requestedByEquipmentId[$equipmentId] = ($requestedByEquipmentId[$equipmentId] ?? 0) + $qty;
        }

        if ($requestedByEquipmentId === []) {
            return;
        }

        $equipmentIds = array_keys($requestedByEquipmentId);
        $catalogEquipmentById = Equipment::query()
            ->whereIn('id', $equipmentIds)
            ->where('is_catalog', true)
            ->get(['id', 'name'])
            ->keyBy('id');

        foreach ($equipmentIds as $equipmentId) {
            if (! $catalogEquipmentById->has($equipmentId)) {
                throw ValidationException::withMessages([
                    'equipment' => 'Можно выбирать только оборудование из общего каталога. Резервные позиции из других заявок недоступны.',
                ]);
            }
        }

        $mainWarehouse = $this->resolveMainWarehouseForAccounting();
        if (! $mainWarehouse) {
            throw ValidationException::withMessages([
                'equipment' => 'Не найден основной склад "Администрация". Проверьте настройки склада.',
            ]);
        }

        $errors = [];
        foreach ($requestedByEquipmentId as $equipmentId => $requestedQty) {
            $availableQty = max(0.0, $this->warehouseEquipmentBalance((int) $equipmentId, (int) $mainWarehouse->id));
            if ((float) $requestedQty <= $availableQty + 0.0005) {
                continue;
            }

            $equipmentName = (string) ($catalogEquipmentById->get($equipmentId)?->name ?? ('ID '.$equipmentId));
            $errors[] = sprintf(
                '%s: запрошено %d, доступно на основном складе %s.',
                $equipmentName,
                (int) $requestedQty,
                number_format($availableQty, 3, '.', ' ')
            );
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'equipment' => 'Недостаточно остатков на основном складе: '.implode(' ', $errors),
            ]);
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
        return $this->resolveStoredPublicDiskAbsolutePath(trim((string) ($application->commercial_offer_path ?? '')));
    }

    private function resolveInstallationActPath(Application $application): ?string
    {
        return $this->resolveStoredPublicDiskAbsolutePath(trim((string) ($application->installation_act_path ?? '')));
    }

    private function resolveStoredPublicDiskAbsolutePath(string $relativePath): ?string
    {
        $relativePath = trim($relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..')) {
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

    private function deleteStoredPublicDiskFileIfExists(?string $relativePath): void
    {
        $relativePath = trim((string) ($relativePath ?? ''));
        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return;
        }

        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        } elseif (Storage::exists($relativePath)) {
            Storage::delete($relativePath);
        }
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
        return User::MANAGEMENT_EDITOR_ROLE_IDS;
    }

    /**
     * @return list<int>
     */
    private function customEquipmentOrderingRoleIds(): array
    {
        return User::CUSTOM_EQUIPMENT_ORDERING_ROLE_IDS;
    }

    private function isForemanCreatedApplication(Application $application): bool
    {
        $application->loadMissing('user:id,role_id');

        return (int) ($application->user?->role_id ?? 0) === 4;
    }

    private function approvalLockedByDeliveryProgress(Application $application): bool
    {
        $application->loadMissing('items');

        return $application->items->contains(function (ApplicationItem $item): bool {
            return in_array(
                $item->resolvedDeliveryStatus(),
                [ApplicationItem::DELIVERY_IN_TRANSIT, ApplicationItem::DELIVERY_DELIVERED],
                true
            );
        });
    }

    private function customSupplyStatusAfterApprovalToggle(bool $isChecked, ApplicationItem $item): int
    {
        if (! $isChecked) {
            return ApplicationItem::CUSTOM_SUPPLY_PENDING_APPROVAL_ID;
        }

        $normalized = $item->normalizedCustomSupplyStatus();
        if ($normalized === ApplicationItem::CUSTOM_SUPPLY_ON_WAREHOUSE) {
            return ApplicationItem::CUSTOM_SUPPLY_ON_WAREHOUSE_ID;
        }
        if ($normalized === ApplicationItem::CUSTOM_SUPPLY_ORDERED) {
            return ApplicationItem::CUSTOM_SUPPLY_ORDERED_ID;
        }
        if ($normalized === ApplicationItem::CUSTOM_SUPPLY_IN_TRANSIT) {
            return ApplicationItem::CUSTOM_SUPPLY_IN_TRANSIT_ID;
        }

        return ApplicationItem::CUSTOM_SUPPLY_ACCEPTED_ID;
    }

    /**
     * @return list<int>
     */
    private function editApplicationRoleIds(): array
    {
        return [...User::MANAGEMENT_EDITOR_ROLE_IDS, 4, self::BOILER_CHIEF_ROLE_ID];
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

    /**
     * Перенос в архив, если выполнены акт, фото, полное согласование и списания по каталожным позициям.
     */
    private function archiveCompletedApplicationIfReady(Application $application): ?string
    {
        $application->refresh();
        $application->load(['items', 'installationActPhotos']);

        if ($application->archiveIfEligible()) {
            return 'Заявка перенесена в архив выполненных.';
        }

        // Резервный сценарий для "зависших" кейсов: если по факту акт/фото есть,
        // все строки заявки завершены (согласовано или есть причина отказа),
        // и каталожные согласованные позиции списаны — архивируем принудительно.
        if (! $this->forceArchiveIfBusinessComplete($application)) {
            return null;
        }

        return 'Заявка перенесена в архив выполненных.';
    }

    private function forceArchiveIfBusinessComplete(Application $application): bool
    {
        if ($application->archived_at !== null) {
            return false;
        }

        $application->loadMissing(['items', 'installationActPhotos']);
        if ($application->items->isEmpty()) {
            return false;
        }
        if (! filled(trim((string) ($application->installation_act_path ?? '')))) {
            return false;
        }
        if ($application->installationActPhotos->isEmpty()) {
            return false;
        }

        $allResolved = $application->items->every(function (ApplicationItem $item): bool {
            if ((bool) $item->is_checked) {
                return true;
            }

            return trim((string) ($item->reason_not_selected ?? '')) !== '';
        });
        if (! $allResolved) {
            return false;
        }

        if (! $application->catalogApprovedItemsFullyIssued()) {
            return false;
        }

        $completedId = ApplicationStatus::query()
            ->where('code', ApplicationStatus::CODE_COMPLETED)
            ->value('id');

        $payload = ['archived_at' => now()];
        if ($completedId !== null) {
            $payload['application_status_id'] = (int) $completedId;
        }
        $application->forceFill($payload)->save();

        return true;
    }

    private function syncCompletionArchiveForEligibleApplications(): void
    {
        if (! Schema::hasColumn('applications', 'archived_at')) {
            return;
        }

        $candidates = Application::query()
            ->whereNull('archived_at')
            ->whereNotNull('installation_act_path')
            ->where('installation_act_path', '!=', '')
            ->with(['items', 'installationActPhotos'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        foreach ($candidates as $candidate) {
            $this->archiveCompletedApplicationIfReady($candidate);
        }
    }

    /**
     * Повторная проверка условий и перенос в архив (если последнее действие не вызвало автоархивацию).
     */
    public function tryArchiveCompletion(Request $request, Application $application): RedirectResponse
    {
        if (! $request->user()?->hasAnyRoleId([1, 2, 6, 3])) {
            abort(403, 'Операция доступна директору, техническому директору, начальнику отдела снабжения и бухгалтеру.');
        }

        $this->authorizeViewApplication($request, $application);

        if ($application->archived_at !== null) {
            return redirect()->route('applications.show', $application)
                ->with('status', 'Заявка уже в архиве выполненных.');
        }

        $hint = $this->archiveCompletedApplicationIfReady($application);
        if ($hint !== null) {
            return redirect()->route('applications.show', $application)
                ->with('status', $hint);
        }

        return redirect()->route('applications.show', $application)
            ->withErrors([
                'archive' => 'Условия переноса в архив ещё не выполнены: все позиции должны быть согласованы; загружены акт и хотя бы одно фото; по каждой каталожной строке сумма списаний по учёту (с привязкой к заявке) не меньше количества в строке.',
            ]);
    }

    private function issueDocumentRef(int $applicationId, int $itemId): string
    {
        return 'APP:'.$applicationId.':ITEM:'.$itemId;
    }

    private function customReceiptDocumentRef(int $applicationId, int $itemId): string
    {
        return 'APP:'.$applicationId.':ITEM:'.$itemId.':CUSTOM-RCPT';
    }

    private function deliveryReceiptDocumentRef(int $applicationId, int $itemId, int $warehouseId): string
    {
        return 'APP:'.$applicationId.':ITEM:'.$itemId.':DELIVERY-RCPT:WH:'.$warehouseId;
    }

    /**
     * Списание со склада подразделения после монтажа (отдельно от списания с основного склада по {@see issueDocumentRef}).
     */
    private function installationIssueDocumentRef(int $applicationId, int $itemId): string
    {
        return 'APP:'.$applicationId.':ITEM:'.$itemId.':INSTALL';
    }

    /**
     * @return Collection<int, ApplicationItem>
     */
    private function deliveredWarehouseIssueCandidates(Application $application): Collection
    {
        $application->loadMissing([
            'items.equipment.measurementUnit.unitType',
            'items.manualDetail',
            'items.deliveryWarehouse.subdivision',
        ]);

        return $application->items->filter(function (ApplicationItem $item) use ($application) {
            if (! $item->is_checked || $item->equipment_id === null) {
                return false;
            }
            if ($item->resolvedDeliveryStatus() !== ApplicationItem::DELIVERY_DELIVERED) {
                return false;
            }
            if ((int) ($item->delivery_warehouse_id ?? 0) <= 0) {
                return false;
            }

            $docRef = $this->installationIssueDocumentRef((int) $application->id, (int) $item->id);

            return ! MaterialStockMovement::query()
                ->where('type', 'issue')
                ->where('document_ref', $docRef)
                ->exists();
        })->values();
    }

    /**
     * Для доставленных на склад получателя позиций: одно списание на полную согласованную величину по строке (идемпотентно по document_ref).
     *
     * @return array{issued_lines: int, warnings: list<string>}
     */
    private function writeOffDeliveredItemsOnRecipientWarehouses(Application $application, ?User $actor, string $movementComment, ?Collection $allowedItemIds = null): array
    {
        $user = $actor;
        if (! $user) {
            return ['issued_lines' => 0, 'warnings' => []];
        }

        $issuedLines = 0;
        $warnings = [];

        foreach ($application->items as $item) {
            if ($allowedItemIds instanceof Collection && ! $allowedItemIds->contains((int) $item->id)) {
                continue;
            }
            if (! $item->is_checked || $item->equipment_id === null) {
                continue;
            }

            if ($item->resolvedDeliveryStatus() !== ApplicationItem::DELIVERY_DELIVERED) {
                continue;
            }

            $warehouseId = (int) ($item->delivery_warehouse_id ?? 0);
            if ($warehouseId <= 0) {
                continue;
            }

            $docRef = $this->installationIssueDocumentRef((int) $application->id, (int) $item->id);
            $alreadyIssued = MaterialStockMovement::query()
                ->where('type', 'issue')
                ->where('document_ref', $docRef)
                ->exists();
            if ($alreadyIssued) {
                continue;
            }

            $quantity = (float) $item->quantity;
            if ($quantity < 0.0005) {
                continue;
            }

            // Для старых заявок/данных: если по доставленной позиции не записан приход на склад получателя,
            // дописываем его идемпотентно, чтобы автосписание и автоархивация могли отработать.
            $deliveryReceiptRef = $this->deliveryReceiptDocumentRef((int) $application->id, (int) $item->id, $warehouseId);
            $hasDeliveryReceipt = MaterialStockMovement::query()
                ->where('type', 'receipt')
                ->where('document_ref', $deliveryReceiptRef)
                ->exists();
            if (! $hasDeliveryReceipt) {
                MaterialStockMovement::query()->create([
                    'equipment_id' => (int) $item->equipment_id,
                    'warehouse_id' => $warehouseId,
                    'type' => 'receipt',
                    'quantity' => $quantity,
                    'unit_price' => null,
                    'happened_at' => now(),
                    'document_ref' => $deliveryReceiptRef,
                    'counterparty' => 'Восстановление прихода по доставке заявки №'.$application->id,
                    'comment' => 'Автовосстановление прихода перед списанием доставленного оборудования.',
                    'created_by_user_id' => $user->id,
                ]);
            }

            $balance = $this->warehouseEquipmentBalance((int) $item->equipment_id, $warehouseId);
            if ($balance < $quantity - 0.0005) {
                $warnings[] = 'Не списано «'.$item->equipment_display_name.'»: недостаточно остатка на складе получателя (по данным учёта).';

                continue;
            }

            MaterialStockMovement::query()->create([
                'equipment_id' => (int) $item->equipment_id,
                'warehouse_id' => $warehouseId,
                'type' => 'issue',
                'quantity' => $quantity,
                'unit_price' => null,
                'happened_at' => now(),
                'document_ref' => $docRef,
                'counterparty' => 'Заявка №'.$application->id.' / '.$application->subdivision?->name,
                'comment' => $movementComment,
                'created_by_user_id' => $user->id,
            ]);
            $issuedLines++;
        }

        return ['issued_lines' => $issuedLines, 'warnings' => $warnings];
    }

    private function resolveMeasurementUnitIdForApplicationItem(ApplicationItem $item): int
    {
        $typeCode = trim((string) ($item->measurement_type ?? '')) ?: 'piece';
        $unitCode = trim((string) ($item->quantity_unit ?? '')) ?: 'шт';

        $id = MeasurementUnit::query()
            ->whereHas('unitType', fn ($q) => $q->where('code', $typeCode))
            ->where('code', $unitCode)
            ->value('id');

        if ($id !== null) {
            return (int) $id;
        }

        $pieceTypeId = UnitType::query()->where('code', 'piece')->value('id');
        if ($pieceTypeId) {
            $fallback = MeasurementUnit::query()
                ->where('unit_type_id', (int) $pieceTypeId)
                ->where('code', 'шт')
                ->value('id');
            if ($fallback !== null) {
                return (int) $fallback;
            }
        }

        $any = MeasurementUnit::query()->orderBy('id')->value('id');
        if ($any === null) {
            throw ValidationException::withMessages([
                'custom_supply' => 'В системе нет единиц измерения — нельзя добавить позицию в справочник оборудования.',
            ]);
        }

        return (int) $any;
    }

    private function resolveOrCreateEquipmentForCustomApplicationItem(Application $application, ApplicationItem $item): Equipment
    {
        $baseName = trim((string) ($item->base_name ?? ''));
        if ($baseName === '') {
            $baseName = trim((string) ($item->equipment_name ?? ''));
        }
        $sizeValue = trim((string) ($item->size_value ?? ''));
        $name = trim($baseName.($sizeValue !== '' ? ' '.$sizeValue : ''));

        if ($name === '' || $name === '—') {
            throw ValidationException::withMessages([
                'custom_supply' => 'У позиции нет названия для записи в справочник оборудования.',
            ]);
        }

        $name = mb_substr($name, 0, 150);
        $baseName = mb_substr($baseName !== '' ? $baseName : $name, 0, 120);
        $sizeForDb = $sizeValue !== '' ? mb_substr($sizeValue, 0, 120) : null;

        $measurementUnitId = $this->resolveMeasurementUnitIdForApplicationItem($item);

        $reservedName = $this->buildReservedEquipmentName($name, $application, $item);

        return Equipment::query()->create([
            'name' => $reservedName,
            'base_name' => $baseName,
            'size_value' => $sizeForDb,
            'measurement_unit_id' => $measurementUnitId,
            'is_catalog' => false,
        ]);
    }

    private function buildReservedEquipmentName(string $name, Application $application, ApplicationItem $item): string
    {
        $suffix = ' [РЕЗЕРВ заявка '.$application->id.', строка '.$item->id.']';
        $maxBaseLength = 150 - mb_strlen($suffix);
        if ($maxBaseLength < 1) {
            $maxBaseLength = 1;
        }

        return mb_substr($name, 0, $maxBaseLength).$suffix;
    }

    private function catalogEquipmentQuery()
    {
        return Equipment::query()->where('is_catalog', true);
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
     * @param  array<int, array<string, mixed>>  $items
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
     * @param  array{primary_field:string,primary_direction:string,secondary_field:?string,secondary_direction:string}  $sortState
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
