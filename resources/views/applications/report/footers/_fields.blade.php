@php
    /** @var array<string, mixed> $settings */
    $s = $settings;
@endphp

<div class="space-y-5">
    <div>
        <label for="ftr-name" class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Название шаблона</label>
        <input type="text" name="name" id="ftr-name" value="{{ old('name', $nameValue ?? '') }}" required maxlength="255"
            class="w-full max-w-xl rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500"
            placeholder="Например: Подписи комиссии">
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Шрифт подвала</label>
            <select name="settings[font_family]"
                class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
                @foreach($fontOptions as $val => $label)
                    <option value="{{ $val }}" @selected(old('settings.font_family', $s['font_family']) === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Число строк «Члены комиссии»</label>
            <input type="number" name="settings[members_count]" min="1" max="12" value="{{ old('settings.members_count', $s['members_count']) }}"
                class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Выравнивание блока председателя</label>
            <select name="settings[chairman_align]" class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
                @foreach(['left' => 'Слева', 'center' => 'По центру', 'right' => 'Справа'] as $val => $label)
                    <option value="{{ $val }}" @selected(old('settings.chairman_align', $s['chairman_align']) === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Выравнивание членов комиссии</label>
            <select name="settings[members_align]" class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
                @foreach(['left' => 'Слева', 'center' => 'По центру', 'right' => 'Справа'] as $val => $label)
                    <option value="{{ $val }}" @selected(old('settings.members_align', $s['members_align']) === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="space-y-3">
            <h3 class="text-sm font-semibold text-black dark:text-white">Председатель</h3>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Подпись строки</label>
                <input type="text" name="settings[chairman_label]" value="{{ old('settings.chairman_label', $s['chairman_label']) }}"
                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Подпись под полем «подпись»</label>
                <input type="text" name="settings[chairman_sig_caption]" value="{{ old('settings.chairman_sig_caption', $s['chairman_sig_caption']) }}"
                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Подпись под полем «ФИО»</label>
                <input type="text" name="settings[chairman_name_caption]" value="{{ old('settings.chairman_name_caption', $s['chairman_name_caption']) }}"
                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
            </div>
        </div>
        <div class="space-y-3">
            <h3 class="text-sm font-semibold text-black dark:text-white">Члены комиссии</h3>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Заголовок блока</label>
                <input type="text" name="settings[members_label]" value="{{ old('settings.members_label', $s['members_label']) }}"
                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Подпись под полем «подпись»</label>
                <input type="text" name="settings[member_sig_caption]" value="{{ old('settings.member_sig_caption', $s['member_sig_caption']) }}"
                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
            </div>
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wide text-black/70 dark:text-white/60 mb-1.5">Подпись под полем «ФИО»</label>
                <input type="text" name="settings[member_name_caption]" value="{{ old('settings.member_name_caption', $s['member_name_caption']) }}"
                    class="w-full rounded-lg border-orange-300 dark:border-orange-600 bg-white dark:bg-orange-900/40 text-black dark:text-white text-sm shadow-sm focus:border-orange-500 focus:ring-orange-500">
            </div>
        </div>
    </div>
</div>
