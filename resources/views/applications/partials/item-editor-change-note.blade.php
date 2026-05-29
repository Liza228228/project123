@php
    $entries = $item->relationLoaded('changeJournalEntries')
        ? $item->changeJournalEntries->where('field_key', '!=', \App\Models\ApplicationChangeJournal::FIELD_ITEM_REMOVED)
        : collect();
@endphp
@if($entries->isNotEmpty())
    <div class="mt-1 space-y-0.5 text-xs text-amber-900/90 dark:text-amber-100/85">
        @foreach($entries->sortByDesc('created_at')->take(3) as $entry)
            <p>
                <span class="font-medium">Причина изменения:</span> {{ $entry->reason }}
            </p>
        @endforeach
    </div>
@endif
