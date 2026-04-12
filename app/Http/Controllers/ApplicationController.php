<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\EquipmentType;
use App\Models\Subdivision;
use App\Models\TransportOption;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ApplicationChangeRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->input('q', ''));
        $equipmentFilter = (string) $request->input('equipment_filter', 'all');
        $allowedFilters = ['all', 'has_approved', 'has_not_approved', 'fully_approved', 'on_approval'];
        if (! in_array($equipmentFilter, $allowedFilters, true)) {
            $equipmentFilter = 'all';
        }

        $applications = Application::query()
            ->with(['subdivision', 'responsibleUser', 'items.equipmentType', 'user', 'approvedBy', 'sourceApplication', 'transportOption']);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $applications->where(function ($query) use ($search, $like) {
                $query->whereRaw('0 = 1');
                if (ctype_digit($search)) {
                    $id = (int) $search;
                    $query->orWhere('id', $id)->orWhere('source_application_id', $id);
                }
                $query->orWhereHas('subdivision', fn ($q) => $q->where('name', 'like', $like))
                    ->orWhereHas('responsibleUser', function ($q) use ($like) {
                        $q->where('surname', 'like', $like)
                            ->orWhere('name', 'like', $like)
                            ->orWhere('patronymic', 'like', $like);
                    })
                    ->orWhereHas('user', function ($q) use ($like) {
                        $q->where('surname', 'like', $like)
                            ->orWhere('name', 'like', $like)
                            ->orWhere('patronymic', 'like', $like);
                    })
                    ->orWhereHas('approvedBy', function ($q) use ($like) {
                        $q->where('surname', 'like', $like)
                            ->orWhere('name', 'like', $like)
                            ->orWhere('patronymic', 'like', $like);
                    })
                    ->orWhereHas('transportOption', fn ($q) => $q->where('name', 'like', $like))
                    ->orWhereHas('items', function ($q) use ($like) {
                        $q->where('equipment_name', 'like', $like)
                            ->orWhereHas('equipmentType', fn ($eq) => $eq->where('name', 'like', $like));
                    });
            });
        }

        match ($equipmentFilter) {
            'has_approved' => $applications->whereHas('items', fn ($q) => $q->where('is_checked', true)),
            'has_not_approved' => $applications->whereHas('items', fn ($q) => $q->where('is_checked', false)),
            'fully_approved' => $applications
                ->whereHas('items')
                ->whereDoesntHave('items', function ($q) {
                    $q->where('is_checked', false)
                        ->where(function ($q2) {
                            $q2->whereNull('reason_not_selected')
                                ->orWhereRaw("TRIM(COALESCE(reason_not_selected, '')) = ''");
                        });
                }),
            'on_approval' => $applications->where(function ($query) {
                $query->whereDoesntHave('items')
                    ->orWhereHas('items', function ($q) {
                        $q->where('is_checked', false)
                            ->where(function ($q2) {
                                $q2->whereNull('reason_not_selected')
                                    ->orWhereRaw("TRIM(COALESCE(reason_not_selected, '')) = ''");
                            });
                    });
            }),
            default => null,
        };

        $applications = $applications->orderByDesc('created_at')->get();

        return view('applications.index', compact('applications', 'search', 'equipmentFilter'));
    }

    public function create(Request $request): View
    {
        $this->authorizeCanCreateApplications($request);

        $subdivisions = $this->availableSubdivisionsForCreate($request);
        $equipmentTypes = EquipmentType::orderBy('name')->get();
        $users = User::query()
            ->where('role_id', 4)
            ->orderBy('surname')
            ->orderBy('name')
            ->get();
        $prefill = null;
        $transportOptions = TransportOption::query()
            ->orderBy('name')
            ->get();

        $warehousesBySubdivision = $this->warehousesBySubdivisionForUi();
        $subdivisionIdsByForeman = $this->subdivisionIdsByForemanForUi();

        return view('applications.create', compact('subdivisions', 'equipmentTypes', 'users', 'prefill', 'transportOptions', 'warehousesBySubdivision', 'subdivisionIdsByForeman'));
    }

    public function repeat(Request $request, Application $application): View
    {
        $this->authorizeCanRepeatApplications($request);

        $application->load(['items']);
        $subdivisions = $this->availableSubdivisionsForCreate($request);
        $equipmentTypes = EquipmentType::orderBy('name')->get();
        $users = User::query()
            ->where('role_id', 4)
            ->orderBy('surname')
            ->orderBy('name')
            ->get();
        $prefill = [
            'source_application_id' => $application->id,
            'subdivision_id' => $subdivisions->contains('id', $application->subdivision_id) ? $application->subdivision_id : null,
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
            ->orderBy('name')
            ->get();

        $warehousesBySubdivision = $this->warehousesBySubdivisionForUi();
        $subdivisionIdsByForeman = $this->subdivisionIdsByForemanForUi();

        return view('applications.create', compact('subdivisions', 'equipmentTypes', 'users', 'prefill', 'transportOptions', 'warehousesBySubdivision', 'subdivisionIdsByForeman'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeCanCreateApplications($request);

        $isSiteForeman = $request->user()->hasRoleId(4);
        $allowedSubdivisionIds = $this->availableSubdivisionsForCreate($request)->pluck('id')->map(fn ($id) => (int) $id);

        $validated = $request->validate([
            'subdivision_id' => ['required', 'exists:subdivisions,id'],
            'source_application_id' => ['nullable', 'exists:applications,id'],
            'responsible_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role_id', 4),
            ],
            'desired_delivery_date' => ['required', 'date', 'after_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.equipment_type_id' => ['nullable', 'exists:equipment_types,id'],
            'items.*.equipment_name' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'transport_option_id' => ['nullable', 'exists:transport_options,id'],
            'commercial_offer' => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:10240'],
        ], [
            'desired_delivery_date.after_or_equal' => 'Желаемая дата поставки не может быть в прошлом.',
            'items.min' => 'Добавьте хотя бы одну позицию оборудования.',
            'commercial_offer.mimes' => 'Поддерживаемые форматы: PDF, DOC, DOCX, XLS, XLSX, JPG, JPEG, PNG.',
            'commercial_offer.max' => 'Максимальный размер файла: 10 МБ.',
        ]);

        if (! $allowedSubdivisionIds->contains((int) $validated['subdivision_id'])) {
            throw ValidationException::withMessages([
                'subdivision_id' => 'Вы не можете создать заявку для этого подразделения.',
            ]);
        }
        $this->validateSubdivisionAllowedForResponsibleUser(
            (int) $validated['subdivision_id'],
            isset($validated['responsible_user_id']) ? (int) $validated['responsible_user_id'] : null
        );

        $equipmentTypeNames = EquipmentType::query()
            ->pluck('name')
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->filter()
            ->flip();
        foreach ($validated['items'] as $index => $item) {
            $typeId = $item['equipment_type_id'] ?? null;
            $name = trim((string) ($item['equipment_name'] ?? ''));
            if (! empty($typeId) || $name === '') {
                continue;
            }
            if ($equipmentTypeNames->has(mb_strtolower($name))) {
                throw ValidationException::withMessages([
                    "items.{$index}.equipment_name" => 'Такое оборудование уже есть в списке. ',
                ]);
            }
        }

        $hasValidItem = collect($validated['items'])->contains(fn (array $item) => ! empty($item['equipment_type_id'] ?? null) || ! empty(trim($item['equipment_name'] ?? ''))
        );
        $hasCommercialOffer = $request->hasFile('commercial_offer');
        if (! $hasValidItem && ! $hasCommercialOffer) {
            return back()->withErrors(['equipment' => 'Укажите оборудование: выберите из списка или введите вручную.'])->withInput();
        }

        $validated['user_id'] = $request->user()->id;
        if ($isSiteForeman) {
            $validated['responsible_user_id'] = $request->user()->id;
        } elseif (empty($validated['responsible_user_id'])) {
            $validated['responsible_user_id'] = $request->user()->id;
        }
        $validated['equipment_in_warehouse'] = null;
        $commercialOfferPath = null;
        if ($request->hasFile('commercial_offer')) {
            $file = $request->file('commercial_offer');
            $storageDisk = 'public';
            $storageDir = 'commercial-offers';

            // Явно создаем отдельную папку для коммерческих предложений.
            Storage::disk($storageDisk)->makeDirectory($storageDir);

            $commercialOfferPath = $file->store($storageDir, $storageDisk);
        }

        $application = Application::create([
            'subdivision_id' => $validated['subdivision_id'],
            'source_application_id' => $validated['source_application_id'] ?? null,
            'responsible_user_id' => $validated['responsible_user_id'],
            'transport_option_id' => $validated['transport_option_id'] ?? null,
            'desired_delivery_date' => $validated['desired_delivery_date'],
            'user_id' => $validated['user_id'],
            'equipment_in_warehouse' => $validated['equipment_in_warehouse'],
            'commercial_offer_path' => $commercialOfferPath,
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
        $application->load([
            'subdivision.warehouses',
            'responsibleUser',
            'user',
            'approvedBy',
            'items.equipmentType',
            'sourceApplication',
            'transportOption',
            'latestEditHistory.user.role',
            'latestEditHistory.lines',
        ]);

        return view('applications.show', compact('application'));
    }

    public function viewCommercialOffer(Application $application): BinaryFileResponse
    {
        $path = $this->resolveCommercialOfferPath($application);
        if (! $path) {
            abort(404, 'Файл коммерческого предложения не найден.');
        }

        return response()->file($path);
    }

    public function downloadCommercialOffer(Application $application): BinaryFileResponse
    {
        $path = $this->resolveCommercialOfferPath($application);
        if (! $path) {
            abort(404, 'Файл коммерческого предложения не найден.');
        }

        $name = basename($path);

        return response()->download($path, $name);
    }

    public function edit(Request $request, Application $application): View
    {
        $this->authorizeCanEditApplications($request);

        $subdivisions = Subdivision::orderBy('name')->get();
        $equipmentTypes = EquipmentType::orderBy('name')->get();
        $users = User::query()
            ->where('role_id', 4)
            ->orderBy('surname')
            ->orderBy('name')
            ->get();

        $transportOptions = TransportOption::query()
            ->orderBy('name')
            ->get();

        $application->load(['items.equipmentType']);

        $warehousesBySubdivision = $this->warehousesBySubdivisionForUi();
        $subdivisionIdsByForeman = $this->subdivisionIdsByForemanForUi();

        return view('applications.edit', compact('application', 'subdivisions', 'equipmentTypes', 'users', 'transportOptions', 'warehousesBySubdivision', 'subdivisionIdsByForeman'));
    }

    public function update(Request $request, Application $application): RedirectResponse
    {
        $this->authorizeCanEditApplications($request);

        $isSiteForeman = $request->user()->hasRoleId(4);
        $application->load(['items.equipmentType']);

        $shouldRecordManagementEdit = $request->user()->hasAnyRoleId($this->managementEditorRoleIds());
        $snapshotBefore = $shouldRecordManagementEdit ? ApplicationChangeRecorder::snapshot($application) : null;

        $validated = $request->validate([
            'subdivision_id' => ['required', 'exists:subdivisions,id'],
            'responsible_user_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role_id', 4),
            ],
            'management_change_reason' => ['nullable', 'string', 'max:500'],
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
        $this->validateSubdivisionAllowedForResponsibleUser(
            (int) $validated['subdivision_id'],
            isset($validated['responsible_user_id']) ? (int) $validated['responsible_user_id'] : null
        );

        $equipmentTypeNames = EquipmentType::query()
            ->pluck('name')
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->filter()
            ->flip();
        foreach ($validated['items'] as $index => $row) {
            $typeId = $row['equipment_type_id'] ?? null;
            $name = trim((string) ($row['equipment_name'] ?? ''));
            if (! empty($typeId) || $name === '') {
                continue;
            }
            if ($equipmentTypeNames->has(mb_strtolower($name))) {
                throw ValidationException::withMessages([
                    "items.{$index}.equipment_name" => 'Такое оборудование уже есть в списке.',
                ]);
            }
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

        DB::transaction(function () use ($application, $validated, $seenUnapprovedIds, $toCreate, $request, $isSiteForeman) {
            $responsibleUserId = $validated['responsible_user_id'] ?? null;
            if ($isSiteForeman) {
                $responsibleUserId = $request->user()->id;
            }

            $application->update([
                'subdivision_id' => $validated['subdivision_id'],
                'responsible_user_id' => $responsibleUserId,
                'transport_option_id' => $validated['transport_option_id'] ?? null,
                'desired_delivery_date' => $validated['desired_delivery_date'],
                'approved_by_user_id' => null,
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

        if ($shouldRecordManagementEdit && $snapshotBefore !== null) {
            $application->refresh();
            $application->load(['subdivision', 'responsibleUser', 'transportOption', 'items.equipmentType']);
            $changeLines = ApplicationChangeRecorder::diff($snapshotBefore, $application);
            $managementReason = trim((string) ($validated['management_change_reason'] ?? ''));
            if ($managementReason !== '') {
                array_unshift($changeLines, 'Причина изменения: '.$managementReason);
            }
            if ($changeLines !== []) {
                DB::transaction(function () use ($application, $request, $changeLines) {
                    $history = $application->editHistories()->create([
                        'user_id' => $request->user()->id,
                        'edited_at' => now(),
                    ]);
                    foreach (array_values($changeLines) as $i => $line) {
                        $history->lines()->create([
                            'sort_order' => $i,
                            'body' => $line,
                        ]);
                    }
                });
            }
        }

        return redirect()->route('applications.index')
            ->with('status', 'Заявка успешно обновлена.');
    }

    public function saveApproval(Request $request, Application $application): RedirectResponse
    {
        if (! $request->user()->hasAnyRoleId($this->managementEditorRoleIds())) {
            abort(403, 'Согласование доступно только директору, техническому директору и начальнику отдела снабжения.');
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
                    $errors["items.{$item->id}.reason_not_selected"] = 'Укажите причину не одобрения.';
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
            'approved_by_user_id' => $request->user()->id,
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

    private function authorizeCanCreateApplications(Request $request): void
    {
        $allowed = $request->user() && $request->user()->hasAnyRoleId($this->createEditApplicationRoleIds());

        if (! $allowed) {
            abort(403, 'Создание заявок разрешено только директору, техническому директору, начальнику отдела снабжения и мастеру участка.');
        }
    }

    private function authorizeCanEditApplications(Request $request): void
    {
        $allowed = $request->user() && $request->user()->hasAnyRoleId($this->createEditApplicationRoleIds());

        if (! $allowed) {
            abort(403, 'Редактирование заявок разрешено директору, техническому директору, начальнику отдела снабжения и мастеру участка.');
        }
    }

    private function authorizeCanRepeatApplications(Request $request): void
    {
        if (! $request->user() || ! $request->user()->hasRoleId(4)) {
            abort(403, 'Создание повторной заявки разрешено только мастеру участка.');
        }
    }

    private function availableSubdivisionsForCreate(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return Subdivision::query()->whereRaw('1 = 0')->get();
        }

        if ($user->hasRoleId(4)) {
            return $user->assignedSubdivisions()->orderBy('name')->get();
        }

        return Subdivision::query()->orderBy('name')->get();
    }

    private function resolveCommercialOfferPath(Application $application): ?string
    {
        $relativePath = trim((string) ($application->commercial_offer_path ?? ''));
        if ($relativePath === '') {
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

    /**
     * Склады по подразделению (для подсказки в формах): строки «Нет» из справочника привязаны к «Да» через warehouses.subdivision_id.
     *
     * @return array<string, list<array{code: string, name: string}>>
     */
    private function warehousesBySubdivisionForUi(): array
    {
        return Warehouse::query()
            ->whereNotNull('subdivision_id')
            ->orderBy('name')
            ->get(['subdivision_id', 'code', 'name'])
            ->groupBy(fn (Warehouse $w): string => (string) $w->subdivision_id)
            ->map(fn ($group) => $group->map(fn (Warehouse $w): array => [
                'code' => $w->code,
                'name' => $w->name,
            ])->values()->all())
            ->all();
    }

    /**
     * Привязки «мастер участка -> подразделения» для UI-фильтра подразделений.
     *
     * @return array<string, list<string>>
     */
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

    /**
     * @return list<int>
     */
    private function managementEditorRoleIds(): array
    {
        return [1, 6, 2];
    }

    /**
     * @return list<int>
     */
    private function createEditApplicationRoleIds(): array
    {
        return [1, 6, 2, 4];
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
                'subdivision_id' => 'Выбранное подразделение не назначено выбранному мастеру участка.',
            ]);
        }
    }
}
