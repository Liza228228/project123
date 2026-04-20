@php
    $warehousesBySubdivision = $warehousesBySubdivision ?? [];
@endphp
@if(count($warehousesBySubdivision) > 0)
    <div
        id="subdivision-warehouses-panel"
        class="hidden rounded-xl border border-orange-200/80 bg-orange-50/50 px-4 py-3 text-black shadow-sm ring-1 ring-orange-100/70 dark:border-orange-900/50 dark:bg-orange-950/20 dark:text-white dark:ring-orange-950/30"
    >
        <p class="text-xs font-semibold uppercase tracking-wide text-stone-600 dark:text-stone-300 mb-2">Склады выбранного подразделения</p>

        <ul id="subdivision-warehouses-list" class="text-sm max-h-44 overflow-y-auto space-y-0.5 list-none m-0 p-0"></ul>
    </div>
    <script>
        (function () {
            var map = @json($warehousesBySubdivision);
            var sel = document.getElementById('subdivision_id');
            var panel = document.getElementById('subdivision-warehouses-panel');
            var list = document.getElementById('subdivision-warehouses-list');
            if (!sel || !panel || !list) {
                return;
            }
            function escapeHtml(s) {
                var d = document.createElement('div');
                d.textContent = s;
                return d.innerHTML;
            }
            function sync() {
                var id = sel.value;
                var rows = map[id] || [];
                if (rows.length === 0) {
                    panel.classList.add('hidden');
                    list.innerHTML = '';
                    return;
                }
                panel.classList.remove('hidden');
                list.innerHTML = rows
                    .map(function (r) {
                        return (
                            '<li class="py-2 border-b border-orange-100/90 text-sm last:border-0 dark:border-orange-900/40">' +
                            '<span class="font-mono text-xs opacity-80">' +
                            escapeHtml(r.code) +
                            '</span> — ' +
                            escapeHtml(r.name) +
                            '</li>'
                        );
                    })
                    .join('');
            }
            sel.addEventListener('change', sync);
            sync();
        })();
    </script>
@endif
