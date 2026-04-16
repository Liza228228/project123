@php
    /** @var array<string, mixed> $settings */
    $s = $settings;
@endphp

<div class="space-y-5">
    <div>
        <label for="hdr-name" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Название шаблона</label>
        <input type="text" name="name" id="hdr-name" value="{{ old('name', $nameValue ?? '') }}" required maxlength="255"
            class="w-full max-w-xl rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500"
            placeholder="Например: Акт для цеха №6">
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Шрифт шапки (организация, утверждение, дата)</label>
            <select name="settings[font_family]"
                class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
                @foreach($fontOptions as $val => $label)
                    <option value="{{ $val }}" @selected(old('settings.font_family', $s['font_family']) === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Шрифт заголовка документа</label>
            <select name="settings[title_font_family]"
                class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
                <option value="" @selected(old('settings.title_font_family', $s['title_font_family'] ?? '') === '')>Как у шапки</option>
                @foreach($fontOptions as $val => $label)
                    <option value="{{ $val }}" @selected(old('settings.title_font_family', $s['title_font_family'] ?? '') === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Размер заголовка (pt)</label>
            <input type="number" name="settings[title_font_pt]" min="8" max="36" value="{{ old('settings.title_font_pt', $s['title_font_pt']) }}"
                class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Выравнивание блока организации</label>
            <select name="settings[org_align]" class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
                @foreach(['left' => 'Слева', 'center' => 'По центру', 'right' => 'Справа'] as $val => $label)
                    <option value="{{ $val }}" @selected(old('settings.org_align', $s['org_align']) === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Выравнивание «Утверждаю»</label>
            <select name="settings[approval_align]" class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
                @foreach(['left' => 'Слева', 'center' => 'По центру', 'right' => 'Справа'] as $val => $label)
                    <option value="{{ $val }}" @selected(old('settings.approval_align', $s['approval_align']) === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Выравнивание названия документа</label>
            <select name="settings[title_align]" class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
                @foreach(['left' => 'Слева', 'center' => 'По центру', 'right' => 'Справа'] as $val => $label)
                    <option value="{{ $val }}" @selected(old('settings.title_align', $s['title_align']) === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="space-y-3">
            <h3 class="text-sm font-semibold text-black dark:text-white">Организация (слева вверху)</h3>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Наименование</label>
                <textarea name="settings[org_name]" rows="2"
                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">{{ old('settings.org_name', $s['org_name']) }}</textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Подпись под строкой</label>
                <input type="text" name="settings[org_caption]" value="{{ old('settings.org_caption', $s['org_caption']) }}"
                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
            </div>
        </div>
        <div class="space-y-3">
            <h3 class="text-sm font-semibold text-black dark:text-white">Утверждение (справа вверху)</h3>
            <div>
                <label for="approval-role-select" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Кто утверждает (роль)</label>
                <select id="approval-role-select"
                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
                    <option value="">Выберите роль</option>
                    @foreach($approverRoles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="approval-user-select" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">ФИО утверждающего</label>
                <select id="approval-user-select"
                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
                    <option value="">Сначала выберите роль</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Заголовок</label>
                <input type="text" name="settings[approval_label]" value="{{ old('settings.approval_label', $s['approval_label']) }}"
                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Должность</label>
                <input type="text" id="approval-position-input" name="settings[approval_position]" value="{{ old('settings.approval_position', $s['approval_position']) }}"
                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Подпись под должностью</label>
                <input type="text" name="settings[approval_position_caption]" value="{{ old('settings.approval_position_caption', $s['approval_position_caption']) }}"
                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">ФИО</label>
                <input type="text" id="approval-name-input" name="settings[approval_name]" value="{{ old('settings.approval_name', $s['approval_name']) }}"
                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Подпись под ФИО</label>
                <input type="text" name="settings[approval_name_caption]" value="{{ old('settings.approval_name_caption', $s['approval_name_caption']) }}"
                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
            </div>
        </div>
    </div>

    <div class="space-y-3">
        <h3 class="text-sm font-semibold text-black dark:text-white">Название документа и дата</h3>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Заголовок (например, название акта)</label>
            <textarea name="settings[title]" rows="2"
                class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">{{ old('settings.title', $s['title']) }}</textarea>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Дата (строка слева)</label>
                <input type="date" name="settings[date_text]" value="{{ old('settings.date_text', ($s['date_text'] ?? '') !== '' ? $s['date_text'] : now()->toDateString()) }}"
                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Населённый пункт (справа)</label>
                <input type="text" name="settings[city_text]" value="{{ old('settings.city_text', $s['city_text']) }}"
                    class="w-full rounded-lg border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-900/40 text-black dark:text-white text-sm shadow-sm focus:border-stone-500 focus:ring-stone-500"
                    placeholder="г. Воронеж">
            </div>
        </div>
    </div>
</div>

<script>
    (function () {
        var roleSelect = document.getElementById('approval-role-select');
        var userSelect = document.getElementById('approval-user-select');
        var positionInput = document.getElementById('approval-position-input');
        var nameInput = document.getElementById('approval-name-input');
        var approversByRole = @json($approversByRole ?? []);
        var roleNames = @json(($approverRoles ?? collect())->pluck('name', 'id')->mapWithKeys(fn ($name, $id) => [(string) $id => $name])->all());

        if (!roleSelect || !userSelect || !positionInput || !nameInput) {
            return;
        }

        function renderUsersForRole(roleId) {
            var users = approversByRole[roleId] || [];
            userSelect.innerHTML = '';
            if (!roleId) {
                userSelect.add(new Option('Сначала выберите роль', ''));
                return;
            }
            userSelect.add(new Option('Выберите ФИО', ''));
            users.forEach(function (u) {
                userSelect.add(new Option(u.fio, String(u.id)));
            });
        }

        roleSelect.addEventListener('change', function () {
            var roleId = roleSelect.value || '';
            renderUsersForRole(roleId);
            positionInput.value = roleNames[roleId] || positionInput.value;
            nameInput.value = '';
        });

        userSelect.addEventListener('change', function () {
            var roleId = roleSelect.value || '';
            var selected = (approversByRole[roleId] || []).find(function (u) {
                return String(u.id) === String(userSelect.value || '');
            });
            if (roleNames[roleId]) {
                positionInput.value = roleNames[roleId];
            }
            if (selected) {
                nameInput.value = selected.fio;
            }
        });

        var initialRoleId = Object.keys(roleNames).find(function (id) {
            return roleNames[id] === (positionInput.value || '').trim();
        }) || '';
        if (initialRoleId) {
            roleSelect.value = initialRoleId;
            renderUsersForRole(initialRoleId);
            var matched = (approversByRole[initialRoleId] || []).find(function (u) {
                return u.fio === (nameInput.value || '').trim();
            });
            if (matched) {
                userSelect.value = String(matched.id);
            }
        }
    })();
</script>
