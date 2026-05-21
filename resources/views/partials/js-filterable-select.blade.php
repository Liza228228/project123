@php
    $searchInputId = $searchInputId ?? 'filterable_select_search';
    $selectInputId = $selectInputId ?? 'filterable_select';
    $preserveSelection = (bool) ($preserveSelection ?? true);
@endphp
<script>
    (function () {
        const searchInput = document.getElementById(@json($searchInputId));
        const select = document.getElementById(@json($selectInputId));
        if (!searchInput || !select) {
            return;
        }

        const initialOptions = Array.from(select.options).map((option) => ({
            value: option.value,
            text: option.textContent || '',
            selected: option.selected,
        }));
        const placeholder = initialOptions[0] ?? { value: '', text: '', selected: true };

        const rebuildOptions = (query) => {
            const q = String(query || '').trim().toLowerCase();
            const currentValue = select.value;
            let matched = initialOptions
                .slice(1)
                .filter((opt) => q === '' || opt.text.toLowerCase().includes(q));

            if (@json($preserveSelection) && currentValue !== '' && !matched.some((opt) => opt.value === currentValue)) {
                const selected = initialOptions.find((opt) => opt.value === currentValue);
                if (selected) {
                    matched = [selected, ...matched];
                }
            }

            select.innerHTML = '';
            const placeholderOption = document.createElement('option');
            placeholderOption.value = placeholder.value;
            placeholderOption.textContent = placeholder.text;
            select.appendChild(placeholderOption);

            matched.forEach((opt) => {
                const option = document.createElement('option');
                option.value = opt.value;
                option.textContent = opt.text;
                if (opt.value === currentValue) {
                    option.selected = true;
                }
                select.appendChild(option);
            });

            if (!@json($preserveSelection) && currentValue !== '' && !matched.some((opt) => opt.value === currentValue)) {
                select.value = '';
            }
        };

        searchInput.addEventListener('input', () => rebuildOptions(searchInput.value));
    })();
</script>
