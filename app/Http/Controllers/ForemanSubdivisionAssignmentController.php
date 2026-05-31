<?php

// контроллер
namespace App\Http\Controllers;

use App\Models\Subdivision;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DadataAddressService;
use App\Support\AdministrationWarehouse;
use App\Support\AssignmentListPerPage;
use App\Support\ForemanApplicationReassignment;
use App\Support\SubdivisionInfrastructureDeactivation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class ForemanSubdivisionAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        return $this->subdivisionDirectory($request, archived: false);
    }

    public function archiveIndex(Request $request): View
    {
        return $this->subdivisionDirectory($request, archived: true);
    }

    private function subdivisionDirectory(Request $request, bool $archived): View
    {
        $this->authorizeForView($request);
        $pagination = AssignmentListPerPage::fromRequest($request);
        $perPage = $pagination['perPage'];
        $allowedPerPage = $pagination['allowedPerPage'];
        $defaultPerPage = $pagination['defaultPerPage'];

        $canViewAdministration = AdministrationWarehouse::userCanAccess($request->user());
        $administrationSubdivision = $canViewAdministration && ! $archived
            ? AdministrationWarehouse::resolveSubdivisionWithWarehouses()
            : null;

        $subdivisionsQuery = Subdivision::query()
            ->with(['warehouses' => fn ($q) => $q->orderBy('name'), 'archive'])
            ->orderBy('name');

        if ($archived) {
            $subdivisionsQuery->archived();
        } else {
            $subdivisionsQuery->active();
        }

        AdministrationWarehouse::excludeAdministrationSubdivision($subdivisionsQuery);

        if ($request->user()?->hasRoleId(7)) {
            $ids = $request->user()->boilerChiefSubdivisions()->pluck('subdivisions.id');
            $subdivisionsQuery->whereIn('id', $ids);
        }

        $subdivisions = $subdivisionsQuery
            ->paginate($perPage)
            ->withQueryString();
        $subdivisionOptionsQuery = Subdivision::query()->active()->orderBy('name');
        if (! $canViewAdministration) {
            AdministrationWarehouse::excludeAdministrationSubdivision($subdivisionOptionsQuery);
        }
        $subdivisionOptions = $subdivisionOptionsQuery->get(['id', 'name']);

        $canManage = $this->canManageSubdivisionInfrastructure($request) && ! $archived;
        $canDeleteInfrastructure = $this->canDeleteSubdivisionInfrastructure($request) && ! $archived;

        return view('foreman-subdivisions.subdivisions-readonly', compact(
            'subdivisions',
            'subdivisionOptions',
            'canManage',
            'canDeleteInfrastructure',
            'canViewAdministration',
            'administrationSubdivision',
            'perPage',
            'allowedPerPage',
            'defaultPerPage',
            'archived',
        ));
    }

    public function subdivisionDeactivatePreview(Request $request, Subdivision $subdivision): JsonResponse
    {
        $this->authorizeSubdivisionInfrastructureDelete($request);

        return response()->json(
            app(SubdivisionInfrastructureDeactivation::class)->subdivisionDeactivatePreview($subdivision)
        );
    }

    public function deactivateSubdivision(Request $request, Subdivision $subdivision): RedirectResponse
    {
        $this->authorizeSubdivisionInfrastructureDelete($request);

        $validated = $request->validate([
            'chief_subdivisions' => ['sometimes', 'array'],
            'chief_subdivisions.*' => ['nullable'],
            'foreman_subdivisions' => ['sometimes', 'array'],
            'foreman_subdivisions.*' => ['nullable'],
        ]);

        try {
            app(SubdivisionInfrastructureDeactivation::class)->deactivateSubdivision(
                $subdivision,
                is_array($validated['chief_subdivisions'] ?? null) ? $validated['chief_subdivisions'] : [],
                is_array($validated['foreman_subdivisions'] ?? null) ? $validated['foreman_subdivisions'] : [],
                $request->user()?->id,
            );
        } catch (ValidationException $e) {
            return redirect()
                ->route('foreman-subdivisions.index')
                ->withErrors($e->errors());
        }

        return redirect()
            ->route('foreman-subdivisions.index')
            ->with('status', 'Подразделение «'.$subdivision->name.'» сделано недоступным.');
    }

    public function assignments(Request $request): View
    {
        $this->authorizeForemanAssignmentAccess($request);

        $pagination = AssignmentListPerPage::fromRequest($request);
        $perPage = $pagination['perPage'];
        $allowedPerPage = $pagination['allowedPerPage'];
        $defaultPerPage = $pagination['defaultPerPage'];

        $search = trim((string) $request->input('q', ''));

        $foremenQuery = User::query()
            ->where('role_id', 4)
            ->with(['assignedSubdivisions' => fn ($q) => $q->orderBy('name')]);

        if ($search !== '') {
            $foremenQuery->where(function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query
                    ->where('surname', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('patronymic', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $foremen = $foremenQuery
            ->orderBy('surname')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('foreman-subdivisions.index', compact(
            'foremen',
            'search',
            'perPage',
            'allowedPerPage',
            'defaultPerPage',
        ));
    }

    public function storeSubdivision(Request $request): RedirectResponse
    {
        $this->authorizeSubdivisionInfrastructureManage($request);

        $request->merge([
            'subdivision_name' => trim((string) $request->input('subdivision_name', '')),
        ]);

        $validated = $request->validate([
            'subdivision_name' => ['required', 'string', 'max:255', 'unique:subdivisions,name'],
        ], [
            'subdivision_name.required' => 'Укажите название подразделения.',
            'subdivision_name.string' => 'Название подразделения должно быть текстом.',
            'subdivision_name.max' => 'Название подразделения не может быть длиннее :max символов.',
            'subdivision_name.unique' => 'Подразделение с таким названием уже существует.',
        ]);

        $normalizedName = mb_strtolower(trim((string) $validated['subdivision_name']));
        $alreadyExists = Subdivision::query()
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])
            ->exists();
        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'subdivision_name' => 'Подразделение с таким названием уже существует.',
            ]);
        }

        Subdivision::query()->create([
            'name' => $validated['subdivision_name'],
        ]);

        return redirect()
            ->route('foreman-subdivisions.index')
            ->with('status', 'Подразделение добавлено.');
    }

    public function storeWarehouse(Request $request): RedirectResponse
    {
        $this->authorizeSubdivisionInfrastructureManage($request);

        $validated = $request->validate([
            'subdivision_id' => ['required', 'integer', 'exists:subdivisions,id'],
            'warehouse_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'comment' => ['nullable', 'string'],
        ], [
            'subdivision_id.required' => 'Выберите подразделение.',
            'subdivision_id.exists' => 'Выбрано несуществующее подразделение.',
            'warehouse_name.required' => 'Укажите название склада.',
            'warehouse_name.string' => 'Название склада должно быть текстом.',
            'warehouse_name.max' => 'Название склада не может быть длиннее :max символов.',
            'address.required' => 'Укажите адрес склада.',
            'address.string' => 'Адрес склада должен быть текстом.',
            'address.max' => 'Адрес склада не может быть длиннее :max символов.',
        ]);

        if (AdministrationWarehouse::isAdministrationSubdivisionId((int) $validated['subdivision_id'])
            && ! AdministrationWarehouse::userCanManageWarehouses($request->user())) {
            throw ValidationException::withMessages([
                'subdivision_id' => 'Склады подразделения «'.AdministrationWarehouse::SUBDIVISION_NAME.'» может добавлять только директор, начальник отдела снабжения или администратор.',
            ]);
        }

        $subdivision = Subdivision::query()->find((int) $validated['subdivision_id']);
        if ($subdivision === null || $subdivision->isArchived()) {
            throw ValidationException::withMessages([
                'subdivision_id' => 'Нельзя добавить склад к недоступному подразделению.',
            ]);
        }

        $warehouseName = trim((string) $validated['warehouse_name']);

        if (AdministrationWarehouse::isReservedWarehouseName($warehouseName)) {
            throw ValidationException::withMessages([
                'warehouse_name' => 'Склад «'.AdministrationWarehouse::WAREHOUSE_NAME.'» уже есть в системе (главный склад). Добавить его повторно нельзя — укажите другое название.',
            ]);
        }

        $duplicateInSubdivision = Warehouse::query()
            ->where('subdivision_id', (int) $validated['subdivision_id'])
            ->whereRaw('LOWER(TRIM(name)) = ?', [AdministrationWarehouse::normalizeWarehouseName($warehouseName)])
            ->exists();
        if ($duplicateInSubdivision) {
            throw ValidationException::withMessages([
                'warehouse_name' => 'В этом подразделении уже есть склад с таким названием.',
            ]);
        }

        $normalizedAddress = trim((string) $validated['address']);
        $addressParts = [
            'address_postal_code' => null,
            'address_region' => null,
            'address_city' => null,
            'address_street' => null,
            'address_house' => null,
            'address_block' => null,
            'address_flat' => null,
            'address_fias_id' => null,
        ];

        try {
            $dadata = app(DadataAddressService::class);
            $cleaned = $dadata->clean($normalizedAddress);
            if ($cleaned !== []) {
                $addressParts = [
                    'address_postal_code' => $this->toNullableString($cleaned['postal_code'] ?? null, 20),
                    'address_region' => $this->toNullableString($cleaned['region_with_type'] ?? null, 150),
                    'address_city' => $this->toNullableString(($cleaned['city_with_type'] ?? null) ?: ($cleaned['settlement_with_type'] ?? null), 150),
                    'address_street' => $this->toNullableString($cleaned['street_with_type'] ?? null, 150),
                    'address_house' => $this->toNullableString($cleaned['house'] ?? null, 50),
                    'address_block' => $this->toNullableString($cleaned['block'] ?? null, 50),
                    'address_flat' => $this->toNullableString($cleaned['flat'] ?? null, 50),
                    'address_fias_id' => $this->toNullableString($cleaned['fias_id'] ?? null, 50),
                ];
            }
        } catch (RuntimeException) {
        }

        if (! $this->warehouseStructuredAddressIsPresent($addressParts)) {
            throw ValidationException::withMessages([
                'address' => 'Не удалось распознать адрес. Уточните строку адреса или проверьте настройки DaData (ключи и доступность сервиса).',
            ]);
        }

        if (Warehouse::existsWithStructuredAddress($addressParts)) {
            throw ValidationException::withMessages([
                'address' => 'Склад с таким адресом уже есть в системе. Укажите другой адрес.',
            ]);
        }

        DB::transaction(function () use ($validated, $warehouseName, $addressParts): void {
            Warehouse::query()->create([
                'subdivision_id' => (int) $validated['subdivision_id'],
                'name' => $warehouseName,
                ...$addressParts,
                'is_primary' => false,
                'comment' => isset($validated['comment']) ? trim((string) $validated['comment']) : null,
                'warehouse_type_id' => null,
            ]);
        });

        return redirect()
            ->route('foreman-subdivisions.index')
            ->with('status', 'Склад добавлен.');
    }

    public function edit(Request $request, User $foreman): View
    {
        $this->authorizeForemanAssignmentAccess($request);

        if (! $foreman->hasRoleId(4)) {
            abort(404);
        }

        $foreman->load(['assignedSubdivisions' => fn ($q) => $q->orderBy('name')]);
        $chiefManagedIds = $this->chiefManagedSubdivisionIds($request);
        $subdivisionsQuery = Subdivision::query()->active()->orderBy('name');
        AdministrationWarehouse::excludeAdministrationSubdivision($subdivisionsQuery);
        $subdivisions = $chiefManagedIds !== null
            ? $subdivisionsQuery->whereIn('id', $chiefManagedIds)->get()
            : $subdivisionsQuery->get();
        $foremanAssignmentRestrictedToChiefSubdivisions = $chiefManagedIds !== null;

        return view('foreman-subdivisions.edit', compact(
            'foreman',
            'subdivisions',
            'foremanAssignmentRestrictedToChiefSubdivisions',
        ));
    }

    public function updatePreview(Request $request, User $foreman): JsonResponse
    {
        $this->authorizeForemanAssignmentAccess($request);

        if (! $foreman->hasRoleId(4)) {
            abort(404);
        }

        $newSubdivisionIds = $this->resolveNewSubdivisionIdsForUpdate($request, $foreman);
        $reassignment = app(ForemanApplicationReassignment::class);
        $requires = $reassignment->requiresReassignmentBeforeSubdivisionRemoval($foreman, $newSubdivisionIds);

        return response()->json([
            'requires_reassignment' => $requires,
            'removed_subdivision_ids' => $requires
                ? $reassignment->removedSubdivisionIds($foreman, $newSubdivisionIds)
                : [],
            'applications' => $requires
                ? $reassignment->subdivisionRemovalPreviewPayload($foreman, $newSubdivisionIds)
                : [],
        ]);
    }

    public function update(Request $request, User $foreman): RedirectResponse
    {
        $this->authorizeForemanAssignmentAccess($request);

        if (! $foreman->hasRoleId(4)) {
            abort(404);
        }

        $chiefManagedIds = $this->chiefManagedSubdivisionIds($request);
        $subdivisionIdRule = ['integer', 'exists:subdivisions,id'];
        if ($chiefManagedIds !== null) {
            $subdivisionIdRule[] = Rule::in($chiefManagedIds->all());
        }

        $validated = $request->validate([
            'subdivision_ids' => ['array'],
            'subdivision_ids.*' => $subdivisionIdRule,
            'reassignments' => ['sometimes', 'array'],
            'reassignments.*' => ['integer'],
        ]);

        $newSubdivisionIds = $this->resolveNewSubdivisionIdsFromValidated($foreman, $validated, $chiefManagedIds);
        AdministrationWarehouse::rejectForemanAssignmentToAdministration($newSubdivisionIds);
        $newSubdivisionIds = AdministrationWarehouse::withoutAdministrationSubdivisionIds($newSubdivisionIds);
        $reassignment = app(ForemanApplicationReassignment::class);

        try {
            DB::transaction(function () use ($request, $foreman, $newSubdivisionIds, $reassignment): void {
                if ($reassignment->requiresReassignmentBeforeSubdivisionRemoval($foreman, $newSubdivisionIds)) {
                    $reassignment->applySubdivisionRemovalReassignments(
                        $foreman,
                        $newSubdivisionIds,
                        is_array($request->input('reassignments')) ? $request->input('reassignments') : []
                    );
                }

                $foreman->assignedSubdivisions()->sync($newSubdivisionIds);
            });
        } catch (ValidationException $e) {
            return redirect()
                ->route('foreman-subdivisions.edit', $foreman)
                ->withErrors($e->errors())
                ->withInput();
        }

        $hadReassignments = is_array($request->input('reassignments'))
            && $request->input('reassignments') !== [];

        return redirect()
            ->route('foreman-subdivisions.assignments')
            ->with('status', $hadReassignments
                ? 'Заявки переназначены, назначения мастера обновлены.'
                : 'Назначения для мастера участка обновлены.');
    }
    private function resolveNewSubdivisionIdsForUpdate(Request $request, User $foreman): array
    {
        $chiefManagedIds = $this->chiefManagedSubdivisionIds($request);
        $raw = $request->input('subdivision_ids', []);
        if (! is_array($raw)) {
            $raw = [];
        }

        $selectedFromForm = collect($raw)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($chiefManagedIds !== null) {
            $allowed = $chiefManagedIds->flip();
            $selectedFromForm = $selectedFromForm->filter(fn (int $id) => $allowed->has($id))->values();
        }

        return $this->resolveNewSubdivisionIdsFromValidated(
            $foreman,
            ['subdivision_ids' => $selectedFromForm->all()],
            $chiefManagedIds
        );
    }
    private function resolveNewSubdivisionIdsFromValidated(User $foreman, array $validated, ?Collection $chiefManagedIds): array
    {
        $selectedFromForm = collect($validated['subdivision_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($chiefManagedIds !== null) {
            $foreman->loadMissing('assignedSubdivisions:id');
            $existingIds = $foreman->assignedSubdivisions->pluck('id')->map(fn ($id) => (int) $id);
            $managedSet = $chiefManagedIds->flip();
            $outsideChiefZone = $existingIds->filter(fn (int $id) => ! $managedSet->has($id))->values();

            return $outsideChiefZone->merge($selectedFromForm)->unique()->values()->all();
        }

        return $selectedFromForm->all();
    }

    private function authorizeForView(Request $request): void
    {
        $allowed = $request->user()?->hasAnyRoleId([1, 6, 2, 3, 5]) ?? false;

        if (! $allowed) {
            abort(403, 'Просмотр подразделений и складов разрешён только директору, техническому директору, начальнику отдела снабжения, администратору и бухгалтеру.');
        }
    }
    private function authorizeForemanAssignmentAccess(Request $request): void
    {
        if ($this->canAssignForemenToSubdivisions($request)) {
            return;
        }

        abort(403, 'Назначение мастеров участка по подразделениям разрешено директору, техническому директору, начальнику отдела снабжения и администратору.');
    }

    private function authorizeSubdivisionInfrastructureManage(Request $request): void
    {
        if (! $this->canManageSubdivisionInfrastructure($request)) {
            abort(403, 'Создание подразделений и складов разрешено только директору, начальнику отдела снабжения и администратору.');
        }
    }

    private function canAssignForemenToSubdivisions(Request $request): bool
    {
        return $request->user()?->hasAnyRoleId(User::SUBDIVISION_ASSIGNMENT_MANAGER_ROLE_IDS) ?? false;
    }

    private function canManageSubdivisionInfrastructure(Request $request): bool
    {
        return $request->user()?->hasAnyRoleId(User::SUBDIVISION_INFRASTRUCTURE_MANAGER_ROLE_IDS) ?? false;
    }

    private function canDeleteSubdivisionInfrastructure(Request $request): bool
    {
        return (bool) $request->user()?->hasRoleId(User::ADMINISTRATOR_ROLE_ID);
    }

    private function authorizeSubdivisionInfrastructureDelete(Request $request): void
    {
        if (! $this->canDeleteSubdivisionInfrastructure($request)) {
            abort(403, 'Удаление подразделений и складов разрешено только администратору.');
        }
    }
    private function chiefManagedSubdivisionIds(Request $request): ?Collection
    {
        if (! $request->user()?->hasRoleId(7)) {
            return null;
        }

        return $request->user()->boilerChiefSubdivisions()->pluck('subdivisions.id')->map(fn ($id) => (int) $id)->values();
    }

    private function toNullableString(mixed $value, int $maxLength): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $maxLength);
    }
    private function warehouseStructuredAddressIsPresent(array $addressParts): bool
    {
        foreach ($addressParts as $key => $value) {
            if ($key === 'address_fias_id') {
                continue;
            }
            if ($value !== null && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }
}
