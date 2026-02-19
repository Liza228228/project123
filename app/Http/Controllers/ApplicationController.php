<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\EquipmentType;
use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\Validator;

class ApplicationController extends Controller
{
    public function index(): View
    {
        $applications = Application::with(['subdivision', 'responsibleUser', 'items.equipmentType', 'user'])
            ->orderByDesc('created_at')
            ->get();

        return view('applications.index', compact('applications'));
    }

    public function create(Request $request): View
    {
        $this->authorizeSiteForeman($request);

        $subdivisions = Subdivision::orderBy('name')->get();
        $equipmentTypes = EquipmentType::orderBy('name')->get();
        $users = User::where('role', User::ROLE_SITE_FOREMAN)->orderBy('surname')->orderBy('name')->get();

        return view('applications.create', compact('subdivisions', 'equipmentTypes', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeSiteForeman($request);

        $validated = $request->validate([
            'subdivision_id' => ['required', 'exists:subdivisions,id'],
            'responsible_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', User::ROLE_SITE_FOREMAN),
            ],
            'desired_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.equipment_type_id' => ['nullable', 'exists:equipment_types,id'],
            'items.*.equipment_name' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ], [
            'desired_delivery_date.after_or_equal' => 'Желаемая дата поставки не может быть в прошлом.',
            'items.min' => 'Добавьте хотя бы одну позицию оборудования.',
        ]);

        $hasValidItem = collect($validated['items'])->contains(fn (array $item) =>
            !empty($item['equipment_type_id'] ?? null) || !empty(trim($item['equipment_name'] ?? ''))
        );
        if (!$hasValidItem) {
            return back()->withErrors(['equipment' => 'Укажите оборудование: выберите из списка или введите вручную.'])->withInput();
        }

        $validated['user_id'] = $request->user()->id;
        if (empty($validated['responsible_user_id'])) {
            $validated['responsible_user_id'] = $request->user()->id;
        }
        $validated['equipment_in_warehouse'] = null;

        $application = Application::create([
            'subdivision_id' => $validated['subdivision_id'],
            'responsible_user_id' => $validated['responsible_user_id'],
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
            ]);
        }

        return redirect()->route('applications.index')
            ->with('status', 'Заявка успешно создана.');
    }

    public function show(Application $application): View
    {
        $application->load(['subdivision', 'responsibleUser', 'user', 'items.equipmentType']);

        return view('applications.show', compact('application'));
    }

    public function edit(Request $request, Application $application): View
    {
        $this->authorizeSiteForeman($request);

        $subdivisions = Subdivision::orderBy('name')->get();
        $equipmentTypes = EquipmentType::orderBy('name')->get();
        $users = User::where('role', User::ROLE_SITE_FOREMAN)->orderBy('surname')->orderBy('name')->get();

        return view('applications.edit', compact('application', 'subdivisions', 'equipmentTypes', 'users'));
    }

    public function update(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeSiteForeman($request);

        $validated = $request->validate([
            'subdivision_id' => ['required', 'exists:subdivisions,id'],
            'responsible_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', User::ROLE_SITE_FOREMAN),
            ],
            'desired_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.equipment_type_id' => ['nullable', 'exists:equipment_types,id'],
            'items.*.equipment_name' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ], [
            'desired_delivery_date.after_or_equal' => 'Желаемая дата поставки не может быть в прошлом.',
            'items.min' => 'Добавьте хотя бы одну позицию оборудования.',
        ]);

        $hasValidItem = collect($validated['items'])->contains(fn (array $item) =>
            !empty($item['equipment_type_id'] ?? null) || !empty(trim($item['equipment_name'] ?? ''))
        );
        if (!$hasValidItem) {
            return back()->withErrors(['equipment' => 'Укажите оборудование: выберите из списка или введите вручную.'])->withInput();
        }

        $application->update([
            'subdivision_id' => $validated['subdivision_id'],
            'responsible_user_id' => $validated['responsible_user_id'],
            'desired_delivery_date' => $validated['desired_delivery_date'],
        ]);

        $application->items()->delete();
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
            ]);
        }

        return redirect()->route('applications.index')
            ->with('status', 'Заявка успешно обновлена.');
    }

    public function toggleCheck(Request $request, ApplicationItem $item): RedirectResponse
    {
        $newChecked = ! $item->is_checked;
        $item->update([
            'is_checked' => $newChecked,
            'reason_not_selected' => $newChecked ? null : $item->reason_not_selected,
        ]);

        return redirect()->route('applications.show', $item->application_id)
            ->with('status', 'Отметка обновлена.');
    }

    public function updateReason(Request $request, ApplicationItem $item): RedirectResponse
    {
        if ($item->is_checked) {
            return redirect()->route('applications.show', $item->application_id);
        }

        $validator = Validator::make($request->all(), [
            'reason_not_selected' => ['required', 'string', 'min:1', 'max:500'],
        ], [
            'reason_not_selected.required' => 'Обязательно укажите причину, почему оборудование не было выбрано.',
        ]);

        if ($validator->fails()) {
            return redirect()->route('applications.show', $item->application_id)
                ->withErrors($validator)
                ->with('reason_error_item_id', $item->id);
        }

        $item->update(['reason_not_selected' => trim($request->input('reason_not_selected'))]);

        return redirect()->route('applications.show', $item->application_id)
            ->with('status', 'Комментарий сохранён.');
    }

    private function authorizeSiteForeman(Request $request): void
    {
        if (! $request->user() || $request->user()->role !== User::ROLE_SITE_FOREMAN) {
            abort(403, 'Создание и редактирование заявок разрешено только мастеру участка.');
        }
    }
}
