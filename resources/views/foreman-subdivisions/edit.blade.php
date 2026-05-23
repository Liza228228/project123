<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 w-full min-w-0">
            <x-page-header-nav :href="route('foreman-subdivisions.assignments')">Назад к мастерам</x-page-header-nav>
            <h2 class="font-semibold text-xl text-black dark:text-white leading-tight tracking-tight min-w-0 break-words">
                Назначить подразделения
            </h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl px-0 py-2 max-sm:-mx-4 sm:px-6 sm:py-8 md:py-10 lg:px-8"
         x-data="{
            skipPreview: false,
            previewLoading: false,
            previewError: '',
            requiresReassignment: false,
            blockApplications: [],
            bulkForemanId: '',
            previewUrl: @js(route('foreman-subdivisions.update.preview', $foreman)),
            normalizeApplications(apps) {
                return (apps || []).map((app) => ({
                    ...app,
                    reassignment_foreman_id: app.reassignment_foreman_id ?? '',
                }));
            },
            get bulkForemanOptions() {
                const optionsById = new Map();
                this.blockApplications
                    .filter((app) => app.can_reassign)
                    .forEach((app) => {
                        (app.foremen || []).forEach((foreman) => {
                            optionsById.set(String(foreman.id), foreman);
                        });
                    });
                return Array.from(optionsById.values()).sort((a, b) => a.label.localeCompare(b.label, 'ru'));
            },
            applyBulkForeman() {
                const id = String(this.bulkForemanId || '');
                if (id === '') {
                    return;
                }
                this.blockApplications.forEach((app) => {
                    if (! app.can_reassign) {
                        return;
                    }
                    const eligible = (app.foremen || []).some((foreman) => String(foreman.id) === id);
                    if (eligible) {
                        app.reassignment_foreman_id = id;
                    }
                });
            },
            get canConfirmAssign() {
                if (! this.requiresReassignment) {
                    return true;
                }
                return this.blockApplications.length > 0
                    && this.blockApplications.every((app) => app.can_reassign);
            },
            selectedSubdivisionIds() {
                const form = document.getElementById('foreman-subdivision-assign-form');
                if (! form) {
                    return [];
                }
                return Array.from(form.querySelectorAll('input[name=\'subdivision_ids[]\']:checked'))
                    .map((el) => el.value);
            },
            async handleSubmit(event) {
                if (this.skipPreview) {
                    this.skipPreview = false;
                    return;
                }
                event.preventDefault();
                this.previewLoading = true;
                this.previewError = '';
                const params = new URLSearchParams();
                this.selectedSubdivisionIds().forEach((id) => params.append('subdivision_ids[]', id));
                try {
                    const response = await fetch(this.previewUrl + '?' + params.toString(), {
                        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (! response.ok) {
                        throw new Error('Не удалось проверить заявки мастера.');
                    }
                    const data = await response.json();
                    this.requiresReassignment = Boolean(data.requires_reassignment);
                    this.bulkForemanId = '';
                    this.blockApplications = this.normalizeApplications(
                        Array.isArray(data.applications) ? data.applications : []
                    );
                    if (this.requiresReassignment) {
                        $dispatch('open-modal', 'foreman-subdivision-reassign');
                        return;
                    }
                    this.skipPreview = true;
                    event.target.requestSubmit();
                } catch (error) {
                    this.previewError = error?.message || 'Ошибка проверки.';
                    $dispatch('open-modal', 'foreman-subdivision-preview-error');
                } finally {
                    this.previewLoading = false;
                }
            },
         }">
        <div class="app-form-card">
            <div class="px-4 py-5 sm:p-8">
                <div class="mb-6 rounded-xl border border-stone-200/90 bg-stone-50/80 px-4 py-4 dark:border-stone-600 dark:bg-stone-800/35 sm:px-5">
                    <p class="text-xs font-medium uppercase tracking-wide text-stone-600 dark:text-stone-400">Мастер участка</p>
                    <p class="mt-1 text-base font-semibold text-stone-900 dark:text-stone-100">
                        {{ $foreman->surname }} {{ $foreman->name }} {{ $foreman->patronymic }}
                    </p>
                    <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">{{ $foreman->email }}</p>
                </div>

                @if($errors->any())
                    <x-app-alert type="error" class="mb-6">
                        @foreach($errors->all() as $message)
                            <p>{{ $message }}</p>
                        @endforeach
                    </x-app-alert>
                @endif

                <form id="foreman-subdivision-assign-form" method="POST" action="{{ route('foreman-subdivisions.update', $foreman) }}" class="space-y-8 sm:space-y-10" x-on:submit="handleSubmit($event)">
                    @csrf
                    @method('PUT')

                    <section class="space-y-4" aria-labelledby="foreman-subdivisions-section">
                        <h3 id="foreman-subdivisions-section" class="app-section-title">Подразделения</h3>
                        <p class="text-xs text-stone-500 dark:text-stone-400 -mt-1 max-w-2xl">
                            Отметьте подразделения, за которые отвечает мастер, и сохраните назначение.
                        </p>
                        @if($foremanAssignmentRestrictedToChiefSubdivisions ?? false)
                            <p class="text-xs text-amber-900/90 dark:text-amber-100/85 rounded-lg border border-amber-200/80 bg-amber-50/70 px-3 py-2 dark:border-amber-900/45 dark:bg-amber-950/30">
                                Вы назначаете мастера только по <strong>своим</strong> подразделениям. Назначения на другие подразделения не меняются и задаются директором, техническим директором, начальником отдела снабжения или администратором.
                            </p>
                        @endif
                        @if(($foremanAssignmentRestrictedToChiefSubdivisions ?? false) && $subdivisions->isEmpty())
                            <p class="text-sm text-stone-600 dark:text-stone-400">
                                У вас не закреплено ни одного подразделения как за начальника котельной — сначала их нужно назначить в разделе «Назначения котельной».
                            </p>
                        @endif

                        <div class="rounded-xl border border-stone-200/80 bg-white px-4 py-3 dark:border-stone-600/80 dark:bg-stone-900/30 sm:px-5 sm:py-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-stone-600 dark:text-stone-400 mb-2">Сейчас назначено</p>
                            @if($foreman->assignedSubdivisions->isEmpty())
                                <p class="text-sm text-stone-500 dark:text-stone-400">Подразделения пока не назначены.</p>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    @foreach($foreman->assignedSubdivisions as $assigned)
                                        <span class="inline-flex items-center rounded-full border border-orange-200/80 bg-orange-50/90 px-3 py-1 text-xs font-medium text-stone-800 dark:border-orange-900/50 dark:bg-orange-950/40 dark:text-stone-100">
                                            {{ $assigned->name }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div>
                            <label for="subdivision-search" class="app-form-label">Поиск по списку</label>
                            <div class="relative">
                                <span class="pointer-events-none absolute left-3 top-1/2 z-10 -translate-y-1/2 text-stone-400 dark:text-stone-500" aria-hidden="true">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                </span>
                                <input
                                    id="subdivision-search"
                                    type="search"
                                    autocomplete="off"
                                    placeholder="Начните вводить название…"
                                    class="app-input app-input--with-icon"
                                />
                            </div>
                        </div>

                        <div class="max-h-80 overflow-y-auto rounded-xl border border-dashed border-orange-300/80 bg-orange-50/40 p-4 dark:border-orange-800/50 dark:bg-orange-950/20 sm:p-5">
                            <div id="subdivision-list" class="grid gap-3 sm:grid-cols-2">
                                @foreach($subdivisions as $subdivision)
                                    <label class="subdivision-option flex cursor-pointer items-start gap-3 rounded-xl border border-stone-200/90 bg-white px-3 py-3 text-sm text-stone-800 shadow-sm transition hover:border-orange-200/90 hover:bg-orange-50/30 dark:border-stone-600 dark:bg-stone-900/50 dark:text-stone-100 dark:hover:border-orange-900/40 dark:hover:bg-orange-950/25" data-name="{{ mb_strtolower($subdivision->name) }}">
                                        <input
                                            type="checkbox"
                                            name="subdivision_ids[]"
                                            value="{{ $subdivision->id }}"
                                            @checked($foreman->assignedSubdivisions->contains('id', $subdivision->id))
                                            class="mt-0.5 shrink-0 rounded-md border-stone-300 text-orange-600 shadow-sm focus:ring-2 focus:ring-orange-400/30 dark:border-stone-500 dark:bg-stone-900 dark:text-orange-500 dark:focus:ring-orange-500/30"
                                        >
                                        <span class="min-w-0 leading-snug">{{ $subdivision->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <p id="subdivision-empty" class="hidden text-sm text-stone-500 dark:text-stone-400 mt-2">Ничего не найдено.</p>
                        </div>
                        <x-input-error :messages="$errors->get('subdivision_ids')" class="mt-1.5" />
                        <x-input-error :messages="$errors->get('subdivision_ids.*')" class="mt-1.5" />
                    </section>

                    <div class="app-form-actions-mobile">
                        <a href="{{ route('foreman-subdivisions.assignments') }}" class="min-h-11 content-center text-center text-sm font-medium text-stone-600 underline decoration-stone-300 underline-offset-2 hover:text-stone-900 dark:text-stone-400 dark:hover:text-stone-200 sm:text-left">
                            Отмена и к списку мастеров
                        </a>
                        <button type="submit" class="ui-btn ui-btn--primary ui-btn--lg w-full text-base sm:w-auto" :disabled="previewLoading">
                            <span x-show="! previewLoading">Назначить</span>
                            <span x-show="previewLoading" x-cloak>Проверка заявок…</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <x-modal name="foreman-subdivision-preview-error" :show="false" maxWidth="md" focusable>
            <div class="p-5 sm:p-6 space-y-4">
                <h3 class="text-base font-semibold text-black dark:text-white">Ошибка</h3>
                <p class="text-sm text-rose-800 dark:text-rose-200" x-text="previewError || 'Не удалось проверить заявки.'"></p>
                <div class="flex justify-end">
                    <button type="button" x-on:click="$dispatch('close-modal', 'foreman-subdivision-preview-error')" class="ui-btn ui-btn--secondary">Закрыть</button>
                </div>
            </div>
        </x-modal>

        <x-modal name="foreman-subdivision-reassign" :show="false" maxWidth="2xl" focusable>
            <div class="p-5 sm:p-6 space-y-4 max-h-[min(90vh,42rem)] overflow-y-auto">
                <h3 class="text-base sm:text-lg font-semibold text-black dark:text-white">
                    Переназначение заявок перед сменой подразделений
                </h3>
                <p class="text-sm text-black/85 dark:text-white/85">
                    У мастера есть активные заявки в подразделениях, с которых вы снимаете назначение.
                    Выберите мастера для всех заявок или назначьте по строкам — только из подразделения заявки.
                </p>

                <div class="rounded-xl border border-orange-200/80 bg-orange-50/50 px-4 py-4 dark:border-orange-900/45 dark:bg-orange-950/25"
                     x-show="blockApplications.some((app) => app.can_reassign)">
                    <label for="bulk-foreman-subdivision" class="app-form-label">Мастер для всех заявок</label>
                    <select
                        id="bulk-foreman-subdivision"
                        class="app-select text-sm w-full max-w-md mt-1"
                        x-model="bulkForemanId"
                        x-on:change="applyBulkForeman()"
                    >
                        <option value="">— выберите мастера —</option>
                        <template x-for="foreman in bulkForemanOptions" :key="foreman.id">
                            <option :value="foreman.id" x-text="foreman.label"></option>
                        </template>
                    </select>
                    <p class="mt-2 text-xs text-stone-600 dark:text-stone-400">
                        Выбор сразу заполнит все строки, где этот мастер допустим для подразделения заявки.
                    </p>
                </div>

                <div class="app-table-shell">
                    <table class="text-sm min-w-full">
                        <thead class="bg-orange-100/70 dark:bg-orange-900/35">
                            <tr>
                                <th class="px-3 py-2 text-left font-semibold">Заявка</th>
                                <th class="px-3 py-2 text-left font-semibold">Подразделение</th>
                                <th class="px-3 py-2 text-left font-semibold">Роль</th>
                                <th class="px-3 py-2 text-left font-semibold">Новый мастер</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-orange-100/90 dark:divide-orange-900/30">
                            <template x-for="app in blockApplications" :key="app.id">
                                <tr class="bg-white/90 dark:bg-stone-900/40 align-top">
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        <span class="font-medium" x-text="'№' + app.id"></span>
                                        <p class="text-xs text-stone-500 dark:text-stone-400 mt-0.5" x-show="app.desired_delivery_date" x-text="app.desired_delivery_date"></p>
                                    </td>
                                    <td class="px-3 py-3" x-text="app.subdivision_name"></td>
                                    <td class="px-3 py-3 text-xs" x-text="app.involvement_label"></td>
                                    <td class="px-3 py-3 min-w-[10rem]">
                                        <template x-if="! app.can_reassign">
                                            <p class="text-xs text-amber-800 dark:text-amber-200" x-text="app.message"></p>
                                        </template>
                                        <template x-if="app.can_reassign">
                                            <select
                                                class="app-select text-sm w-full"
                                                :name="'reassignments[' + app.id + ']'"
                                                :form="'foreman-subdivision-assign-form'"
                                                x-model="app.reassignment_foreman_id"
                                                required
                                            >
                                                <option value="">— выберите мастера —</option>
                                                <template x-for="foreman in app.foremen" :key="foreman.id">
                                                    <option :value="String(foreman.id)" x-text="foreman.label"></option>
                                                </template>
                                            </select>
                                        </template>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <p class="text-xs text-amber-800 dark:text-amber-200" x-show="requiresReassignment && ! canConfirmAssign">
                    Сохранение невозможно: для одной или нескольких заявок нет другого мастера в подразделении. Сначала назначьте в это подразделение другого мастера.
                </p>

                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2 pt-1">
                    <button type="button"
                        x-on:click="$dispatch('close-modal', 'foreman-subdivision-reassign')"
                        class="ui-btn ui-btn--secondary w-full sm:w-auto">
                        Отмена
                    </button>
                    <button type="submit"
                        form="foreman-subdivision-assign-form"
                        class="ui-btn ui-btn--primary w-full sm:w-auto"
                        :disabled="! canConfirmAssign"
                        x-on:click="skipPreview = true">
                        Переназначить и сохранить
                    </button>
                </div>
            </div>
        </x-modal>
    </div>

    <script>
        (function () {
            var searchInput = document.getElementById('subdivision-search');
            var options = Array.prototype.slice.call(document.querySelectorAll('.subdivision-option'));
            var emptyMessage = document.getElementById('subdivision-empty');

            if (!searchInput || options.length === 0 || !emptyMessage) {
                return;
            }

            function normalize(value) {
                return (value || '').toString().trim().toLowerCase();
            }

            function applyFilter() {
                var query = normalize(searchInput.value);
                var visibleCount = 0;

                options.forEach(function (option) {
                    var name = option.getAttribute('data-name') || '';
                    var isVisible = query === '' || name.indexOf(query) !== -1;
                    option.style.display = isVisible ? '' : 'none';
                    if (isVisible) {
                        visibleCount++;
                    }
                });

                emptyMessage.classList.toggle('hidden', visibleCount > 0);
            }

            searchInput.addEventListener('input', applyFilter);
            applyFilter();
        })();
    </script>
</x-app-layout>
