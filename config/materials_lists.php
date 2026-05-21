<?php

/**
 * Пагинация: журнал операций по складу, таблицы остатков (учёт оборудования и обзор по складам).
 *
 * MATERIALS_JOURNAL_PER_PAGE — по умолчанию для журнала (должно входить в MATERIALS_JOURNAL_PER_PAGE_OPTIONS).
 * MATERIALS_BALANCES_PER_PAGE — по умолчанию для остатков (должно входить в MATERIALS_BALANCES_PER_PAGE_OPTIONS).
 * По умолчанию допустимые размеры: 10, 15, 20, 30, 50.
 */
$parse = static function (string $optionsEnv, string $defaultEnv, string $optionsCsvDefault, string $defaultInt): array {
    $allowedRaw = array_filter(array_map('trim', explode(',', (string) env($optionsEnv, $optionsCsvDefault))));
    $allowed = array_values(array_unique(array_filter(array_map(static fn (string $v): int => (int) $v, $allowedRaw), static fn (int $v): bool => $v > 0)));
    if ($allowed === []) {
        $allowed = array_values(array_unique(array_filter(array_map(static fn (string $v): int => (int) $v, array_map('trim', explode(',', $optionsCsvDefault))), static fn (int $v): bool => $v > 0)));
    }
    sort($allowed);

    $default = (int) env($defaultEnv, $defaultInt);
    if (! in_array($default, $allowed, true)) {
        $default = $allowed[0];
    }

    return [
        'default' => $default,
        'allowed' => $allowed,
    ];
};

return [
    'journal' => $parse('MATERIALS_JOURNAL_PER_PAGE_OPTIONS', 'MATERIALS_JOURNAL_PER_PAGE', '10,15,20,30,50', '20'),
    'balances' => $parse('MATERIALS_BALANCES_PER_PAGE_OPTIONS', 'MATERIALS_BALANCES_PER_PAGE', '10,15,20,30,50', '20'),
];
