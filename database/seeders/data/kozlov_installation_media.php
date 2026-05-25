<?php

/**
 * Медиа для выполненных заявок Козлова (5 штук, по порядку в сидере).
 *
 * В git лежит зеркало storage/app/public:
 *   public/seeders/kozlov-installation/installation-acts/{slot}/…
 *   public/seeders/kozlov-installation/installation-act-photos/{slot}/…
 *
 * {slot} — 1…5 (первая выполненная заявка → 1, вторая → 2 и т.д.).
 * В БД после сидера пути как при загрузке: installation-acts/{id заявки}/…
 */
return [
    'act_filename' => 'zajavka-1 (11).pdf',

    'photo_filenames' => [
        'frisquet.jpg',
        '2трубы.png',
        '8818.jpg',
        '68.jpg',
        'отопление.jpg',
    ],
];
