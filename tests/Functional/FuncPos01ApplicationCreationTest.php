<?php

use App\Models\Application;
use App\Models\ApplicationStatus;
use Tests\Support\FunctionalScenarioFixture;

test('create form shows draft save buttons for site foreman', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КВ-100');

    $response = $this->actingAs($ctx['foreman'])
        ->get(route('applications.create'));

    $response->assertOk()
        ->assertSee('Сохранить', false)
        ->assertSee('Отправить на согласование ', false)
        ->assertSee('name="submit_action"', false)
        ->assertSee('value="save"', false);
});

test('site foreman can create application with valid data', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КВ-100');

    $response = $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'submit_action' => 'save',
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_id' => $ctx['equipment']->id,
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
    ]);

    $response->assertRedirect(route('applications.index'));
    $response->assertSessionHas('status');
    expect(Application::query()->count())->toBe(1);
    $app = Application::query()->first();
    expect($app)->not->toBeNull();
    expect($app->application_status_id)->toBe(ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING));
});

test('foreman can save draft and submit to boiler chief when chief assigned', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КВ-200');

    $chief = \App\Models\User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Котельный',
        'patronymic' => 'Тест',
        'email' => 'chief-draft-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'submit_action' => 'save',
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_id' => $ctx['equipment']->id,
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
    ])->assertRedirect(route('applications.index'));

    $app = Application::query()->first();
    expect($app->isForemanDraftBeforeBoilerChief())->toBeTrue();

    $this->actingAs($ctx['foreman'])->get(route('applications.show', $app))
        ->assertOk()
        ->assertSee('Отправить на согласование', false)
        ->assertSee('data-app-open-modal="confirm-submit-boiler-chief"', false)
        ->assertSee('id="submit-to-boiler-chief-form"', false)
        ->assertSee('form="submit-to-boiler-chief-form"', false);

    $this->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app))
        ->assertRedirect(route('applications.show', $app));

    $app->refresh();
    expect($app->application_status_id)->toBe(ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING));
    expect($app->needsBoilerChiefReviewBeforeManagement())->toBeTrue();

    $this->actingAs($ctx['foreman'])->get(route('applications.edit', $app))
        ->assertForbidden();
});

test('boiler chief can save draft and submit for management approval', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КВ-300');

    $chief = \App\Models\User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Котельный',
        'patronymic' => 'Черновик',
        'email' => 'chief-mgmt-draft-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    $management = \App\Models\User::query()->create([
        'surname' => 'Тех',
        'name' => 'Директор',
        'patronymic' => 'Тест',
        'email' => 'td-mgmt-draft-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 6,
    ]);

    $this->actingAs($chief)->post(route('applications.store'), [
        'submit_action' => 'save',
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_id' => $ctx['equipment']->id,
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
    ])->assertRedirect(route('applications.index'));

    $app = Application::query()->where('user_id', $chief->id)->first();
    expect($app)->not->toBeNull();
    expect($app->isBoilerChiefDraftBeforeManagement())->toBeTrue();

    $this->actingAs($management)->get(route('applications.show', $app))
        ->assertForbidden();

    $this->actingAs($chief)->post(route('applications.submit-for-management', $app))
        ->assertRedirect(route('applications.show', $app));

    $app->refresh();
    expect($app->isBoilerChiefDraftBeforeManagement())->toBeFalse();
    expect($app->application_status_id)->toBe(ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING));

    $this->actingAs($management)->get(route('applications.show', $app))
        ->assertOk();

    $this->actingAs($chief)->get(route('applications.edit', $app))
        ->assertForbidden();
});

test('boiler chief submits foreman application to management after boiler approval', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КВ-400');

    $chief = \App\Models\User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Котельный',
        'patronymic' => 'Форман',
        'email' => 'chief-foreman-submit-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    $management = \App\Models\User::query()->create([
        'surname' => 'Снаб',
        'name' => 'Начальник',
        'patronymic' => 'Тест',
        'email' => 'supply-foreman-submit-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 2,
    ]);

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'submit_action' => 'save',
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_id' => $ctx['equipment']->id,
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
    ])->assertRedirect(route('applications.index'));

    $app = Application::query()->first();
    $this->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app))
        ->assertRedirect(route('applications.show', $app));

    $app->refresh();
    $itemId = $app->items()->value('id');
    expect($itemId)->not->toBeNull();

    $this->actingAs($chief)->post(route('applications.boiler-chief-approval', $app), [
        'boiler_items' => [
            (string) $itemId => ['is_checked' => '1'],
        ],
    ])->assertRedirect(route('applications.show', $app));

    $app->refresh();
    expect($app->needsBoilerChiefReviewBeforeManagement())->toBeFalse();
    expect($app->boilerChiefCanSubmitToManagement())->toBeTrue();
    expect($app->boilerChiefReleasedToManagement())->toBeFalse();
    expect($app->isVisibleToManagementEditors())->toBeFalse();

    $this->actingAs($management)->get(route('applications.index'))
        ->assertOk()
        ->assertDontSee('applications/'.$app->id, false);

    $this->actingAs($management)->get(route('applications.show', $app))
        ->assertForbidden();

    $this->actingAs($chief)->get(route('applications.show', $app))
        ->assertOk()
        ->assertSee('Отправить на согласование', false)
        ->assertSee('data-app-open-modal="confirm-submit-for-management"', false);

    $this->actingAs($chief)->post(route('applications.submit-for-management', $app))
        ->assertRedirect(route('applications.show', $app));

    $app->refresh();
    expect($app->boilerChiefReleasedToManagement())->toBeTrue();
    expect($app->approved_by_user_id)->toBe($chief->id);
    expect($app->boilerChiefCanSubmitToManagement())->toBeFalse();

    $this->actingAs($management)->get(route('applications.index'))
        ->assertOk()
        ->assertSee('applications/'.$app->id, false);

    $this->actingAs($management)->get(route('applications.show', $app))
        ->assertOk();

    $this->actingAs($management)->post(route('applications.approval', $app), [
        'items' => [
            (string) $itemId => ['is_checked' => '1'],
        ],
    ])->assertRedirect(route('applications.show', $app));

    $app->refresh();
    expect($app->managementHasSavedApproval())->toBeTrue();

    $this->actingAs($management)->get(route('applications.show', $app))
        ->assertOk()
        ->assertDontSee(route('applications.edit', $app), false);

    $this->actingAs($management)->get(route('applications.edit', $app))
        ->assertForbidden();

    $this->actingAs($chief)->get(route('applications.edit', $app))
        ->assertForbidden();
});

test('management creator application skips boiler chief and is open for management approval', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КВ-500');

    $chief = \App\Models\User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Котельный',
        'patronymic' => 'Менеджмент',
        'email' => 'chief-mgmt-skip-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    $supplyHead = \App\Models\User::query()->create([
        'surname' => 'Снаб',
        'name' => 'Начальник',
        'patronymic' => 'Создатель',
        'email' => 'supply-mgmt-skip-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 2,
    ]);

    $this->actingAs($supplyHead)->post(route('applications.store'), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_id' => $ctx['equipment']->id,
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
    ])->assertRedirect(route('applications.index'));

    $app = Application::query()->where('user_id', $supplyHead->id)->first();
    expect($app)->not->toBeNull();
    expect($app->needsBoilerChiefReviewBeforeManagement())->toBeFalse();
    expect($app->managementMayReviewAfterBoilerChief())->toBeTrue();
    expect($app->awaitsManagementEquipmentApproval())->toBeTrue();

    $this->actingAs($chief)->get(route('applications.show', $app))
        ->assertOk()
        ->assertDontSee('id="boiler-chief-approval-form"', false);

    $this->actingAs($supplyHead)->get(route('applications.show', $app))
        ->assertOk()
        ->assertSee('id="approval-form"', false);

    $atBoilerChiefIds = Application::query()
        ->select('applications.id')
        ->tap(fn ($q) => \App\Support\ApplicationApprovalListingFilter::apply($q, \App\Support\ApplicationApprovalListingFilter::KEY_AT_BOILER_CHIEF))
        ->pluck('id')
        ->all();
    expect($atBoilerChiefIds)->not->toContain($app->id);

    $atManagementIds = Application::query()
        ->select('applications.id')
        ->tap(fn ($q) => \App\Support\ApplicationApprovalListingFilter::apply($q, \App\Support\ApplicationApprovalListingFilter::KEY_AT_MANAGEMENT))
        ->pluck('id')
        ->all();
    expect($atManagementIds)->toContain($app->id);
});

test('application update rejects equipment name longer than limit with russian message', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл валидация');

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_id' => $ctx['equipment']->id,
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
    ])->assertRedirect(route('applications.index'));

    $app = Application::query()->first();
    expect($app)->not->toBeNull();

    $longName = str_repeat('А', \App\Models\ApplicationItem::EQUIPMENT_NAME_MAX_LENGTH + 1);

    $this->actingAs($ctx['foreman'])
        ->from(route('applications.edit', $app))
        ->put(route('applications.update', $app), [
            'subdivision_id' => $ctx['subdivision']->id,
            'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
            'items' => [
                [
                    'equipment_id' => '',
                    'equipment_name' => $longName,
                    'quantity' => 1,
                    'measurement_type' => 'piece',
                    'quantity_unit' => 'шт',
                ],
            ],
        ])
        ->assertSessionHasErrors('items.0.equipment_name')
        ->assertRedirect(route('applications.edit', $app));

    expect(session('errors')->first('items.0.equipment_name'))
        ->toContain((string) \App\Models\ApplicationItem::EQUIPMENT_NAME_MAX_LENGTH);
});

test('application store rejects duplicate equipment from catalog and custom line', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Хлебников ГСМ и зап.части');

    $response = $this->actingAs($ctx['foreman'])->from(route('applications.create'))->post(route('applications.store'), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_id' => $ctx['equipment']->id,
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
            [
                'equipment_name' => 'Хлебников ГСМ и зап.части',
                'quantity' => 2,
                'measurement_type' => 'mass',
                'quantity_unit' => 'кг',
            ],
        ],
    ]);

    $response->assertSessionHasErrors('equipment');
    expect(Application::query()->count())->toBe(0);
});
