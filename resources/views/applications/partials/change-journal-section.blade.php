@if($application->changeJournalEntries->isNotEmpty())
    <section class="space-y-3" aria-labelledby="show-section-change-journal">
        <h3 id="show-section-change-journal" class="app-section-title">Журнал изменений</h3>
        <p class="text-xs text-black/70 dark:text-white/70">
            Записи о правках заявки: что изменено, прежнее и новое значение, комментарий автора.
        </p>
        <div class="overflow-x-auto rounded-xl border border-stone-200/90 dark:border-stone-600">
            <table class="min-w-full divide-y divide-stone-200 text-sm dark:divide-stone-700">
                <thead class="bg-stone-50/90 dark:bg-stone-900/50">
                    <tr>
                        <th scope="col" class="px-3 py-2 text-left font-medium text-black dark:text-white">Дата</th>
                        <th scope="col" class="px-3 py-2 text-left font-medium text-black dark:text-white">Действие</th>
                        <th scope="col" class="px-3 py-2 text-left font-medium text-black dark:text-white">Поле</th>
                        <th scope="col" class="px-3 py-2 text-left font-medium text-black dark:text-white">Было → стало</th>
                        <th scope="col" class="px-3 py-2 text-left font-medium text-black dark:text-white">Комментарий</th>
                        <th scope="col" class="px-3 py-2 text-left font-medium text-black dark:text-white">Кто</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-200 bg-white dark:divide-stone-700 dark:bg-stone-950/30">
                    @foreach($application->changeJournalEntries as $entry)
                        <tr>
                            <td class="whitespace-nowrap px-3 py-2 text-xs text-black/80 dark:text-white/80">
                                {{ $entry->created_at?->format('d.m.Y H:i') ?? '—' }}
                            </td>
                            <td class="px-3 py-2 text-black dark:text-white">{{ $entry->actionLabelRu() }}</td>
                            <td class="px-3 py-2 text-black dark:text-white">{{ $entry->field_label }}</td>
                            <td class="px-3 py-2 text-xs text-black dark:text-white">
                                @if(filled($entry->old_value) || filled($entry->new_value))
                                    @if(filled($entry->old_value))
                                        <span class="line-through opacity-75">{{ $entry->old_value }}</span>
                                    @else
                                        <span class="opacity-60">—</span>
                                    @endif
                                    <span class="mx-1">→</span>
                                    @if(filled($entry->new_value))
                                        <span>{{ $entry->new_value }}</span>
                                    @else
                                        <span class="opacity-60">—</span>
                                    @endif
                                @else
                                    <span class="opacity-60">—</span>
                                @endif
                            </td>
                            <td class="max-w-xs px-3 py-2 text-xs text-amber-900/90 dark:text-amber-100/85">{{ $entry->reason }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-xs text-black/80 dark:text-white/80">
                                @if($entry->user)
                                    {{ $entry->user->surname }} {{ $entry->user->name }}
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
