<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\EquipmentType;
use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApplicationController extends Controller
{
    public function index(): View
    {
        $applications = Application::with(['subdivision', 'responsibleUser', 'equipmentType', 'user'])
            ->orderByDesc('created_at')
            ->get();

        return view('applications.index', compact('applications'));
    }

    public function create(): View
    {
        $subdivisions = Subdivision::orderBy('name')->get();
        $equipmentTypes = EquipmentType::orderBy('name')->get();
        $users = User::orderBy('surname')->orderBy('name')->get();

        return view('applications.create', compact('subdivisions', 'equipmentTypes', 'users'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subdivision_id' => ['required', 'exists:subdivisions,id'],
            'responsible_user_id' => ['nullable', 'exists:users,id'],
            'equipment_type_id' => ['nullable', 'exists:equipment_types,id'],
            'equipment_name' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'desired_delivery_date' => ['required', 'date'],
        ]);

        if (empty($validated['equipment_type_id']) && empty(trim($validated['equipment_name'] ?? ''))) {
            return back()->withErrors(['equipment' => 'Укажите оборудование: выберите из списка или введите вручную.'])->withInput();
        }
        if (!empty($validated['equipment_type_id'])) {
            $validated['equipment_name'] = null;
        } else {
            $validated['equipment_type_id'] = null;
        }

        $validated['user_id'] = $request->user()->id;
        $validated['equipment_in_warehouse'] = null;

        Application::create($validated);

        return redirect()->route('applications.index')
            ->with('status', 'Заявка успешно создана.');
    }

    public function edit(Application $application): View
    {
        $subdivisions = Subdivision::orderBy('name')->get();
        $equipmentTypes = EquipmentType::orderBy('name')->get();
        $users = User::orderBy('surname')->orderBy('name')->get();

        return view('applications.edit', compact('application', 'subdivisions', 'equipmentTypes', 'users'));
    }

    public function update(Request $request, Application $application): RedirectResponse
    {
        $validated = $request->validate([
            'subdivision_id' => ['required', 'exists:subdivisions,id'],
            'responsible_user_id' => ['nullable', 'exists:users,id'],
            'equipment_type_id' => ['nullable', 'exists:equipment_types,id'],
            'equipment_name' => ['nullable', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'desired_delivery_date' => ['required', 'date'],
        ]);

        if (empty($validated['equipment_type_id']) && empty(trim($validated['equipment_name'] ?? ''))) {
            return back()->withErrors(['equipment' => 'Укажите оборудование: выберите из списка или введите вручную.'])->withInput();
        }
        if (!empty($validated['equipment_type_id'])) {
            $validated['equipment_name'] = null;
        } else {
            $validated['equipment_type_id'] = null;
        }

        $application->update($validated);

        return redirect()->route('applications.index')
            ->with('status', 'Заявка успешно обновлена.');
    }
}
