<?php

// функциональный тест
use App\Models\RequestLayout;
use App\Models\RequestSubmission;
use App\Models\User;
use App\Support\RequestLayoutDocumentBuilder;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

test('equipment insert html table in text field is preserved in pdf body', function (): void {
    $layout = RequestLayout::query()->create([
        'title' => 'Отчет',
        'schema' => [
            'document_title' => 'Отчет',
            'body_template' => '{{equipment_block}}',
            'fields' => [
                ['key' => 'equipment_block', 'label' => 'Оборудование', 'type' => 'text'],
            ],
            'signature_slots_count' => 0,
            'signature_roles' => [],
        ],
        'has_header' => false,
        'type' => 'pdf',
        'version' => 1,
    ]);

    $tableHtml = '<table border="1"><thead><tr><th>Наименование</th><th>Количество</th></tr></thead>'
        .'<tbody><tr><td colspan="2"><strong>Заявка №42</strong></td></tr>'
        .'<tr><td>Компенсатор</td><td>7 шт</td></tr></tbody></table>';

    $builder = app(RequestLayoutDocumentBuilder::class);
    $parts = $builder->pdfParts($layout, ['equipment_block' => $tableHtml]);
    $bodyHtml = $builder->bodyHtmlForPdf($parts['bodyText']);

    expect($parts['bodyText'])->toContain('<table');
    expect($bodyHtml)->toContain('<table');
    expect($bodyHtml)->toContain('Компенсатор');
    expect($bodyHtml)->not->toContain('НаименованиеКоличество');
});

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
            'category' => 'installation-act',
            'allow_application_equipment_insert' => true,
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

test('site foreman fill uses same rich report form', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $foreman = User::query()->create([
        'surname' => 'Мастер',
        'name' => 'Участка',
        'patronymic' => 'Тест',
        'email' => 'foreman-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 1,
    ]);

    $layout = RequestLayout::query()->create([
        'title' => 'Отчёт по заявкам',
        'schema' => [
            'document_title' => 'Отчёт',
            'body_template' => '{{pole}}',
            'fields' => [
                ['key' => 'pole', 'label' => 'Поле', 'type' => 'textarea'],
            ],
            'signature_slots_count' => 0,
            'signature_roles' => [],
        ],
        'has_header' => false,
        'type' => 'pdf',
        'version' => 1,
    ]);

    $response = $this->actingAs($foreman)->get(route('applications.installation-act.layout-fill.fill', $layout));

    $response->assertOk();
    $html = $response->getContent();
    expect($html)->toContain('layoutApplicationCreate');
    expect($html)->toContain('Оборудование из заявки');
    expect($html)->toContain('Дата формирования');
});

test('site foreman can download filled pdf from rich form', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $foreman = User::query()->create([
        'surname' => 'Мастер2',
        'name' => 'Участка',
        'patronymic' => 'Тест',
        'email' => 'foreman2-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 1,
    ]);

    $signer = User::query()->create([
        'surname' => 'Подписант',
        'name' => 'Тест',
        'patronymic' => 'Тест',
        'email' => 'sign-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 7,
    ]);

    $layout = RequestLayout::query()->create([
        'title' => 'Отчёт PDF',
        'schema' => [
            'document_title' => 'Отчёт',
            'body_template' => 'Текст: {{pole}}',
            'fields' => [
                ['key' => 'pole', 'label' => 'Поле', 'type' => 'textarea'],
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

    $response = $this->actingAs($foreman)->post(route('applications.installation-act.layout-fill.pdf', $layout), [
        'values' => ['pole' => 'Готово'],
        'signer_1_user_id' => $signer->id,
        'use_current_date' => '1',
    ]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    $saved = RequestSubmission::query()->where('created_by', $foreman->id)->latest('id')->first();
    expect($saved)->not->toBeNull();
    expect((int) $saved->layout_structure_id)->toBe((int) $layout->id);
});

test('site foreman can view own saved pdf list and reopen pdf', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $foreman = User::query()->create([
        'surname' => 'Мастер3',
        'name' => 'Участка',
        'patronymic' => 'Тест',
        'email' => 'foreman3-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 1,
    ]);
    $other = User::query()->create([
        'surname' => 'Другой',
        'name' => 'Пользователь',
        'patronymic' => 'Тест',
        'email' => 'other-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 1,
    ]);
    $layout = RequestLayout::query()->create([
        'title' => 'Отчёт архив',
        'schema' => [
            'document_title' => 'Отчёт',
            'body_template' => '{{pole}}',
            'fields' => [
                ['key' => 'pole', 'label' => 'Поле', 'type' => 'textarea'],
            ],
            'signature_slots_count' => 0,
            'signature_roles' => [],
        ],
        'has_header' => false,
        'type' => 'pdf',
        'version' => 1,
    ]);
    $ownSubmission = RequestSubmission::query()->create([
        'data' => ['pole' => 'Свой отчет', '_document_date' => now()->format('d.m.Y')],
        'created_by' => $foreman->id,
        'layout_structure_id' => $layout->id,
    ]);
    $otherSubmission = RequestSubmission::query()->create([
        'data' => ['pole' => 'Чужой отчет'],
        'created_by' => $other->id,
        'layout_structure_id' => $layout->id,
    ]);

    $index = $this->actingAs($foreman)->get(route('applications.installation-act.layout-fill.index'));
    $index->assertOk();
    $index->assertSee('Просмотр созданных отчетов');

    $submissionsPage = $this->actingAs($foreman)->get(route('applications.installation-act.layout-fill.submissions'));
    $submissionsPage->assertOk();
    $submissionsPage->assertSee('№'.$ownSubmission->id.' · '.$layout->title);
    $submissionsPage->assertDontSee('№'.$otherSubmission->id.' · '.$layout->title);

    $pdf = $this->actingAs($foreman)->get(route('applications.installation-act.layout-fill.submission-pdf', $ownSubmission));
    $pdf->assertOk();
    expect($pdf->headers->get('Content-Type'))->toContain('application/pdf');

    $forbidden = $this->actingAs($foreman)->get(route('applications.installation-act.layout-fill.submission-pdf', $otherSubmission));
    $forbidden->assertForbidden();
});

test('layout applications index shows only submissions created by current user', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $foreman = User::query()->create([
        'surname' => 'Козлов',
        'name' => 'Алексей',
        'patronymic' => 'Тест',
        'email' => 'foreman-layout-apps-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 4,
    ]);
    $other = User::query()->create([
        'surname' => 'Другой',
        'name' => 'Мастер',
        'patronymic' => 'Тест',
        'email' => 'other-foreman-layout-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 4,
    ]);
    $layout = RequestLayout::query()->create([
        'title' => 'Акт установки оборудования',
        'schema' => [
            'document_title' => 'Акт',
            'body_template' => '{{pole}}',
            'fields' => [
                ['key' => 'pole', 'label' => 'Поле', 'type' => 'textarea'],
            ],
            'signature_slots_count' => 0,
            'signature_roles' => [],
        ],
        'has_header' => false,
        'type' => 'pdf',
        'version' => 1,
    ]);
    $ownSubmission = RequestSubmission::query()->create([
        'data' => ['pole' => 'Свой отчет'],
        'created_by' => $foreman->id,
        'layout_structure_id' => $layout->id,
    ]);
    $otherSubmission = RequestSubmission::query()->create([
        'data' => ['pole' => 'Чужой отчет'],
        'created_by' => $other->id,
        'layout_structure_id' => $layout->id,
    ]);

    $index = $this->actingAs($foreman)->get(route('boiler-chief.layout-applications.index'));
    $index->assertOk();
    $index->assertSee('Акт установки оборудования', false);
    $index->assertSee($foreman->fullName(), false);
    $index->assertDontSee($other->fullName(), false);

    $forbiddenPdf = $this->actingAs($foreman)->get(route('boiler-chief.layout-applications.pdf', $otherSubmission));
    $forbiddenPdf->assertForbidden();

    $forbiddenEdit = $this->actingAs($foreman)->get(route('boiler-chief.layout-applications.edit', $otherSubmission));
    $forbiddenEdit->assertForbidden();

    expect($ownSubmission->id)->not->toBe($otherSubmission->id);
});

test('user can delete own layout application report even when layout was soft deleted', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $foreman = User::query()->create([
        'surname' => 'Удаляторов',
        'name' => 'Мастер',
        'patronymic' => 'Тест',
        'email' => 'foreman-layout-delete-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 4,
    ]);

    $activeLayout = RequestLayout::query()->create([
        'title' => 'Акт для удаления',
        'schema' => [
            'document_title' => 'Акт',
            'body_template' => '{{pole}}',
            'fields' => [
                ['key' => 'pole', 'label' => 'Поле', 'type' => 'textarea'],
            ],
            'signature_slots_count' => 0,
            'signature_roles' => [],
        ],
        'has_header' => false,
        'type' => 'pdf',
        'version' => 1,
    ]);
    $deletedLayout = RequestLayout::query()->create([
        'title' => 'Устаревший макет',
        'schema' => [
            'document_title' => 'Акт',
            'body_template' => '{{pole}}',
            'fields' => [
                ['key' => 'pole', 'label' => 'Поле', 'type' => 'textarea'],
            ],
            'signature_slots_count' => 0,
            'signature_roles' => [],
        ],
        'has_header' => false,
        'type' => 'pdf',
        'version' => 1,
    ]);
    $deletedLayout->delete();

    $activeSubmission = RequestSubmission::query()->create([
        'data' => ['pole' => 'Активный отчет'],
        'created_by' => $foreman->id,
        'layout_structure_id' => $activeLayout->id,
    ]);
    $orphanedSubmission = RequestSubmission::query()->create([
        'data' => ['pole' => 'Отчет по удаленному макету'],
        'created_by' => $foreman->id,
        'layout_structure_id' => $deletedLayout->id,
    ]);

    $deleteActive = $this->actingAs($foreman)->delete(
        route('boiler-chief.layout-applications.destroy', $activeSubmission)
    );
    $deleteActive->assertRedirect(route('boiler-chief.layout-applications.index'));
    $deleteActive->assertSessionHas('status');
    expect(RequestSubmission::query()->find($activeSubmission->id))->toBeNull();

    $deleteOrphaned = $this->actingAs($foreman)->delete(
        route('boiler-chief.layout-applications.destroy', $orphanedSubmission)
    );
    $deleteOrphaned->assertRedirect(route('boiler-chief.layout-applications.index'));
    expect(RequestSubmission::query()->find($orphanedSubmission->id))->toBeNull();
});

test('boiler chief can open edit form and update layout submission pdf', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $chief = User::query()->create([
        'surname' => 'Редакторов',
        'name' => 'Редактор',
        'patronymic' => 'Редактор',
        'email' => 'editor-chief-'.uniqid('', true).'@nachalnik.local',
        'password' => Hash::make('password'),
        'role_id' => 7,
    ]);

    $signer = User::query()->create([
        'surname' => 'Подписант',
        'name' => 'Подписант',
        'patronymic' => 'Подписант',
        'email' => 'sign-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 7,
    ]);

    $layout = RequestLayout::query()->create([
        'title' => 'Отчет для правки',
        'schema' => [
            'document_title' => 'Док',
            'body_template' => 'Текст: {{status}}',
            'fields' => [
                ['key' => 'status', 'label' => 'Статус', 'type' => 'text'],
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

    $submission = RequestSubmission::query()->create([
        'data' => [
            'status' => 'Старое значение',
            'signer_1_user_id' => $signer->id,
            '_document_date' => '01.01.2026',
        ],
        'created_by' => $chief->id,
        'layout_structure_id' => $layout->id,
    ]);

    $edit = $this->actingAs($chief)->get(route('boiler-chief.layout-applications.edit', $submission));
    $edit->assertOk();
    $edit->assertSee('Редактирование отчета');

    $response = $this->actingAs($chief)->put(route('boiler-chief.layout-applications.update', $submission), [
        'values' => ['status' => 'Новое значение'],
        'signer_1_user_id' => $signer->id,
        'use_current_date' => '1',
    ]);

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');

    $submission->refresh();
    expect($submission->data['status'] ?? '')->toBe('Новое значение');
});
