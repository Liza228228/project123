<?php

namespace App\Http\Controllers;

use App\Models\Subdivision;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\DadataAddressService;
use App\Support\AssignmentListPerPage;
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
        $this->authorizeForView($request);
        $pagination = AssignmentListPerPage::fromRequest($request);
        $perPage = $pagination['perPage'];
        $allowedPerPage = $pagination['allowedPerPage'];
        $defaultPerPage = $pagination['defaultPerPage'];

        $subdivisionsQuery = Subdivision::query()
            ->with(['warehouses' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name');

        if ($request->user()?->hasRoleId(7)) {
            $ids = $request->user()->boilerChiefSubdivisions()->pluck('subdivisions.id');
            $subdivisionsQuery->whereIn('id', $ids);
        }

        $subdivisions = $subdivisionsQuery
            ->paginate($perPage)
            ->withQueryString();
        $subdivisionOptions = Subdivision::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $canManage = $this->canManageSubdivisionInfrastructure($request);

        return view('foreman-subdivisions.subdivisions-readonly', compact(
            'subdivisions',
            'subdivisionOptions',
            'canManage',
            'perPage',
            'allowedPerPage',
            'defaultPerPage',
        ));
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
            'code' => ['required', 'string', 'max:255', 'unique:warehouses,code'],
            'address' => ['required', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
            'comment' => ['nullable', 'string'],
        ], [
            'subdivision_id.required' => 'Выберите подразделение.',
            'subdivision_id.exists' => 'Выбрано несуществующее подразделение.',
            'warehouse_name.required' => 'Укажите название склада.',
            'warehouse_name.string' => 'Название склада должно быть текстом.',
            'warehouse_name.max' => 'Название склада не может быть длиннее :max символов.',
            'code.required' => 'Укажите код склада.',
            'code.string' => 'Код склада должен быть текстом.',
            'code.max' => 'Код склада не может быть длиннее :max символов.',
            'code.unique' => 'Склад с таким кодом уже существует.',
            'address.required' => 'Укажите адрес склада.',
            'address.string' => 'Адрес склада должен быть текстом.',
            'address.max' => 'Адрес склада не может быть длиннее :max символов.',
        ]);

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
            /** @var DadataAddressService $dadata */
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
            //
        }

        if (! $this->warehouseStructuredAddressIsPresent($addressParts)) {
            throw ValidationException::withMessages([
                'address' => 'Не удалось распознать адрес. Уточните строку адреса или проверьте настройки DaData (ключи и доступность сервиса).',
            ]);
        }

        $warehouseName = trim((string) $validated['warehouse_name']);
        $isAdministrationWarehouse = preg_match('/администрац/iu', $warehouseName) === 1;
        $isPrimary = (bool) ($validated['is_primary'] ?? false) || $isAdministrationWarehouse;

        DB::transaction(function () use ($validated, $warehouseName, $addressParts, $isPrimary): void {
            if ($isPrimary) {
                // In the system there must be only one priority warehouse.
                Warehouse::query()->where('is_primary', true)->update(['is_primary' => false]);
            }

            Warehouse::query()->create([
                'subdivision_id' => (int) $validated['subdivision_id'],
                'name' => $warehouseName,
                'code' => trim((string) $validated['code']),
                ...$addressParts,
                'is_primary' => $isPrimary,
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
        $subdivisions = $chiefManagedIds !== null
            ? Subdivision::query()->whereIn('id', $chiefManagedIds)->orderBy('name')->get()
            : Subdivision::query()->orderBy('name')->get();
        $foremanAssignmentRestrictedToChiefSubdivisions = $chiefManagedIds !== null;

        return view('foreman-subdivisions.edit', compact(
            'foreman',
            'subdivisions',
            'foremanAssignmentRestrictedToChiefSubdivisions',
        ));
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
        ]);

        $selectedFromForm = collect($validated['subdivision_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($chiefManagedIds !== null) {
            $foreman->loadMissing('assignedSubdivisions:id');
            $existingIds = $foreman->assignedSubdivisions->pluck('id')->map(fn ($id) => (int) $id);
            $managedSet = $chiefManagedIds->flip();
            $outsideChiefZone = $existingIds->filter(fn (int $id) => ! $managedSet->has($id))->values();
            $merged = $outsideChiefZone->merge($selectedFromForm)->unique()->values();
            $foreman->assignedSubdivisions()->sync($merged->all());
        } else {
            $foreman->assignedSubdivisions()->sync($selectedFromForm->all());
        }

        return redirect()
            ->route('foreman-subdivisions.assignments')
            ->with('status', 'Назначения для мастера участка обновлены.');
    }

    private function authorizeForView(Request $request): void
    {
        $allowed = $request->user()?->hasAnyRoleId([1, 6, 2, 3, 5]) ?? false;

        if (! $allowed) {
            abort(403, 'Просмотр подразделений и складов разрешён только директору, техническому директору, начальнику отдела снабжения, администратору и бухгалтеру.');
        }
    }

    /**
     * Просмотр списка мастеров и правка назначений мастеров на подразделения.
     */
    private function authorizeForemanAssignmentAccess(Request $request): void
    {
        if ($this->canAssignForemenToSubdivisions($request)) {
            return;
        }

        abort(403, 'Назначение мастеров участка по подразделениям разрешено директору, техническому директору, начальнику отдела снабжения, администратору и начальнику котельной (только по своим подразделениям).');
    }

    private function authorizeSubdivisionInfrastructureManage(Request $request): void
    {
        if (! $this->canManageSubdivisionInfrastructure($request)) {
            abort(403, 'Создание подразделений и складов разрешено только директору, техническому директору, начальнику отдела снабжения и администратору.');
        }
    }

    private function canAssignForemenToSubdivisions(Request $request): bool
    {
        return $this->canManageSubdivisionInfrastructure($request)
            || (bool) $request->user()?->hasRoleId(7);
    }

    private function canManageSubdivisionInfrastructure(Request $request): bool
    {
        return $request->user()?->hasAnyRoleId(User::SUBDIVISION_ASSIGNMENT_MANAGER_ROLE_IDS) ?? false;
    }

    /**
     * Для начальника котельной — id подразделений в его зоне; иначе null (полный доступ).
     *
     * @return Collection<int, int>|null
     */
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

    /**
     * @param  array<string, ?string>  $addressParts
     */
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
