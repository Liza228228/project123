<?php

/**
 * Пагинация списков: подразделения/склады, мастера участка, начальники котельных.
 *
 * ASSIGNMENT_LIST_PER_PAGE — значение по умолчанию (должно входить в список допустимых).
 * ASSIGNMENT_LIST_PER_PAGE_OPTIONS — допустимые размеры страницы через запятую.
 */
$allowedRaw = array_filter(array_map('trim', explode(',', (string) env('ASSIGNMENT_LIST_PER_PAGE_OPTIONS', '10,25,50'))));
$allowed = array_values(array_unique(array_filter(array_map(static fn (string $v): int => (int) $v, $allowedRaw), static fn (int $v): bool => $v > 0)));
if ($allowed === []) {
    $allowed = [10, 25, 50];
}
sort($allowed);

$default = (int) env('ASSIGNMENT_LIST_PER_PAGE', (string) $allowed[0]);
if (! in_array($default, $allowed, true)) {
    $default = $allowed[0];
}

return [
    'per_page' => [
        'default' => $default,
        'allowed' => $allowed,
    ],
];
