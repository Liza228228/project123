@php
    $warehousesBySubdivision = $warehousesBySubdivision ?? [];
@endphp
@if(count($warehousesBySubdivision) > 0)
    <div
        id="subdivision-warehouses-panel"
        class="hidden rounded-lg border border-stone-200 dark:border-stone-700 bg-stone-50/70 dark:bg-stone-900/25 px-3 py-2 text-black dark:text-white"
    >
        <p class="text-xs font-medium text-black dark:text-white mb-1">Склады выбранного подразделения</p>

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
                            '<li class="py-1 border-b border-stone-200 dark:border-stone-800 last:border-0 last:pb-0">' +
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
