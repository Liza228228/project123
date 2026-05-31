@php
    // шаблон страницы
    $searchInputId = $searchInputId ?? 'filterable_select_search';
    $selectInputId = $selectInputId ?? 'filterable_select';
    $preserveSelection = (bool) ($preserveSelection ?? true);
    $expandOnSearch = (bool) ($expandOnSearch ?? false);
    $comboMode = (bool) ($comboMode ?? false);
@endphp
<script>
    (function () {
        const searchInputId = @json($searchInputId);
        const selectInputId = @json($selectInputId);
        const preserveSelection = @json($preserveSelection);
        const expandOnSearch = @json($expandOnSearch);
        const comboMode = @json($comboMode);

        const init = () => {
            const searchInput = document.getElementById(searchInputId);
            const select = document.getElementById(selectInputId);
            if (!searchInput || !select) {
                return;
            }

            const initialOptions = Array.from(select.options).map((option) => ({
                value: option.value,
                text: (option.textContent || '').trim(),
                selected: option.selected,
            }));
            const placeholder = initialOptions[0] ?? { value: '', text: '', selected: true };

            const collapseSelect = () => {
                if (!expandOnSearch) {
                    return;
                }
                select.size = 1;
                select.classList.remove('filterable-select--expanded');
                if (comboMode) {
                    select.setAttribute('aria-hidden', 'true');
                    select.tabIndex = -1;
                }
            };

            const rebuildOptions = (query) => {
                const q = String(query || '').trim().toLowerCase();
                const currentValue = select.value;
                let matched = initialOptions
                    .slice(1)
                    .filter((opt) => q === '' || opt.text.toLowerCase().includes(q));

                if (preserveSelection && currentValue !== '' && !matched.some((opt) => opt.value === currentValue)) {
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

                if (!preserveSelection && currentValue !== '' && !matched.some((opt) => opt.value === currentValue)) {
                    select.value = '';
                }

                if (expandOnSearch) {
                    const shouldExpand = matched.length > 0 && (q !== '' || comboMode);
                    if (shouldExpand) {
                        select.size = Math.min(matched.length + 1, 10);
                        select.classList.add('filterable-select--expanded');
                        if (comboMode) {
                            select.removeAttribute('aria-hidden');
                            select.tabIndex = 0;
                        }
                    } else {
                        collapseSelect();
                    }
                }
            };

            if (comboMode) {
                collapseSelect();
            }

            searchInput.addEventListener('input', () => rebuildOptions(searchInput.value));
            searchInput.addEventListener('focus', () => {
                rebuildOptions(searchInput.value);
            });
            searchInput.addEventListener('blur', () => {
                window.setTimeout(collapseSelect, 180);
            });

            select.addEventListener('change', () => {
                const selected = initialOptions.find((opt) => opt.value === select.value);
                if (selected && selected.text !== '') {
                    searchInput.value = selected.text;
                } else if (select.value === '') {
                    searchInput.value = '';
                }
                collapseSelect();
            });

            select.addEventListener('blur', () => {
                window.setTimeout(collapseSelect, 180);
            });

            select.addEventListener('mousedown', (event) => {
                if (comboMode && expandOnSearch) {
                    event.stopPropagation();
                }
            });

            const initiallySelected = initialOptions.find((opt) => opt.selected && opt.value !== '');
            if (initiallySelected && String(searchInput.value || '').trim() === '') {
                searchInput.value = initiallySelected.text;
            }

            if (String(searchInput.value || '').trim() !== '') {
                rebuildOptions(searchInput.value);
            }
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
</script>
