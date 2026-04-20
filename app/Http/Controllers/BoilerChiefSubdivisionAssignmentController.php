<?php

namespace App\Http\Controllers;

use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BoilerChiefSubdivisionAssignmentController extends Controller
{
    public function assignments(Request $request): View
    {
        $this->authorizeForManage($request);

        $search = trim((string) $request->input('q', ''));

        $chiefsQuery = User::query()
            ->where('role_id', 7)
            ->with(['boilerChiefSubdivisions' => fn ($q) => $q->orderBy('name')]);

        if ($search !== '') {
            $chiefsQuery->where(function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query
                    ->where('surname', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('patronymic', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $chiefs = $chiefsQuery
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        return view('boiler-chief-subdivisions.assignments', compact('chiefs', 'search'));
    }

    public function edit(Request $request, User $chief): View
    {
        $this->authorizeForManage($request);

        if (! $chief->hasRoleId(7)) {
            abort(404);
        }

        $chief->load(['boilerChiefSubdivisions' => fn ($q) => $q->orderBy('name')]);
        $subdivisions = Subdivision::query()->orderBy('name')->get();

        return view('boiler-chief-subdivisions.edit', compact('chief', 'subdivisions'));
    }

    public function update(Request $request, User $chief): RedirectResponse
    {
        $this->authorizeForManage($request);

        if (! $chief->hasRoleId(7)) {
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

        $chief->boilerChiefSubdivisions()->sync($syncData);

        return redirect()
            ->route('boiler-chief-subdivisions.assignments')
            ->with('status', 'Назначения для начальника котельной обновлены.');
    }

    private function authorizeForManage(Request $request): void
    {
        if (! $request->user()?->hasAnyRoleId([1, 6, 2])) {
            abort(403, 'Назначение подразделений начальникам котельных разрешено только директору, техническому директору и начальнику отдела снабжения.');
        }
    }
}
