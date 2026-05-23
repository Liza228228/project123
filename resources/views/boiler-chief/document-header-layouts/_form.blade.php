@php
    use Illuminate\Support\Js;

    $defaultBlock = [
        'align' => 'center',
        'bold' => true,
        'font_family' => 'times_new_roman',
        'font_size_pt' => 11,
        'lines' => [['text' => '']],
    ];
    if (old('blocks_json')) {
        $decoded = json_decode((string) old('blocks_json'), true);
        $initialBlocks = is_array($decoded) && $decoded !== [] ? $decoded : [$defaultBlock];
    } elseif (isset($layout) && $layout && is_array($layout->schema['blocks'] ?? null) && $layout->schema['blocks'] !== []) {
        $initialBlocks = $layout->schema['blocks'];
    } else {
        $initialBlocks = [$defaultBlock];
    }
    foreach ($initialBlocks as $i => $block) {
        if (! is_array($block)) {
            continue;
        }
        $initialBlocks[$i]['font_family'] = 'times_new_roman';
    }
@endphp

<div class="overflow-hidden rounded-2xl border border-orange-200/85 bg-white shadow-md shadow-orange-950/[0.07] ring-1 ring-orange-100/90 dark:border-orange-900/50 dark:bg-stone-950 dark:shadow-black/35 dark:ring-orange-950/35"
     x-data="{
        maxBlocks: 3,
        blocks: {{ Js::from($initialBlocks) }},
        fontSizes: [8,9,10,11,12,13,14,16,18,20,22,24],
        fontFamilyCss(key) {
            const k = String(key || '').toLowerCase();
            if (k === 'arial' || k === 'dejavu_sans') return 'DejaVu Sans, Arial, sans-serif';
            if (k === 'georgia' || k === 'dejavu_serif' || k === 'times_new_roman') return 'DejaVu Serif, serif';
            return 'DejaVu Serif, serif';
        },
        previewText(text) {
            const t = String(text || '').trim();
            return t === '' ? '—' : t;
        },
        remainingSlots() { return Math.max(0, this.maxBlocks - this.blocks.length); },
        addBlock() {
            if (this.blocks.length >= this.maxBlocks) return;
            this.blocks.push({
                align: 'center',
                bold: true,
                font_family: 'times_new_roman',
                font_size_pt: 11,
                lines: [{ text: '' }]
            });
        },
        removeBlock(i) {
            if (this.blocks.length <= 1) return;
            this.blocks.splice(i, 1);
        },
        addLine(bi) {
            this.blocks[bi].lines.push({ text: '' });
        },
        removeLine(bi, li) {
            if (this.blocks[bi].lines.length <= 1) return;
            this.blocks[bi].lines.splice(li, 1);
        }
     }">
    <form method="POST" action="{{ $action }}" class="p-5 sm:p-8 space-y-6">
        @csrf
        @if (strtoupper($httpMethod ?? 'POST') === 'PUT')
            @method('PUT')
        @endif

        <x-validation-errors />

        <input type="hidden" name="blocks_json" :value="JSON.stringify(blocks)"/>

        <div>
            <label for="title" class="block text-sm font-medium text-stone-800 dark:text-stone-100 mb-1">Название макета шапки</label>
            <input id="title" name="title" type="text" required maxlength="255" value="{{ old('title', isset($layout) && $layout ? $layout->title : '') }}"
                   class="app-input"/>
        </div>

        <template x-for="(block, bi) in blocks" :key="bi">
            <div class="rounded-xl border border-orange-100/90 dark:border-orange-900/35 p-4 space-y-4 bg-orange-50/25 dark:bg-orange-950/15">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-stone-900 dark:text-white" x-text="'Блок шапки ' + (bi + 1)"></h3>
                    <button type="button" class="text-stone-400 hover:text-rose-600 p-1" title="Удалить блок"
                            @click="removeBlock(bi)" x-show="blocks.length > 1">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="flex flex-wrap gap-3 items-end">
                    <div class="min-w-[140px] flex-1">
                        <label class="block text-[10px] font-semibold uppercase tracking-wide text-orange-900/90 dark:text-orange-200/85 mb-1">Выравнивание</label>
                        <select class="app-select text-sm min-h-0 sm:min-h-0 py-2"
                                x-model="block.align">
                            <option value="left">По левому краю</option>
                            <option value="center">По центру</option>
                            <option value="right">По правому краю</option>
                        </select>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm cursor-pointer pb-1">
                        <input type="checkbox" class="rounded border-stone-300 text-orange-600 focus:ring-orange-500/40" x-model="block.bold"/>
                        <span>Жирный</span>
                    </label>
                    <div class="min-w-[160px] flex-1">
                        <label class="block text-[10px] font-semibold uppercase tracking-wide text-orange-900/90 dark:text-orange-200/85 mb-1">Шрифт блока</label>
                        <p class="rounded-xl border border-stone-200/80 bg-stone-50/90 px-3 py-2 text-sm text-stone-800 dark:border-stone-600 dark:bg-stone-900/50 dark:text-stone-100">
                            Times New Roman
                        </p>
                    </div>
                    <div class="w-24">
                        <label class="block text-[10px] font-semibold uppercase tracking-wide text-orange-900/90 dark:text-orange-200/85 mb-1">Размер (pt)</label>
                        <select class="app-select text-sm min-h-0 sm:min-h-0 py-2"
                                x-model.number="block.font_size_pt">
                            <template x-for="sz in fontSizes" :key="sz">
                                <option :value="sz" x-text="sz"></option>
                            </template>
                        </select>
                    </div>
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-medium text-stone-700 dark:text-stone-200">Строки блока</p>
                    <template x-for="(line, li) in block.lines" :key="li">
                        <div class="flex flex-wrap items-start gap-2">
                            <input type="text" placeholder="Текст строки"
                                   class="app-input flex-1 min-w-[200px] min-h-0 sm:min-h-0 py-2 text-sm"
                                   x-model="line.text"/>
                            <button type="button" class="p-2 text-stone-400 hover:text-rose-600 shrink-0" title="Удалить строку"
                                    @click="removeLine(bi, li)" x-show="block.lines.length > 1">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </div>
                    </template>
                    <button type="button" class="text-sm font-medium text-orange-800 hover:underline dark:text-orange-200/90"
                            @click="addLine(bi)">+ Строка</button>
                </div>

                <div class="rounded-lg border border-orange-200/80 dark:border-orange-900/40 bg-white/80 dark:bg-stone-900/60 p-3">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-orange-900/90 dark:text-orange-200/85 mb-2">
                        Предпросмотр блока
                    </p>
                    <div class="rounded-md border border-stone-200/80 dark:border-stone-700/70 bg-white dark:bg-stone-950 p-3">
                        <template x-for="(line, li) in block.lines" :key="'preview_' + bi + '_' + li">
                            <div class="leading-6"
                                 :style="'text-align:' + (block.align || 'center') + ';font-family:' + fontFamilyCss(block.font_family) + ';font-weight:' + (block.bold ? 700 : 400) + ';font-size:' + (Number(block.font_size_pt || 11)) + 'pt;'"
                                 x-text="previewText(line.text)"></div>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <div class="rounded-xl border-2 border-dashed border-orange-200/90 dark:border-orange-900/50 p-4 text-center space-y-2">
            <button type="button"
                    class="w-full sm:w-auto inline-flex justify-center rounded-lg border border-orange-500/70 bg-orange-50 dark:bg-orange-950/35 px-4 py-3 text-sm font-medium text-orange-950 dark:text-orange-100 hover:bg-orange-100/90 dark:hover:bg-orange-950/55 disabled:opacity-40 disabled:pointer-events-none"
                    @click="addBlock()"
                    :disabled="blocks.length >= maxBlocks">
                <span>+ Добавить блок шапки</span>
                <span class="ms-1 opacity-80" x-text="'(ещё ' + remainingSlots() + ' из ' + maxBlocks + ')'"></span>
            </button>
            
        </div>

        @if (! empty($returnTo))
            <input type="hidden" name="return" value="{{ $returnTo }}">
        @endif

        <div class="flex flex-col-reverse sm:flex-row gap-2 sm:justify-end pt-4 border-t border-orange-100/90 dark:border-orange-900/40">
            <a href="{{ $backHref ?? route('boiler-chief.document-header-layouts.index') }}"
               class="ui-btn ui-btn--secondary w-full sm:w-auto justify-center">
                Отмена
            </a>
            <button type="submit" class="ui-btn ui-btn--primary w-full sm:w-auto justify-center">
                {{ $submitLabel ?? 'Сохранить' }}
            </button>
        </div>
    </form>
</div>
