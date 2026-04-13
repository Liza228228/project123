@php
    /** @var array<string, mixed> $settings */
    $s = $settings;
@endphp

<div class="space-y-5">
    <div>
        <label for="hdr-name" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Название шаблона</label>
        <input type="text" name="name" id="hdr-name" value="{{ old('name', $nameValue ?? '') }}" required maxlength="255"
            class="w-full max-w-xl rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500"
            placeholder="Например: Акт для цеха №6">
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Шрифт шапки (организация, утверждение, дата)</label>
            <select name="settings[font_family]"
                class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
                @foreach($fontOptions as $val => $label)
                    <option value="{{ $val }}" @selected(old('settings.font_family', $s['font_family']) === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Шрифт заголовка документа</label>
            <select name="settings[title_font_family]"
                class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
                <option value="" @selected(old('settings.title_font_family', $s['title_font_family'] ?? '') === '')>Как у шапки</option>
                @foreach($fontOptions as $val => $label)
                    <option value="{{ $val }}" @selected(old('settings.title_font_family', $s['title_font_family'] ?? '') === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Размер заголовка (pt)</label>
            <input type="number" name="settings[title_font_pt]" min="8" max="36" value="{{ old('settings.title_font_pt', $s['title_font_pt']) }}"
                class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Выравнивание блока организации</label>
            <select name="settings[org_align]" class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
                @foreach(['left' => 'Слева', 'center' => 'По центру', 'right' => 'Справа'] as $val => $label)
                    <option value="{{ $val }}" @selected(old('settings.org_align', $s['org_align']) === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Выравнивание «Утверждаю»</label>
            <select name="settings[approval_align]" class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
                @foreach(['left' => 'Слева', 'center' => 'По центру', 'right' => 'Справа'] as $val => $label)
                    <option value="{{ $val }}" @selected(old('settings.approval_align', $s['approval_align']) === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Выравнивание названия документа</label>
            <select name="settings[title_align]" class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
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
                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">{{ old('settings.org_name', $s['org_name']) }}</textarea>
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Подпись под строкой</label>
                <input type="text" name="settings[org_caption]" value="{{ old('settings.org_caption', $s['org_caption']) }}"
                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
            </div>
        </div>
        <div class="space-y-3">
            <h3 class="text-sm font-semibold text-black dark:text-white">Утверждение (справа вверху)</h3>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Заголовок</label>
                <input type="text" name="settings[approval_label]" value="{{ old('settings.approval_label', $s['approval_label']) }}"
                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Должность</label>
                <input type="text" name="settings[approval_position]" value="{{ old('settings.approval_position', $s['approval_position']) }}"
                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Подпись под должностью</label>
                <input type="text" name="settings[approval_position_caption]" value="{{ old('settings.approval_position_caption', $s['approval_position_caption']) }}"
                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">ФИО</label>
                <input type="text" name="settings[approval_name]" value="{{ old('settings.approval_name', $s['approval_name']) }}"
                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Подпись под ФИО</label>
                <input type="text" name="settings[approval_name_caption]" value="{{ old('settings.approval_name_caption', $s['approval_name_caption']) }}"
                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
            </div>
        </div>
    </div>

    <div class="space-y-3">
        <h3 class="text-sm font-semibold text-black dark:text-white">Название документа и дата</h3>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Заголовок (например, название акта)</label>
            <textarea name="settings[title]" rows="2"
                class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">{{ old('settings.title', $s['title']) }}</textarea>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Дата (строка слева)</label>
                <input type="text" name="settings[date_text]" value="{{ old('settings.date_text', $s['date_text']) }}"
                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500"
                    placeholder="«23» декабря 2019 г.">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Населённый пункт (справа)</label>
                <input type="text" name="settings[city_text]" value="{{ old('settings.city_text', $s['city_text']) }}"
                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500"
                    placeholder="г. Воронеж">
            </div>
        </div>
    </div>
</div>
