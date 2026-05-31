<?php

// управление пользователями
namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Support\ForemanApplicationReassignment;
use App\Support\ListingPerPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $roles = Role::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedRoleId = null;
        $candidateRoleId = (int) $request->integer('role_id');
        if ($candidateRoleId > 0 && $roles->contains('id', $candidateRoleId)) {
            $selectedRoleId = $candidateRoleId;
        }

        $statusFilter = (string) $request->input('status', 'all');
        $allowedStatusFilters = ['all', 'active', 'blocked'];
        if (! in_array($statusFilter, $allowedStatusFilters, true)) {
            $statusFilter = 'all';
        }

        $pagination = ListingPerPage::fromRequest($request);
        $perPage = $pagination['perPage'];
        $allowedPerPage = $pagination['allowedPerPage'];

        $usersQuery = User::query()->with('role');

        if ($search !== '') {
            $usersQuery->where(function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query
                    ->where('surname', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('patronymic', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        if ($selectedRoleId !== null) {
            $usersQuery->where('role_id', $selectedRoleId);
        }

        if ($statusFilter === 'active') {
            $usersQuery->where('is_blocked', false);
        } elseif ($statusFilter === 'blocked') {
            $usersQuery->where('is_blocked', true);
        }

        $sortState = $this->resolveSortState($request);
        $this->applyUserSorting($usersQuery, $sortState);

        $users = $usersQuery
            ->paginate($perPage)
            ->withQueryString();

        return view('users.index', compact('users', 'roles', 'search', 'selectedRoleId', 'statusFilter', 'perPage', 'allowedPerPage', 'sortState'));
    }

    public function create(): View
    {
        $roles = Role::orderBy('name')->get();

        return view('users.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'surname' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'patronymic' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'role_id' => ['required', 'exists:roles,id'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        User::create([
            'surname' => $request->surname,
            'name' => $request->name,
            'patronymic' => $request->patronymic,
            'email' => $request->email,
            'role_id' => $request->role_id,
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('users.index')
            ->with('status', 'Пользователь успешно добавлен.');
    }

    public function edit(User $user): View
    {
        $roles = Role::orderBy('name')->get();
        $foremanReassignment = app(ForemanApplicationReassignment::class);
        $foremanApplicationsCount = $user->hasRoleId(ForemanApplicationReassignment::FOREMAN_ROLE_ID)
            ? $foremanReassignment->applicationsRequiringReassignment($user)->count()
            : 0;

        return view('users.edit', compact('user', 'roles', 'foremanApplicationsCount'));
    }

    public function reassignApplications(User $user): View
    {
        $this->assertForemanReassignmentTarget($user);

        $reassignment = app(ForemanApplicationReassignment::class);
        $applications = $reassignment->applicationsRequiringReassignment($user);
        $blockedId = (int) $user->id;

        $applicationRows = $applications->map(function ($application) use ($reassignment, $blockedId) {
            $subdivisionId = (int) $application->subdivision_id;

            return [
                'application' => $application,
                'foremen' => $reassignment->replacementForemenOptionsForSubdivision($subdivisionId, $blockedId),
            ];
        });

        return view('users.reassign-applications', [
            'user' => $user,
            'applicationRows' => $applicationRows,
        ]);
    }

    public function storeReassignApplications(Request $request, User $user): RedirectResponse
    {
        $this->assertForemanReassignmentTarget($user);

        $reassignment = app(ForemanApplicationReassignment::class);
        $applications = $reassignment->applicationsRequiringReassignment($user);

        if ($applications->isEmpty()) {
            return redirect()
                ->route('users.edit', $user)
                ->with('status', 'У мастера нет активных заявок для переназначения.');
        }

        $validated = $request->validate([
            'reassignments' => ['required', 'array'],
            'reassignments.*' => ['required', 'integer'],
        ], [
            'reassignments.required' => 'Укажите новых мастеров для всех заявок.',
        ]);

        try {
            DB::transaction(function () use ($reassignment, $user, $validated): void {
                $reassignment->applyBlockReassignments($user, $validated['reassignments']);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('users.reassign-applications', $user)
                ->withErrors($e->errors())
                ->withInput();
        }

        return redirect()
            ->route('users.edit', $user)
            ->with('status', 'Заявки мастера переназначены.');
    }

    public function blockPreview(User $user): JsonResponse
    {
        $this->assertNotSelf($user);

        $reassignment = app(ForemanApplicationReassignment::class);
        $requires = $reassignment->requiresReassignmentBeforeBlock($user);

        return response()->json([
            'requires_reassignment' => $requires,
            'user' => [
                'id' => (int) $user->id,
                'name' => trim($user->surname.' '.$user->name.' '.$user->patronymic),
                'role_id' => (int) $user->role_id,
            ],
            'applications' => $requires ? $reassignment->blockPreviewPayload($user) : [],
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $rules = [
            'surname' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'patronymic' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.',email,'.$user->id],
            'role_id' => ['required', 'exists:roles,id'],
        ];

        if ($request->filled('password')) {
            $rules['password'] = ['required', 'confirmed', Rules\Password::defaults()];
        }

        $request->validate($rules);

        if ($user->is(Auth::user()) && (int) $request->role_id !== (int) $user->role_id) {
            abort(403, 'Нельзя изменить свою роль.');
        }

        $data = [
            'surname' => $request->surname,
            'name' => $request->name,
            'patronymic' => $request->patronymic,
            'email' => $request->email,
            'role_id' => $user->is(Auth::user()) ? $user->role_id : (int) $request->role_id,
        ];

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        return redirect()->route('users.index')
            ->with('status', 'Данные пользователя успешно обновлены.');
    }

    public function block(Request $request, User $user): RedirectResponse
    {
        $this->assertNotSelf($user);

        $reassignment = app(ForemanApplicationReassignment::class);

        try {
            DB::transaction(function () use ($request, $user, $reassignment): void {
                if ($reassignment->requiresReassignmentBeforeBlock($user)) {
                    $reassignment->applyBlockReassignments(
                        $user,
                        is_array($request->input('reassignments')) ? $request->input('reassignments') : []
                    );
                }

                $user->update(['is_blocked' => true]);
            });
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->route('users.index')
                ->withErrors($e->errors())
                ->withInput();
        }

        $reassigned = $user->hasRoleId(ForemanApplicationReassignment::FOREMAN_ROLE_ID)
            && is_array($request->input('reassignments'))
            && $request->input('reassignments') !== [];

        return redirect()->route('users.index')
            ->with('status', $reassigned
                ? 'Заявки переназначены, пользователь заблокирован.'
                : 'Пользователь заблокирован.');
    }

    public function unblock(User $user): RedirectResponse
    {
        $this->assertNotSelf($user);

        $user->update(['is_blocked' => false]);

        return redirect()->route('users.index')
            ->with('status', 'Пользователь разблокирован.');
    }

    private function assertNotSelf(User $target): void
    {
        if ($target->is(Auth::user())) {
            abort(403, 'Это действие недоступно для собственной учётной записи.');
        }
    }

    private function assertForemanReassignmentTarget(User $user): void
    {
        if (! $user->hasRoleId(ForemanApplicationReassignment::FOREMAN_ROLE_ID)) {
            abort(404);
        }
    }
    private function resolveSortState(Request $request): array
    {
        $allowedFields = $this->allowedSortFields();
        $allowedDirections = ['asc', 'desc'];

        $primaryField = (string) $request->input('sort_primary_field', 'surname');
        if (! array_key_exists($primaryField, $allowedFields)) {
            $primaryField = 'surname';
        }

        $primaryDirection = strtolower((string) $request->input('sort_primary_direction', 'asc'));
        if (! in_array($primaryDirection, $allowedDirections, true)) {
            $primaryDirection = 'asc';
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
    private function applyUserSorting($usersQuery, array $sortState): void
    {
        $allowedFields = $this->allowedSortFields();
        $applied = [];

        $primaryField = $sortState['primary_field'];
        $primaryDirection = $sortState['primary_direction'];
        $usersQuery->orderBy($allowedFields[$primaryField], $primaryDirection);
        $applied[] = $primaryField;

        if ($sortState['secondary_field'] !== null) {
            $secondaryField = $sortState['secondary_field'];
            if (! in_array($secondaryField, $applied, true)) {
                $usersQuery->orderBy($allowedFields[$secondaryField], $sortState['secondary_direction']);
                $applied[] = $secondaryField;
            }
        }

        foreach (['surname', 'name'] as $fallbackField) {
            if (! in_array($fallbackField, $applied, true)) {
                $usersQuery->orderBy($allowedFields[$fallbackField], 'asc');
            }
        }

        $usersQuery->orderBy('id', 'asc');
    }
    private function allowedSortFields(): array
    {
        return [
            'surname' => 'surname',
            'name' => 'name',
            'patronymic' => 'patronymic',
            'email' => 'email',
            'role' => 'role_id',
            'created_at' => 'created_at',
        ];
    }
}
