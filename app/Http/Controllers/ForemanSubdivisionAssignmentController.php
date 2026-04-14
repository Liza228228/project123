<?php

namespace App\Http\Controllers;

use App\Models\Subdivision;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForemanSubdivisionAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeForView($request);

        $subdivisions = Subdivision::query()
            ->with(['warehouses' => fn ($q) => $q->orderBy('name')])
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
        $subdivisionOptions = Subdivision::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $canManage = $this->canManageSubdivisionsAndWarehouses($request);

        return view('foreman-subdivisions.subdivisions-readonly', compact('subdivisions', 'subdivisionOptions', 'canManage'));
    }

    public function assignments(Request $request): View
    {
        $this->authorizeForManage($request);

        $foremen = User::query()
            ->where('role_id', 4)
            ->with(['assignedSubdivisions' => fn ($q) => $q->orderBy('name')])
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        return view('foreman-subdivisions.index', compact('foremen'));
    }

    public function storeSubdivision(Request $request): RedirectResponse
    {
        $this->authorizeForManage($request);

        $validated = $request->validate([
            'subdivision_name' => ['required', 'string', 'max:255', 'unique:subdivisions,name'],
        ], [
            'subdivision_name.required' => 'Укажите название подразделения.',
            'subdivision_name.string' => 'Название подразделения должно быть текстом.',
            'subdivision_name.max' => 'Название подразделения не может быть длиннее :max символов.',
            'subdivision_name.unique' => 'Подразделение с таким названием уже существует.',
        ]);

        Subdivision::query()->create([
            'name' => trim($validated['subdivision_name']),
        ]);

        return redirect()
            ->route('foreman-subdivisions.index')
            ->with('status', 'Подразделение добавлено.');
    }

    public function storeWarehouse(Request $request): RedirectResponse
    {
        $this->authorizeForManage($request);

        $validated = $request->validate([
            'subdivision_id' => ['required', 'integer', 'exists:subdivisions,id'],
            'warehouse_name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:warehouses,code'],
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
        ]);

        Warehouse::query()->create([
            'subdivision_id' => (int) $validated['subdivision_id'],
            'name' => trim($validated['warehouse_name']),
            'code' => trim($validated['code']),
            'is_primary' => (bool) ($validated['is_primary'] ?? false),
            'comment' => isset($validated['comment']) ? trim((string) $validated['comment']) : null,
            'warehouse_type_id' => null,
        ]);

        return redirect()
            ->route('foreman-subdivisions.index')
            ->with('status', 'Склад добавлен.');
    }

    public function edit(Request $request, User $foreman): View
    {
        $this->authorizeForManage($request);

        if (! $foreman->hasRoleId(4)) {
            abort(404);
        }

        $foreman->load(['assignedSubdivisions' => fn ($q) => $q->orderBy('name')]);
        $subdivisions = Subdivision::query()->orderBy('name')->get();

        return view('foreman-subdivisions.edit', compact('foreman', 'subdivisions'));
    }

    public function update(Request $request, User $foreman): RedirectResponse
    {
        $this->authorizeForManage($request);

        if (! $foreman->hasRoleId(4)) {
            abort(404);
        }

        $validated = $request->validate([
            'subdivision_ids' => ['array'],
            'subdivision_ids.*' => ['integer', 'exists:subdivisions,id'],
        ]);

        $selectedIds = collect($validated['subdivision_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $syncData = $selectedIds
            ->mapWithKeys(fn (int $id): array => [
                $id => ['assigned_by_user_id' => $request->user()->id],
            ])
            ->all();

        $foreman->assignedSubdivisions()->sync($syncData);

        return redirect()
            ->route('foreman-subdivisions.assignments')
            ->with('status', 'Назначения для мастера участка обновлены.');
    }

    private function authorizeForView(Request $request): void
    {
        $allowed = $request->user()?->hasAnyRoleId([1, 6, 2, 3]) ?? false;

        if (! $allowed) {
            abort(403, 'Доступ разрешён только директору, техническому директору, начальнику отдела снабжения и бухгалтеру.');
        }
    }

    private function authorizeForManage(Request $request): void
    {
        if (! $this->canManageSubdivisionsAndWarehouses($request)) {
            abort(403, 'Изменение подразделений и складов разрешено только директору, техническому директору и начальнику отдела снабжения.');
        }
    }

    private function canManageSubdivisionsAndWarehouses(Request $request): bool
    {
        return $request->user()?->hasAnyRoleId([1, 6, 2]) ?? false;
    }
}
