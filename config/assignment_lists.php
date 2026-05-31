<?php
// свой код проекта
$allowedRaw = array_filter(array_map('trim', explode(',', (string) env('ASSIGNMENT_LIST_PER_PAGE_OPTIONS', '10,15,20,30,50'))));
$allowed = array_values(array_unique(array_filter(array_map(static fn (string $v): int => (int) $v, $allowedRaw), static fn (int $v): bool => $v > 0)));
if ($allowed === []) {
    $allowed = [10, 15, 20, 30, 50];
}
sort($allowed);

$default = (int) env('ASSIGNMENT_LIST_PER_PAGE', '20');
if (! in_array($default, $allowed, true)) {
    $default = $allowed[0];
}

return [
    'per_page' => [
        'default' => $default,
        'allowed' => $allowed,
    ],
];
