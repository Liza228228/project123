<?php

// функциональный тест
use App\Models\Application;
use App\Models\ApplicationItem;
use App\Models\ApplicationStatus;
use App\Models\User;
use App\Support\ApplicationIndexPresenter;
use Illuminate\Support\Facades\Hash;
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
    expect($app->foremanMayEditWithoutChangeReasons())->toBeTrue();

    $itemId = (int) $app->items()->value('id');
    $newDeliveryDate = now()->addDays(12)->format('Y-m-d');
    $this->actingAs($ctx['foreman'])->put(route('applications.update', $app), [
        'submit_action' => 'save',
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => $newDeliveryDate,
        'items' => [
            [
                'item_id' => $itemId,
                'equipment_id' => $ctx['equipment']->id,
                'quantity' => 3,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
    ])->assertRedirect(route('applications.index'));

    $app->refresh();
    expect($app->desired_delivery_date?->format('Y-m-d'))->toBe($newDeliveryDate);
    expect((int) $app->items()->find($itemId)?->quantity)->toBe(3);
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

    ApplicationIndexPresenter::prepare(
        new \Illuminate\Pagination\LengthAwarePaginator([$app], 1, 15, 1),
        $chief
    );
    expect($app->index_list_status)->toBe('boiler');
    expect($app->index_stage_key)->toBe('boiler');
    expect($app->index_approval_key)->toBeNull();

    $pendingIds = Application::query()
        ->select('applications.id')
        ->tap(fn ($q) => \App\Support\ApplicationApprovalListingFilter::apply($q, \App\Support\ApplicationApprovalListingFilter::KEY_PENDING))
        ->pluck('id')
        ->all();
    expect($pendingIds)->not->toContain($app->id);

    $this->actingAs($ctx['foreman'])->get(route('applications.edit', $app))
        ->assertForbidden();
});

test('submit to approval from applications index stays on list with notification', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл отправка-список');

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
    $indexUrl = route('applications.index');

    $this->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app), [
        '_return_url' => $indexUrl,
    ])
        ->assertRedirect($indexUrl)
        ->assertSessionHas('status', 'Заявка отправлена на согласование. Редактирование больше недоступно.');

    $this->actingAs($ctx['foreman'])->get($indexUrl)
        ->assertOk()
        ->assertSee('Заявка отправлена на согласование', false);
});

test('foreman can submit draft application with commercial offer only', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КП-отправка');

    $chief = \App\Models\User::query()->create([
        'surname' => 'Начальник',
        'name' => 'КП',
        'patronymic' => 'Тест',
        'email' => 'chief-kp-submit-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    \Illuminate\Support\Facades\Storage::fake('public');

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'submit_action' => 'save',
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_id' => '',
                'equipment_name' => '',
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
        'commercial_offer' => \Illuminate\Http\UploadedFile::fake()->create('kp.pdf', 100, 'application/pdf'),
    ])->assertRedirect(route('applications.index'));

    $app = Application::query()->first();
    expect($app)->not->toBeNull();
    expect($app->hasCommercialOfferAttached())->toBeTrue();
    expect($app->items)->toHaveCount(0);

    $this->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app))
        ->assertRedirect(route('applications.show', $app));

    expect($app->fresh()->application_status_id)->toBe(ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING));
});

test('boiler chief can approve commercial offer only application and release to management', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КП-согласование');

    $chief = \App\Models\User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Соглас',
        'patronymic' => 'КП',
        'email' => 'chief-kp-approve-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    \Illuminate\Support\Facades\Storage::fake('public');

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'submit_action' => 'save',
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_id' => '',
                'equipment_name' => '',
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
        'commercial_offer' => \Illuminate\Http\UploadedFile::fake()->create('kp.pdf', 100, 'application/pdf'),
    ]);

    $app = Application::query()->first();
    $this->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app))
        ->assertRedirect(route('applications.show', $app));

    $this->actingAs($chief)->post(route('applications.boiler-chief-approval', $app), [
        'commercial_offer_chief_is_checked' => '1',
    ])->assertRedirect(route('applications.show', $app));

    $app->refresh();
    expect($app->commercial_offer_chief_is_checked)->toBeTrue();
    expect($app->approved_by_user_id)->toBe($chief->id);
    expect($app->needsBoilerChiefReviewBeforeManagement())->toBeFalse();
    expect($app->needsManagementCommercialOfferReview())->toBeTrue();

    $director = \App\Models\User::query()->create([
        'surname' => 'Директор',
        'name' => 'КП',
        'patronymic' => 'Тест',
        'email' => 'director-kp-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 1,
    ]);

    $this->actingAs($director)->post(route('applications.approval', $app), [
        'commercial_offer_management_is_checked' => '1',
    ])->assertRedirect(route('applications.show', $app));

    $app->refresh();
    expect($app->commercial_offer_management_is_checked)->toBeTrue();
    expect($app->management_supply_items_saved_at)->not->toBeNull();
    expect($app->isStatusApproved())->toBeTrue();

    CommercialOfferApplicationLines::persistForApplication((int) $app->id, [
        [
            'equipment_name' => 'вапрвапр',
            'quantity' => 45,
            'quantity_unit' => 'шт',
            'measurement_type' => 'piece',
        ],
    ]);

    $this->actingAs($director)
        ->get(route('applications.show', $app))
        ->assertOk()
        ->assertSee('Данные подставлены из таблицы коммерческого предложения', false);

    expect(CommercialOfferApplicationLines::linesForOrderFormPrefill($app->fresh()))
        ->toHaveCount(1)
        ->and(CommercialOfferApplicationLines::linesForOrderFormPrefill($app->fresh())[0]['equipment_name'])
        ->toBe('вапрвапр');

    $this->actingAs($director)
        ->post(route('applications.commercial-offer-order-lines.store', $app), [
            'items' => [
                [
                    'equipment_name' => 'Насос циркуляционный',
                    'measurement_type' => 'piece',
                    'quantity' => 2,
                    'quantity_unit' => 'шт',
                ],
            ],
        ])
        ->assertRedirect(route('applications.show', $app))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('status');

    $this->assertDatabaseHas('application_items', [
        'application_id' => $app->id,
        'is_checked' => true,
        'custom_equipment_supply_status_id' => \App\Models\ApplicationItem::CUSTOM_SUPPLY_ACCEPTED_ID,
    ]);

    $customItem = \App\Models\ApplicationItem::query()
        ->where('application_id', $app->id)
        ->whereNull('equipment_id')
        ->where('is_checked', true)
        ->latest('id')
        ->first();
    expect($customItem)->not->toBeNull();
    expect($customItem->is_checked)->toBeTrue();
    expect($customItem->isOrderedFromCommercialOffer())->toBeTrue();
    expect($customItem->custom_equipment_supply_status_id)->toBe(\App\Models\ApplicationItem::CUSTOM_SUPPLY_ACCEPTED_ID);

    $this->actingAs($director)
        ->get(route('applications.show', $app))
        ->assertOk()
        ->assertSee('Заказано по КП', false);
    expect($customItem->canMarkCustomSupplyOrdered())->toBeTrue();
    expect($customItem->equipment_display_name)->not->toBe('—');
});

test('boiler chief can approve commercial offer together with equipment items', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КП+позиции');

    $chief = \App\Models\User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Смеш',
        'patronymic' => 'КП',
        'email' => 'chief-kp-items-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    \Illuminate\Support\Facades\Storage::fake('public');

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'submit_action' => 'save',
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_id' => $ctx['equipment']->id,
                'quantity' => 2,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
        'commercial_offer' => \Illuminate\Http\UploadedFile::fake()->create('kp-mixed.pdf', 100, 'application/pdf'),
    ])->assertRedirect(route('applications.index'));

    $app = Application::query()->first();
    expect($app->items)->toHaveCount(1);
    expect($app->hasCommercialOfferAttached())->toBeTrue();
    expect($app->isCommercialOfferOnlyApplication())->toBeFalse();

    $this->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app))
        ->assertRedirect(route('applications.show', $app));

    $itemId = $app->items()->value('id');

    $this->actingAs($chief)->get(route('applications.show', $app))
        ->assertOk()
        ->assertSee('Согласовать коммерческое предложение', false)
        ->assertSee('id="boiler-chief-approval-form"', false);

    $this->actingAs($chief)->post(route('applications.boiler-chief-approval', $app), [
        'commercial_offer_chief_is_checked' => '1',
        'boiler_items' => [
            (string) $itemId => ['is_checked' => '1'],
        ],
    ])->assertRedirect(route('applications.show', $app));

    $app->refresh();
    expect($app->commercial_offer_chief_is_checked)->toBeTrue();
    expect($app->needsBoilerChiefReviewBeforeManagement())->toBeFalse();
    expect($app->boilerChiefCanSubmitToManagement())->toBeTrue();
    expect($app->boilerChiefReleasedToManagement())->toBeFalse();
});

test('management can approve commercial offer together with equipment items', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КП+снабжение');

    $chief = \App\Models\User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Котельный',
        'patronymic' => 'КП',
        'email' => 'chief-mgmt-co-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    $management = \App\Models\User::query()->create([
        'surname' => 'Снаб',
        'name' => 'КП',
        'patronymic' => 'Позиции',
        'email' => 'supply-mgmt-co-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 2,
    ]);

    \Illuminate\Support\Facades\Storage::fake('public');

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
        'commercial_offer' => \Illuminate\Http\UploadedFile::fake()->create('kp-mgmt-mixed.pdf', 100, 'application/pdf'),
    ]);

    $app = Application::query()->first();
    $itemId = $app->items()->value('id');

    $this->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app));
    $this->actingAs($chief)->post(route('applications.boiler-chief-approval', $app), [
        'commercial_offer_chief_is_checked' => '1',
        'boiler_items' => [(string) $itemId => ['is_checked' => '1']],
    ]);
    $this->actingAs($chief)->post(route('applications.submit-for-management', $app));

    $app->refresh();
    expect($app->needsManagementCommercialOfferReview())->toBeTrue();

    $this->actingAs($management)->get(route('applications.show', $app))
        ->assertOk()
        ->assertSee('Согласовать коммерческое предложение', false)
        ->assertSee('id="approval-form"', false);

    $this->actingAs($management)->post(route('applications.approval', $app), [
        'commercial_offer_management_is_checked' => '1',
        'items' => [
            (string) $itemId => ['is_checked' => '1'],
        ],
    ])->assertRedirect(route('applications.show', $app));

    $app->refresh();
    expect($app->commercial_offer_management_is_checked)->toBeTrue();
    expect($app->needsManagementCommercialOfferReview())->toBeFalse();
    expect($app->managementHasSavedApproval())->toBeTrue();
    expect($app->management_supply_items_saved_at)->not->toBeNull();
});

test('boiler chief must provide reason when rejecting commercial offer only application', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КП-отказ');

    $chief = \App\Models\User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Отказ',
        'patronymic' => 'КП',
        'email' => 'chief-kp-reject-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    \Illuminate\Support\Facades\Storage::fake('public');

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'submit_action' => 'save',
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_id' => '',
                'equipment_name' => '',
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
        'commercial_offer' => \Illuminate\Http\UploadedFile::fake()->create('kp.pdf', 100, 'application/pdf'),
    ])->assertRedirect(route('applications.index'));

    $app = Application::query()->first();
    expect($app)->not->toBeNull();

    $this->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app))
        ->assertRedirect(route('applications.show', $app));

    $this->actingAs($chief)->post(route('applications.boiler-chief-approval', $app), [
        'commercial_offer_chief_is_checked' => '0',
        'commercial_offer_chief_reason_not_selected' => 'Сумма завышена',
    ])->assertRedirect(route('applications.show', $app));

    $app->refresh();
    expect($app->commercial_offer_chief_is_checked)->toBeFalse();
    expect($app->commercial_offer_chief_reason_not_selected)->toBe('Сумма завышена');
    expect($app->approved_by_user_id)->toBeNull();
    expect($app->isStatusRejected())->toBeTrue();
    expect($app->commercialOfferShowsAsRejected())->toBeTrue();

    $this->actingAs($chief)->get(route('applications.show', $app))
        ->assertOk()
        ->assertSee('Коммерческое предложение', false)
        ->assertSee('Сумма завышена', false);
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
        'responsible_user_id' => $ctx['foreman']->id,
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
    expect((int) $app->responsible_user_id)->toBe((int) $ctx['foreman']->id);
    expect($app->isBoilerChiefDraftBeforeManagement())->toBeTrue();
    expect($app->needsBoilerChiefReviewBeforeManagement())->toBeFalse();

    $this->actingAs($chief)->get(route('applications.show', $app))
        ->assertOk()
        ->assertSee('Отправить на согласование', false)
        ->assertDontSee('id="boiler-chief-approval-form"', false)
        ->assertDontSee('>Не согласовано<', false);

    $this->actingAs($ctx['foreman'])->get(route('applications.show', $app))
        ->assertOk()
        ->assertDontSee('>Изменить<', false);

    $this->actingAs($ctx['foreman'])->get(route('applications.edit', $app))
        ->assertForbidden();

    expect($app->foremanCanEditApplication())->toBeFalse();

    $this->actingAs($management)->get(route('applications.show', $app))
        ->assertForbidden();

    $this->actingAs($chief)->post(route('applications.submit-for-management', $app))
        ->assertRedirect(route('applications.show', $app));

    $app->refresh();
    expect($app->isBoilerChiefDraftBeforeManagement())->toBeFalse();
    expect($app->application_status_id)->toBe(ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING));
    expect($app->needsBoilerChiefReviewBeforeManagement())->toBeFalse();
    expect((int) $app->approved_by_user_id)->toBe((int) $chief->id);

    $this->actingAs($management)->get(route('applications.show', $app))
        ->assertOk()
        ->assertDontSee('Ожидается согласование начальником котельной', false);

    $this->actingAs($chief)->get(route('applications.edit', $app))
        ->assertForbidden();
});

test('management roles cannot create applications', function (int $roleId): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КВ-запрет-создания-'.$roleId);

    $managementUser = \App\Models\User::query()->create([
        'surname' => 'Руководство',
        'name' => 'Создатель',
        'patronymic' => 'Запрет',
        'email' => 'mgmt-no-create-'.$roleId.'-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => $roleId,
    ]);

    $this->actingAs($managementUser)->get(route('applications.create'))->assertForbidden();

    $this->actingAs($managementUser)->post(route('applications.store'), [
        'subdivision_id' => $ctx['subdivision']->id,
        'responsible_user_id' => $ctx['foreman']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_id' => $ctx['equipment']->id,
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
    ])->assertForbidden();

    expect(Application::query()->where('user_id', $managementUser->id)->exists())->toBeFalse();
})->with(\App\Models\User::APPLICATION_SUPPLY_WORKFLOW_ROLE_IDS);

test('boiler chief cannot assign responsible foreman from another subdivision', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КВ-ответственный');

    $otherSubdivision = \App\Models\Subdivision::query()->create(['name' => 'Другое подразделение ответственный']);
    $otherForeman = \App\Models\User::query()->create([
        'surname' => 'Чужой',
        'name' => 'Мастер',
        'patronymic' => 'Тест',
        'email' => 'other-foreman-resp-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 4,
    ]);
    $otherForeman->assignedSubdivisions()->sync([$otherSubdivision->id]);

    $chief = \App\Models\User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Проверка',
        'patronymic' => 'Ответственный',
        'email' => 'chief-resp-block-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    $this->actingAs($chief)->from(route('applications.create'))->post(route('applications.store'), [
        'submit_action' => 'save',
        'subdivision_id' => $ctx['subdivision']->id,
        'responsible_user_id' => $otherForeman->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_id' => $ctx['equipment']->id,
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
    ])->assertRedirect(route('applications.create'))
        ->assertSessionHasErrors('responsible_user_id');

    expect(Application::query()->where('user_id', $chief->id)->exists())->toBeFalse();
});

test('foreman can revise items rejected by boiler chief and resubmit without releasing to management', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КВ-ревизия');

    $pieceUnitId = (int) \App\Models\MeasurementUnit::query()
        ->where('code', 'шт')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'piece'))
        ->value('id');
    $equipment2 = \App\Models\Equipment::query()->create([
        'name' => 'Насос НР-ревизия',
        'value' => null,
        'measurement_unit_id' => $pieceUnitId,
        'is_catalog' => true,
    ]);
    $equipment3 = \App\Models\Equipment::query()->create([
        'name' => 'Клапан КР-ревизия',
        'value' => null,
        'measurement_unit_id' => $pieceUnitId,
        'is_catalog' => true,
    ]);
    $mainWarehouse = FunctionalScenarioFixture::primaryAdministrationWarehouse();
    $receiptTypeId = (int) \App\Models\MaterialStockMovementType::idFor(\App\Models\MaterialStockMovementType::NAME_RECEIPT);
    foreach ([$ctx['equipment'], $equipment2, $equipment3] as $equipment) {
        \App\Models\MaterialStockMovement::query()->create([
            'equipment_id' => $equipment->id,
            'warehouse_id' => $mainWarehouse->id,
            'material_stock_movement_type_id' => $receiptTypeId,
            'quantity' => 50,
        ]);
    }

    $chief = \App\Models\User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Ревизия',
        'patronymic' => 'Котельный',
        'email' => 'chief-revise-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    $management = \App\Models\User::query()->create([
        'surname' => 'Снаб',
        'name' => 'Ревизия',
        'patronymic' => 'Тест',
        'email' => 'supply-revise-'.uniqid('', true).'@test.local',
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
            [
                'equipment_id' => $equipment2->id,
                'quantity' => 2,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
            [
                'equipment_id' => $equipment3->id,
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
    ])->assertRedirect(route('applications.index'));

    $app = Application::query()->where('user_id', $ctx['foreman']->id)->first();
    expect($app)->not->toBeNull();
    $this->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app))
        ->assertRedirect(route('applications.show', $app));

    $app->refresh()->load('items');
    expect($app->items)->toHaveCount(3);
    $sortedItems = $app->items->sortBy('id')->values();
    $approvedItemId = (int) $sortedItems[0]->id;
    $modifiedRejectedItemId = (int) $sortedItems[1]->id;
    $unchangedRejectedItemId = (int) $sortedItems[2]->id;

    $this->actingAs($chief)->post(route('applications.boiler-chief-approval', $app), [
        'boiler_items' => [
            (string) $approvedItemId => ['is_checked' => '1'],
            (string) $modifiedRejectedItemId => [
                'is_checked' => '0',
                'reason_not_selected' => 'Не подходит марка',
            ],
            (string) $unchangedRejectedItemId => [
                'is_checked' => '0',
                'reason_not_selected' => 'Оставляем без изменений',
            ],
        ],
    ])->assertRedirect(route('applications.show', $app));

    $app->refresh();
    expect($app->needsBoilerChiefReviewBeforeManagement())->toBeFalse();
    expect($app->boilerChiefReleasedToManagement())->toBeFalse();
    expect($app->foremanCanReviseAfterBoilerChiefRejection())->toBeTrue();
    expect($app->foremanCanEditApplication())->toBeTrue();
    expect($app->foremanCanResubmitAwaitingItemsToBoilerChief())->toBeFalse();
    expect($app->isForemanDraftAfterBoilerChiefBeforeManagement())->toBeTrue();

    $this->actingAs($chief)->get(route('applications.index'))
        ->assertOk()
        ->assertSee('applications/'.$app->id, false);

    $atBoilerChiefIds = Application::query()
        ->select('applications.id')
        ->tap(fn ($q) => \App\Support\ApplicationApprovalListingFilter::apply($q, \App\Support\ApplicationApprovalListingFilter::KEY_AT_BOILER_CHIEF))
        ->pluck('id')
        ->all();
    expect($atBoilerChiefIds)->toContain($app->id);

    $this->actingAs($management)->get(route('applications.show', $app))
        ->assertForbidden();

    $this->actingAs($ctx['foreman'])->get(route('applications.edit', $app))
        ->assertOk()
        ->assertSee('не согласовал часть позиций', false);

    $newDeliveryDate = now()->addDays(10)->format('Y-m-d');
    $this->actingAs($ctx['foreman'])->put(route('applications.update', $app), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => $newDeliveryDate,
        'field_change_reasons' => [
            'desired_delivery_date' => 'Срок по замечанию котельной.',
        ],
        'item_change_reasons' => [
            $modifiedRejectedItemId => 'Уточнили количество после отказа котельной.',
        ],
        'items' => [
            [
                'item_id' => $approvedItemId,
                'equipment_id' => $sortedItems[0]->equipment_id ?? '',
                'equipment_name' => $sortedItems[0]->equipment_name ?? '',
                'quantity' => (int) $sortedItems[0]->quantity,
                'measurement_type' => $sortedItems[0]->measurement_type ?? 'piece',
                'quantity_unit' => $sortedItems[0]->quantity_unit ?? 'шт',
                'size_value' => $sortedItems[0]->size_value ?? '',
            ],
            [
                'item_id' => $modifiedRejectedItemId,
                'equipment_id' => $sortedItems[1]->equipment_id ?? '',
                'equipment_name' => $sortedItems[1]->equipment_name ?? '',
                'quantity' => (int) $sortedItems[1]->quantity + 1,
                'measurement_type' => $sortedItems[1]->measurement_type ?? 'piece',
                'quantity_unit' => $sortedItems[1]->quantity_unit ?? 'шт',
                'size_value' => $sortedItems[1]->size_value ?? '',
            ],
            [
                'item_id' => $unchangedRejectedItemId,
                'equipment_id' => $sortedItems[2]->equipment_id ?? '',
                'equipment_name' => $sortedItems[2]->equipment_name ?? '',
                'quantity' => (int) $sortedItems[2]->quantity,
                'measurement_type' => $sortedItems[2]->measurement_type ?? 'piece',
                'quantity_unit' => $sortedItems[2]->quantity_unit ?? 'шт',
                'size_value' => $sortedItems[2]->size_value ?? '',
            ],
        ],
    ])->assertRedirect(route('applications.edit', $app));

    $app->refresh();
    expect($app->desired_delivery_date?->format('Y-m-d'))->toBe($newDeliveryDate);
    expect($app->items()->find($modifiedRejectedItemId)?->quantity)->toBe((int) $sortedItems[1]->quantity + 1);
    expect($app->itemAwaitingBoilerChiefReview($app->items()->find($modifiedRejectedItemId)))->toBeTrue();
    expect($app->itemIsRejectedByBoilerChief($app->items()->find($unchangedRejectedItemId)))->toBeTrue();
    expect($app->foremanCanResubmitAwaitingItemsToBoilerChief())->toBeTrue();

    $this->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app))
        ->assertRedirect(route('applications.show', $app));

    $app->refresh();
    expect($app->needsBoilerChiefReviewBeforeManagement())->toBeTrue();
    expect($app->foremanCanReviseAfterBoilerChiefRejection())->toBeFalse();
    expect($app->foremanCanEditApplication())->toBeFalse();
    expect($app->boilerChiefReleasedToManagement())->toBeFalse();
    expect(trim((string) $app->items()->find($modifiedRejectedItemId)?->reason_not_selected))->toBe('');
    expect($app->items()->find($unchangedRejectedItemId)?->reason_not_selected)->toBe('Оставляем без изменений');
    expect($app->itemsAwaitingBoilerChiefReview())->toHaveCount(1);

    $this->actingAs($chief)->get(route('applications.show', $app))
        ->assertOk()
        ->assertSee('id="boiler-chief-approval-form"', false)
        ->assertSee((string) $app->items()->find($modifiedRejectedItemId)->equipment_display_name, false)
        ->assertDontSee((string) $app->items()->find($unchangedRejectedItemId)->equipment_display_name, false);
});

test('foreman fixing the only rejected line auto resubmits to boiler chief', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КВ-одна-ревизия');
    $pieceUnitId = (int) \App\Models\MeasurementUnit::query()
        ->where('code', 'шт')
        ->whereHas('unitType', fn ($q) => $q->where('code', 'piece'))
        ->value('id');
    $equipment2 = \App\Models\Equipment::query()->create([
        'name' => 'Опора подвижная одна-ревизия',
        'value' => null,
        'measurement_unit_id' => $pieceUnitId,
        'is_catalog' => true,
    ]);

    $chief = \App\Models\User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Одна',
        'patronymic' => 'Ревизия',
        'email' => 'chief-one-revise-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 7,
    ]);
    $ctx['subdivision']->users()->attach($chief->id);

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'submit_action' => 'save',
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_id' => $ctx['equipment']->id,
                'quantity' => 5,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
            [
                'equipment_id' => $equipment2->id,
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
    ])->assertRedirect(route('applications.index'));

    $app = Application::query()->where('user_id', $ctx['foreman']->id)->latest('id')->first();
    expect($app)->not->toBeNull();
    $this->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app))
        ->assertRedirect(route('applications.show', $app));

    $app->refresh()->load('items');
    $sortedItems = $app->items->sortBy('id')->values();
    $approvedItemId = (int) $sortedItems[0]->id;
    $rejectedItemId = (int) $sortedItems[1]->id;

    $this->actingAs($chief)->post(route('applications.boiler-chief-approval', $app), [
        'boiler_items' => [
            (string) $approvedItemId => ['is_checked' => '1'],
            (string) $rejectedItemId => [
                'is_checked' => '0',
                'reason_not_selected' => 'Слишком мало',
            ],
        ],
    ])->assertRedirect(route('applications.show', $app));

    $app->refresh();
    expect($app->foremanCanReviseAfterBoilerChiefRejection())->toBeTrue();

    $rejectedItem = $app->items()->find($rejectedItemId);
    $this->actingAs($ctx['foreman'])->put(route('applications.update', $app), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => $app->desired_delivery_date?->format('Y-m-d'),
        'items' => [
            [
                'item_id' => $approvedItemId,
                'equipment_id' => $sortedItems[0]->equipment_id,
                'quantity' => (int) $sortedItems[0]->quantity,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
            [
                'item_id' => $rejectedItemId,
                'equipment_id' => $rejectedItem->equipment_id,
                'catalog_label' => $rejectedItem->catalogEquipmentName(),
                'quantity' => 200,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
    ])->assertRedirect(route('applications.show', $app))
        ->assertSessionHas('status');

    $app->refresh();
    expect($app->items()->find($rejectedItemId)?->quantity)->toBe(200);
    expect($app->itemAwaitingBoilerChiefReview($app->items()->find($rejectedItemId)))->toBeTrue();
    expect($app->foremanSubmittedAwaitingItemsForBoilerChiefReview())->toBeTrue();
    expect($app->foremanCanReviseAfterBoilerChiefRejection())->toBeFalse();

    $this->actingAs($chief)->get(route('applications.show', $app))
        ->assertOk()
        ->assertSee('id="boiler-chief-approval-form"', false)
        ->assertSee((string) $app->items()->find($rejectedItemId)->equipment_display_name, false);
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
    expect($app->application_status_id)->toBe(ApplicationStatus::idForDraft());
    expect($app->isForemanDraftAfterBoilerChiefBeforeManagement())->toBeTrue();
    expect($app->isWorkflowDraftForDisplay())->toBeTrue();
    expect($app->needsBoilerChiefReviewBeforeManagement())->toBeFalse();
    expect($app->boilerChiefCanSubmitToManagement())->toBeTrue();
    expect($app->boilerChiefReleasedToManagement())->toBeFalse();
    expect($app->isVisibleToManagementEditors())->toBeFalse();

    ApplicationIndexPresenter::prepare(
        new \Illuminate\Pagination\LengthAwarePaginator([$app], 1, 15, 1),
        $chief
    );
    expect($app->index_list_status)->toBe('draft');

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
    expect($app->application_status_id)->toBe(ApplicationStatus::idFor(ApplicationStatus::NAME_PENDING));
    expect($app->boilerChiefReleasedToManagement())->toBeTrue();
    expect($app->approved_by_user_id)->toBe($chief->id);
    expect($app->boilerChiefCanSubmitToManagement())->toBeFalse();
    expect($app->isPendingManagementReview())->toBeTrue();

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

    $pendingIds = Application::query()
        ->select('applications.id')
        ->tap(fn ($q) => \App\Support\ApplicationApprovalListingFilter::apply($q, \App\Support\ApplicationApprovalListingFilter::KEY_PENDING))
        ->pluck('id')
        ->all();
    expect($pendingIds)->toContain($app->id);

    $this->actingAs($chief)->get(route('applications.index', ['archive' => 'active']))
        ->assertOk()
        ->assertSee('applications/'.$app->id, false);

    ApplicationIndexPresenter::prepare(
        new \Illuminate\Pagination\LengthAwarePaginator([$app], 1, 15, 1),
        $management
    );
    expect($app->index_stage_key)->toBe('management');
    expect($app->index_approval_key)->toBe('pending');
    expect($app->index_list_status)->toBe('management');

    $this->actingAs($management)->get(route('applications.index'))
        ->assertOk()
        ->assertSee('applications/'.$app->id, false);

    $app->refresh();
    expect($app->managementCanEditApplication())->toBeTrue();

    $this->actingAs($management)->get(route('applications.show', $app))
        ->assertOk()
        ->assertSee(route('applications.edit', $app), false);

    $this->actingAs($management)->post(route('applications.approval', $app), [
        'items' => [
            (string) $itemId => ['is_checked' => '1'],
        ],
    ])->assertRedirect(route('applications.show', $app));

    $app->refresh();
    expect($app->managementHasSavedApproval())->toBeTrue();
    expect($app->managementCanEditApplication())->toBeFalse();

    $this->actingAs($management)->get(route('applications.show', $app))
        ->assertOk()
        ->assertDontSee(route('applications.edit', $app), false);

    $this->actingAs($management)->get(route('applications.edit', $app))
        ->assertForbidden();

    $this->actingAs($chief)->get(route('applications.edit', $app))
        ->assertForbidden();
});

test('approved catalog application is highlighted for management until marked in transit', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл transit-highlight');

    $mainWarehouse = \App\Support\AdministrationWarehouse::resolvePrimaryWarehouse();
    expect($mainWarehouse)->not->toBeNull();

    \App\Models\MaterialStockMovement::query()->where('equipment_id', $ctx['equipment']->id)->delete();
    \App\Models\MaterialStockMovement::query()->create([
        'equipment_id' => $ctx['equipment']->id,
        'warehouse_id' => (int) $mainWarehouse->id,
        'material_stock_movement_type_id' => \App\Models\MaterialStockMovementType::idFor(\App\Models\MaterialStockMovementType::NAME_RECEIPT),
        'quantity' => 100,
    ]);

    $chief = \App\Models\User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Транзит',
        'patronymic' => 'Подсветка',
        'email' => 'chief-transit-highlight-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    $management = \App\Models\User::query()->create([
        'surname' => 'Снаб',
        'name' => 'Транзит',
        'patronymic' => 'Подсветка',
        'email' => 'supply-transit-highlight-'.uniqid('', true).'@test.local',
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
    $itemId = (int) $app->items()->value('id');
    expect($itemId)->toBeGreaterThan(0);
    expect((int) $app->items()->value('equipment_id'))->toBe((int) $ctx['equipment']->id);

    $this->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app))
        ->assertRedirect(route('applications.show', $app));
    $this->actingAs($chief)->post(route('applications.boiler-chief-approval', $app), [
        'boiler_items' => [(string) $itemId => ['is_checked' => '1']],
    ])->assertRedirect(route('applications.show', $app));
    $this->actingAs($chief)->post(route('applications.submit-for-management', $app))
        ->assertRedirect(route('applications.show', $app));
    $this->actingAs($management)->post(route('applications.approval', $app), [
        'items' => [(string) $itemId => ['is_checked' => '1']],
    ])->assertRedirect(route('applications.show', $app));

    $app->refresh()->load('items');
    $item = $app->items->firstWhere('id', $itemId);
    expect($item)->not->toBeNull();
    expect($item->equipment_id)->toBe($ctx['equipment']->id);
    expect($app->managementHasSavedApproval())->toBeTrue();
    expect($item->canMarkDeliveryInTransit())->toBeTrue();
    expect($app->needsCatalogDeliveryInTransit())->toBeTrue();

    ApplicationIndexPresenter::prepare(
        new \Illuminate\Pagination\LengthAwarePaginator([$app], 1, 15, 1),
        $management
    );
    expect($app->index_needs_delivery_in_transit)->toBeTrue();
    expect($app->index_fulfillment_key)->toBe('needs_delivery_in_transit');

    $needsTransitFilterKey = \App\Support\ApplicationApprovalListingFilter::KEY_NEEDS_DELIVERY_IN_TRANSIT;
    expect(\App\Support\ApplicationApprovalListingFilter::optionGroupsForUser($management)['Исполнение'])
        ->toHaveKey($needsTransitFilterKey);
    expect(\App\Support\ApplicationApprovalListingFilter::optionGroupsForUser($ctx['foreman'])['Исполнение'] ?? [])
        ->not->toHaveKey($needsTransitFilterKey);

    $needsTransitIds = Application::query()
        ->select('applications.id')
        ->tap(fn ($q) => \App\Support\ApplicationApprovalListingFilter::apply($q, $needsTransitFilterKey, $management))
        ->pluck('id')
        ->all();
    expect($needsTransitIds)->toContain($app->id);

    ApplicationIndexPresenter::prepare(
        new \Illuminate\Pagination\LengthAwarePaginator([$app], 1, 15, 1),
        $ctx['foreman']
    );
    expect($app->index_needs_delivery_in_transit)->toBeFalse();

    $app->items()->whereKey($itemId)->update([
        'delivery_status_id' => \App\Models\ApplicationItem::DELIVERY_IN_TRANSIT_ID,
    ]);

    $app->refresh()->load('items');
    expect($app->needsCatalogDeliveryInTransit())->toBeFalse();
    expect($app->isApprovedDeliveryFullyInTransit())->toBeTrue();

    $needsTransitIdsAfter = Application::query()
        ->select('applications.id')
        ->tap(fn ($q) => \App\Support\ApplicationApprovalListingFilter::apply($q, $needsTransitFilterKey, $management))
        ->pluck('id')
        ->all();
    expect($needsTransitIdsAfter)->not->toContain($app->id);

    ApplicationIndexPresenter::prepare(
        new \Illuminate\Pagination\LengthAwarePaginator([$app], 1, 15, 1),
        $management
    );
    expect($app->index_needs_delivery_in_transit)->toBeFalse();
    expect($app->index_fulfillment_key)->toBe('in_transit');
});

test('partial position approval filter matches applications with partly approved items', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл partial-filter');

    $equipmentTwo = \App\Models\Equipment::query()->create([
        'name' => 'Фильтр частичное-'.uniqid('', true),
        'measurement_unit_id' => $ctx['equipment']->measurement_unit_id,
    ]);

    $chief = \App\Models\User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Частичный',
        'patronymic' => 'Фильтр',
        'email' => 'chief-partial-filter-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    $management = \App\Models\User::query()->create([
        'surname' => 'Снаб',
        'name' => 'Частичный',
        'patronymic' => 'Фильтр',
        'email' => 'supply-partial-filter-'.uniqid('', true).'@test.local',
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
            [
                'equipment_id' => $equipmentTwo->id,
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
    ])->assertRedirect(route('applications.index'));

    $app = Application::query()->latest('id')->first();
    $itemIds = $app->items()->orderBy('id')->pluck('id')->all();
    expect($itemIds)->toHaveCount(2);

    $this->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app));
    $this->actingAs($chief)->post(route('applications.boiler-chief-approval', $app), [
        'boiler_items' => [
            (string) $itemIds[0] => ['is_checked' => '1'],
            (string) $itemIds[1] => ['is_checked' => '1'],
        ],
    ]);
    $this->actingAs($chief)->post(route('applications.submit-for-management', $app));

    $app->refresh();
    $this->actingAs($management)->post(route('applications.approval', $app), [
        'items' => [
            (string) $itemIds[0] => ['is_checked' => '1'],
            (string) $itemIds[1] => ['is_checked' => '0', 'reason_not_selected' => 'Не требуется по объекту'],
        ],
    ])->assertRedirect(route('applications.show', $app));

    $app->refresh()->load('items');
    ApplicationIndexPresenter::prepare(
        new \Illuminate\Pagination\LengthAwarePaginator([$app], 1, 15, 1),
        $management
    );
    expect($app->index_approval_key)->toBe('partial');

    $partialIds = Application::query()
        ->select('applications.id')
        ->tap(fn ($q) => \App\Support\ApplicationApprovalListingFilter::apply($q, \App\Support\ApplicationApprovalListingFilter::KEY_PARTIAL))
        ->pluck('id')
        ->all();
    expect($partialIds)->toContain($app->id);
});

test('partial at management shows mixed badge and matches partial filter before management saves', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл partial-at-mgmt');

    $equipmentTwo = \App\Models\Equipment::query()->create([
        'name' => 'Фильтр partial-at-mgmt-'.uniqid('', true),
        'measurement_unit_id' => $ctx['equipment']->measurement_unit_id,
    ]);

    $chief = \App\Models\User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Частичный',
        'patronymic' => 'УРуководства',
        'email' => 'chief-partial-at-mgmt-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    $management = \App\Models\User::query()->create([
        'surname' => 'Снаб',
        'name' => 'Частичный',
        'patronymic' => 'УРуководства',
        'email' => 'supply-partial-at-mgmt-'.uniqid('', true).'@test.local',
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
            [
                'equipment_id' => $equipmentTwo->id,
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
    ])->assertRedirect(route('applications.index'));

    $app = Application::query()->latest('id')->first();
    $itemIds = $app->items()->orderBy('id')->pluck('id')->all();

    $this->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app));
    $this->actingAs($chief)->post(route('applications.boiler-chief-approval', $app), [
        'boiler_items' => [
            (string) $itemIds[0] => ['is_checked' => '1'],
            (string) $itemIds[1] => ['is_checked' => '0', 'reason_not_selected' => 'Не требуется по объекту'],
        ],
    ]);
    $this->actingAs($chief)->post(route('applications.submit-for-management', $app));

    $app->refresh()->load('items');
    expect($app->isPendingManagementReview())->toBeTrue();
    expect(\App\Support\ApplicationApprovalListingFilter::hasMixedItemApproval($app))->toBeTrue();

    ApplicationIndexPresenter::prepare(
        new \Illuminate\Pagination\LengthAwarePaginator([$app], 1, 15, 1),
        $management
    );
    expect($app->index_stage_key)->toBe('management');
    expect($app->index_approval_key)->toBe('partial');

    $atManagementIds = Application::query()
        ->select('applications.id')
        ->tap(fn ($q) => \App\Support\ApplicationApprovalListingFilter::apply($q, \App\Support\ApplicationApprovalListingFilter::KEY_AT_MANAGEMENT))
        ->pluck('id')
        ->all();
    expect($atManagementIds)->toContain($app->id);

    $partialIds = Application::query()
        ->select('applications.id')
        ->tap(fn ($q) => \App\Support\ApplicationApprovalListingFilter::apply($q, \App\Support\ApplicationApprovalListingFilter::KEY_PARTIAL))
        ->pluck('id')
        ->all();
    expect($partialIds)->toContain($app->id);
});

test('management edit of released application requires manual approval without boiler chief re-review', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КВ-mgmt-edit');

    $chief = \App\Models\User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Котельный',
        'patronymic' => 'Редакт',
        'email' => 'chief-mgmt-edit-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    $management = \App\Models\User::query()->create([
        'surname' => 'Снаб',
        'name' => 'Редакт',
        'patronymic' => 'Тест',
        'email' => 'supply-mgmt-edit-'.uniqid('', true).'@test.local',
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
    ]);

    $app = Application::query()->first();
    $this->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app));
    $itemId = (int) $app->items()->value('id');

    $this->actingAs($chief)->post(route('applications.boiler-chief-approval', $app), [
        'boiler_items' => [
            (string) $itemId => ['is_checked' => '1'],
        ],
    ]);
    $this->actingAs($chief)->post(route('applications.submit-for-management', $app));

    $app->refresh()->load('items');
    $item = $app->items->first();

    $this->actingAs($management)->get(route('applications.edit', $app))
        ->assertOk();

    $newQuantity = (int) $item->quantity + 2;
    $this->actingAs($management)->put(route('applications.update', $app), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(14)->format('Y-m-d'),
        'field_change_reasons' => [
            'desired_delivery_date' => 'Срок поставки скорректирован снабжением.',
        ],
        'item_change_reasons' => [
            $itemId => 'Корректировка количества.',
        ],
        'items' => [
            [
                'item_id' => $itemId,
                'equipment_id' => $item->equipment_id,
                'equipment_name' => $item->equipment_name ?? '',
                'quantity' => $newQuantity,
                'measurement_type' => $item->measurement_type ?? 'piece',
                'quantity_unit' => $item->quantity_unit ?? 'шт',
                'size_value' => $item->size_value ?? '',
            ],
        ],
    ])->assertRedirect(route('applications.show', $app).'#approval-form');

    $app->refresh();
    expect($app->needsBoilerChiefReviewBeforeManagement())->toBeFalse();
    expect($app->boilerChiefReleasedToManagement())->toBeTrue();
    expect($app->managementHasSavedApproval())->toBeFalse();
    expect($app->approved_by_user_id)->toBe($chief->id);
    expect($app->items()->find($itemId)?->is_checked)->toBeFalse();
    expect($app->items()->find($itemId)?->quantity)->toBe($newQuantity);

    expect($app->managementCanEditApplication())->toBeTrue();

    $this->actingAs($management)->get(route('applications.show', $app))
        ->assertOk()
        ->assertSee(route('applications.edit', $app), false)
        ->assertSee('id="approval-form"', false)
        ->assertSee('approval-item-checkbox', false)
        ->assertDontSee('не в согласовании ', false)
        ->assertDontSee('У начальника котельной', false);

    $this->actingAs($management)->post(route('applications.approval', $app), [
        'items' => [
            (string) $itemId => ['is_checked' => '1'],
        ],
    ])->assertRedirect(route('applications.show', $app));

    $app->refresh();
    expect($app->managementHasSavedApproval())->toBeTrue();
    expect($app->items()->find($itemId)?->is_checked)->toBeTrue();

    $this->actingAs($management)->get(route('applications.edit', $app))
        ->assertForbidden();
});

test('subdivision cannot be changed on application edit', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КВ-fixed-subdivision');

    $otherSubdivision = \App\Models\Subdivision::query()->create([
        'name' => 'Другое подразделение редактирования',
    ]);
    $ctx['foreman']->assignedSubdivisions()->sync([$ctx['subdivision']->id, $otherSubdivision->id]);

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

    $app = Application::query()->firstOrFail();

    $this->actingAs($ctx['foreman'])
        ->from(route('applications.edit', $app))
        ->put(route('applications.update', $app), [
            'subdivision_id' => $otherSubdivision->id,
            'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
            'items' => [
                [
                    'item_id' => (int) $app->items()->value('id'),
                    'equipment_id' => $ctx['equipment']->id,
                    'quantity' => 1,
                    'measurement_type' => 'piece',
                    'quantity_unit' => 'шт',
                    'size_value' => '',
                ],
            ],
        ])
        ->assertRedirect(route('applications.edit', $app))
        ->assertSessionHasErrors('subdivision_id');

    $app->refresh();
    expect((int) $app->subdivision_id)->toBe((int) $ctx['subdivision']->id);
});

test('management rejection of all equipment lines marks application rejected and skips supply handoff', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КВ-отклонение');

    $chief = \App\Models\User::query()->create([
        'surname' => 'Начальник',
        'name' => 'Отклон',
        'patronymic' => 'Все',
        'email' => 'chief-reject-all-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 7,
    ]);
    $chief->boilerChiefSubdivisions()->sync([$ctx['subdivision']->id]);

    $management = \App\Models\User::query()->create([
        'surname' => 'Снаб',
        'name' => 'Отклон',
        'patronymic' => 'Все',
        'email' => 'supply-reject-all-'.uniqid('', true).'@test.local',
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
    ]);

    $app = Application::query()->first();
    $this->actingAs($ctx['foreman'])->post(route('applications.submit-to-boiler-chief', $app));
    $itemId = $app->items()->value('id');

    $this->actingAs($chief)->post(route('applications.boiler-chief-approval', $app), [
        'boiler_items' => [
            (string) $itemId => ['is_checked' => '1'],
        ],
    ]);

    $this->actingAs($chief)->post(route('applications.submit-for-management', $app));

    $app->refresh();
    $this->actingAs($management)->post(route('applications.approval', $app), [
        'items' => [
            (string) $itemId => [
                'is_checked' => '0',
                'reason_not_selected' => '345',
            ],
        ],
    ])->assertRedirect(route('applications.show', $app));

    $app->refresh();
    expect($app->application_status_id)->toBe(ApplicationStatus::idFor(ApplicationStatus::NAME_REJECTED));
    expect($app->isStatusRejected())->toBeTrue();
    expect($app->isPendingManagementReview())->toBeFalse();
    expect($app->managementHasSavedApproval())->toBeTrue();
    expect($app->management_supply_items_saved_at)->toBeNull();

    ApplicationIndexPresenter::prepare(
        new \Illuminate\Pagination\LengthAwarePaginator([$app], 1, 1, 1),
        $management
    );
    expect($app->index_list_status)->toBe('rejected');
    expect($app->index_needs_submit)->toBeFalse();
    expect($app->needsSubmitToApprovalBy($management))->toBeFalse();
    expect($app->needsSubmitToApprovalBy($ctx['foreman']))->toBeFalse();
    expect($app->needsSubmitToApprovalBy($chief))->toBeFalse();

    $this->actingAs($management)->get(route('applications.index'))
        ->assertOk()
        ->assertDontSee('На согласование', false);

    $this->actingAs($ctx['foreman'])->get(route('applications.index', [
        'approval_filter' => 'rejected',
        'archive' => 'active',
    ]))
        ->assertOk()
        ->assertSee((string) $app->id, false);

    expect(\App\Support\ApplicationApprovalListingFilter::countWithFilter(
        Application::listingQuery(\Illuminate\Http\Request::create('/applications', 'GET', ['archive' => 'active']))
            ->tap(fn ($q) => $q->forSiteForemanAccess($ctx['foreman'])),
        \App\Support\ApplicationApprovalListingFilter::KEY_REJECTED,
        $ctx['foreman']
    ))->toBeGreaterThanOrEqual(1);
});

test('legacy management-delegated application with assigned foreman is supply-ready', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КВ-500');

    $supplyHead = \App\Models\User::query()->create([
        'surname' => 'Снаб',
        'name' => 'Начальник',
        'patronymic' => 'Создатель',
        'email' => 'supply-mgmt-skip-'.uniqid('', true).'@test.local',
        'password' => \Illuminate\Support\Facades\Hash::make('password'),
        'role_id' => 2,
    ]);

    $app = Application::query()->create([
        'user_id' => $supplyHead->id,
        'subdivision_id' => $ctx['subdivision']->id,
        'responsible_user_id' => $ctx['foreman']->id,
        'application_status_id' => ApplicationStatus::idFor(ApplicationStatus::NAME_APPROVED),
        'desired_delivery_date' => now()->addDays(7)->toDateString(),
        'approved_by_user_id' => $supplyHead->id,
        'management_supply_items_saved_at' => now(),
    ]);
    ApplicationItem::query()->create([
        'application_id' => $app->id,
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 1,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
        'is_checked' => true,
    ]);
    $app = $app->fresh(['items', 'user']);
    expect($app)->not->toBeNull();
    expect($app->isManagementDelegatedToSiteForeman())->toBeTrue();
    expect($app->needsBoilerChiefReviewBeforeManagement())->toBeFalse();
    expect($app->managementMayReviewAfterBoilerChief())->toBeFalse();
    expect($app->awaitsManagementEquipmentApproval())->toBeFalse();
    expect($app->isStatusApproved())->toBeTrue();
    expect($app->management_supply_items_saved_at)->not->toBeNull();
    expect($app->hasApprovedEquipmentLines())->toBeTrue();
    expect($app->isSupplyApprovedForCustomEquipmentWorkflow())->toBeTrue();
    expect($app->foremanCanEditApplication())->toBeFalse();

    $this->actingAs($ctx['foreman'])->get(route('applications.show', $app))
        ->assertOk()
        ->assertDontSee('Изменить', false);

    $this->actingAs($supplyHead)->get(route('applications.show', $app))
        ->assertOk()
        ->assertDontSee('id="approval-form"', false);

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
    expect($atManagementIds)->not->toContain($app->id);
});

test('application update rejects equipment name longer than limit with russian message', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл валидация');

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

test('application store allows same equipment name with different measurement types', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Хлебников ГСМ и зап.части');

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
            [
                'equipment_name' => 'Хлебников ГСМ и зап.части',
                'quantity' => 2,
                'measurement_type' => 'mass',
                'quantity_unit' => 'кг',
            ],
        ],
    ])->assertRedirect(route('applications.index'));

    expect(Application::query()->count())->toBe(1);
    expect(Application::query()->first()?->items)->toHaveCount(2);
});

test('application store rejects duplicate custom equipment with same measurement type', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл дубликат');

    $this->actingAs($ctx['foreman'])->from(route('applications.create'))->post(route('applications.store'), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [
            [
                'equipment_name' => 'Гвозди',
                'quantity' => 2,
                'measurement_type' => 'mass',
                'quantity_unit' => 'кг',
            ],
            [
                'equipment_name' => 'Гвозди',
                'quantity' => 1,
                'measurement_type' => 'mass',
                'quantity_unit' => 'кг',
            ],
        ],
    ])->assertSessionHasErrors('equipment');

    expect(Application::query()->count())->toBe(0);
});

test('commercial offer is stored with readable file name', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КП-имя');

    \Illuminate\Support\Facades\Storage::fake('public');

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
        'commercial_offer' => \Illuminate\Http\UploadedFile::fake()->create('Смета поставщика.pdf', 100, 'application/pdf'),
    ])->assertRedirect(route('applications.index'));

    $app = Application::query()->first();
    expect($app)->not->toBeNull();
    expect($app->commercial_offer)->not->toBeNull();
    expect(basename((string) $app->commercial_offer))->toBe('Смета поставщика.pdf');
    \Illuminate\Support\Facades\Storage::disk('public')->assertExists((string) $app->commercial_offer);
});

test('applications index filters by commercial offer attachment', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КП-фильтр');

    $itemPayload = [
        'equipment_id' => $ctx['equipment']->id,
        'quantity' => 1,
        'measurement_type' => 'piece',
        'quantity_unit' => 'шт',
    ];

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'submit_action' => 'save',
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(7)->format('Y-m-d'),
        'items' => [$itemPayload],
    ])->assertRedirect(route('applications.index'));

    $withKp = Application::query()->first();
    expect($withKp)->not->toBeNull();
    $withKp->update(['commercial_offer' => 'commercial-offers/kp-test.pdf']);

    $this->actingAs($ctx['foreman'])->post(route('applications.store'), [
        'submit_action' => 'save',
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(14)->format('Y-m-d'),
        'items' => [$itemPayload],
    ])->assertRedirect(route('applications.index'));

    $withoutKp = Application::query()->orderByDesc('id')->first();
    expect($withoutKp)->not->toBeNull();
    expect($withoutKp->id)->not->toBe($withKp->id);
    expect($withoutKp->commercial_offer)->toBeNull();

    $this->actingAs($ctx['foreman'])->get(route('applications.index', ['commercial_offer_filter' => 'with']))
        ->assertOk()
        ->assertSee('applications/'.$withKp->id, false)
        ->assertDontSee('applications/'.$withoutKp->id, false);

    $this->actingAs($ctx['foreman'])->get(route('applications.index', ['commercial_offer_filter' => 'without']))
        ->assertOk()
        ->assertSee('applications/'.$withoutKp->id, false)
        ->assertDontSee('applications/'.$withKp->id, false);

    $this->actingAs($ctx['foreman'])->get(route('applications.index', ['commercial_offer_filter' => 'all']))
        ->assertOk()
        ->assertSee('applications/'.$withKp->id, false)
        ->assertSee('applications/'.$withoutKp->id, false);
});

test('application edit can replace commercial offer when attached', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $ctx = FunctionalScenarioFixture::foremanCatalogStockContext('Котёл КП-редакт');

    \Illuminate\Support\Facades\Storage::fake('public');

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
        'commercial_offer' => \Illuminate\Http\UploadedFile::fake()->create('Старое КП.pdf', 100, 'application/pdf'),
    ])->assertRedirect(route('applications.index'));

    $application = Application::query()->first();
    expect($application)->not->toBeNull();
    $oldPath = (string) $application->commercial_offer;

    $this->actingAs($ctx['foreman'])
        ->get(route('applications.edit', $application))
        ->assertOk()
        ->assertSee('Коммерческое предложение', false)
        ->assertSee('Старое КП.pdf', false)
        ->assertSee('Заменить файлом', false);

    $this->actingAs($ctx['foreman'])->put(route('applications.update', $application), [
        'subdivision_id' => $ctx['subdivision']->id,
        'desired_delivery_date' => now()->addDays(8)->format('Y-m-d'),
        'field_change_reasons' => [
            'desired_delivery_date' => 'Смещаем дату поставки.',
        ],
        'items' => [
            [
                'item_id' => $application->items()->first()?->id,
                'equipment_id' => $ctx['equipment']->id,
                'quantity' => 1,
                'measurement_type' => 'piece',
                'quantity_unit' => 'шт',
            ],
        ],
        'commercial_offer' => \Illuminate\Http\UploadedFile::fake()->create('Новое КП.pdf', 100, 'application/pdf'),
    ])->assertRedirect();

    $application->refresh();
    expect(basename((string) $application->commercial_offer))->toBe('Новое КП.pdf');
    \Illuminate\Support\Facades\Storage::disk('public')->assertMissing($oldPath);
    \Illuminate\Support\Facades\Storage::disk('public')->assertExists((string) $application->commercial_offer);
});

test('director technical director and supply head share application workflow access', function (int $roleId): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $user = User::query()->create([
        'surname' => 'Руководство',
        'name' => 'Заявки',
        'patronymic' => 'Тест',
        'email' => 'app-workflow-'.$roleId.'-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => $roleId,
    ]);

    $this->actingAs($user)->get(route('applications.index'))->assertOk();
    $this->actingAs($user)->get(route('applications.create'))->assertForbidden();
    $this->actingAs($user)->get(route('applications.installation-act.upload'))->assertOk();
    if ($roleId === User::TECHNICAL_DIRECTOR_ROLE_ID) {
        $this->actingAs($user)->get(route('applications.custom-equipment-to-order'))->assertForbidden();
    } else {
        $this->actingAs($user)->get(route('applications.custom-equipment-to-order'))->assertOk();
    }
})->with(User::APPLICATION_SUPPLY_WORKFLOW_ROLE_IDS);
