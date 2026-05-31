<?php

// функциональный тест
use App\Models\RequestLayout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

uses(RefreshDatabase::class);

test('layout application store accepts checked use_current_date with hidden fallback field', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $chief = User::query()->create([
        'surname' => 'Тест',
        'name' => 'Начальник',
        'patronymic' => 'Тест',
        'email' => 'chief-date-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 7,
    ]);

    $layout = RequestLayout::query()->create([
        'title' => 'Тест даты',
        'schema' => [
            'fields' => [
                ['key' => 'поле', 'label' => 'Поле', 'type' => 'text'],
            ],
            'signature_slots_count' => 0,
        ],
        'has_header' => false,
        'type' => 'pdf',
        'version' => 1,
    ]);

    $response = $this->actingAs($chief)->post(route('boiler-chief.layout-applications.store'), [
        'layout_structure_id' => $layout->id,
        'use_current_date' => ['0', '1'],
        'values' => ['поле' => 'текст'],
    ]);

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});
