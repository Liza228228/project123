@php
    // шаблон страницы
    $uid = (string) ($fieldUid ?? 'delivery');
    $nameTransport = $nameTransport ?? null;
    $namePlate = $namePlate ?? null;
    $selectedMethodId = $selectedMethodId ?? null;
    $vehiclePlateValue = trim((string) ($vehiclePlateValue ?? ''));
    $methodNameById = ($transportOptions ?? collect())->pluck('name', 'id')->map(fn ($name) => trim((string) $name));
    $selectedMethodName = $selectedMethodId
        ? ($methodNameById[(int) $selectedMethodId] ?? '')
        : '';
    $plateFormatted = \App\Support\RussianVehiclePlate::formatWithSpaces($vehiclePlateValue);
    $hasPlateColumn = \Illuminate\Support\Facades\Schema::hasColumn('transport_options', 'plate');
    $fieldsRequired = ! ($optional ?? false) && filled($nameTransport);
@endphp
<div class="delivery-transport-fields grid grid-cols-1 gap-2 sm:grid-cols-2 sm:items-end" data-delivery-transport-fields>
    <div class="w-full">
        <label for="{{ $uid }}-transport-method" class="app-form-label !normal-case">Способ доставки</label>
        <select
            id="{{ $uid }}-transport-method"
            @if($nameTransport) name="{{ $nameTransport }}" @endif
            class="app-select text-sm w-full delivery-transport-method"
            data-delivery-method
            @if($fieldsRequired) required @endif
        >
            <option value="" disabled @selected($selectedMethodId === null || $selectedMethodId === '' || $selectedMethodId === 0)>Выберите способ</option>
            @foreach(($transportOptions ?? collect()) as $transportOption)
                <option
                    value="{{ $transportOption->id }}"
                    data-transport-name="{{ $transportOption->name }}"
                    @selected((string) $selectedMethodId === (string) $transportOption->id)
                >
                    {{ $transportOption->name }}
                </option>
            @endforeach
        </select>
    </div>
    @if($hasPlateColumn)
        <div class="delivery-plate-field w-full @if($selectedMethodName === \App\Models\TransportOption::NAME_SELF_PICKUP) hidden @endif" data-delivery-plate-field>
            <span class="app-form-label !normal-case block">Номер машины</span>
            <input
                id="{{ $uid }}-plate-text"
                type="text"
                @if($namePlate) name="{{ $namePlate }}" @endif
                value="{{ $plateFormatted }}"
                maxlength="13"
                inputmode="text"
                autocomplete="off"
                autocapitalize="characters"
                spellcheck="false"
                placeholder="А 123 ВС 77"
                class="delivery-plate-input delivery-transport-plate-text app-input text-sm w-full font-semibold uppercase @if($selectedMethodName === \App\Models\TransportOption::NAME_SERVICE_VEHICLE) hidden @endif"
                data-delivery-plate-text
                @if($fieldsRequired && $selectedMethodName !== \App\Models\TransportOption::NAME_SELF_PICKUP && $selectedMethodName !== \App\Models\TransportOption::NAME_SERVICE_VEHICLE) required @endif
                @if($selectedMethodName === \App\Models\TransportOption::NAME_SERVICE_VEHICLE || $selectedMethodName === \App\Models\TransportOption::NAME_SELF_PICKUP) disabled @endif
            />
            <select
                id="{{ $uid }}-plate-select"
                @if($namePlate && $selectedMethodName === \App\Models\TransportOption::NAME_SERVICE_VEHICLE) name="{{ $namePlate }}" @endif
                class="app-select text-sm w-full delivery-transport-plate-select @if($selectedMethodName !== \App\Models\TransportOption::NAME_SERVICE_VEHICLE) hidden @endif"
                data-delivery-plate-select
                @if($fieldsRequired && $selectedMethodName === \App\Models\TransportOption::NAME_SERVICE_VEHICLE) required @else disabled @endif
            >
                <option value="" disabled @selected($vehiclePlateValue === '')>Выберите машину</option>
                @foreach(($serviceVehiclePlateOptions ?? collect()) as $serviceVehicle)
                    <option value="{{ $serviceVehicle->plate }}" @selected((string) $vehiclePlateValue === (string) $serviceVehicle->plate)>
                        {{ $serviceVehicle->plate }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif
</div>
