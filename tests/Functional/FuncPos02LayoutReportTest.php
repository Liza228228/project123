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
