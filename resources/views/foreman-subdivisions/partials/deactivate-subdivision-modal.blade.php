@php // шаблон страницы
@endphp
@if($canDeleteInfrastructure ?? false)
    <form
        id="subdivision-deactivate-form"
        method="post"
        action="#"
        class="hidden"
    >
        @csrf
        <div id="subdivision-deactivate-form-fields"></div>
    </form>

    <x-modal name="confirm-subdivision-deactivate" :show="false" maxWidth="2xl" focusable>
        <div
            class="p-6"
            data-subdivision-deactivate-modal-root
            x-data="subdivisionDeactivateModal"
            @open-subdivision-deactivate.window="openForSubdivision($event.detail)"
        >
            <h3 class="text-lg font-semibold text-stone-900 dark:text-stone-100" x-text="modalTitle"></h3>

            <template x-if="hardBlock">
                <p class="mt-4 text-sm text-stone-700 dark:text-stone-300" x-text="hardBlock"></p>
            </template>

            <template x-if="! hardBlock && ! requiresStaffActions">
                <p class="mt-4 text-sm text-stone-700 dark:text-stone-300">
                    Вы уверены, что хотите сделать подразделение «<span class="font-medium" x-text="subdivisionName"></span>» недоступным?
                    Его склады тоже станут недоступными для новых операций. Существующие заявки останутся в этом подразделении и не переедут вместе с мастером.
                </p>
            </template>

            <template x-if="! hardBlock && requiresStaffActions">
                <div class="mt-4 space-y-4">
                    <p class="text-sm text-stone-700 dark:text-stone-300">
                        Перед отключением подразделения «<span class="font-medium" x-text="subdivisionName"></span>» укажите,
                        что сделать с назначенными сотрудниками. Заявки в этом подразделении не переносятся — меняются только закрепления мастера и начальника котельной.
                    </p>

                    <template x-if="boilerChiefs.length > 0">
                        <div class="space-y-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-stone-600 dark:text-stone-400">Начальники котельной</p>
                            <template x-for="chief in boilerChiefs" :key="'chief-' + chief.user_id">
                                <div class="rounded-lg border border-stone-200/90 bg-stone-50/80 px-3 py-3 dark:border-stone-600 dark:bg-stone-800/40">
                                    <label class="app-form-label" x-text="chief.label"></label>
                                    <p class="mt-0.5 text-xs text-stone-500 dark:text-stone-400" x-show="chief.has_other_subdivisions">
                                        Есть другие подразделения — можно только снять с отключаемого.
                                    </p>
                                    <select
                                        class="app-select mt-1.5 w-full"
                                        x-model="chiefAssignments[chief.user_id]"
                                        required
                                    >
                                        <option value="">Выберите действие…</option>
                                        <option :value="detachOnlyValue">Только снять с этого подразделения</option>
                                        <template x-for="opt in chief.subdivision_options" :key="'chief-opt-' + chief.user_id + '-' + opt.id">
                                            <option :value="String(opt.id)" x-text="'Назначить: ' + opt.name"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                        </div>
                    </template>

                    <template x-if="foremen.length > 0">
                        <div class="space-y-3">
                            <p class="text-xs font-semibold uppercase tracking-wide text-stone-600 dark:text-stone-400">Мастера участка</p>
                            <template x-for="foreman in foremen" :key="'foreman-' + foreman.user_id">
                                <div class="rounded-lg border border-stone-200/90 bg-stone-50/80 px-3 py-3 dark:border-stone-600 dark:bg-stone-800/40">
                                    <label class="app-form-label" x-text="foreman.label"></label>
                                    <p class="mt-0.5 text-xs text-stone-500 dark:text-stone-400" x-show="foreman.has_other_subdivisions">
                                        Есть другие подразделения — можно только снять с отключаемого.
                                    </p>
                                    <select
                                        class="app-select mt-1.5 w-full"
                                        x-model="foremanAssignments[foreman.user_id]"
                                        required
                                    >
                                        <option value="">Выберите действие…</option>
                                        <option :value="detachOnlyValue">Только снять с этого подразделения</option>
                                        <template x-for="opt in foreman.subdivision_options" :key="'foreman-opt-' + foreman.user_id + '-' + opt.id">
                                            <option :value="String(opt.id)" x-text="'Назначить: ' + opt.name"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>

            <p class="mt-3 text-sm text-red-700 dark:text-red-300" x-show="previewError" x-text="previewError"></p>

            <div class="mt-6 flex flex-wrap justify-end gap-2">
                <button
                    type="button"
                    class="ui-btn ui-btn--secondary"
                    x-on:click="$dispatch('close-modal', 'confirm-subdivision-deactivate')"
                >
                    Отмена
                </button>
                <button
                    type="button"
                    class="ui-btn ui-btn--danger"
                    x-show="! hardBlock"
                    x-bind:disabled="! canConfirmDeactivate || loading"
                    x-on:click="confirmDeactivate()"
                >
                    <span x-show="! loading">Сделать недоступным</span>
                    <span x-show="loading">Проверка…</span>
                </button>
            </div>
        </div>
    </x-modal>
@endif
