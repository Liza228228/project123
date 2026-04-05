<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\EquipmentType;
use App\Models\Role;
use App\Models\Subdivision;
use App\Models\TransportOption;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function index(): View
    {
        $applications = Application::with(['subdivision', 'responsibleUser', 'items.equipmentType', 'user', 'sourceApplication', 'transportOption'])
            ->orderByDesc('created_at')
            ->get();

        return view('applications.index', compact('applications'));
    }

    public function create(Request $request): View
    {
        $this->authorizeCanCreateOrEditApplications($request);

        $subdivisions = Subdivision::orderBy('name')->get();
        $equipmentTypes = EquipmentType::orderBy('name')->get();
        $users = User::where('role_id', Role::ID_SITE_FOREMAN)
            ->orderBy('surname')
            ->orderBy('name')
            ->get();
        $prefill = null;
        $transportOptions = TransportOption::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('applications.create', compact('subdivisions', 'equipmentTypes', 'users', 'prefill', 'transportOptions'));
    }

    public function repeat(Request $request, Application $application): View
    {
        $this->authorizeCanRepeatApplications($request);

        $application->load(['items']);
        $subdivisions = Subdivision::orderBy('name')->get();
        $equipmentTypes = EquipmentType::orderBy('name')->get();
        $users = User::where('role_id', Role::ID_SITE_FOREMAN)
            ->orderBy('surname')
            ->orderBy('name')
            ->get();
        $prefill = [
            'source_application_id' => $application->id,
            'subdivision_id' => $application->subdivision_id,
            'responsible_user_id' => $application->responsible_user_id,
            'transport_option_id' => $application->transport_option_id,
            'desired_delivery_date' => now()->toDateString(),
            'items' => $application->items->map(fn (ApplicationItem $item): array => [
                'equipment_type_id' => $item->equipment_type_id ?? '',
                'equipment_name' => $item->equipment_name ?? '',
                'quantity' => $item->quantity,
            ])->all(),
        ];
        $transportOptions = TransportOption::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('applications.create', compact('subdivisions', 'equipmentTypes', 'users', 'prefill', 'transportOptions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeCanCreateOrEditApplications($request);

        $validated = $request->validate([
            'subdivision_id' => ['required', 'exists:subdivisions,id'],
            'source_application_id' => ['nullable', 'exists:applications,id'],
            'responsible_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role_id', Role::ID_SITE_FOREMAN),
            ],
            'desired_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.equipment_type_id' => ['nullable', 'exists:equipment_types,id'],
            'items.*.equipment_name' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'transport_option_id' => ['nullable', 'exists:transport_options,id'],
        ], [
            'desired_delivery_date.after_or_equal' => 'Желаемая дата поставки не может быть в прошлом.',
            'items.min' => 'Добавьте хотя бы одну позицию оборудования.',
        ]);

        $hasValidItem = collect($validated['items'])->contains(fn (array $item) => ! empty($item['equipment_type_id'] ?? null) || ! empty(trim($item['equipment_name'] ?? ''))
        );
        if (! $hasValidItem) {
            return back()->withErrors(['equipment' => 'Укажите оборудование: выберите из списка или введите вручную.'])->withInput();
        }

        $validated['user_id'] = $request->user()->id;
        if (empty($validated['responsible_user_id'])) {
            $validated['responsible_user_id'] = $request->user()->id;
        }
        $validated['equipment_in_warehouse'] = null;

        $application = Application::create([
            'subdivision_id' => $validated['subdivision_id'],
            'source_application_id' => $validated['source_application_id'] ?? null,
            'responsible_user_id' => $validated['responsible_user_id'],
            'transport_option_id' => $validated['transport_option_id'] ?? null,
            'desired_delivery_date' => $validated['desired_delivery_date'],
            'user_id' => $validated['user_id'],
            'equipment_in_warehouse' => $validated['equipment_in_warehouse'],
        ]);

        foreach ($validated['items'] as $item) {
            $typeId = $item['equipment_type_id'] ?? null;
            $name = trim($item['equipment_name'] ?? '');
            if (empty($typeId) && $name === '') {
                continue;
            }
            $application->items()->create([
                'equipment_type_id' => $typeId ?: null,
                'equipment_name' => $typeId ? null : $name,
                'quantity' => (int) ($item['quantity'] ?? 1),
                'is_checked' => false,
                'reason_not_selected' => null,
            ]);
        }

        return redirect()->route('applications.index')
            ->with('status', 'Заявка успешно создана.');
    }

    public function show(Application $application): View
    {
        $application->load(['subdivision', 'responsibleUser', 'user', 'items.equipmentType', 'sourceApplication', 'transportOption']);

        return view('applications.show', compact('application'));
    }

    public function edit(Request $request, Application $application): View
    {
        $this->authorizeCanCreateOrEditApplications($request);

        $subdivisions = Subdivision::orderBy('name')->get();
        $equipmentTypes = EquipmentType::orderBy('name')->get();
        $users = User::where('role_id', Role::ID_SITE_FOREMAN)
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        $transportOptions = TransportOption::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $application->load(['items.equipmentType']);

        return view('applications.edit', compact('application', 'subdivisions', 'equipmentTypes', 'users', 'transportOptions'));
    }

    public function update(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeCanCreateOrEditApplications($request);

        $application->load(['items.equipmentType']);

        $validated = $request->validate([
            'subdivision_id' => ['required', 'exists:subdivisions,id'],
            'responsible_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role_id', Role::ID_SITE_FOREMAN),
            ],
            'desired_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => [
                'nullable',
                'integer',
                Rule::exists('application_items', 'id')->where('application_id', $application->id),
            ],
            'items.*.equipment_type_id' => ['nullable', 'exists:equipment_types,id'],
            'items.*.equipment_name' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'transport_option_id' => ['nullable', 'exists:transport_options,id'],
        ], [
            'desired_delivery_date.after_or_equal' => 'Желаемая дата поставки не может быть в прошлом.',
            'items.min' => 'Добавьте хотя бы одну позицию оборудования.',
        ]);

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
            $typeId = $row['equipment_type_id'] ?? null;
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
                    $existingTypeId = $existing->equipment_type_id !== null ? (int) $existing->equipment_type_id : null;
                    if (
                        $typeId !== $existingTypeId
                        || $name !== trim((string) ($existing->equipment_name ?? ''))
                        || $qty !== (int) $existing->quantity
                    ) {
                        throw ValidationException::withMessages([
                            'equipment' => 'Одобренное оборудование нельзя изменять.',
                        ]);
                    }

                    continue;
                }

                if ($typeId === null && $name === '') {
                    throw ValidationException::withMessages([
                        "items.{$index}.equipment_type_id" => 'Укажите оборудование или удалите строку.',
                    ]);
                }

                $seenUnapprovedIds[] = $itemId;

                continue;
            }

            if ($typeId === null && $name === '') {
                continue;
            }

            $toCreate[] = [
                'equipment_type_id' => $typeId,
                'equipment_name' => $typeId ? null : $name,
                'quantity' => $qty,
            ];
        }

        $approvedCount = $application->items->where('is_checked', true)->count();
        $linesWithEquipment = $approvedCount + count($seenUnapprovedIds) + count($toCreate);
        if ($linesWithEquipment < 1) {
            return back()->withErrors(['equipment' => 'Укажите оборудование: выберите из списка или введите вручную.'])->withInput();
        }

        DB::transaction(function () use ($application, $validated, $seenUnapprovedIds, $toCreate) {
            $application->update([
                'subdivision_id' => $validated['subdivision_id'],
                'responsible_user_id' => $validated['responsible_user_id'],
                'transport_option_id' => $validated['transport_option_id'] ?? null,
                'desired_delivery_date' => $validated['desired_delivery_date'],
                'approved_at' => null,
            ]);

            $application->items()
                ->where('is_checked', false)
                ->whereNotIn('id', $seenUnapprovedIds)
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

                $typeId = $row['equipment_type_id'] ?? null;
                $typeId = $typeId !== null && $typeId !== '' ? (int) $typeId : null;
                $name = trim((string) ($row['equipment_name'] ?? ''));

                $existing->update([
                    'equipment_type_id' => $typeId ?: null,
                    'equipment_name' => $typeId ? null : $name,
                    'quantity' => (int) ($row['quantity'] ?? 1),
                ]);
            }

            foreach ($toCreate as $payload) {
                $application->items()->create([
                    'equipment_type_id' => $payload['equipment_type_id'] ?: null,
                    'equipment_name' => $payload['equipment_name'],
                    'quantity' => $payload['quantity'],
                    'is_checked' => false,
                    'reason_not_selected' => null,
                ]);
            }
        });

        return redirect()->route('applications.index')
            ->with('status', 'Заявка успешно обновлена.');
    }

    public function saveApproval(Request $request, Application $application): RedirectResponse
    {
        if (! in_array((int) $request->user()?->role_id, [Role::ID_DIRECTOR, Role::ID_SUPPLY_DEPARTMENT_HEAD], true)) {
            abort(403, 'Согласование доступно только директору и начальнику отдела снабжения.');
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
                    $errors["items.{$item->id}.reason_not_selected"] = 'Укажите причину, почему позиция не одобрена.';
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

        DB::transaction(function () use ($application, $itemsInput) {
            foreach ($application->items as $item) {
                $row = $itemsInput[(string) $item->id] ?? $itemsInput[$item->id];
                $checkedRaw = $row['is_checked'] ?? '0';
                $isChecked = $checkedRaw === '1' || $checkedRaw === 1 || $checkedRaw === true;
                $item->update([
                    'is_checked' => $isChecked,
                    'reason_not_selected' => $isChecked ? null : trim((string) ($row['reason_not_selected'] ?? '')),
                ]);
            }
        });

        $application->refresh();
        $application->update([
            'approved_at' => now(),
        ]);

        return redirect()->route('applications.show', $application)
            ->with('status', 'Согласование сохранено.');
    }

    public function toggleCheck(Request $request, ApplicationItem $item): RedirectResponse
    {
        $newChecked = ! $item->is_checked;

        // При снятии отметки заявка не обновляется, пока не указана причина
        if (! $newChecked) {
            // Пользователь передумал: галочка уже снята на экране, нажал снова — вернуть отметку без причины
            if ($item->is_checked && $request->boolean('restore')) {
                return redirect()->route('applications.show', $item->application_id)
                    ->with('status', 'Отметка сохранена.');
            }

            return redirect()->route('applications.show', $item->application_id)
                ->with('require_reason_item_id', $item->id);
        }

        $item->update([
            'is_checked' => $newChecked,
            'reason_not_selected' => null,
        ]);

        return redirect()->route('applications.show', $item->application_id)
            ->with('status', 'Отметка обновлена.');
    }

    public function updateReason(Request $request, ApplicationItem $item): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'reason_not_selected' => ['required', 'string', 'min:1', 'max:500'],
        ], [
            'reason_not_selected.required' => 'Обязательно укажите причину, почему оборудование не было выбрано.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('applications.show', $item->application_id)
                ->withErrors($validator)
                ->with('reason_error_item_id', $item->id)
                ->with('require_reason_item_id', $item->is_checked ? $item->id : null);
        }

        $reason = trim($request->input('reason_not_selected'));
        $item->update([
            'reason_not_selected' => $reason,
            'is_checked' => false,
        ]);

        return redirect()->route('applications.show', $item->application_id)
            ->with('status', 'Сохранено');
    }

    private function authorizeCanCreateOrEditApplications(Request $request): void
    {
        $allowed = in_array((int) $request->user()?->role_id, [
            Role::ID_DIRECTOR,
            Role::ID_SITE_FOREMAN,
            Role::ID_SUPPLY_DEPARTMENT_HEAD,
        ], true);

        if (! $request->user() || ! $allowed) {
            abort(403, 'Создание и редактирование заявок разрешено только директору, начальнику отдела снабжения и мастеру участка.');
        }
    }

    private function authorizeCanRepeatApplications(Request $request): void
    {
        if ((int) $request->user()?->role_id !== Role::ID_SITE_FOREMAN) {
            abort(403, 'Создание повторной заявки разрешено только мастеру участка.');
        }
    }
}
