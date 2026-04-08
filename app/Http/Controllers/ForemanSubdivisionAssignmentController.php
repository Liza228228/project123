<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForemanSubdivisionAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeForSupplyHead($request);

        $foremen = User::query()
            ->where('role_id', Role::ID_SITE_FOREMAN)
            ->with(['assignedSubdivisions' => fn ($q) => $q->orderBy('name')])
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        return view('foreman-subdivisions.index', compact('foremen'));
    }

    public function edit(Request $request, User $foreman): View
    {
        $this->authorizeForSupplyHead($request);

        if ((int) $foreman->role_id !== Role::ID_SITE_FOREMAN) {
            abort(404);
        }

        $foreman->load(['assignedSubdivisions' => fn ($q) => $q->orderBy('name')]);
        $subdivisions = Subdivision::query()->orderBy('name')->get();

        return view('foreman-subdivisions.edit', compact('foreman', 'subdivisions'));
    }

    public function update(Request $request, User $foreman): RedirectResponse
    {
        $this->authorizeForSupplyHead($request);

        if ((int) $foreman->role_id !== Role::ID_SITE_FOREMAN) {
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
            ->route('foreman-subdivisions.index')
            ->with('status', 'Назначения для мастера участка обновлены.');
    }

    private function authorizeForSupplyHead(Request $request): void
    {
        if ((int) $request->user()?->role_id !== Role::ID_SUPPLY_DEPARTMENT_HEAD) {
            abort(403, 'Доступ разрешён только начальнику отдела снабжения.');
        }
    }
}
