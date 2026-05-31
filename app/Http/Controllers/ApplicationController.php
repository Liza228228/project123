<?php

// основная логика заявок
namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationChangeJournal;
use App\Models\ApplicationInstallationActPhoto;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\Equipment;
use App\Models\MaterialStockMovement;
use App\Models\MaterialStockMovementType;
use App\Models\MeasurementUnit;
use App\Models\Subdivision;
use App\Models\TransportOption;
use App\Models\UnitType;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\AdministrationWarehouse;
use App\Support\ApplicationCatalogStockAvailability;
use App\Support\ListingPerPage;
use App\Support\PieceQuantity;
use App\Support\ReportLayoutEquipmentApplications;
use App\Support\WarehouseStockBucket;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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

    public function index(Request $request): View
    {
        $user = $request->user();
        $isSiteForeman = $user?->hasRoleId(4) ?? false;
        $isBoilerChief = $user?->hasRoleId(self::BOILER_CHIEF_ROLE_ID) ?? false;
        $isAdministratorViewer = $this->isAdministratorApplicationViewer($user);
        $canForceArchiveApplications = $this->canForceArchiveApplications($user);
        $search = trim((string) $request->input('q', ''));
        $pagination = ListingPerPage::fromRequest($request);
        $perPage = $pagination['perPage'];
        $allowedPerPage = $pagination['allowedPerPage'];
        $approvalFilter = \App\Support\ApplicationApprovalListingFilter::normalizeForUser(
            $request->input('approval_filter', $request->input('equipment_filter', 'all')),
            $user
        );
        $approvalFilterOptionGroups = \App\Support\ApplicationApprovalListingFilter::optionGroupsForUser($user);

        $foremen = collect();
        if (! $isSiteForeman && ! $isBoilerChief) {
            $foremen = User::query()
                ->where('role_id', 4)
                ->orderBy('surname')
                ->orderBy('name')
                ->get(['id', 'surname', 'name', 'patronymic', 'is_blocked']);
        }
        $selectedForemanId = null;
        if (! $isSiteForeman && ! $isBoilerChief) {
            $candidateForemanId = (int) $request->integer('foreman_user_id');
            if ($candidateForemanId > 0 && $foremen->contains('id', $candidateForemanId)) {
                $selectedForemanId = $candidateForemanId;
            }
        }

        $archiveFilter = Application::archiveFilterFromRequest($request);

        $applicationsQuery = Application::listingQuery($request);

        if ($user?->hasAnyRoleId([User::ACCOUNTANT_ROLE_ID, User::ADMINISTRATOR_ROLE_ID])) {
            $applicationsQuery->withCount('installationActPhotos');
        }

        if (! $isAdministratorViewer && $user?->hasAnyRoleId($this->managementEditorRoleIds())) {
            $draftStatusId = ApplicationStatus::idForDraft();
            $applicationsQuery
                ->where('application_status_id', '!=', $draftStatusId)
                ->visibleToManagementEditors();
        }

        if ($isBoilerChief && $user) {
            $chiefSubIds = $user->boilerChiefSubdivisions()->pluck('subdivisions.id');
            $applicationsQuery->whereIn('subdivision_id', $chiefSubIds);
            $draftStatusId = ApplicationStatus::idForDraft();
            $applicationsQuery->where(function (Builder $outer) use ($draftStatusId): void {
                $outer->where('application_status_id', '!=', $draftStatusId)
                    ->orWhereDoesntHave('user', fn (Builder $q) => $q->where('role_id', 4));
            });
        }

        if ($isSiteForeman && $user) {
            $applicationsQuery->forSiteForemanAccess($user);
        } elseif ($selectedForemanId !== null) {
            $applicationsQuery->where('user_id', $selectedForemanId);
        }

        $sortState = $this->resolveIndexSortState($request);
        $this->applyIndexSorting($applicationsQuery, $sortState);

        $applications = $applicationsQuery
            ->with([
                'archive',
                'subdivision:id,name',
                'responsibleUser:id,surname,name,patronymic',
                'items' => fn ($query) => $query
                    ->select([
                        'id',
                        'application_id',
                        'equipment_id',
                        'quantity',
                        'is_checked',
                        'reason_not_selected',
                        'custom_equipment_supply_status_id',
                        'delivery_status_id',
                        'expected_arrival_at',
                    ])
                    ->orderBy('id')
                    ->with([
                        'equipment:id,name',
                        'manualDetail:id,application_item_id,equipment_name,base_name,size_value,measurement_type,quantity_unit',
                    ]),
                'user:id,surname,name,patronymic,role_id',
                'approvedBy:id,surname,name,patronymic,role_id',
                'approvedBy.role:id,name',
                'sourceApplication:id',
                'transportOption:id,name,plate',
                'applicationStatus:id,name',
            ])
            ->paginate($perPage)
            ->withQueryString();

        \App\Support\ApplicationIndexPresenter::prepare($applications, $user);

        $customEquipmentPendingOrderCount = 0;
        if ($user?->hasAnyRoleId($this->customEquipmentOrderingRoleIds())) {
            $customEquipmentPendingOrderCount = Cache::remember(
                'applications:custom-order-pending-count',
                now()->addSeconds(30),
                fn (): int => ApplicationItem::queryPendingCustomEquipmentOrder()->count()
            );
        }

        return view('applications.index', compact(
            'applications',
            'search',
            'approvalFilter',
            'approvalFilterOptionGroups',
            'isSiteForeman',
            'isBoilerChief',
            'foremen',
            'selectedForemanId',
            'perPage',
            'allowedPerPage',
            'sortState',
            'archiveFilter',
            'customEquipmentPendingOrderCount',
            'isAdministratorViewer',
            'canForceArchiveApplications',
        ));
    }
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
            ->notArchived()
            ->whereSupplyApprovedForCustomEquipmentWorkflow()
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

        $pagination = ListingPerPage::fromRequest($request);

        $applications = $applicationsQuery
            ->paginate($pagination['perPage'])
            ->withQueryString();

        return view('applications.custom-equipment-to-order', [
            'applications' => $applications,
            'sortDate' => $sortDate,
            'perPage' => $pagination['perPage'],
            'allowedPerPage' => $pagination['allowedPerPage'],
            'defaultPerPage' => $pagination['defaultPerPage'],
        ]);
    }
    public function customEquipmentOrderForm(Request $request, Application $application): View
    {
        if (! $request->user()?->hasAnyRoleId($this->customEquipmentOrderingRoleIds())) {
            abort(403, 'Форма доступна только директору и начальнику отдела снабжения.');
        }

        $this->authorizeViewApplication($request, $application);

        if ($application->archived_at !== null) {
            abort(404);
        }

        if (! $application->isSupplyApprovedForCustomEquipmentWorkflow()) {
            abort(403, 'Раздел «Своё оборудование к заказу» доступен после сохранения согласования снабжения по заявке (и повторного сохранения после этапа котельной, если он есть).');
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
            return redirect()->to($this->customEquipmentBulkReturnUrl($request, $application))
                ->withErrors(['custom_supply' => 'Заявка в архиве.']);
        }

        if (! $application->isSupplyApprovedForCustomEquipmentWorkflow()) {
            return redirect()->route('applications.custom-equipment-to-order')
                ->withErrors(['custom_supply' => 'Сначала сохраните согласование снабжения по заявке (после этапа котельной — повторное «Сохранить согласование»).']);
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
            return redirect()->to($this->customEquipmentBulkReturnUrl($request, $application))
                ->withErrors(['custom_supply' => 'Не выбрано ни одной позиции, которую можно отметить как заказанную.']);
        }

        return redirect()->to($this->customEquipmentBulkReturnUrl($request, $application))
            ->with('status', 'Отмечено как заказано позиций: '.$updated.'.');
    }

    public function markCustomEquipmentOnWarehouseBulk(Request $request, Application $application): RedirectResponse
    {
        if (! $request->user()->hasAnyRoleId($this->customEquipmentOrderingRoleIds())) {
            abort(403);
        }

        $this->authorizeViewApplication($request, $application);

        if ($application->archived_at !== null) {
            return redirect()->to($this->customEquipmentBulkReturnUrl($request, $application))
                ->withErrors(['custom_supply' => 'Заявка в архиве.']);
        }

        if (! $application->isSupplyApprovedForCustomEquipmentWorkflow()) {
            return redirect()->route('applications.custom-equipment-to-order')
                ->withErrors(['custom_supply' => 'Сначала сохраните согласование снабжения по заявке (после этапа котельной — повторное «Сохранить согласование»).']);
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
            return redirect()->to($this->customEquipmentBulkReturnUrl($request, $application))
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
            return redirect()->to($this->customEquipmentBulkReturnUrl($request, $application))
                ->withErrors($e->errors());
        }

        if ($processed === 0) {
            return redirect()->to($this->customEquipmentBulkReturnUrl($request, $application))
                ->withErrors(['custom_supply' => 'Не выбрано ни одной позиции для прихода на основной склад.']);
        }

        return redirect()->to($this->customEquipmentBulkReturnUrl($request, $application))
            ->with('status', 'Оприходовано на основной склад «'.$mainWarehouse->name.'», позиций: '.$processed.'.');
    }
    private function transportMethodOptionsForForms(): Collection
    {
        $q = TransportOption::query()->orderBy('name');
        if (Schema::hasColumn('transport_options', 'plate')) {
            $q->whereNull('plate');
        }

        return $q->get();
    }
    private function transportOptionsWithPlateForDatalist(): Collection
    {
        if (! Schema::hasColumn('transport_options', 'plate')) {
            return new Collection;
        }

        return TransportOption::query()
            ->whereNotNull('plate')
            ->where('name', '!=', TransportOption::NAME_SERVICE_VEHICLE)
            ->orderBy('plate')
            ->get(['id', 'plate', 'label']);
    }
    private function serviceVehiclePlateOptionsForForms(): Collection
    {
        if (! Schema::hasColumn('transport_options', 'plate')) {
            return new Collection;
        }

        return TransportOption::query()
            ->where('name', TransportOption::NAME_SERVICE_VEHICLE)
            ->whereNotNull('plate')
            ->orderBy('plate')
            ->get(['id', 'plate']);
    }
    private function transportOptionIdRuleForCreateUpdateForms(): array
    {
        if (Schema::hasColumn('transport_options', 'plate')) {
            return ['nullable', Rule::exists('transport_options', 'id')->whereNull('plate')];
        }

        return ['nullable', 'exists:transport_options,id'];
    }
    private function transportOptionIdRuleForDeliveryInTransit(): array
    {
        if (Schema::hasColumn('transport_options', 'plate')) {
            return ['required', Rule::exists('transport_options', 'id')->whereNull('plate')];
        }

        return ['required', 'exists:transport_options,id'];
    }
    private function processCustomEquipmentOnWarehouseItem(Request $request, Application $application, ApplicationItem $item, Warehouse $mainWarehouse): void
    {
        $item->refresh();

        if ($item->equipment_id !== null || ! $item->canMarkCustomSupplyOnWarehouse()) {
            throw ValidationException::withMessages([
                'custom_supply' => 'Позиция уже обработана или недоступна для отметки «На складе».',
            ]);
        }

        $docRef = $this->customReceiptDocumentRef($application->id, (int) $item->id);
        $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
        $existingReceipt = MaterialStockMovement::query()
            ->where('material_stock_movement_type_id', $receiptTypeId)
            ->whereCorrelationKey($docRef)
            ->first();

        if ($existingReceipt) {
            $equipment = Equipment::query()->findOrFail((int) $existingReceipt->equipment_id);
        } else {
            $equipment = $this->resolveOrCreateEquipmentForCustomApplicationItem($application, $item);
            MaterialStockMovement::query()->create([
                'equipment_id' => $equipment->id,
                'warehouse_id' => (int) $mainWarehouse->id,
                'material_stock_movement_type_id' => $receiptTypeId,
                'quantity' => (float) $item->quantity,
                'stock_bucket' => WarehouseStockBucket::GOOD,
                'unit_price' => null,
                'counterparty' => null,
                'comment' => MaterialStockMovement::packCommentWithCorrelation(
                    $docRef,
                    'Приход по заявке №'.$application->id.' (позиция со своим названием).'
                ),
            ]);
        }

        $item->update([
            'equipment_id' => $equipment->id,
            'equipment_name' => null,
            'custom_equipment_supply_status_id' => null,
            'base_name' => trim((string) ($item->base_name ?? '')) !== '' ? $item->base_name : $equipment->name,
            'size_value' => $equipment->value,
            'delivery_status_id' => null,
            'delivery_warehouse_id' => null,
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        $this->authorizeCanCreateApplications($request);

        $subdivisions = $this->availableSubdivisionsForCreate($request);
        $equipment = $this->catalogEquipmentForForms();
        $usersQuery = User::query()
            ->where('role_id', 4)
            ->where('is_blocked', false)
            ->orderBy('surname')
            ->orderBy('name');
        if ($request->user()?->hasRoleId(self::BOILER_CHIEF_ROLE_ID)) {
            $chiefSubdivisionIds = $request->user()->boilerChiefSubdivisions()->pluck('subdivisions.id');
            $usersQuery->whereHas('assignedSubdivisions', function ($query) use ($chiefSubdivisionIds): void {
                $query->whereIn('subdivisions.id', $chiefSubdivisionIds);
            });
        }
        $users = $usersQuery->get();
        $prefill = null;
        $transportOptions = $this->transportMethodOptionsForForms();

        $warehousesBySubdivision = $this->warehousesBySubdivisionForUi();
        $subdivisionIdsByForeman = $this->subdivisionIdsByForemanForUi();
        $foremanIdsBySubdivision = $this->foremanIdsBySubdivisionForUi();
        $measurementMeta = $this->measurementMetaForUi();
        $measurementMeta['catalogById'] = $this->catalogEquipmentMeasurementMetaById($equipment);
        $usesDraftSubmitFlow = $this->userUsesApplicationDraftSubmitFlow($request);
        $responsibleFilterMode = $this->usesSubdivisionFirstResponsibleFilter($request)
            ? 'subdivision_first'
            : 'foreman_first';

        return view('applications.create', compact(
            'subdivisions',
            'equipment',
            'users',
            'prefill',
            'transportOptions',
            'warehousesBySubdivision',
            'subdivisionIdsByForeman',
            'foremanIdsBySubdivision',
            'measurementMeta',
            'usesDraftSubmitFlow',
            'responsibleFilterMode',
        ));
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
        $installationIssueMaxQuantitiesByItemId = [];
        if ($selectedApplication instanceof Application) {
            $selectedApplication->loadMissing([
                'items.equipment.measurementUnit.unitType',
                'items.manualDetail',
                'items.deliveryWarehouse.subdivision',
            ]);
            $deliveredWarehouseIssueCandidates = $this->deliveredWarehouseIssueCandidates($selectedApplication);
            $installationIssueMaxQuantitiesByItemId = $this->installationIssueMaxQuantitiesByItemId($selectedApplication);
        }

        return view('applications.installation-act-upload', compact(
            'applications',
            'preselectedApplicationId',
            'selectedApplication',
            'deliveredWarehouseIssueCandidates',
            'installationIssueMaxQuantitiesByItemId',
        ));
    }
    public function browseInstallationActs(Request $request): View
    {
        $user = $request->user();
        if (! $user || ! $user->hasRoleId(User::ACCOUNTANT_ROLE_ID)) {
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
            'installation_act' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'issue_item_ids' => ['nullable', 'array'],
            'issue_item_ids.*' => ['integer'],
            'issue_quantities' => ['nullable', 'array'],
            'issue_quantities.*' => ['nullable', 'numeric'],
        ], [
            'application_id.required' => 'Выберите заявку.',
            'installation_act.required' => 'Загрузите файл акта установки.',
            'installation_act.mimes' => 'Акт установки: только PDF.',
            'installation_act.max' => 'Максимальный размер файла акта: 10 МБ.',
            'issue_item_ids.array' => 'Выбор оборудования для списания передан некорректно.',
            'issue_quantities.array' => 'Количество к списанию передано некорректно.',
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

            $issueQuantitiesByItemId = $this->resolveInstallationActIssueQuantities(
                $application,
                $deliveredCandidates,
                $selectedIssueItemIds,
                is_array($validated['issue_quantities'] ?? null) ? $validated['issue_quantities'] : []
            );
        } else {
            $issueQuantitiesByItemId = [];
        }

        $installationStockSummary = [
            'issued_lines' => 0,
            'warnings' => [],
        ];

        DB::transaction(function () use ($application, $actFile, $photoFiles, $storageDisk, $installationActsDir, $installationPhotosDir, $request, $selectedIssueItemIds, $issueQuantitiesByItemId, &$installationStockSummary) {
            $application->load('installationActPhotos');
            foreach ($application->installationActPhotos as $photo) {
                $this->deleteStoredPublicDiskFileIfExists($photo->path);
            }
            $application->installationActPhotos()->delete();

            $this->deleteStoredPublicDiskFileIfExists($application->act_of_installation);
            $newActName = $this->safeUploadedOriginalName($actFile, 'act-installation');
            $newActPath = $actFile->storeAs($installationActsDir, $newActName, $storageDisk);
            $application->update(['act_of_installation' => $newActPath]);

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
                $selectedIssueItemIds,
                $issueQuantitiesByItemId
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
    public function repeat(Request $request, Application $application): View
    {
        $this->authorizeCanRepeatApplications($request);
        $this->authorizeViewApplication($request, $application);

        $application->load(['items']);
        $subdivisions = $this->availableSubdivisionsForCreate($request);
        $equipment = $this->catalogEquipmentForForms();
        $usersQuery = User::query()
            ->where('role_id', 4)
            ->where('is_blocked', false)
            ->orderBy('surname')
            ->orderBy('name');
        if ($request->user()?->hasRoleId(self::BOILER_CHIEF_ROLE_ID)) {
            $chiefSubdivisionIds = $request->user()->boilerChiefSubdivisions()->pluck('subdivisions.id');
            $usersQuery->whereHas('assignedSubdivisions', function ($query) use ($chiefSubdivisionIds): void {
                $query->whereIn('subdivisions.id', $chiefSubdivisionIds);
            });
        }
        $users = $usersQuery->get();
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
        $transportOptions = $this->transportMethodOptionsForForms();

        $warehousesBySubdivision = $this->warehousesBySubdivisionForUi();
        $subdivisionIdsByForeman = $this->subdivisionIdsByForemanForUi();
        $foremanIdsBySubdivision = $this->foremanIdsBySubdivisionForUi();
        $measurementMeta = $this->measurementMetaForUi();
        $measurementMeta['catalogById'] = $this->catalogEquipmentMeasurementMetaById($equipment);

        $usesDraftSubmitFlow = $this->userUsesApplicationDraftSubmitFlow($request);
        $responsibleFilterMode = $this->usesSubdivisionFirstResponsibleFilter($request)
            ? 'subdivision_first'
            : 'foreman_first';

        return view('applications.create', compact(
            'subdivisions',
            'equipment',
            'users',
            'prefill',
            'transportOptions',
            'warehousesBySubdivision',
            'subdivisionIdsByForeman',
            'foremanIdsBySubdivision',
            'measurementMeta',
            'usesDraftSubmitFlow',
            'responsibleFilterMode',
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeCanCreateApplications($request);

        $isSiteForemanLike = $request->user()->hasAnyRoleId([4, self::BOILER_CHIEF_ROLE_ID]);
        $isSiteForeman = $request->user()->hasRoleId(4);
        $isBoilerChiefCreator = $request->user()->hasRoleId(self::BOILER_CHIEF_ROLE_ID);
        $isManagementCreator = $request->user()->hasAnyRoleId(User::MANAGEMENT_EDITOR_ROLE_IDS);
        $allowedSubdivisionIds = $this->availableSubdivisionsForCreate($request)->pluck('id')->map(fn ($id) => (int) $id);

        $responsibleUserRules = $isBoilerChiefCreator
            ? ['required', 'integer', 'min:1']
            : ($isManagementCreator
                ? ['nullable', 'integer', 'min:1']
                : [
                    'nullable',
                    'integer',
                    Rule::exists('users', 'id')->where('role_id', 4)->where('is_blocked', false),
                ]);

        $validated = $request->validate([
            'submit_action' => ['nullable', Rule::in(['save', 'submit_to_boiler_chief', 'submit_for_management'])],
            'subdivision_id' => ['required', 'exists:subdivisions,id'],
            'source_application_id' => ['nullable', 'exists:applications,id'],
            'responsible_user_id' => $responsibleUserRules,
            'desired_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.equipment_id' => ['nullable', 'exists:equipment,id'],
            'items.*.equipment_name' => ['nullable', 'string', 'max:'.ApplicationItem::EQUIPMENT_NAME_MAX_LENGTH],
            'items.*.size_value' => ['nullable', 'string', 'max:'.ApplicationItem::SIZE_VALUE_MAX_LENGTH],
            'items.*.measurement_type' => ['nullable', Rule::in(array_keys($this->measurementUnitsMap()))],
            'items.*.quantity_unit' => ['nullable', 'string', 'max:'.ApplicationItem::QUANTITY_UNIT_MAX_LENGTH],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'transport_option_id' => $this->transportOptionIdRuleForCreateUpdateForms(),
        ], array_merge([
            'desired_delivery_date.after_or_equal' => 'Желаемая дата поставки не может быть в прошлом.',
            'items.min' => 'Добавьте хотя бы одну позицию оборудования.',
            'responsible_user_id.required' => 'Выберите ответственного мастера участка для выбранного подразделения.',
        ], ApplicationItem::applicationFormValidationMessages()));

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

        $subdivisionId = (int) $validated['subdivision_id'];
        $hasBoilerChief = Subdivision::hasBoilerChiefAssigned($subdivisionId);
        if ($isSiteForeman && $hasBoilerChief) {
            $submitAction = (string) $request->input('submit_action', '');
            if (! in_array($submitAction, ['save', 'submit_to_boiler_chief'], true)) {
                throw ValidationException::withMessages([
                    'submit_action' => 'Укажите: сохранить черновик или отправить заявку на согласование.',
                ]);
            }
        }
        if ($isBoilerChiefCreator) {
            $submitAction = (string) $request->input('submit_action', '');
            if (! in_array($submitAction, ['save', 'submit_for_management'], true)) {
                throw ValidationException::withMessages([
                    'submit_action' => 'Укажите: сохранить черновик или отправить заявку на согласование.',
                ]);
            }
        }

        if (($isBoilerChiefCreator || $isManagementCreator) && ! empty($validated['responsible_user_id'])) {
            $responsibleId = (int) $validated['responsible_user_id'];
            $allowedForemanIds = $this->activeForemenForSubdivisionQuery($subdivisionId)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();
            if (! in_array($responsibleId, $allowedForemanIds, true)) {
                throw ValidationException::withMessages([
                    'responsible_user_id' => 'Выберите мастера участка, назначенного на выбранное подразделение.',
                ]);
            }
        }

        $this->validateSubdivisionAllowedForResponsibleUser(
            (int) $validated['subdivision_id'],
            isset($validated['responsible_user_id']) ? (int) $validated['responsible_user_id'] : null
        );

        $this->validateCustomEquipmentRowsHaveMeasurementType($validated['items']);
        $this->validateSubstantiveEquipmentItemQuantities($validated['items']);
        $this->validateMeasurementPairs($validated['items']);
        $this->validateUniqueEquipmentLines($validated['items']);

        $sourceId = isset($validated['source_application_id']) ? (int) $validated['source_application_id'] : null;
        if ($sourceId !== null && $sourceId > 0) {
            $sourceApplication = Application::query()->find($sourceId);
            if ($sourceApplication === null) {
                throw ValidationException::withMessages([
                    'source_application_id' => 'Исходная заявка не найдена.',
                ]);
            }
            $this->authorizeViewApplication($request, $sourceApplication);
        }

        $hasValidItem = collect($validated['items'])->contains(fn (array $item) => ! empty($item['equipment_id'] ?? null) || ! empty(trim($item['equipment_name'] ?? ''))
        );
        if (! $hasValidItem) {
            return back()->withErrors(['equipment' => 'Укажите оборудование: выберите из списка или введите вручную.'])->withInput();
        }

        $validated['user_id'] = $request->user()->id;
        if ($isSiteForeman) {
            $validated['responsible_user_id'] = $request->user()->id;
        } elseif (! $isBoilerChiefCreator && empty($validated['responsible_user_id'])) {
            $validated['responsible_user_id'] = $request->user()->id;
        }

        $applicationStatusId = $this->resolveApplicationStatusIdOnCreate(
            $isSiteForeman,
            $isBoilerChiefCreator,
            $subdivisionId,
            (string) ($request->input('submit_action') ?? '')
        );
        $submittedToBoilerChief = $applicationStatusId === ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING)
            && $isSiteForeman
            && Subdivision::hasBoilerChiefAssigned($subdivisionId);
        $submittedForManagement = $applicationStatusId === ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING)
            && $isBoilerChiefCreator;

        $application = Application::create([
            'subdivision_id' => $validated['subdivision_id'],
            'source_application_id' => $validated['source_application_id'] ?? null,
            'responsible_user_id' => $validated['responsible_user_id'],
            'transport_option_id' => null,
            'desired_delivery_date' => $validated['desired_delivery_date'],
            'user_id' => $validated['user_id'],
            'application_status_id' => $applicationStatusId,
        ]);

        $itemsToSave = $this->expandCatalogRowsAgainstMainWarehouseVirtualStock($validated['items']);

        foreach ($itemsToSave as $item) {
            $typeId = $item['equipment_id'] ?? null;
            $name = trim($item['equipment_name'] ?? '');
            if (empty($typeId) && $name === '') {
                continue;
            }
            $normalized = $this->normalizeItemPayload($item, $typeId ? Equipment::query()->find((int) $typeId)?->name : null);
            $createdItem = $application->items()->create([
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
            ]);
            $this->syncCatalogItemManualDetail($createdItem, $normalized);
        }

        $application->refresh();
        $this->applyBoilerChiefAutoGate($application);
        $this->applyManagementDelegationSupplyRelease($application, $request->user());

        $statusMessage = match (true) {
            $application->isManagementDelegatedToSiteForeman() => 'Заявка создана и передана ответственному мастеру участка. Позиции доступны для заказа без дополнительного согласования.',
            $application->isForemanDraftBeforeBoilerChief() => 'Заявка сохранена. Её можно изменить и отправить на согласование, когда будете готовы.',
            $application->isBoilerChiefDraftBeforeManagement() => 'Заявка сохранена. Её можно изменить и отправить на согласование, когда будете готовы.',
            $submittedToBoilerChief => 'Заявка создана и отправлена на согласование.',
            $submittedForManagement => 'Заявка создана и отправлена на согласование руководству и снабжению.',
            default => 'Заявка успешно создана.',
        };

        return redirect()->route('applications.index')
            ->with('status', $statusMessage);
    }

    public function submitToBoilerChief(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeCanEditApplications($request);
        $this->authorizeViewApplication($request, $application);
        $this->authorizeForemanCanModifyApplication($request, $application);

        if ($application->isForemanDraftBeforeBoilerChief()) {
            if (! $application->items()->exists()) {
                return $this->redirectAfterApplicationSubmitAction(
                    $request,
                    $application,
                    errors: ['submit' => 'Добавьте хотя бы одну позицию оборудования перед отправкой.']
                );
            }

            $application->update([
                'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING),
            ]);

            return $this->redirectAfterApplicationSubmitAction(
                $request,
                $application,
                status: 'Заявка отправлена на согласование. Редактирование больше недоступно.'
            );
        }

        if ($application->foremanCanResubmitAwaitingItemsToBoilerChief()) {
            if (! $application->items()->exists()) {
                return $this->redirectAfterApplicationSubmitAction(
                    $request,
                    $application,
                    errors: ['submit' => 'Добавьте хотя бы одну позицию оборудования перед отправкой.']
                );
            }

            $this->finalizeForemanResubmissionToBoilerChief($application);

            return $this->redirectAfterApplicationSubmitAction(
                $request,
                $application,
                status: 'Изменённые позиции отправлены на повторное согласование начальнику котельной. Редактирование больше недоступно.'
            );
        }

        if ($application->boilerChiefSubdivisionReviewCycleStarted() && ! $application->isForemanDraftBeforeBoilerChief()) {
            return $this->redirectAfterApplicationSubmitAction(
                $request,
                $application,
                errors: ['submit' => 'Измените и сохраните хотя бы одну несогласованную позицию перед повторной отправкой.']
            );
        }

        return $this->redirectAfterApplicationSubmitAction(
            $request,
            $application,
            errors: ['submit' => 'Заявка уже отправлена или не требует отправки на согласование.']
        );
    }

    public function submitForManagement(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeCanEditApplications($request);
        $this->authorizeViewApplication($request, $application);

        if (! $request->user()?->hasRoleId(self::BOILER_CHIEF_ROLE_ID)) {
            abort(403, 'Отправка на согласование руководству доступна только начальнику котельной.');
        }

        if (! $application->boilerChiefCanSubmitToManagement()) {
            return $this->redirectAfterApplicationSubmitAction(
                $request,
                $application,
                errors: ['submit' => 'Заявка уже отправлена или не требует отправки на согласование.']
            );
        }

        if (! $application->items()->exists()) {
            return $this->redirectAfterApplicationSubmitAction(
                $request,
                $application,
                errors: ['submit' => 'Добавьте хотя бы одну позицию оборудования перед отправкой.']
            );
        }

        $update = [];
        if ($application->isBoilerChiefDraftBeforeManagement()) {
            $update['application_status_id'] = ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING);
            $update['approved_by_user_id'] = $request->user()->id;
        } elseif ($application->isForemanCreatedApplication()
            && ! $application->needsBoilerChiefReviewBeforeManagement()) {
            $update['application_status_id'] = ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING);
            $update['approved_by_user_id'] = $request->user()->id;
        }
        $application->update($update);

        return $this->redirectAfterApplicationSubmitAction(
            $request,
            $application,
            status: 'Заявка отправлена на согласование руководству и снабжению. Редактирование больше недоступно.'
        );
    }

    public function show(Request $request, Application $application): View|RedirectResponse
    {
        $this->authorizeViewApplication($request, $application);

        $application->load([
            'subdivision.warehouses',
            'responsibleUser',
            'user',
            'approvedBy.role',
            'items.equipment.measurementUnit.unitType',
            'items.manualDetail',
            'items.transportOption',
            'items.deliveryWarehouse',
            'changeJournalEntries.user',
            'items.changeJournalEntries.user',
            'removedItems.equipment',
            'removedItems.manualDetail',
            'removedItems.removedBy',
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

        $catalogStockOnMainWarehouseByItemId = [];
        if ($mainWarehouse) {
            $reservedByEquipmentId = ApplicationCatalogStockAvailability::reservedQuantitiesByEquipmentId(
                (int) $application->id
            );
            foreach ($application->items as $item) {
                if ($item->equipment_id) {
                    $equipmentId = (int) $item->equipment_id;
                    $sizeVariant = PieceQuantity::isClothingMeasurement($item->storedMeasurementType())
                        ? ($item->storedSizeValue() ?? '')
                        : '';
                    $physical = $sizeVariant !== ''
                        ? ApplicationCatalogStockAvailability::physicalBalanceOnWarehouse(
                            $equipmentId,
                            (int) $mainWarehouse->id,
                            $sizeVariant
                        )
                        : $this->warehouseEquipmentBalance($equipmentId, (int) $mainWarehouse->id);
                    $stockKey = ApplicationCatalogStockAvailability::stockAggregateKey(
                        $equipmentId,
                        $sizeVariant !== '' ? $sizeVariant : null
                    );
                    $reserved = (float) ($reservedByEquipmentId[$stockKey] ?? 0.0);
                    $catalogStockOnMainWarehouseByItemId[(int) $item->id] = max(0.0, $physical - $reserved);
                }
            }
        }

        $deliveredWarehouseIssueCandidates = $this->deliveredWarehouseIssueCandidates($application);
        $transportOptions = $this->transportMethodOptionsForForms();
        $companyDeliveryVehicles = $this->transportOptionsWithPlateForDatalist();
        $serviceVehiclePlateOptions = $this->serviceVehiclePlateOptionsForForms();

        $canChangeApplicationResponsible = $this->canOfferApplicationResponsibleChange($request, $application);
        $canForemanSubmitToBoilerChief = $request->user()?->hasRoleId(4)
            && $application->needsSubmitToApprovalBy($request->user());
        $canBoilerChiefSubmitForManagement = $request->user()?->hasRoleId(self::BOILER_CHIEF_ROLE_ID)
            && $application->needsSubmitToApprovalBy($request->user());
        $canForceArchiveApplications = $this->canForceArchiveApplications($request->user());
        $measurementMeta = $this->measurementMetaForUi();
        $equipmentNameMax = ApplicationItem::EQUIPMENT_NAME_MAX_LENGTH;

        return view('applications.show', compact(
            'application',
            'mainWarehouse',
            'issuedByItemId',
            'remainingByItemId',
            'catalogStockOnMainWarehouseByItemId',
            'boilerChiefDeliverySubdivisions',
            'deliveredWarehouseIssueCandidates',
            'transportOptions',
            'companyDeliveryVehicles',
            'serviceVehiclePlateOptions',
            'canChangeApplicationResponsible',
            'canForemanSubmitToBoilerChief',
            'canBoilerChiefSubmitForManagement',
            'canForceArchiveApplications',
            'measurementMeta',
            'equipmentNameMax',
        ));
    }

    public function editResponsible(Request $request, Application $application): View
    {
        $this->authorizeCanChangeApplicationResponsible($request);
        $this->authorizeViewApplication($request, $application);

        if ($application->archived_at !== null) {
            abort(404);
        }

        if ($this->applicationItemsLockedForResponsibleChange($application)) {
            abort(403, 'Смена ответственного недоступна: по заявке уже есть оборудование «В пути» или «Доставлено».');
        }

        $application->loadMissing('responsibleUser:id,surname,name,patronymic,role_id,is_blocked', 'subdivision:id,name');
        $responsible = $application->responsibleUser;
        if (! $responsible || ! $responsible->hasRoleId(4)) {
            abort(404);
        }

        $replacementForemen = $this->activeForemenForSubdivisionQuery((int) $application->subdivision_id)
            ->where('users.id', '!=', (int) $responsible->id)
            ->orderBy('surname')
            ->orderBy('name')
            ->get(['id', 'surname', 'name', 'patronymic']);

        return view('applications.responsible-edit', compact('application', 'responsible', 'replacementForemen'));
    }

    public function updateResponsible(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeCanChangeApplicationResponsible($request);
        $this->authorizeViewApplication($request, $application);

        if ($application->archived_at !== null) {
            abort(404);
        }

        if ($this->applicationItemsLockedForResponsibleChange($application)) {
            abort(403, 'Смена ответственного недоступна: по заявке уже есть оборудование «В пути» или «Доставлено».');
        }

        $application->loadMissing('responsibleUser:id,role_id,is_blocked');
        $responsible = $application->responsibleUser;
        if (! $responsible || ! $responsible->hasRoleId(4)) {
            abort(404);
        }

        $existsActiveForemanRule = Rule::exists('users', 'id')->where('role_id', 4)->where('is_blocked', false);

        $validated = $request->validate([
            'responsible_user_id' => [
                'required',
                'integer',
                Rule::notIn([(int) $responsible->id]),
                $existsActiveForemanRule,
            ],
        ], [
            'responsible_user_id.required' => 'Выберите мастера участка.',
            'responsible_user_id.not_in' => 'Нужно выбрать другого мастера, не текущего ответственного.',
        ]);

        $newId = (int) $validated['responsible_user_id'];
        $this->validateSubdivisionAllowedForResponsibleUser((int) $application->subdivision_id, $newId);

        $application->update(['responsible_user_id' => $newId]);

        return redirect()
            ->route('applications.show', $application)
            ->with('status', 'Ответственный по заявке обновлён.');
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

        DB::transaction(function () use ($rows, $application, $mainWarehouse, $validated): void {
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

                $sizeVariant = PieceQuantity::isClothingMeasurement($item->storedMeasurementType())
                    ? ($item->storedSizeValue() ?? '')
                    : '';
                $warehouseBalance = $sizeVariant !== ''
                    ? ApplicationCatalogStockAvailability::physicalBalanceOnWarehouse(
                        (int) $item->equipment_id,
                        (int) $mainWarehouse->id,
                        $sizeVariant
                    )
                    : $this->warehouseEquipmentBalance((int) $item->equipment_id, (int) $mainWarehouse->id);
                if ($warehouseBalance < $row['quantity'] - 0.0005) {
                    throw ValidationException::withMessages([
                        'stock' => 'Недостаточно остатка на складе "Администрация" для одной из позиций.',
                    ]);
                }

                $issueRef = $this->issueDocumentRef($application->id, (int) $item->id);
                MaterialStockMovement::query()->create([
                    'equipment_id' => (int) $item->equipment_id,
                    'warehouse_id' => (int) $mainWarehouse->id,
                    'material_stock_movement_type_id' => MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE),
                    'quantity' => $row['quantity'],
                    'stock_bucket' => WarehouseStockBucket::GOOD,
                    'unit_price' => null,
                    'counterparty' => 'Заявка №'.$application->id.' / '.$application->subdivision?->name,
                    'comment' => MaterialStockMovement::packCommentWithCorrelation(
                        $issueRef,
                        trim((string) ($validated['comment'] ?? '')) ?: 'Списание по заявке.'
                    ),
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
        $redirect = $this->redirectIfApplicationEditUnavailable($request, $application);
        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        }

        $subdivisions = $request->user()->hasAnyRoleId(User::MANAGEMENT_EDITOR_ROLE_IDS)
            ? Subdivision::query()->active()->orderBy('name')->get()
            : $this->availableSubdivisionsForCreate($request);
        $equipment = $this->catalogEquipmentForForms();

        $transportOptions = $this->transportMethodOptionsForForms();

        $application->load(['items.equipment.measurementUnit.unitType', 'items.manualDetail', 'applicationStatus']);

        $warehousesBySubdivision = $this->warehousesBySubdivisionForUi();
        $subdivisionIdsByForeman = $this->subdivisionIdsByForemanForUi();
        $measurementMeta = $this->measurementMetaForUi();
        $measurementMeta['catalogById'] = $this->catalogEquipmentMeasurementMetaById($equipment);

        $managementMayEditBoilerApprovedEquipment = $request->user()->hasAnyRoleId(User::MANAGEMENT_EDITOR_ROLE_IDS)
            && $application->managementCanEditApplication();

        $usesDraftSubmitFlow = $this->userUsesApplicationDraftSubmitFlow($request);
        $isCreatorDraft = $application->isCreatorDraftApplication();
        $foremanMayReviseRejectedByBoilerChiefOnly = $request->user()?->hasRoleId(4)
            && $application->foremanCanReviseAfterBoilerChiefRejection();
        $foremanCanResubmitToBoilerChief = $application->foremanCanResubmitAwaitingItemsToBoilerChief();

        return view('applications.edit', compact(
            'application',
            'subdivisions',
            'equipment',
            'transportOptions',
            'warehousesBySubdivision',
            'subdivisionIdsByForeman',
            'measurementMeta',
            'managementMayEditBoilerApprovedEquipment',
            'usesDraftSubmitFlow',
            'isCreatorDraft',
            'foremanMayReviseRejectedByBoilerChiefOnly',
            'foremanCanResubmitToBoilerChief',
        ));
    }

    public function update(Request $request, Application $application): RedirectResponse
    {
        $redirect = $this->redirectIfApplicationEditUnavailable($request, $application);
        if ($redirect instanceof RedirectResponse) {
            return $redirect;
        }

        $isSiteForeman = $request->user()->hasRoleId(4);
        $isBoilerChiefUser = $request->user()->hasRoleId(self::BOILER_CHIEF_ROLE_ID);
        $allowedSubdivisionIds = $this->availableSubdivisionsForCreate($request)->pluck('id')->map(fn ($id) => (int) $id);
        $application->load(['items.equipment.measurementUnit.unitType', 'items.manualDetail', 'applicationStatus']);
        $wasCreatorDraftBeforeUpdate = $application->isCreatorDraftApplication();
        $subdivisionIdForDraftRules = (int) $application->subdivision_id;

        if ($wasCreatorDraftBeforeUpdate && $isSiteForeman && Subdivision::hasBoilerChiefAssigned($subdivisionIdForDraftRules)) {
            $submitAction = (string) $request->input('submit_action', '');
            if (! in_array($submitAction, ['save', 'submit_to_boiler_chief'], true)) {
                throw ValidationException::withMessages([
                    'submit_action' => 'Укажите: сохранить черновик или отправить заявку на согласование.',
                ]);
            }
        }
        if ($wasCreatorDraftBeforeUpdate && $isBoilerChiefUser) {
            $submitAction = (string) $request->input('submit_action', '');
            if (! in_array($submitAction, ['save', 'submit_for_management'], true)) {
                throw ValidationException::withMessages([
                    'submit_action' => 'Укажите: сохранить черновик или отправить заявку на согласование.',
                ]);
            }
        }

        $rules = [
            'submit_action' => ['nullable', Rule::in(['save', 'submit_to_boiler_chief', 'submit_for_management'])],
            'subdivision_id' => ['required', 'exists:subdivisions,id'],
            'field_change_reasons' => ['nullable', 'array'],
            'field_change_reasons.subdivision_id' => ['nullable', 'string', 'max:500'],
            'field_change_reasons.desired_delivery_date' => ['nullable', 'string', 'max:500'],
            'item_change_reasons' => ['nullable', 'array'],
            'item_change_reasons.*' => ['nullable', 'string', 'max:500'],
            'items.*.addition_reason' => ['nullable', 'string', 'max:500'],
            'removed_item_reasons' => ['nullable', 'array'],
            'removed_item_reasons.*' => ['nullable', 'string', 'max:500'],
            'desired_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => [
                'nullable',
                'integer',
                Rule::exists('application_items', 'id')->where('application_id', $application->id),
            ],
            'items.*.equipment_id' => ['nullable', 'exists:equipment,id'],
            'items.*.equipment_name' => ['nullable', 'string', 'max:'.ApplicationItem::EQUIPMENT_NAME_MAX_LENGTH],
            'items.*.size_value' => ['nullable', 'string', 'max:'.ApplicationItem::SIZE_VALUE_MAX_LENGTH],
            'items.*.measurement_type' => ['nullable', Rule::in(array_keys($this->measurementUnitsMap()))],
            'items.*.quantity_unit' => ['nullable', 'string', 'max:'.ApplicationItem::QUANTITY_UNIT_MAX_LENGTH],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'transport_option_id' => $this->transportOptionIdRuleForCreateUpdateForms(),
        ];

        $validated = $request->validate($rules, array_merge([
            'desired_delivery_date.after_or_equal' => 'Желаемая дата поставки не может быть в прошлом.',
            'items.min' => 'Добавьте хотя бы одну позицию оборудования.',
        ], ApplicationItem::applicationFormValidationMessages()));

        $requestedSubdivisionId = (int) $validated['subdivision_id'];
        $currentSubdivisionId = (int) $application->subdivision_id;
        if ($requestedSubdivisionId !== $currentSubdivisionId) {
            throw ValidationException::withMessages([
                'subdivision_id' => 'Подразделение нельзя изменить при редактировании заявки.',
            ]);
        }
        $validated['subdivision_id'] = $currentSubdivisionId;

        $managementMayEditCheckedEquipmentLines = $request->user()->hasAnyRoleId(User::MANAGEMENT_EDITOR_ROLE_IDS)
            && $application->managementCanEditApplication();
        $foremanMayReviseRejectedByBoilerChiefOnly = $isSiteForeman
            && $application->foremanCanReviseAfterBoilerChiefRejection();

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

        if ($isBoilerChiefUser) {
            $chiefSubs = $request->user()->boilerChiefSubdivisions()->pluck('subdivisions.id')->map(fn ($id) => (int) $id);
            if (! $chiefSubs->contains((int) $validated['subdivision_id'])) {
                throw ValidationException::withMessages([
                    'subdivision_id' => 'Вы не можете перевести заявку на подразделение вне вашей зоны ответственности.',
                ]);
            }
        }

        if ($isSiteForeman) {
            $this->validateSubdivisionAllowedForResponsibleUser(
                (int) $validated['subdivision_id'],
                (int) $request->user()->id
            );
        }

        $this->validateCustomEquipmentRowsHaveMeasurementType($validated['items']);
        $this->validateSubstantiveEquipmentItemQuantities($validated['items']);
        $this->validateMeasurementPairs($validated['items']);
        $this->validateUniqueEquipmentLines($validated['items']);

        if (in_array((string) $request->input('submit_action'), ['submit_to_boiler_chief', 'submit_for_management'], true)
            && ! $this->willHaveEquipmentForSubmission($application, $validated['items'])) {
            throw ValidationException::withMessages([
                'submit_action' => 'Добавьте хотя бы одну позицию оборудования перед отправкой.',
            ]);
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

            if ($foremanMayReviseRejectedByBoilerChiefOnly && ! $itemId) {
                if ($typeId !== null || $name !== '') {
                    throw ValidationException::withMessages([
                        'equipment' => 'На этом этапе можно изменять только позиции, не согласованные начальником котельной.',
                    ]);
                }

                continue;
            }

            if ($itemId) {
                $existing = $application->items->firstWhere('id', $itemId);
                if (! $existing) {
                    throw ValidationException::withMessages([
                        'equipment' => 'Некорректная позиция заявки.',
                    ]);
                }

                if ($foremanMayReviseRejectedByBoilerChiefOnly) {
                    if ($existing->is_checked) {
                        if (! $this->applicationItemRowMatchesStored($existing, $row)) {
                            throw ValidationException::withMessages([
                                'equipment' => 'Согласованные начальником котельной позиции нельзя изменять.',
                            ]);
                        }
                    } elseif (! $application->itemIsRejectedByBoilerChief($existing)) {
                        throw ValidationException::withMessages([
                            'equipment' => 'Можно изменять только позиции, не согласованные начальником котельной.',
                        ]);
                    }
                }

                if ($existing->is_checked) {
                    if ($this->applicationItemRowMatchesStored($existing, $row)) {
                        continue;
                    }
                    if (! $managementMayEditCheckedEquipmentLines) {
                        throw ValidationException::withMessages([
                            'equipment' => 'Согласованное оборудование нельзя изменять.',
                        ]);
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

            $toCreate[] = $row;

            continue;
        }

        $toCreateExpanded = $this->expandCatalogRowsAgainstMainWarehouseVirtualStock($toCreate);

        $approvedCount = $application->items->where('is_checked', true)->count();
        $linesWithEquipment = $approvedCount + count($seenUnapprovedIds) + count($toCreateExpanded);
        if ($linesWithEquipment < 1) {
            return back()->withErrors(['equipment' => 'Укажите оборудование: выберите из списка или введите вручную.'])->withInput();
        }

        $submittedItemIds = $itemIdsInRequest->values()->all();

        $activeIdsBefore = $application->items->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $removedActiveIds = array_values(array_diff($activeIdsBefore, $submittedItemIds));

        foreach ($removedActiveIds as $removedId) {
            $removalReason = trim((string) $request->input("removed_item_reasons.{$removedId}", ''));
            if ($removalReason === '') {
                throw ValidationException::withMessages([
                    "removed_item_reasons.{$removedId}" => 'Укажите причину снятия этой позиции с заявки (она останется в истории для мастера участка).',
                ]);
            }
            if (mb_strlen($removalReason) < 3) {
                throw ValidationException::withMessages([
                    "removed_item_reasons.{$removedId}" => 'Причина снятия позиции должна быть не короче 3 символов.',
                ]);
            }
        }

        $this->validateApplicationEditPerFieldChangeReasons(
            $request,
            $application,
            $validated,
            $foremanMayReviseRejectedByBoilerChiefOnly,
            $managementMayEditCheckedEquipmentLines,
        );

        $subdivisionChanged = (int) $validated['subdivision_id'] !== (int) $application->subdivision_id;
        $deliveryDateChanged = $application->desired_delivery_date?->toDateString() !== $validated['desired_delivery_date'];

        if (! $managementMayEditCheckedEquipmentLines) {
            foreach ($application->items as $item) {
                if (! (bool) $item->is_checked) {
                    continue;
                }
                if (! in_array((int) $item->id, $submittedItemIds, true)) {
                    throw ValidationException::withMessages([
                        'equipment' => 'Согласованную позицию нельзя убрать из заявки.',
                    ]);
                }
            }
        }

        $previousSubdivisionId = (int) $application->subdivision_id;

        DB::transaction(function () use (
            $application,
            $validated,
            $toCreateExpanded,
            $request,
            $isSiteForeman,
            $submittedItemIds,
            $previousSubdivisionId,
            $managementMayEditCheckedEquipmentLines,
            $foremanMayReviseRejectedByBoilerChiefOnly,
            $wasCreatorDraftBeforeUpdate,
            $subdivisionChanged,
            $deliveryDateChanged,
        ) {
            $nextUserId = (int) $application->user_id;

            $responsibleUserId = $application->responsible_user_id;
            if ($isSiteForeman && ! $application->isBoilerChiefCreatedApplication()) {
                $responsibleUserId = $request->user()->id;
            }
            $existingApprovedByUserId = $application->approved_by_user_id;

            $applicationCoreUpdate = [
                'user_id' => $nextUserId,
                'subdivision_id' => $validated['subdivision_id'],
                'responsible_user_id' => $responsibleUserId,
                'transport_option_id' => $application->transport_option_id,
                'desired_delivery_date' => $validated['desired_delivery_date'],
            ];
            $journalUserId = (int) $request->user()->id;
            $oldSubdivisionName = $subdivisionChanged
                ? (Subdivision::query()->find($previousSubdivisionId)?->name ?? '—')
                : null;
            $newSubdivisionName = $subdivisionChanged
                ? (Subdivision::query()->find((int) $validated['subdivision_id'])?->name ?? '—')
                : null;
            $oldDeliveryLabel = $deliveryDateChanged
                ? ($application->desired_delivery_date?->format('d.m.Y') ?? '—')
                : null;
            $newDeliveryLabel = $deliveryDateChanged
                ? Carbon::parse($validated['desired_delivery_date'])->format('d.m.Y')
                : null;

            $application->update($applicationCoreUpdate);

            if ($subdivisionChanged) {
                $this->recordApplicationChangeJournal(
                    $application,
                    $journalUserId,
                    ApplicationChangeJournal::ACTION_UPDATED,
                    ApplicationChangeJournal::FIELD_SUBDIVISION,
                    'Подразделение',
                    trim((string) $request->input('field_change_reasons.subdivision_id')),
                    null,
                    $oldSubdivisionName,
                    $newSubdivisionName,
                );
            }
            if ($deliveryDateChanged) {
                $this->recordApplicationChangeJournal(
                    $application,
                    $journalUserId,
                    ApplicationChangeJournal::ACTION_UPDATED,
                    ApplicationChangeJournal::FIELD_DELIVERY_DATE,
                    'Желаемая дата поставки',
                    trim((string) $request->input('field_change_reasons.desired_delivery_date')),
                    null,
                    $oldDeliveryLabel,
                    $newDeliveryLabel,
                );
            }

            if ((int) $validated['subdivision_id'] !== $previousSubdivisionId) {
            }

            if ($managementMayEditCheckedEquipmentLines) {
                $idsToSoftRemove = $application->items()
                    ->whereNotIn('id', $submittedItemIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            } else {
                $idsToSoftRemove = $application->items()
                    ->where('is_checked', false)
                    ->whereNotIn('id', $submittedItemIds)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }

            foreach ($idsToSoftRemove as $softRemoveId) {
                $removalReason = trim((string) $request->input("removed_item_reasons.{$softRemoveId}", ''));
                $itemToRemove = $application->items()->where('id', $softRemoveId)->first();
                $removedLineLabel = $itemToRemove
                    ? $this->formatApplicationItemLineForJournal($itemToRemove)
                    : null;
                ApplicationItem::query()
                    ->where('application_id', (int) $application->id)
                    ->where('id', $softRemoveId)
                    ->update([
                        'removed_at' => now(),
                        'removed_by_user_id' => $request->user()->id,
                        'is_checked' => false,
                        'reason_not_selected' => null,
                        'delivery_status_id' => null,
                        'delivery_warehouse_id' => null,
                        'transport_option_id' => null,
                        'expected_arrival_at' => null,
                        'custom_equipment_supply_status_id' => null,
                    ]);
                $this->recordApplicationChangeJournal(
                    $application,
                    $journalUserId,
                    ApplicationChangeJournal::ACTION_REMOVED,
                    ApplicationChangeJournal::FIELD_ITEM_REMOVED,
                    'Позиция снята с заявки',
                    $removalReason,
                    $softRemoveId,
                    $removedLineLabel,
                    null,
                );
            }

            $clearedManagementSupplySavedAt = false;

            foreach ($validated['items'] as $row) {
                $itemId = isset($row['item_id']) ? (int) $row['item_id'] : null;
                if (! $itemId) {
                    continue;
                }

                $existing = $application->items()->where('id', $itemId)->first();
                if (! $existing) {
                    continue;
                }
                if ($existing->is_checked) {
                    if (! $managementMayEditCheckedEquipmentLines) {
                        continue;
                    }
                    if ($this->applicationItemRowMatchesStored($existing, $row)) {
                        continue;
                    }
                }

                $typeId = $row['equipment_id'] ?? null;
                $typeId = $typeId !== null && $typeId !== '' ? (int) $typeId : null;
                $normalized = $this->normalizeItemPayload($row, $typeId ? Equipment::query()->find($typeId)?->name : null);

                $wasChecked = (bool) $existing->is_checked;
                $payload = [
                    'equipment_id' => $typeId ?: null,
                    'equipment_name' => $typeId ? null : $normalized['equipment_name'],
                    'base_name' => $normalized['base_name'],
                    'size_value' => $normalized['size_value'],
                    'quantity' => $normalized['quantity'],
                    'measurement_type' => $normalized['measurement_type'],
                    'quantity_unit' => $normalized['quantity_unit'],
                    'raw_input' => $normalized['raw_input'],
                    'custom_equipment_supply_status_id' => $typeId ? null : ApplicationItem::CUSTOM_SUPPLY_PENDING_APPROVAL_ID,
                    'delivery_status_id' => null,
                    'delivery_warehouse_id' => null,
                ];
                if ($managementMayEditCheckedEquipmentLines) {
                    $payload['is_checked'] = false;
                    $payload['reason_not_selected'] = null;
                    $clearedManagementSupplySavedAt = true;
                } elseif ($foremanMayReviseRejectedByBoilerChiefOnly
                    && $application->itemIsRejectedByBoilerChief($existing)
                    && ! $this->applicationItemRowMatchesStored($existing, $row)) {
                    $payload['reason_not_selected'] = null;
                }
                $changedAspectKeys = $this->itemRowChangedAspectKeys($existing, $row);
                $changeReason = null;
                $oldItemLineLabel = null;
                if ($changedAspectKeys !== []) {
                    $changeReason = trim((string) $request->input("item_change_reasons.{$itemId}", ''));
                    $oldItemLineLabel = $this->formatApplicationItemLineForJournal($existing);
                }
                $existing->update($payload);
                $this->syncCatalogItemManualDetail($existing, $normalized);
                if ($changeReason !== null && $changeReason !== '') {
                    $existing->refresh();
                    $existing->loadMissing(['equipment.measurementUnit.unitType', 'manualDetail']);
                    $this->recordApplicationChangeJournal(
                        $application,
                        $journalUserId,
                        ApplicationChangeJournal::ACTION_UPDATED,
                        ApplicationChangeJournal::FIELD_ITEM_UPDATED,
                        'Изменение позиции',
                        $changeReason,
                        (int) $existing->id,
                        $oldItemLineLabel,
                        $this->formatApplicationItemLineForJournal($existing),
                    );
                }
            }

            foreach ($toCreateExpanded as $payload) {
                $normalized = $this->normalizeItemPayload($payload, $payload['equipment_id'] ? Equipment::query()->find((int) $payload['equipment_id'])?->name : null);
                $additionReason = trim((string) ($payload['addition_reason'] ?? ''));
                $createdItem = $application->items()->create([
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
                    'delivery_status_id' => null,
                    'delivery_warehouse_id' => null,
                ]);
                $this->syncCatalogItemManualDetail($createdItem, $normalized);
                if ($additionReason !== '') {
                    $createdItem->loadMissing(['equipment.measurementUnit.unitType', 'manualDetail']);
                    $this->recordApplicationChangeJournal(
                        $application,
                        $journalUserId,
                        ApplicationChangeJournal::ACTION_ADDED,
                        ApplicationChangeJournal::FIELD_ITEM_ADDED,
                        'Добавление позиции',
                        $additionReason,
                        (int) $createdItem->id,
                        null,
                        $this->formatApplicationItemLineForJournal($createdItem),
                    );
                }
            }

            if ($clearedManagementSupplySavedAt) {
                $application->update([
                    'management_supply_items_saved_at' => null,
                    'transport_option_id' => null,
                ]);
            }

            $submitForApprovalOnUpdate = in_array((string) $request->input('submit_action'), ['submit_to_boiler_chief', 'submit_for_management'], true);

            $application->refresh();
            $application->load('items');

            if ($submitForApprovalOnUpdate && ! $application->items()->exists()) {
                throw ValidationException::withMessages([
                    'submit_action' => 'Добавьте хотя бы одну позицию оборудования перед отправкой.',
                ]);
            }

            if ($wasCreatorDraftBeforeUpdate) {
                $application->update([
                    'application_status_id' => $submitForApprovalOnUpdate
                        ? ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING)
                        : ApplicationStatus::idForDraft(),
                    'reason_for_refusal' => null,
                    'approved_by_user_id' => null,
                ]);
            } else {
                $approvalPayload = Application::aggregateApprovalPayloadFromItems($application->items);
                $approvedStatusId = ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED);

                $application->update([
                    'application_status_id' => $approvalPayload['application_status_id'],
                    'reason_for_refusal' => $approvalPayload['reason_for_refusal'],
                    'approved_by_user_id' => $managementMayEditCheckedEquipmentLines
                        ? $existingApprovedByUserId
                        : ($approvalPayload['application_status_id'] === $approvedStatusId
                            ? $existingApprovedByUserId
                            : null),
                ]);
            }

            $application->refresh();
            $application->load('items');
            $this->refreshBoilerChiefGateAfterItemChanges($application);
        });

        $application->refresh();

        if ($application->isForemanDraftBeforeBoilerChief()) {
            return redirect()->route('applications.edit', $application)
                ->with('status', 'Изменения сохранены. Заявку можно отправить на согласование, когда будете готовы.');
        }

        if ($isSiteForeman && $application->foremanCanReviseAfterBoilerChiefRejection()) {
            return redirect()->route('applications.edit', $application)
                ->with('status', 'Изменения по несогласованным позициям сохранены. Отправьте заявку на повторное согласование начальнику котельной, когда будете готовы.');
        }

        if ($application->isBoilerChiefDraftBeforeManagement()) {
            return redirect()->route('applications.edit', $application)
                ->with('status', 'Изменения сохранены. Заявку можно отправить на согласование, когда будете готовы.');
        }

        if ($isSiteForeman
            && Subdivision::hasBoilerChiefAssigned((int) $application->subdivision_id)
            && (string) $request->input('submit_action') === 'submit_to_boiler_chief') {
            if ($application->isForemanDraftBeforeBoilerChief()) {
                return redirect()->route('applications.show', $application)
                    ->with('status', 'Заявка отправлена на согласование. Редактирование больше недоступно.');
            }
            if ($application->foremanCanResubmitAwaitingItemsToBoilerChief()) {
                $this->finalizeForemanResubmissionToBoilerChief($application);

                return redirect()->route('applications.show', $application)
                    ->with('status', 'Изменённые позиции отправлены на повторное согласование начальнику котельной. Редактирование больше недоступно.');
            }

            if ($application->boilerChiefSubdivisionReviewCycleStarted()) {
                return redirect()->route('applications.edit', $application)
                    ->withErrors(['submit_action' => 'Измените и сохраните хотя бы одну несогласованную позицию перед повторной отправкой.']);
            }
        }

        if ($isBoilerChiefUser
            && (string) $request->input('submit_action') === 'submit_for_management'
            && ! $application->isBoilerChiefDraftBeforeManagement()) {
            return redirect()->route('applications.show', $application)
                ->with('status', 'Заявка отправлена на согласование руководству и снабжению. Редактирование больше недоступно.');
        }

        if ($managementMayEditCheckedEquipmentLines) {
            return redirect()->to(route('applications.show', $application).'#approval-form')
                ->with('status', 'Изменения сохранены. Отметьте, какие позиции согласованы, и сохраните согласование.');
        }

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
            $approvalUpdate = [
                'application_status_id' => $payload['application_status_id'],
                'reason_for_refusal' => $payload['reason_for_refusal'],
                'approved_by_user_id' => $request->user()->id,
            ];
            if (Subdivision::hasBoilerChiefAssigned((int) $application->subdivision_id)
                && ! $application->needsBoilerChiefReviewBeforeManagement()) {
                $approvalUpdate['management_supply_items_saved_at'] = $application->hasApprovedEquipmentLines()
                    ? now()
                    : null;
            }
            $application->update($approvalUpdate);
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

        if ($application->isBoilerChiefCreatedApplication()) {
            abort(403, 'Заявку создал начальник котельной — согласование на этом этапе не требуется.');
        }

        $application->load('items');

        if ($application->items->isEmpty()) {
            return redirect()->route('applications.show', $application)
                ->with('status', 'Нет позиций для согласования.');
        }

        if (! $application->needsBoilerChiefReviewBeforeManagement()) {
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

        $itemsForChiefApproval = $application->items->filter(
            fn (ApplicationItem $item) => $application->itemAwaitingBoilerChiefReview($item)
        );

        if ($itemsForChiefApproval->isEmpty()) {
            return redirect()->route('applications.show', $application)
                ->with('status', 'Нет позиций, ожидающих согласования начальником котельной.');
        }

        foreach ($itemsForChiefApproval as $item) {
            $row = $itemsInput[(string) $item->id] ?? $itemsInput[$item->id] ?? null;
            if (! is_array($row)) {
                $errors["boiler_items.{$item->id}.is_checked"] = 'Отсутствуют данные по позиции.';

                continue;
            }
            $checkedRaw = $row['is_checked'] ?? '0';
            $isChecked = $checkedRaw === '1' || $checkedRaw === 1 || $checkedRaw === true;
            if (! $isChecked) {
                $reason = trim((string) ($row['reason_not_selected'] ?? ''));
                if ($reason === '' && $bulkUncheckedReason !== '') {
                    $reason = $bulkUncheckedReason;
                }
                if ($reason === '') {
                    $errors["boiler_items.{$item->id}.reason_not_selected"] = 'Укажите причину не согласования.';
                } elseif (mb_strlen($reason) > 500) {
                    $errors["boiler_items.{$item->id}.reason_not_selected"] = 'Причина не может быть длиннее 500 символов.';
                }
            }
        }

        if ($errors !== []) {
            return redirect()->route('applications.show', $application)
                ->withErrors($errors)
                ->withInput();
        }

        DB::transaction(function () use ($itemsInput, $bulkUncheckedReason, $itemsForChiefApproval) {
            foreach ($itemsForChiefApproval as $item) {
                $row = $itemsInput[(string) $item->id] ?? $itemsInput[$item->id];
                $checkedRaw = $row['is_checked'] ?? '0';
                $isChecked = $checkedRaw === '1' || $checkedRaw === 1 || $checkedRaw === true;
                $reason = trim((string) ($row['reason_not_selected'] ?? ''));
                if (! $isChecked && $reason === '' && $bulkUncheckedReason !== '') {
                    $reason = $bulkUncheckedReason;
                }
                $item->update([
                    'is_checked' => $isChecked,
                    'reason_not_selected' => $isChecked ? null : $reason,
                ]);
            }
        });

        $application->refresh();
        $application->load('items');
        if (! $application->needsBoilerChiefReviewBeforeManagement()) {
            $application->update([
                'approved_by_user_id' => null,
                'management_supply_items_saved_at' => null,
                'application_status_id' => ApplicationStatus::idForDraft(),
                'reason_for_refusal' => null,
            ]);
        }

        $statusMessage = ! $application->needsBoilerChiefReviewBeforeManagement()
            ? 'Согласование начальника котельной завершено.'
            : 'Согласование начальника котельной сохранено. Есть не согласованные позиции.';

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

        if ($application->approved_by_user_id === null) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['delivery' => 'Сначала сохраните согласование снабжения по позициям.']);
        }

        if (Subdivision::hasBoilerChiefAssigned((int) $application->subdivision_id)
            && ! $application->needsBoilerChiefReviewBeforeManagement()
            && $application->management_supply_items_saved_at === null) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['delivery' => 'После этапа котельной сначала сохраните согласование снабжения по позициям.']);
        }

        $eligibleById = $application->items
            ->filter(fn (ApplicationItem $i) => $i->canMarkDeliveryInTransit())
            ->keyBy('id');

        if ($eligibleById->isEmpty()) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['delivery' => 'Нет позиций, которые можно отметить как «В пути».']);
        }

        $itemsInput = $request->input('items', []);
        $errors = [];
        $resolved = [];

        foreach ($eligibleById as $itemId => $item) {
            $row = $itemsInput[(string) $itemId] ?? $itemsInput[$itemId] ?? null;
            if (! is_array($row) || empty($row['mark'])) {
                continue;
            }

            $methodId = isset($row['transport_option_id']) ? (int) $row['transport_option_id'] : 0;
            if ($methodId <= 0) {
                $errors["items.{$itemId}.transport_option_id"] = 'Укажите способ доставки.';

                continue;
            }

            try {
                $transportOptionId = $this->resolveDeliveryTransportOptionId(
                    $methodId,
                    isset($row['vehicle_plate']) ? (string) $row['vehicle_plate'] : null,
                    "items.{$itemId}"
                );
            } catch (ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    $errors[$key] = $messages;
                }

                continue;
            }

            try {
                $expectedArrivalAt = $this->parseDeliveryExpectedArrivalAt(
                    isset($row['expected_arrival_at']) ? (string) $row['expected_arrival_at'] : null,
                    "items.{$itemId}"
                );
            } catch (ValidationException $e) {
                foreach ($e->errors() as $key => $messages) {
                    $errors[$key] = $messages;
                }

                continue;
            }

            $resolved[] = [
                'item_id' => (int) $itemId,
                'transport_option_id' => $transportOptionId,
                'expected_arrival_at' => $expectedArrivalAt,
            ];
        }

        if ($resolved === [] && $errors === []) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['delivery' => 'Отметьте хотя бы одну позицию и укажите для неё доставку.'])
                ->withInput();
        }

        if ($errors !== []) {
            return redirect()->route('applications.show', $application)
                ->withErrors($errors)
                ->withInput();
        }

        DB::transaction(function () use ($resolved): void {
            foreach ($resolved as $row) {
                ApplicationItem::query()
                    ->where('id', $row['item_id'])
                    ->update([
                        'delivery_status_id' => ApplicationItem::DELIVERY_IN_TRANSIT_ID,
                        'delivery_warehouse_id' => null,
                        'transport_option_id' => $row['transport_option_id'],
                        'expected_arrival_at' => $row['expected_arrival_at'],
                    ]);
            }
        });

        $application->update([
            'transport_option_id' => $resolved[0]['transport_option_id'],
        ]);

        $count = count($resolved);
        $status = $count === 1
            ? 'Позиция отмечена как «В пути».'
            : "Отмечено позиций «В пути»: {$count}.";

        return redirect()->route('applications.show', $application)
            ->with('status', $status);
    }
    private function parseDeliveryExpectedArrivalAt(?string $raw, string $errorKeyPrefix): Carbon
    {
        $value = trim((string) ($raw ?? ''));
        if ($value === '') {
            throw ValidationException::withMessages([
                "{$errorKeyPrefix}.expected_arrival_at" => 'Укажите дату прибытия оборудования.',
            ]);
        }

        $parsed = Carbon::createFromFormat('Y-m-d', $value);

        if ($parsed === false) {
            throw ValidationException::withMessages([
                "{$errorKeyPrefix}.expected_arrival_at" => 'Некорректная дата прибытия.',
            ]);
        }

        $parsed = $parsed->startOfDay();

        if ($parsed->lt(now()->startOfDay())) {
            throw ValidationException::withMessages([
                "{$errorKeyPrefix}.expected_arrival_at" => 'Дата прибытия не может быть в прошлом.',
            ]);
        }

        return $parsed;
    }
    private function resolveDeliveryTransportOptionId(int $methodId, ?string $vehiclePlateRaw, string $errorKeyPrefix): int
    {
        $method = TransportOption::query()->find($methodId);
        if ($method === null) {
            throw ValidationException::withMessages([
                "{$errorKeyPrefix}.transport_option_id" => 'Выбранный способ доставки не найден.',
            ]);
        }

        if (! Schema::hasColumn('transport_options', 'plate')) {
            return (int) $method->id;
        }

        $methodPlate = trim((string) ($method->plate ?? ''));
        if ($methodPlate !== '') {
            throw ValidationException::withMessages([
                "{$errorKeyPrefix}.transport_option_id" => 'Выберите тип транспорта, а не конкретную машину по госномеру.',
            ]);
        }

        $methodName = trim((string) $method->name);
        if (! TransportOption::deliveryRequiresVehiclePlate($methodName)) {
            return (int) $method->id;
        }

        if (TransportOption::deliveryUsesServiceVehiclePlatePicker($methodName)) {
            $plate = mb_substr(trim((string) ($vehiclePlateRaw ?? '')), 0, 30);
            $serviceVehicle = TransportOption::query()
                ->where('name', TransportOption::NAME_SERVICE_VEHICLE)
                ->where('plate', $plate)
                ->first();
            if ($serviceVehicle === null) {
                throw ValidationException::withMessages([
                    "{$errorKeyPrefix}.vehicle_plate" => 'Выберите служебную машину из списка.',
                ]);
            }

            return (int) $serviceVehicle->id;
        }

        $plate = RussianVehiclePlate::normalize((string) ($vehiclePlateRaw ?? ''));
        if ($plate === '' || ! RussianVehiclePlate::isValid($plate)) {
            throw ValidationException::withMessages([
                "{$errorKeyPrefix}.vehicle_plate" => RussianVehiclePlate::validationMessage(),
            ]);
        }

        $existingVehicle = TransportOption::query()->where('plate', $plate)->first();

        return $existingVehicle !== null
            ? (int) $existingVehicle->id
            : (int) TransportOption::query()->create([
                'name' => $method->name,
                'plate' => $plate,
                'label' => null,
            ])->id;
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

        $allowedSubdivisionIds = $this->allowedDeliverySubdivisionIdsForUser($request->user());

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

        DB::transaction(function () use ($application, $item, $deliveryWarehouseId) {
            $this->markDeliveredWithReceipt($application, $item, $deliveryWarehouseId);
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

    public function markItemsDeliveryDeliveredBulk(Request $request, Application $application): RedirectResponse
    {
        if (! $request->user()->hasAnyRoleId([self::BOILER_CHIEF_ROLE_ID, 4])) {
            abort(403, 'Отметка «Доставлено» доступна только начальнику котельной и мастеру участка.');
        }

        $this->authorizeViewApplication($request, $application);
        $validated = $request->validate([
            'delivery_bulk_warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'item_ids' => ['required', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'distinct'],
        ], [
            'delivery_bulk_warehouse_id.required' => 'Выберите склад поступления для групповой отметки.',
            'item_ids.required' => 'Выберите позиции для групповой отметки «Доставлено».',
        ]);

        $itemIds = collect($validated['item_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $items = ApplicationItem::query()
            ->where('application_id', (int) $application->id)
            ->whereIn('id', $itemIds)
            ->get();

        if ($items->count() !== $itemIds->count()) {
            throw ValidationException::withMessages([
                'delivery' => 'Часть выбранных позиций недоступна для этой заявки.',
            ]);
        }

        $expectedSubdivisionIds = $items
            ->map(fn (ApplicationItem $deliveryItem) => $deliveryItem->resolvedDeliveryTargetSubdivisionId())
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($expectedSubdivisionIds->count() !== 1) {
            throw ValidationException::withMessages([
                'delivery' => 'Групповая отметка возможна только для позиций одного подразделения.',
            ]);
        }

        $expectedSubdivisionId = (int) $expectedSubdivisionIds->first();
        $deliveryWarehouseId = (int) $validated['delivery_bulk_warehouse_id'];
        $warehouse = Warehouse::query()->findOrFail($deliveryWarehouseId);
        $warehouseSubdivisionId = (int) ($warehouse->subdivision_id ?? 0);

        if ($warehouseSubdivisionId !== $expectedSubdivisionId) {
            throw ValidationException::withMessages([
                'delivery_bulk_warehouse_id' => 'Склад должен относиться к подразделению, указанному в выбранных позициях.',
            ]);
        }

        $allowedSubdivisionIds = $this->allowedDeliverySubdivisionIdsForUser($request->user());
        if (! $allowedSubdivisionIds->contains($warehouseSubdivisionId)) {
            throw ValidationException::withMessages([
                'delivery_bulk_warehouse_id' => 'Вы можете отметить доставку только на склады подразделений, закреплённых за вами.',
            ]);
        }

        DB::transaction(function () use ($application, $items, $deliveryWarehouseId) {
            foreach ($items as $deliveryItem) {
                $this->markDeliveredWithReceipt($application, $deliveryItem, $deliveryWarehouseId);
            }
        });

        $application->refresh();
        $application->load(['subdivision', 'items', 'installationActPhotos']);
        $archiveHint = $this->archiveCompletedApplicationIfReady($application);

        $status = 'Выбранные позиции отмечены как доставленные и оприходованы на склад получателя.';
        if ($archiveHint !== null) {
            $status .= ' '.$archiveHint;
        }

        return redirect()->route('applications.show', $application)
            ->with('status', $status);
    }

    public function markItemDeliveryDefective(Request $request, Application $application, ApplicationItem $item): RedirectResponse
    {
        if (! $request->user()->hasAnyRoleId([self::BOILER_CHIEF_ROLE_ID, 4])) {
            abort(403, 'Отметка брака доступна только начальнику котельной и мастеру участка.');
        }

        $this->authorizeViewApplication($request, $application);

        if ((int) $item->application_id !== (int) $application->id) {
            abort(404);
        }

        if (! $this->itemEligibleForDeliveryDefectManagement($item)) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['defect' => 'Отметить брак можно только для принятой на склад каталожной позиции.']);
        }

        $validated = $request->validate([
            'defect_quantity' => ['required', 'numeric'],
            'defect_reason' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'defect_quantity.required' => 'Укажите количество бракованного оборудования.',
            'defect_quantity.numeric' => 'Количество должно быть числом.',
            'defect_reason.required' => 'Укажите причину брака (не менее 3 символов).',
        ]);

        $quantity = (float) $validated['defect_quantity'];
        $deliveredQuantity = (float) $item->quantity;
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'defect_quantity' => 'Количество должно быть больше нуля.',
            ]);
        }
        if ($quantity > $deliveredQuantity + 0.0005) {
            throw ValidationException::withMessages([
                'defect_quantity' => 'Нельзя отметить браком больше, чем доставлено по позиции ('.$this->formatIssueQuantityForMessage($deliveredQuantity).' '.$item->quantityUnitLabelForDisplay().').',
            ]);
        }

        $warehouseId = (int) ($item->delivery_warehouse_id ?? 0);
        $targetSubdivisionId = $item->resolvedDeliveryTargetSubdivisionId();
        if ($targetSubdivisionId === null) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['defect' => 'Не задано подразделение получения по заявке.']);
        }

        $allowedSubdivisionIds = $this->allowedDeliverySubdivisionIdsForUser($request->user());
        if (! $allowedSubdivisionIds->contains((int) $targetSubdivisionId)) {
            abort(403, 'Вы не можете отмечать брак по этому подразделению.');
        }

        $quantity = (float) $validated['defect_quantity'];
        $maxQuantity = $this->maxDefectMarkQuantityForItem($application, $item);
        if ($quantity > $maxQuantity + 0.0005) {
            throw ValidationException::withMessages([
                'defect_quantity' => 'Нельзя отметить браком больше '.$this->formatIssueQuantityForMessage($maxQuantity).' '.$item->quantityUnitLabelForDisplay().' (с учётом уже отмеченного брака и списаний).',
            ]);
        }

        WarehouseStockBucket::transferToDefective(
            (int) $item->equipment_id,
            $warehouseId,
            $quantity,
            (int) $application->id,
            (int) $item->id,
            (string) $validated['defect_reason'],
            (int) $request->user()->id,
        );

        return redirect()->route('applications.show', $application)
            ->with('status', 'Позиция «'.$item->equipment_display_name.'»: '.$this->formatIssueQuantityForMessage($quantity).' '.$item->quantityUnitLabelForDisplay().' переведено в брак на складе получателя.');
    }

    public function disposeItemDeliveryDefective(Request $request, Application $application, ApplicationItem $item): RedirectResponse
    {
        if (! $request->user()->hasAnyRoleId([self::BOILER_CHIEF_ROLE_ID, 4])) {
            abort(403, 'Утилизация брака доступна только начальнику котельной и мастеру участка.');
        }

        $this->authorizeViewApplication($request, $application);

        if ((int) $item->application_id !== (int) $application->id) {
            abort(404);
        }

        if (! $this->itemEligibleForDeliveryDefectManagement($item)) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['defect' => 'Утилизировать брак можно только по принятой на склад каталожной позиции.']);
        }

        $remainingDefective = WarehouseStockBucket::remainingDefectiveQuantityForApplicationItem(
            (int) $application->id,
            (int) $item->id
        );
        if ($remainingDefective < 0.0005) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['defect' => 'По этой позиции нет бракованного остатка для утилизации.']);
        }

        $validated = $request->validate([
            'dispose_quantity' => ['required', 'numeric'],
            'dispose_comment' => ['nullable', 'string', 'max:1000'],
        ], [
            'dispose_quantity.required' => 'Укажите количество к утилизации.',
            'dispose_quantity.numeric' => 'Количество должно быть числом.',
        ]);

        $quantity = (float) $validated['dispose_quantity'];
        if ($quantity <= 0) {
            throw ValidationException::withMessages([
                'dispose_quantity' => 'Количество должно быть больше нуля.',
            ]);
        }
        if ($quantity > $remainingDefective + 0.0005) {
            throw ValidationException::withMessages([
                'dispose_quantity' => 'Нельзя утилизировать больше '.$this->formatIssueQuantityForMessage($remainingDefective).' '.$item->quantityUnitLabelForDisplay().'.',
            ]);
        }

        $targetSubdivisionId = $item->resolvedDeliveryTargetSubdivisionId();
        if ($targetSubdivisionId === null) {
            return redirect()->route('applications.show', $application)
                ->withErrors(['defect' => 'Не задано подразделение получения по заявке.']);
        }

        $allowedSubdivisionIds = $this->allowedDeliverySubdivisionIdsForUser($request->user());
        if (! $allowedSubdivisionIds->contains((int) $targetSubdivisionId)) {
            abort(403, 'Вы не можете утилизировать брак по этому подразделению.');
        }

        $warehouseId = (int) ($item->delivery_warehouse_id ?? 0);
        WarehouseStockBucket::disposeDefective(
            (int) $item->equipment_id,
            $warehouseId,
            $quantity,
            (int) $application->id,
            (int) $item->id,
            isset($validated['dispose_comment']) ? (string) $validated['dispose_comment'] : null,
            (int) $request->user()->id,
        );

        return redirect()->route('applications.show', $application)
            ->with('status', 'Бракованное оборудование «'.$item->equipment_display_name.'» утилизировано: '.$this->formatIssueQuantityForMessage($quantity).' '.$item->quantityUnitLabelForDisplay().'.');
    }

    public function markCustomEquipmentOrdered(Request $request, Application $application, ApplicationItem $item): RedirectResponse
    {
        if (! $request->user()->hasAnyRoleId($this->customEquipmentOrderingRoleIds())) {
            abort(403, 'Отметка «Заказано» доступна только директору, техническому директору и начальнику отдела снабжения.');
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

    public function markCustomEquipmentOnWarehouse(Request $request, Application $application, ApplicationItem $item): RedirectResponse
    {
        if (! $request->user()->hasAnyRoleId($this->customEquipmentOrderingRoleIds())) {
            abort(403, 'Отметка «На складе» доступна только директору, техническому директору и начальнику отдела снабжения.');
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
    private function applicationsSelectableForInstallationActUpload(Request $request): Collection
    {
        return ReportLayoutEquipmentApplications::collectionForInstallationActUser($request->user());
    }
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
            UPLOAD_ERR_INI_SIZE => "Файл «{$label}» больше, чем разрешено.",
            UPLOAD_ERR_FORM_SIZE => "Файл «{$label}» больше, чем 30 МБ.",
            UPLOAD_ERR_PARTIAL => "Файл «{$label}» загружен не полностью — повторите отправку.",
            UPLOAD_ERR_NO_FILE => "Файл «{$label}» не был передан.",
            UPLOAD_ERR_NO_TMP_DIR => 'На сервере нет временной папки для загрузки.',
            UPLOAD_ERR_CANT_WRITE => 'Не удалось записать файл на диск.',
            UPLOAD_ERR_EXTENSION => 'Расширение PHP прервало загрузку файла.',
            default => "Не удалось загрузить файл «{$label}».",
        };
    }

    private function redirectIfApplicationEditUnavailable(Request $request, Application $application): ?RedirectResponse
    {
        $this->authorizeCanEditApplications($request);
        $this->authorizeViewApplication($request, $application);
        $this->authorizeForemanCanModifyApplication($request, $application);
        $this->authorizeBoilerChiefCanModifyApplication($request, $application);
        $this->authorizeApplicationNotLockedAfterManagementApproval($application);

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

        return null;
    }

    private function customEquipmentBulkReturnUrl(Request $request, Application $application): string
    {
        return route('applications.custom-equipment-order', $application);
    }

    private function deleteStoredPublicDiskFile(string $relativePath): void
    {
        $path = trim($relativePath);
        if ($path === '') {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    private function safeUploadedOriginalName(UploadedFile $file, string $fallbackPrefix): string
    {
        $original = trim((string) $file->getClientOriginalName());
        $original = str_replace(['\\', '/'], '-', $original);
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
        if ($user && $user->hasRoleId(User::ACCOUNTANT_ROLE_ID) && $application->hasInstallationActEvidence()) {
            return;
        }

        $this->authorizeViewApplication($request, $application);
    }
    private function applicationsWithInstallationActForAccountant(): Collection
    {
        return Application::query()
            ->with('subdivision')
            ->where(function ($outer) {
                $outer->where(function ($q) {
                    $q->whereNotNull('act_of_installation')
                        ->where('act_of_installation', '!=', '');
                })->orWhereHas('installationActPhotos');
            })
            ->orderByDesc('id')
            ->limit(500)
            ->get();
    }

    private function authorizeCanChangeApplicationResponsible(Request $request): void
    {
        if (! $request->user()?->hasAnyRoleId([
            ...User::APPLICATION_SUPPLY_WORKFLOW_ROLE_IDS,
            self::BOILER_CHIEF_ROLE_ID,
            User::ADMINISTRATOR_ROLE_ID,
        ])) {
            abort(403, 'Смена ответственного по заявке доступна директору, техническому директору, начальнику отдела снабжения, начальнику котельной и администратору.');
        }
    }

    private function applicationItemsLockedForResponsibleChange(Application $application): bool
    {
        $application->loadMissing('items');

        return $application->items->contains(
            fn (ApplicationItem $i) => in_array(
                $i->resolvedDeliveryStatus(),
                [ApplicationItem::DELIVERY_IN_TRANSIT, ApplicationItem::DELIVERY_DELIVERED],
                true
            )
        );
    }

    private function canOfferApplicationResponsibleChange(Request $request, Application $application): bool
    {
        if (! $request->user()?->hasAnyRoleId([
            ...User::APPLICATION_SUPPLY_WORKFLOW_ROLE_IDS,
            self::BOILER_CHIEF_ROLE_ID,
            User::ADMINISTRATOR_ROLE_ID,
        ])) {
            return false;
        }
        if ($application->archived_at !== null) {
            return false;
        }
        $application->loadMissing('responsibleUser:id,role_id,is_blocked', 'items');
        if ($this->applicationItemsLockedForResponsibleChange($application)) {
            return false;
        }
        $ru = $application->responsibleUser;
        if (! $ru || ! $ru->hasRoleId(4)) {
            return false;
        }

        return Application::query()
            ->notArchived()
            ->where('responsible_user_id', $ru->id)
            ->exists();
    }

    private function authorizeViewApplication(Request $request, Application $application): void
    {
        $user = $request->user();
        if (! $user) {
            abort(403, 'Необходима авторизация.');
        }

        if ($user->hasRoleId(4)) {
            if (! $application->isVisibleToSiteForeman($user)) {
                abort(403, 'Заявка закреплена за другим мастером участка или относится к подразделению вне вашей зоны ответственности.');
            }

            return;
        }

        if ($user->hasRoleId(self::BOILER_CHIEF_ROLE_ID)) {
            $ids = $user->boilerChiefSubdivisions()->pluck('subdivisions.id');
            if (! $ids->contains((int) $application->subdivision_id)) {
                abort(403, 'Заявка относится к подразделению вне вашей зоны ответственности.');
            }
            if ($application->isForemanDraftBeforeBoilerChief()) {
                abort(403, 'Заявка ещё не отправлена мастером участка на согласование.');
            }

            return;
        }

        if ($user->hasAnyRoleId($this->managementEditorRoleIds())) {
            if ($application->isBoilerChiefDraftBeforeManagement()) {
                abort(403, 'Заявка ещё не отправлена начальником котельной на согласование.');
            }
            if ($this->isForemanCreatedApplication($application) && $application->needsBoilerChiefReviewBeforeManagement()) {
                abort(403, 'Заявка пока недоступна: сначала её согласует начальник котельной по подразделению.');
            }
            if ($this->isForemanCreatedApplication($application) && ! $application->boilerChiefReleasedToManagement()) {
                abort(403, 'Заявка ещё не отправлена начальником котельной на согласование руководству и снабжению.');
            }

            return;
        }

        if ($user->hasRoleId(User::ACCOUNTANT_ROLE_ID)) {
            return;
        }

        if ($this->isAdministratorApplicationViewer($user)) {
            return;
        }

        abort(403, 'Недостаточно прав для просмотра этой заявки.');
    }

    public function adminArchive(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeForceApplicationArchive($request);

        $this->authorizeViewApplication($request, $application);

        if ($application->archived_at !== null) {
            return back(fallback: route('applications.index'))
                ->with('status', 'Заявка уже в архиве.');
        }

        $application->adminForceArchive($request->user()?->id);

        return back(fallback: route('applications.index'))
            ->with('status', 'Заявка перенесена в архив.');
    }

    public function adminUnarchive(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeForceApplicationArchive($request);

        $this->authorizeViewApplication($request, $application);

        if (! $application->isAdminArchived()) {
            return back(fallback: route('applications.index'))
                ->withErrors([
                    'archive' => $application->archived_at !== null
                        ? 'Эту заявку нельзя вернуть: она в архиве выполненных, а не в принудительном архиве.'
                        : 'Заявка не в принудительном архиве.',
                ]);
        }

        $application->adminRestoreFromArchive();

        return back(fallback: route('applications.index'))
            ->with('status', 'Заявка возвращена из архива и снова активна.');
    }

    private function authorizeForceApplicationArchive(Request $request): void
    {
        if (! $this->canForceArchiveApplications($request->user())) {
            abort(403, 'Архивирование заявок недоступно для вашей роли.');
        }
    }

    private function canForceArchiveApplications(?User $user): bool
    {
        return $user !== null && $user->hasRoleId(User::ADMINISTRATOR_ROLE_ID);
    }

    private function isAdministratorApplicationViewer(?User $user): bool
    {
        return $user !== null && $user->hasRoleId(User::ADMINISTRATOR_ROLE_ID);
    }

    private function applyBoilerChiefAutoGate(Application $application): void
    {
    }
    private function applyManagementDelegationSupplyRelease(Application $application, ?User $creator): void
    {
        if ($creator === null) {
            return;
        }

        $application->loadMissing(['items', 'responsibleUser:id,role_id']);
        if (! $application->isManagementDelegatedToSiteForeman()) {
            return;
        }

        DB::transaction(function () use ($application, $creator): void {
            foreach ($application->items as $item) {
                $payload = [
                    'is_checked' => true,
                    'reason_not_selected' => null,
                ];
                if ($item->equipment_id === null) {
                    $payload['custom_equipment_supply_status_id'] = ApplicationItem::CUSTOM_SUPPLY_ACCEPTED_ID;
                }
                $item->update($payload);
            }

            $application->update([
                'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
                'approved_by_user_id' => $creator->id,
                'reason_for_refusal' => null,
                'management_supply_items_saved_at' => now(),
            ]);
        });
    }

    private function refreshBoilerChiefGateAfterItemChanges(Application $application): void
    {
    }

    private function authorizeCanCreateApplications(Request $request): void
    {
        $allowed = $request->user() && $request->user()->hasAnyRoleId(User::APPLICATION_CREATOR_ROLE_IDS);

        if (! $allowed) {
            abort(403, 'Создание заявок разрешено только мастеру участка и начальнику котельной.');
        }
    }

    private function authorizeCanEditApplications(Request $request): void
    {
        $allowed = $request->user() && $request->user()->hasAnyRoleId($this->editApplicationRoleIds());

        if (! $allowed) {
            abort(403, 'Редактирование заявок разрешено директору, техническому директору, начальнику отдела снабжения, мастеру участка и начальнику котельной.');
        }
    }
    private function normalizedEquipmentRowFromApplicationItem(ApplicationItem $item): array
    {
        $row = [
            'equipment_id' => $item->equipment_id,
            'equipment_name' => $item->equipment_name ?? '',
            'quantity' => $item->quantity,
            'size_value' => $item->size_value ?? '',
            'measurement_type' => $item->measurement_type ?? 'piece',
            'quantity_unit' => $item->quantity_unit ?? 'шт',
        ];
        $typeId = $item->equipment_id !== null ? (int) $item->equipment_id : null;
        $catalog = $typeId ? Equipment::query()->find($typeId)?->name : null;

        return $this->normalizeItemPayload($row, $catalog !== null && trim((string) $catalog) !== '' ? (string) $catalog : null);
    }
    private function applicationItemRowMatchesStored(ApplicationItem $existing, array $requestRow): bool
    {
        $typeId = $requestRow['equipment_id'] ?? null;
        $typeId = $typeId !== null && $typeId !== '' ? (int) $typeId : null;
        $catalog = $typeId ? Equipment::query()->find($typeId)?->name : null;
        $normRequest = $this->normalizeItemPayload($requestRow, $catalog !== null && trim((string) $catalog) !== '' ? (string) $catalog : null);
        $normStored = $this->normalizedEquipmentRowFromApplicationItem($existing);

        return $normStored == $normRequest;
    }
    private function itemRowChangedAspectKeys(ApplicationItem $existing, array $row): array
    {
        $typeIdReq = isset($row['equipment_id']) && $row['equipment_id'] !== '' ? (int) $row['equipment_id'] : null;
        $catalog = $typeIdReq ? Equipment::query()->find($typeIdReq)?->name : null;
        $normReq = $this->normalizeItemPayload($row, $catalog !== null && trim((string) $catalog) !== '' ? (string) $catalog : null);
        $normSt = $this->normalizedEquipmentRowFromApplicationItem($existing);

        if ($normSt == $normReq) {
            return [];
        }

        $keys = [];
        if (($normSt['base_name'] ?? '') !== ($normReq['base_name'] ?? '')
            || ($normSt['equipment_name'] ?? null) !== ($normReq['equipment_name'] ?? null)) {
            $keys[] = 'equipment';
        }
        if ((int) ($normSt['quantity'] ?? 0) !== (int) ($normReq['quantity'] ?? 0)) {
            $keys[] = 'quantity';
        }
        if (($normSt['measurement_type'] ?? '') !== ($normReq['measurement_type'] ?? '')
            || ($normSt['quantity_unit'] ?? '') !== ($normReq['quantity_unit'] ?? '')
            || ($normSt['size_value'] ?? null) !== ($normReq['size_value'] ?? null)) {
            $keys[] = 'measurement';
        }

        return array_values(array_unique($keys));
    }
    private function isNewEquipmentRowInEditRequest(array $row, bool $foremanMayReviseRejectedByBoilerChiefOnly): bool
    {
        if ($foremanMayReviseRejectedByBoilerChiefOnly) {
            $rawId = $row['item_id'] ?? null;
            if ($rawId === null || $rawId === '' || (int) $rawId === 0) {
                return false;
            }
        }
        $rawId = $row['item_id'] ?? null;
        if ($rawId !== null && $rawId !== '' && (int) $rawId > 0) {
            return false;
        }
        $typeIdRaw = $row['equipment_id'] ?? null;
        $typeId = $typeIdRaw !== null && $typeIdRaw !== '' ? (int) $typeIdRaw : null;
        $name = trim((string) ($row['equipment_name'] ?? ''));

        return ! ($typeId === null && $name === '');
    }

    private function recordApplicationChangeJournal(
        Application $application,
        int $userId,
        string $action,
        string $fieldKey,
        string $fieldLabel,
        string $reason,
        ?int $applicationItemId = null,
        ?string $oldValue = null,
        ?string $newValue = null,
    ): void {
        ApplicationChangeJournal::query()->create([
            'application_id' => $application->id,
            'application_item_id' => $applicationItemId,
            'user_id' => $userId,
            'action' => $action,
            'field_key' => $fieldKey,
            'field_label' => $fieldLabel,
            'old_value' => $oldValue !== null && trim($oldValue) !== '' ? $oldValue : null,
            'new_value' => $newValue !== null && trim($newValue) !== '' ? $newValue : null,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }

    private function formatApplicationItemLineForJournal(ApplicationItem $item): string
    {
        $item->loadMissing(['equipment.measurementUnit.unitType', 'manualDetail']);

        return $item->equipment_display_name.' × '.$item->quantity_with_unit;
    }

    private function validateApplicationEditPerFieldChangeReasons(
        Request $request,
        Application $application,
        array $validated,
        bool $foremanMayReviseRejectedByBoilerChiefOnly,
        bool $managementMayEditCheckedEquipmentLines,
    ): void {
        $messages = [];
        $min = 3;

        if ((int) $validated['subdivision_id'] !== (int) $application->subdivision_id) {
            $v = trim((string) $request->input('field_change_reasons.subdivision_id', ''));
            if (mb_strlen($v) < $min) {
                $messages['field_change_reasons.subdivision_id'] = 'Укажите причину смены подразделения (не короче 3 символов).';
            }
        }

        $prevDate = $application->desired_delivery_date?->toDateString() ?? '';
        if ($prevDate !== $validated['desired_delivery_date']) {
            $v = trim((string) $request->input('field_change_reasons.desired_delivery_date', ''));
            if (mb_strlen($v) < $min) {
                $messages['field_change_reasons.desired_delivery_date'] = 'Укажите причину изменения желаемой даты поставки (не короче 3 символов).';
            }
        }

        foreach ($validated['items'] as $idx => $row) {
            if (! $this->isNewEquipmentRowInEditRequest($row, $foremanMayReviseRejectedByBoilerChiefOnly)) {
                continue;
            }
            $v = trim((string) ($row['addition_reason'] ?? ''));
            if (mb_strlen($v) < $min) {
                $messages["items.{$idx}.addition_reason"] = 'Укажите комментарий: почему добавляете эту позицию (не короче 3 символов).';
            }
        }

        foreach ($validated['items'] as $row) {
            $itemId = isset($row['item_id']) ? (int) $row['item_id'] : null;
            if (! $itemId) {
                continue;
            }
            $existing = $application->items->firstWhere('id', $itemId);
            if (! $existing) {
                continue;
            }
            if ($foremanMayReviseRejectedByBoilerChiefOnly && $existing->is_checked) {
                continue;
            }
            if ($existing->is_checked && ! $managementMayEditCheckedEquipmentLines) {
                continue;
            }
            if ($this->itemRowChangedAspectKeys($existing, $row) !== []) {
                $v = trim((string) $request->input("item_change_reasons.{$itemId}", ''));
                if (mb_strlen($v) < $min) {
                    $messages["item_change_reasons.{$itemId}"] = 'Укажите комментарий: почему изменили эту позицию (не короче 3 символов).';
                }
            }
        }

        if ($messages !== []) {
            throw ValidationException::withMessages($messages);
        }
    }

    private function authorizeForemanCanModifyApplication(Request $request, Application $application): void
    {
        $user = $request->user();
        if (! $user || ! $user->hasRoleId(4)) {
            return;
        }

        if ($application->isAdminArchived()) {
            abort(403, 'Заявка в архиве. Изменения недоступны.');
        }

        if ($application->isBoilerChiefCreatedApplication()) {
            abort(403, 'Заявку создал начальник котельной — редактирование доступно только ему.');
        }

        if (! $application->foremanCanEditApplication()) {
            if (Subdivision::hasBoilerChiefAssigned((int) $application->subdivision_id)
                && ! $application->isForemanDraftBeforeBoilerChief()
                && ! $application->foremanCanReviseAfterBoilerChiefRejection()) {
                abort(403, 'Заявка отправлена на согласование — редактирование недоступно.');
            }

            abort(403, 'Заявка полностью согласована — мастер участка не может больше изменять её или добавлять новые позиции.');
        }
    }

    private function finalizeForemanResubmissionToBoilerChief(Application $application): void
    {
        DB::transaction(function () use ($application): void {
            $application->update([
                'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING),
            ]);
        });

        $application->refresh();
    }

    private function userUsesApplicationDraftSubmitFlow(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }
        if ($user->hasRoleId(self::BOILER_CHIEF_ROLE_ID)) {
            return true;
        }

        return $user->hasRoleId(4);
    }

    private function resolveApplicationStatusIdOnCreate(
        bool $isSiteForeman,
        bool $isBoilerChief,
        int $subdivisionId,
        string $submitAction
    ): int {
        if ($isSiteForeman && Subdivision::hasBoilerChiefAssigned($subdivisionId)) {
            return $submitAction === 'submit_to_boiler_chief'
                ? ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING)
                : ApplicationStatus::idForDraft();
        }
        if ($isBoilerChief) {
            return $submitAction === 'submit_for_management'
                ? ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING)
                : ApplicationStatus::idForDraft();
        }

        return ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING);
    }

    private function authorizeApplicationNotLockedAfterManagementApproval(Application $application): void
    {
        if ($application->managementHasSavedApproval()) {
            abort(403, 'Заявка согласована руководством и снабжением — редактирование недоступно.');
        }
    }

    private function authorizeBoilerChiefCanModifyApplication(Request $request, Application $application): void
    {
        $user = $request->user();
        if (! $user || ! $user->hasRoleId(self::BOILER_CHIEF_ROLE_ID)) {
            return;
        }

        if ($application->isAdminArchived()) {
            abort(403, 'Заявка в архиве. Изменения недоступны.');
        }

        if (! $application->boilerChiefCanEditApplication()) {
            if ((int) ($application->user_id ?? 0) === (int) $user->id
                && ! $application->isBoilerChiefDraftBeforeManagement()) {
                abort(403, 'Заявка отправлена на согласование — редактирование недоступно.');
            }

            abort(403, 'Заявка полностью согласована — редактирование недоступно.');
        }
    }

    private function authorizeCanRepeatApplications(Request $request): void
    {
        if (! $request->user() || ! $request->user()->hasAnyRoleId([4, self::BOILER_CHIEF_ROLE_ID])) {
            abort(403, 'Создание повторной заявки разрешено только мастеру участка и начальнику котельной.');
        }
    }

    private function availableSubdivisionsForCreate(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return Subdivision::query()->whereRaw('1 = 0')->get();
        }

        if ($user->hasRoleId(4)) {
            $query = $user->assignedSubdivisions()->orderBy('name');
            if (($adminId = AdministrationWarehouse::subdivisionId()) !== null) {
                $query->where('subdivisions.id', '!=', $adminId);
            }

            return $query->get();
        }
        if ($user->hasRoleId(self::BOILER_CHIEF_ROLE_ID)) {
            return $user->boilerChiefSubdivisions()->orderBy('name')->get();
        }

        return Subdivision::query()->active()->orderBy('name')->get();
    }

    private function resolveInstallationActPath(Application $application): ?string
    {
        return $this->resolveStoredPublicDiskAbsolutePath(trim((string) ($application->act_of_installation ?? '')));
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
    private function warehousesBySubdivisionForUi(): array
    {
        return Warehouse::query()
            ->whereNotNull('subdivision_id')
            ->orderBy('name')
            ->get(['subdivision_id', 'name'])
            ->groupBy(fn (Warehouse $w): string => (string) $w->subdivision_id)
            ->map(fn ($group) => $group->map(fn (Warehouse $w): array => [
                'name' => $w->name,
            ])->values()->all())
            ->all();
    }
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
    private function managementEditorRoleIds(): array
    {
        return User::MANAGEMENT_EDITOR_ROLE_IDS;
    }
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
        return $application->approvalLockedByShipmentProgress();
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
                'responsible_user_id' => 'Выбранный мастер участка не назначен на подразделение этой заявки.',
            ]);
        }
    }
    private function usesSubdivisionFirstResponsibleFilter(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && ($user->hasRoleId(self::BOILER_CHIEF_ROLE_ID) || $user->hasAnyRoleId(User::MANAGEMENT_EDITOR_ROLE_IDS));
    }
    private function foremanIdsBySubdivisionForUi(): array
    {
        $map = [];
        $foremen = User::query()
            ->where('role_id', 4)
            ->where('is_blocked', false)
            ->with(['assignedSubdivisions:id'])
            ->get(['id']);

        foreach ($foremen as $foreman) {
            foreach ($foreman->assignedSubdivisions as $subdivision) {
                $subdivisionKey = (string) $subdivision->id;
                $map[$subdivisionKey] ??= [];
                $map[$subdivisionKey][] = (string) $foreman->id;
            }
        }

        foreach ($map as $subdivisionId => $foremanIds) {
            $map[$subdivisionId] = array_values(array_unique($foremanIds));
        }

        return $map;
    }
    private function activeForemenForSubdivisionQuery(int $subdivisionId): Builder
    {
        return User::query()
            ->where('role_id', 4)
            ->where('is_blocked', false)
            ->whereHas('assignedSubdivisions', function ($q) use ($subdivisionId): void {
                $q->where('subdivisions.id', $subdivisionId);
            });
    }

    private function resolveMainWarehouseForAccounting(): ?Warehouse
    {
        return \App\Support\AdministrationWarehouse::resolvePrimaryWarehouse();
    }
    private function archiveCompletedApplicationIfReady(Application $application): ?string
    {
        $application->refresh();
        $application->load(['items', 'installationActPhotos']);

        if ($application->archiveIfEligible()) {
            return 'Заявка перенесена в архив выполненных.';
        }

        if (! $this->forceArchiveIfBusinessComplete($application)) {
            return null;
        }

        return 'Заявка перенесена в архив выполненных.';
    }

    private function forceArchiveIfBusinessComplete(Application $application): bool
    {
        if ($application->isArchived()) {
            return false;
        }

        $application->loadMissing(['items', 'installationActPhotos']);
        if ($application->items->isEmpty()) {
            return false;
        }
        if (! filled(trim((string) ($application->act_of_installation ?? '')))) {
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
            ->where('name', ApplicationStatus::NAME_COMPLETED)
            ->value('id');

        $application->moveToArchive([], $completedId !== null ? (int) $completedId : null);

        return true;
    }

    private function syncCompletionArchiveForEligibleApplications(): void
    {
        if (! Application::usesArchiveTable() && ! Schema::hasColumn('applications', 'archived_at')) {
            return;
        }

        if (! Cache::add('applications:completion-archive-sync', true, now()->addMinutes(5))) {
            return;
        }

        $candidates = Application::query()
            ->notArchived()
            ->whereNotNull('act_of_installation')
            ->where('act_of_installation', '!=', '')
            ->with(['items', 'installationActPhotos'])
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        foreach ($candidates as $candidate) {
            $this->archiveCompletedApplicationIfReady($candidate);
        }
    }
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

    private function allowedDeliverySubdivisionIdsForUser(?User $user): Collection
    {
        if (! $user) {
            return collect();
        }

        return $user->hasRoleId(self::BOILER_CHIEF_ROLE_ID)
            ? $user->boilerChiefSubdivisions()
                ->pluck('subdivisions.id')
                ->map(fn ($id) => (int) $id)
            : $user->assignedSubdivisions()
                ->pluck('subdivisions.id')
                ->map(fn ($id) => (int) $id);
    }

    private function markDeliveredWithReceipt(Application $application, ApplicationItem $item, int $deliveryWarehouseId): void
    {
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
        $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
        $alreadyReceived = MaterialStockMovement::query()
            ->where('material_stock_movement_type_id', $receiptTypeId)
            ->whereCorrelationKey($docRef)
            ->exists();

        if (! $alreadyReceived) {
            MaterialStockMovement::query()->create([
                'equipment_id' => (int) $item->equipment_id,
                'warehouse_id' => $deliveryWarehouseId,
                'material_stock_movement_type_id' => $receiptTypeId,
                'quantity' => (float) $item->quantity,
                'stock_bucket' => WarehouseStockBucket::GOOD,
                'unit_price' => null,
                'counterparty' => 'Доставка по заявке №'.$application->id,
                'comment' => MaterialStockMovement::packCommentWithCorrelation(
                    $docRef,
                    'Поступление на склад получателя по отметке «Доставлено».'
                ),
            ]);
        }

        $item->update([
            'delivery_status_id' => ApplicationItem::DELIVERY_DELIVERED_ID,
            'delivery_warehouse_id' => $deliveryWarehouseId,
        ]);
    }

    private function deliveryReceiptDocumentRef(int $applicationId, int $itemId, int $warehouseId): string
    {
        return 'APP:'.$applicationId.':ITEM:'.$itemId.':DELIVERY-RCPT:WH:'.$warehouseId;
    }
    private function installationIssueDocumentRef(int $applicationId, int $itemId): string
    {
        return 'APP:'.$applicationId.':ITEM:'.$itemId.':INSTALL';
    }
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

            return $this->remainingInstallationIssueQuantity($application, $item) >= 0.0005;
        })->values();
    }

    private function formatIssueQuantityForMessage(float $quantity): string
    {
        if (abs($quantity - round($quantity)) < 0.0005) {
            return (string) (int) round($quantity);
        }

        return rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');
    }

    private function installationIssuedQuantityForItem(Application $application, ApplicationItem $item): float
    {
        return WarehouseStockBucket::installationIssuedQuantityForApplicationItem(
            (int) $application->id,
            (int) $item->id,
        );
    }

    private function remainingInstallationIssueQuantity(Application $application, ApplicationItem $item): float
    {
        return WarehouseStockBucket::remainingInstallationIssueQuantity(
            (float) $item->quantity,
            (int) $application->id,
            (int) $item->id,
            (int) $item->equipment_id,
            (int) ($item->delivery_warehouse_id ?? 0),
        );
    }

    private function installationIssueMaxQuantitiesByItemId(Application $application): array
    {
        $quantities = [];

        foreach ($this->deliveredWarehouseIssueCandidates($application) as $item) {
            if (! $item instanceof ApplicationItem) {
                continue;
            }

            $quantities[(int) $item->id] = $this->remainingInstallationIssueQuantity($application, $item);
        }

        return $quantities;
    }
    private function resolveInstallationActIssueQuantities(
        Application $application,
        Collection $candidates,
        Collection $selectedItemIds,
        array $rawQuantities,
    ): array {
        $candidatesById = $candidates->keyBy('id');
        $resolved = [];
        $errors = [];

        foreach ($selectedItemIds as $itemId) {
            $item = $candidatesById->get($itemId);
            if (! $item instanceof ApplicationItem) {
                continue;
            }

            $orderedQty = (float) $item->quantity;
            $remainingQty = $this->remainingInstallationIssueQuantity($application, $item);
            $maxQty = min($orderedQty, $remainingQty);

            if (! array_key_exists($itemId, $rawQuantities) && ! array_key_exists((string) $itemId, $rawQuantities)) {
                $errors["issue_quantities.{$itemId}"] = 'Укажите количество к списанию.';

                continue;
            }

            $raw = $rawQuantities[$itemId] ?? $rawQuantities[(string) $itemId] ?? null;
            if ($raw === null || $raw === '') {
                $errors["issue_quantities.{$itemId}"] = 'Укажите количество к списанию.';

                continue;
            }

            if (! is_numeric($raw)) {
                $errors["issue_quantities.{$itemId}"] = 'Количество к списанию должно быть числом.';

                continue;
            }

            $qty = (float) $raw;
            if ($qty < 0.0005) {
                $errors["issue_quantities.{$itemId}"] = 'Нельзя списать 0. Укажите количество больше нуля.';

                continue;
            }

            if ($qty > $orderedQty + 0.0005) {
                $unit = $item->quantityUnitLabelForDisplay();
                $errors["issue_quantities.{$itemId}"] = 'Нельзя списать больше, чем заказано по заявке ('.$this->formatIssueQuantityForMessage($orderedQty).' '.$unit.').';

                continue;
            }

            if ($qty > $maxQty + 0.0005) {
                $unit = $item->quantityUnitLabelForDisplay();
                $errors["issue_quantities.{$itemId}"] = 'Нельзя списать больше остатка по позиции ('.$this->formatIssueQuantityForMessage($maxQty).' '.$unit.').';

                continue;
            }

            $resolved[(int) $itemId] = $qty;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $resolved;
    }
    private function writeOffDeliveredItemsOnRecipientWarehouses(
        Application $application,
        ?User $actor,
        string $movementComment,
        ?Collection $allowedItemIds = null,
        ?array $quantitiesByItemId = null,
    ): array {
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
            $remainingToIssue = $this->remainingInstallationIssueQuantity($application, $item);
            if ($remainingToIssue < 0.0005) {
                continue;
            }

            if (is_array($quantitiesByItemId) && array_key_exists((int) $item->id, $quantitiesByItemId)) {
                $issueQuantity = min((float) $quantitiesByItemId[(int) $item->id], $remainingToIssue);
            } else {
                $issueQuantity = $remainingToIssue;
            }

            if ($issueQuantity < 0.0005) {
                continue;
            }

            $deliveredQuantity = (float) $item->quantity;

            $deliveryReceiptRef = $this->deliveryReceiptDocumentRef((int) $application->id, (int) $item->id, $warehouseId);
            $receiptTypeId = MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_RECEIPT);
            $hasDeliveryReceipt = MaterialStockMovement::query()
                ->where('material_stock_movement_type_id', $receiptTypeId)
                ->whereCorrelationKey($deliveryReceiptRef)
                ->exists();
            if (! $hasDeliveryReceipt) {
                MaterialStockMovement::query()->create([
                    'equipment_id' => (int) $item->equipment_id,
                    'warehouse_id' => $warehouseId,
                    'material_stock_movement_type_id' => $receiptTypeId,
                    'quantity' => $deliveredQuantity,
                    'stock_bucket' => WarehouseStockBucket::GOOD,
                    'unit_price' => null,
                    'counterparty' => 'Восстановление прихода по доставке заявки №'.$application->id,
                    'comment' => MaterialStockMovement::packCommentWithCorrelation(
                        $deliveryReceiptRef,
                        'Автовосстановление прихода перед списанием доставленного оборудования.'
                    ),
                    'created_by_user_id' => (int) $user->id,
                ]);
            }

            $balance = $this->warehouseEquipmentBalance((int) $item->equipment_id, $warehouseId);
            if ($balance < $issueQuantity - 0.0005) {
                $warnings[] = 'Не списано «'.$item->equipment_display_name.'»: недостаточно остатка на складе получателя (по данным учёта).';

                continue;
            }

            MaterialStockMovement::query()->create([
                'equipment_id' => (int) $item->equipment_id,
                'warehouse_id' => $warehouseId,
                'material_stock_movement_type_id' => MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE),
                'quantity' => $issueQuantity,
                'stock_bucket' => WarehouseStockBucket::GOOD,
                'unit_price' => null,
                'counterparty' => 'Заявка №'.$application->id.' / '.$application->subdivision?->name,
                'comment' => MaterialStockMovement::packCommentWithCorrelation($docRef, $movementComment),
                'created_by_user_id' => (int) $user->id,
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
        $name = $baseName;

        if ($name === '' || $name === '—') {
            throw ValidationException::withMessages([
                'custom_supply' => 'У позиции нет названия для записи в справочник оборудования.',
            ]);
        }

        $name = mb_substr($name, 0, 150);
        $sizeForDb = $sizeValue !== '' ? mb_substr($sizeValue, 0, 120) : null;

        $measurementUnitId = $this->resolveMeasurementUnitIdForApplicationItem($item);
        $unitTypeId = $measurementUnitId !== null
            ? MeasurementUnit::query()->whereKey($measurementUnitId)->value('unit_type_id')
            : null;

        $reservedName = $this->buildReservedEquipmentName($name, $application, $item);

        return Equipment::query()->create([
            'name' => $reservedName,
            'value' => $sizeForDb,
            'measurement_unit_id' => $measurementUnitId,
            'unit_type_id' => $unitTypeId !== null ? (int) $unitTypeId : null,
            'is_catalog' => false,
        ]);
    }

    private function buildReservedEquipmentName(string $name, Application $application, ApplicationItem $item): string
    {
        $baseSuffix = ' [РЕЗЕРВ заявка '.$application->id.']';

        for ($n = 1; $n <= 50; $n++) {
            $suffix = $baseSuffix.($n === 1 ? '' : ' ('.$n.')');
            $maxBaseLength = 150 - mb_strlen($suffix);
            if ($maxBaseLength < 1) {
                $maxBaseLength = 1;
            }
            $candidate = mb_substr($name, 0, $maxBaseLength).$suffix;
            if (! Equipment::query()->whereRaw('LOWER(name) = LOWER(?)', [$candidate])->exists()) {
                return $candidate;
            }
        }

        $suffix = $baseSuffix.' #'.$item->id;
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
    private function catalogEquipmentForForms()
    {
        return $this->catalogEquipmentQuery()
            ->with(['measurementUnit.unitType'])
            ->orderBy('name')
            ->get();
    }
    private function catalogEquipmentMeasurementMetaById($equipment): array
    {
        $out = [];
        foreach ($equipment as $eq) {
            $out[(string) $eq->id] = [
                'unitType' => (string) ($eq->measurementUnit?->unitType?->code ?? 'piece'),
                'unitCode' => (string) ($eq->measurementUnit?->code ?? 'шт'),
            ];
        }

        return $out;
    }
    private function clothingCatalogSizeOptionsForApplications(): array
    {
        return ['XXS', 'XS', 'S', 'M', 'L', 'XL', 'XXL', 'XXXL', '4XL', '5XL'];
    }

    private function issuedQuantityForApplicationItem(int $applicationId, int $itemId): float
    {
        $sum = MaterialStockMovement::query()
            ->where('material_stock_movement_type_id', MaterialStockMovementType::idFor(MaterialStockMovementType::NAME_ISSUE))
            ->whereCorrelationKey($this->issueDocumentRef($applicationId, $itemId))
            ->sum('quantity');

        return (float) $sum;
    }

    private function warehouseEquipmentBalance(int $equipmentId, int $warehouseId): float
    {
        return WarehouseStockBucket::balance($equipmentId, $warehouseId, WarehouseStockBucket::GOOD);
    }

    private function itemEligibleForDeliveryDefectManagement(ApplicationItem $item): bool
    {
        return $item->is_checked
            && $item->equipment_id !== null
            && $item->resolvedDeliveryStatus() === ApplicationItem::DELIVERY_DELIVERED
            && (int) ($item->delivery_warehouse_id ?? 0) > 0;
    }

    private function maxDefectMarkQuantityForItem(Application $application, ApplicationItem $item): float
    {
        return WarehouseStockBucket::maxMarkableDefectQuantity(
            (float) $item->quantity,
            (int) $application->id,
            (int) $item->id,
            (int) $item->equipment_id,
            (int) ($item->delivery_warehouse_id ?? 0),
        );
    }
    private function catalogOverflowPendingOrderLabel(string $catalogName, int $overflowQty, array $normalized): string
    {
        $unit = trim((string) ($normalized['quantity_unit'] ?? 'шт'));
        $type = (string) ($normalized['measurement_type'] ?? 'piece');
        $size = trim((string) ($normalized['size_value'] ?? ''));
        if (PieceQuantity::isClothingMeasurement($type) && $size !== '') {
            $label = sprintf('%s (+на согласовании: %d шт., размер %s)', $catalogName, $overflowQty, $size);
        } else {
            $label = sprintf('%s (+на согласовании: %d %s)', $catalogName, $overflowQty, $unit);
        }

        return mb_substr($label, 0, 255);
    }
    private function expandCatalogRowsAgainstMainWarehouseVirtualStock(array $items): array
    {
        $mainWarehouse = $this->resolveMainWarehouseForAccounting();
        $catalogIds = [];
        foreach ($items as $row) {
            $rawId = $row['equipment_id'] ?? null;
            if ($rawId === null || $rawId === '') {
                continue;
            }
            $catalogIds[] = (int) $rawId;
        }
        $catalogIds = array_values(array_unique(array_filter($catalogIds, fn (int $id): bool => $id > 0)));
        $catalogEquipmentById = $catalogIds === []
            ? collect()
            : Equipment::query()
                ->whereIn('id', $catalogIds)
                ->where('is_catalog', true)
                ->get()
                ->keyBy('id');

        $reservedByStockKey = ApplicationCatalogStockAvailability::reservedQuantitiesByEquipmentId();
        $virtualAvailableByStockKey = [];
        $out = [];

        foreach ($items as $row) {
            $typeIdRaw = $row['equipment_id'] ?? null;
            $typeId = $typeIdRaw !== null && $typeIdRaw !== '' ? (int) $typeIdRaw : null;
            $name = trim((string) ($row['equipment_name'] ?? ''));
            if ($typeId === null && $name === '') {
                continue;
            }

            if ($typeId === null || $mainWarehouse === null || ! $catalogEquipmentById->has($typeId)) {
                $out[] = $row;

                continue;
            }

            $equipment = $catalogEquipmentById->get($typeId);
            $equipmentName = trim((string) $equipment->name);
            $normalized = $this->normalizeItemPayload($row, $equipmentName !== '' ? $equipmentName : null);
            $requested = (int) $normalized['quantity'];
            if ($requested < 1) {
                $out[] = $row;

                continue;
            }

            $sizeVariant = PieceQuantity::isClothingMeasurement($normalized['measurement_type'])
                ? trim((string) ($normalized['size_value'] ?? ''))
                : '';
            $stockKey = ApplicationCatalogStockAvailability::stockAggregateKey(
                $typeId,
                $sizeVariant !== '' ? $sizeVariant : null
            );

            if (! isset($virtualAvailableByStockKey[$stockKey])) {
                $balance = $sizeVariant !== ''
                    ? ApplicationCatalogStockAvailability::physicalBalanceOnWarehouse(
                        $typeId,
                        (int) $mainWarehouse->id,
                        $sizeVariant
                    )
                    : $this->warehouseEquipmentBalance($typeId, (int) $mainWarehouse->id);
                $reserved = (float) ($reservedByStockKey[$stockKey] ?? 0.0);
                $virtualAvailableByStockKey[$stockKey] = (int) max(0, (int) floor($balance - $reserved + 1e-9));
            }

            $fromStock = min($requested, $virtualAvailableByStockKey[$stockKey]);
            $over = $requested - $fromStock;
            $virtualAvailableByStockKey[$stockKey] -= $fromStock;

            if ($fromStock > 0) {
                $catalogRow = array_merge($row, [
                    'equipment_id' => $typeIdRaw,
                    'quantity' => $fromStock,
                    'measurement_type' => $normalized['measurement_type'],
                    'quantity_unit' => $normalized['quantity_unit'],
                    'size_value' => $normalized['size_value'] ?? '',
                ]);
                $out[] = $catalogRow;
            }
            if ($over > 0) {
                $overflowRow = [
                    'item_id' => null,
                    'equipment_id' => null,
                    'equipment_name' => $this->catalogOverflowPendingOrderLabel($equipmentName !== '' ? $equipmentName : 'Оборудование', $over, $normalized),
                    'quantity' => $over,
                    'measurement_type' => $normalized['measurement_type'],
                    'quantity_unit' => $normalized['quantity_unit'],
                    'size_value' => $normalized['size_value'] ?? '',
                ];
                if (isset($row['addition_reason']) && trim((string) $row['addition_reason']) !== '') {
                    $overflowRow['addition_reason'] = $row['addition_reason'];
                }
                $out[] = $overflowRow;
            }
        }

        return $out;
    }
    private function normalizeItemPayload(array $row, ?string $equipmentName = null): array
    {
        $rawName = trim((string) ($row['equipment_name'] ?? ''));
        $measurementType = $this->resolveRowMeasurementType($row);
        if (PieceQuantity::isPieceMeasurement($measurementType) || PieceQuantity::isClothingMeasurement($measurementType)) {
            PieceQuantity::assertWholeQuantity($row['quantity'] ?? null);
        }
        $quantity = PieceQuantity::isPieceMeasurement($measurementType) || PieceQuantity::isClothingMeasurement($measurementType)
            ? PieceQuantity::normalizeStoredQuantity($row['quantity'] ?? 1, $measurementType)
            : max(1, (int) round((float) str_replace(',', '.', trim((string) ($row['quantity'] ?? 1)))));
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
                'size_value' => PieceQuantity::isClothingMeasurement($measurementType) ? $sizeValue : null,
                'quantity' => $quantity,
                'measurement_type' => $measurementType,
                'quantity_unit' => $quantityUnit,
                'raw_input' => $rawInput,
            ];
        }

        [$parsedName, $parsedSize, $parsedQty, $parsedUnit] = $this->parseFreeEquipmentText($rawName);
        if ($parsedQty !== null && $quantity === 1) {
            if ($parsedUnit !== null && in_array($parsedUnit, $this->measurementUnitsMap()['mass'] ?? [], true)) {
                $quantity = max(1, (int) round($parsedQty));
            } elseif ($parsedUnit !== null && in_array($parsedUnit, $this->measurementUnitsMap()['length'] ?? [], true)) {
                $quantity = max(1, (int) round($parsedQty));
            } else {
                PieceQuantity::assertWholeQuantity($parsedQty);
                $quantity = PieceQuantity::normalizeStoredQuantity($parsedQty, PieceQuantity::MEASUREMENT_TYPE);
            }
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

        if (! PieceQuantity::isClothingMeasurement($measurementType)) {
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
    private function catalogEquipmentNameLookup(): array
    {
        $map = [];
        $register = function (string $label, int $id) use (&$map): void {
            $label = trim($label);
            if ($label === '') {
                return;
            }
            $map[mb_strtolower($label)] = $id;
        };

        foreach ($this->catalogEquipmentForForms() as $equipment) {
            $id = (int) $equipment->id;
            $register((string) $equipment->name, $id);
            $register($equipment->display_name, $id);
            if (preg_match('/^\s*[\w\d.-]+\s*[—–-]\s*(.+)$/u', (string) $equipment->name, $m)) {
                $register(trim((string) $m[1]), $id);
            }
        }

        return $map;
    }
    private function syncCatalogItemManualDetail(ApplicationItem $item, array $normalized): void
    {
        if ($item->equipment_id === null) {
            return;
        }

        $measurementType = (string) ($normalized['measurement_type'] ?? 'piece');
        if (! PieceQuantity::isClothingMeasurement($measurementType)) {
            $item->manualDetail()->delete();

            return;
        }

        $size = trim((string) ($normalized['size_value'] ?? ''));
        if ($size === '') {
            $item->manualDetail()->delete();

            return;
        }

        $item->manualDetail()->updateOrCreate(
            ['application_item_id' => $item->id],
            [
                'equipment_name' => null,
                'base_name' => null,
                'size_value' => $size,
                'measurement_type' => $measurementType,
                'quantity_unit' => (string) ($normalized['quantity_unit'] ?? 'разм'),
                'raw_input' => null,
            ]
        );
    }

    private function resolveRowMeasurementType(array $row): string
    {
        $measurementType = trim((string) ($row['measurement_type'] ?? ''));
        $map = $this->measurementUnitsMap();
        if ($measurementType !== '' && array_key_exists($measurementType, $map)) {
            return $measurementType;
        }

        $typeId = (int) ($row['equipment_id'] ?? 0);
        if ($typeId > 0) {
            $fromEquipment = Equipment::query()
                ->whereKey($typeId)
                ->with('measurementUnit.unitType:id,code')
                ->first()
                ?->measurementUnit
                ?->unitType
                ?->code;
            if (is_string($fromEquipment) && $fromEquipment !== '' && array_key_exists($fromEquipment, $map)) {
                return $fromEquipment;
            }
        }

        return PieceQuantity::MEASUREMENT_TYPE;
    }

    private function equipmentLineDuplicateKey(array $row, array $catalogNameToId): ?string
    {
        $measurementType = $this->resolveRowMeasurementType($row);
        $sizeValue = trim((string) ($row['size_value'] ?? ''));

        $equipmentIdRaw = $row['equipment_id'] ?? null;
        $typeId = $equipmentIdRaw !== null && $equipmentIdRaw !== '' ? (int) $equipmentIdRaw : 0;
        $baseKey = null;
        if ($typeId > 0) {
            $baseKey = 'catalog:'.$typeId;
        } else {
            $rawName = trim((string) ($row['equipment_name'] ?? ''));
            if ($rawName === '') {
                return null;
            }

            $lower = mb_strtolower($rawName);
            if (isset($catalogNameToId[$lower])) {
                $baseKey = 'catalog:'.$catalogNameToId[$lower];
            } elseif (preg_match('/^\s*[\w\d.-]+\s*[—–-]\s*(.+)$/u', $rawName, $m)) {
                $tail = mb_strtolower(trim((string) $m[1]));
                if ($tail !== '' && isset($catalogNameToId[$tail])) {
                    $baseKey = 'catalog:'.$catalogNameToId[$tail];
                }
            }

            if ($baseKey === null) {
                [$parsedName] = $this->parseFreeEquipmentText($rawName);
                $parsedLower = mb_strtolower(trim($parsedName));
                if ($parsedLower !== '' && isset($catalogNameToId[$parsedLower])) {
                    $baseKey = 'catalog:'.$catalogNameToId[$parsedLower];
                } else {
                    $identityLabel = $parsedLower !== '' ? $parsedLower : $lower;
                    $baseKey = 'custom:'.$identityLabel;
                }
            }
        }

        if ($baseKey === null) {
            return null;
        }

        if (PieceQuantity::isClothingMeasurement($measurementType) && $sizeValue !== '') {
            return $baseKey.':size:'.mb_strtoupper($sizeValue);
        }

        return $baseKey.':mt:'.$measurementType;
    }
    private function requestHasSubstantiveEquipmentItems(array $items): bool
    {
        return collect($items)->contains(function (array $item): bool {
            $equipmentId = $item['equipment_id'] ?? null;

            return ($equipmentId !== null && $equipmentId !== '' && (int) $equipmentId > 0)
                || trim((string) ($item['equipment_name'] ?? '')) !== '';
        });
    }
    private function willHaveEquipmentForSubmission(Application $application, array $items): bool
    {
        if ($this->requestHasSubstantiveEquipmentItems($items)) {
            return true;
        }

        return $application->items()->exists();
    }

    private function validateUniqueEquipmentLines(array $items): void
    {
        $catalogNameToId = $this->catalogEquipmentNameLookup();
        $seenKeys = [];

        foreach ($items as $idx => $row) {
            $name = trim((string) ($row['equipment_name'] ?? ''));
            $equipmentIdRaw = $row['equipment_id'] ?? null;
            $hasEquipmentId = $equipmentIdRaw !== null && $equipmentIdRaw !== '' && (int) $equipmentIdRaw > 0;
            if ($name === '' && ! $hasEquipmentId) {
                continue;
            }

            $key = $this->equipmentLineDuplicateKey($row, $catalogNameToId);
            if ($key === null) {
                continue;
            }

            if (isset($seenKeys[$key])) {
                $isClothingSizeDup = str_contains($key, ':size:');
                throw ValidationException::withMessages([
                    'equipment' => $isClothingSizeDup
                        ? 'Нельзя добавить две строки с одним и тем же наименованием и размером.'
                        : 'Нельзя добавить две строки с одинаковым наименованием и типом измерения.',
                    "items.{$idx}.equipment_name" => $isClothingSizeDup
                        ? 'Этот размер уже указан в другой строке для той же позиции.'
                        : 'Такая позиция с этим типом измерения уже есть в заявке.',
                ]);
            }

            $seenKeys[$key] = $idx;
        }
    }
    private function validateSubstantiveEquipmentItemQuantities(array $items): void
    {
        foreach ($items as $idx => $row) {
            $name = trim((string) ($row['equipment_name'] ?? ''));
            $equipmentIdRaw = $row['equipment_id'] ?? null;
            $hasEquipmentId = $equipmentIdRaw !== null && $equipmentIdRaw !== '' && (int) $equipmentIdRaw > 0;
            if ($name === '' && ! $hasEquipmentId) {
                continue;
            }
            $qty = $row['quantity'] ?? null;
            if ($qty === null || $qty === '') {
                throw ValidationException::withMessages([
                    "items.{$idx}.quantity" => 'Укажите количество.',
                ]);
            }

            $measurementType = $this->resolveRowMeasurementType($row);

            if (PieceQuantity::isPieceMeasurement($measurementType) || PieceQuantity::isClothingMeasurement($measurementType)) {
                PieceQuantity::assertWholeQuantity($qty, "items.{$idx}.quantity");
                if ((int) round((float) str_replace(',', '.', trim((string) $qty))) < 1) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.quantity" => 'Укажите количество.',
                    ]);
                }
            } elseif ((float) str_replace(',', '.', trim((string) $qty)) < 0.0005) {
                throw ValidationException::withMessages([
                    "items.{$idx}.quantity" => 'Укажите количество.',
                ]);
            }
        }
    }
    private function validateCustomEquipmentRowsHaveMeasurementType(array $items): void
    {
        foreach ($items as $idx => $row) {
            $equipmentIdRaw = $row['equipment_id'] ?? null;
            $equipmentId = $equipmentIdRaw !== null && $equipmentIdRaw !== '' ? (int) $equipmentIdRaw : null;
            if ($equipmentId !== null && $equipmentId > 0) {
                continue;
            }
            $name = trim((string) ($row['equipment_name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $type = trim((string) ($row['measurement_type'] ?? ''));
            if ($type === '') {
                throw ValidationException::withMessages([
                    "items.{$idx}.measurement_type" => 'Выберите тип единицы измерения для своего оборудования.',
                ]);
            }
        }
    }
    private function validateMeasurementPairs(array $items): void
    {
        $map = $this->measurementUnitsMap();
        foreach ($items as $idx => $row) {
            $name = trim((string) ($row['equipment_name'] ?? ''));
            $equipmentIdRaw = $row['equipment_id'] ?? null;
            $hasEquipmentId = $equipmentIdRaw !== null && $equipmentIdRaw !== '' && (int) $equipmentIdRaw > 0;
            if ($name === '' && ! $hasEquipmentId) {
                continue;
            }

            $type = trim((string) ($row['measurement_type'] ?? ''));
            if ($type === '') {
                if ($hasEquipmentId) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.measurement_type" => 'Некорректный тип единицы измерения.',
                    ]);
                }

                continue;
            }

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
                        "items.{$idx}.size_value" => 'Для типа «Размер одежды» укажите размер.',
                    ]);
                }
                $equipmentIdRaw = $row['equipment_id'] ?? null;
                $equipmentId = $equipmentIdRaw !== null && $equipmentIdRaw !== '' ? (int) $equipmentIdRaw : null;
                if ($equipmentId !== null && $equipmentId > 0) {
                    $allowed = $this->clothingCatalogSizeOptionsForApplications();
                    if (! in_array($size, $allowed, true)) {
                        throw ValidationException::withMessages([
                            "items.{$idx}.size_value" => 'Выберите размер из списка.',
                        ]);
                    }
                }
            }
        }
    }
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
                'clothing_size' => 'Размер',
            ];
        }

        return [
            'typeOptions' => $typeOptions,
            'unitsByType' => $unitsByType,
            'clothingSizes' => $this->clothingCatalogSizeOptionsForApplications(),
        ];
    }

    private function defaultUnitForType(string $type): string
    {
        $map = $this->measurementUnitsMap();

        return $map[$type][0] ?? 'шт';
    }
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
    private function indexAllowedSortFields(): array
    {
        return [
            'created_at' => 'created_at',
            'desired_delivery_date' => 'desired_delivery_date',
            'subdivision' => 'subdivision_id',
            'responsible' => 'responsible_user_id',
            'author' => 'user_id',
            'approved_by' => 'approved_by_user_id',
        ];
    }
    private function redirectAfterApplicationSubmitAction(
        Request $request,
        Application $application,
        ?string $status = null,
        array $errors = [],
    ): RedirectResponse {
        $redirect = redirect()->to($this->applicationSubmitRedirectUrl($request, $application));

        if ($errors !== []) {
            return $redirect->withErrors($errors);
        }

        return $redirect->with('status', $status);
    }

    private function applicationSubmitRedirectUrl(Request $request, Application $application): string
    {
        $returnUrl = trim((string) $request->input('_return_url', ''));
        if ($returnUrl !== '' && $this->isSafeApplicationReturnUrl($returnUrl)) {
            return $returnUrl;
        }

        return route('applications.show', $application);
    }

    private function isSafeApplicationReturnUrl(string $url): bool
    {
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        if (! str_starts_with($url, $appUrl.'/')) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH) ?? '';

        return $path === '/applications'
            || str_starts_with($path, '/applications/')
            || $path === '/applications/archive';
    }
}
