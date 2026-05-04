<?php

use App\Models\RequestLayout;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('boiler chief can create installation act layout report as pdf', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $chief = User::query()->create([
        'surname' => 'Начальников',
        'name' => 'Начальник',
        'patronymic' => 'Начальник',
        'email' => 'nachalnik-'.uniqid('', true).'@nachalnik.local',
        'password' => Hash::make('password'),
        'role_id' => 7,
    ]);

    $signer = User::query()->create([
        'surname' => 'Тест',
        'name' => 'Тест',
        'patronymic' => 'Тест',
        'email' => 't-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 7,
    ]);

    $layout = RequestLayout::query()->create([
        'title' => 'Акт установки',
        'schema' => [
            'document_title' => 'Акт установки',
            'body_template' => 'Состояние: ',
            'fields' => [
                ['key' => 'status', 'label' => 'Статус установки', 'type' => 'text'],
            ],
            'signature_slots_count' => 1,
            'signature_roles' => [
                1 => 7,
            ],
        ],
        'has_header' => false,
        'type' => 'pdf',
        'version' => 1,
    ]);

    $response = $this->actingAs($chief)->post(route('boiler-chief.layout-applications.store'), [
        'layout_structure_id' => $layout->id,
        'values' => ['status' => 'Оборудование установлено.'],
        'signer_1_user_id' => $signer->id,
        'use_current_date' => '0',
        'form_document_date' => '2026-05-15',
    ]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('layout application pdf succeeds without signers when layout has zero signature slots', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $chief = User::query()->create([
        'surname' => 'Начальников2',
        'name' => 'Начальник',
        'patronymic' => 'Начальник',
        'email' => 'nachalnik2-'.uniqid('', true).'@nachalnik.local',
        'password' => Hash::make('password'),
        'role_id' => 7,
    ]);

    $layout = RequestLayout::query()->create([
        'title' => 'Отчёт без подписей',
        'schema' => [
            'document_title' => 'Отчёт',
            'body_template' => 'Состояние: {{status}}',
            'fields' => [
                ['key' => 'status', 'label' => 'Статус', 'type' => 'text'],
            ],
            'signature_slots_count' => 0,
            'signature_roles' => [],
            'footer_left_template' => '',
            'signature_template' => '',
        ],
        'has_header' => false,
        'type' => 'pdf',
        'version' => 1,
    ]);

    $response = $this->actingAs($chief)->post(route('boiler-chief.layout-applications.store'), [
        'layout_structure_id' => $layout->id,
        'values' => ['status' => 'Готово'],
        'use_current_date' => '1',
    ]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
});

test('accountant can load layout schema json for report fillers', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $accountant = User::query()->create([
        'surname' => 'Бух',
        'name' => 'Галер',
        'patronymic' => 'Тест',
        'email' => 'buh-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 3,
    ]);

    $layout = RequestLayout::query()->create([
        'title' => 'Отчёт по заявкам',
        'schema' => [
            'document_title' => 'Отчёт',
            'body_template' => '{{pole}}',
            'fields' => [
                ['key' => 'pole', 'label' => 'Поле для бухгалтера', 'type' => 'text'],
            ],
            'signature_slots_count' => 0,
            'signature_roles' => [],
        ],
        'has_header' => false,
        'type' => 'pdf',
        'version' => 1,
    ]);

    $response = $this->actingAs($accountant)->getJson(
        route('applications.installation-act.layout-schema', $layout)
    );

    $response->assertOk();
    $response->assertJsonPath('fields.0.key', 'pole');
    $response->assertJsonPath('fields.0.label', 'Поле для бухгалтера');
});

test('accountant layout application create embeds same rich form as chief', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $accountant = User::query()->create([
        'surname' => 'Бух2',
        'name' => 'Тест',
        'patronymic' => 'Тест',
        'email' => 'buh2-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 3,
    ]);

    RequestLayout::query()->create([
        'title' => 'Макет встроенный',
        'schema' => [
            'document_title' => 'Док',
            'body_template' => '{{k}}',
            'fields' => [
                ['key' => 'k', 'label' => 'Ключевое поле', 'type' => 'textarea'],
            ],
            'signature_slots_count' => 0,
            'signature_roles' => [],
        ],
        'has_header' => false,
        'type' => 'pdf',
        'version' => 1,
    ]);

    $response = $this->actingAs($accountant)->get(route('boiler-chief.layout-applications.create'));

    $response->assertOk();
    $html = $response->getContent();
    expect($html)->toContain('layoutApplicationCreate');
    expect($html)->toContain('layoutSchemasById');
    expect($html)->toContain('Оборудование из заявки');
    expect($html)->toContain('\u0022key\u0022:\u0022k\u0022');
});
