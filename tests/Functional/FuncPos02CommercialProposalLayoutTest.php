<?php

use App\Models\RequestLayout;
use App\Models\User;
use App\Support\RequestLayoutDocumentBuilder;
use App\Support\RequestLayoutSignatureLine;
use Database\Seeders\CommercialProposalRequestLayoutSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Support\FunctionalScenarioFixture;

uses(RefreshDatabase::class);

test('commercial proposal layout is seeded without application equipment insert', function (): void {
    $this->seed(CommercialProposalRequestLayoutSeeder::class);

    $layout = RequestLayout::query()
        ->where('title', CommercialProposalRequestLayoutSeeder::TITLE)
        ->first();

    expect($layout)->not->toBeNull();
    $schema = is_array($layout->schema) ? $layout->schema : [];
    expect($schema['category'] ?? null)->toBe('commercial-proposal');
    expect(RequestLayout::allowsApplicationEquipmentInsert($schema))->toBeFalse();
    expect($layout->clientFillPayload()['allow_application_equipment_insert'] ?? null)->toBeFalse();

    $keys = collect($schema['fields'] ?? [])->pluck('key')->all();
    expect($keys)->toContain('таблица_оборудование', 'подразделение', 'адрес', 'итого_оборудование', 'итого_вся_смета');

    $subdivisionField = collect($schema['fields'] ?? [])->firstWhere('key', 'подразделение');
    expect($subdivisionField['type'] ?? null)->toBe('subdivision_warehouse');
    expect($subdivisionField['label'] ?? null)->toBe('Склад');
    expect($keys)->not->toContain('таблица_материалы', 'таблица_работа', 'итого_материалы_оборудование');

    $equipmentField = collect($schema['fields'] ?? [])->firstWhere('key', 'таблица_оборудование');
    expect($equipmentField['table_mode'] ?? null)->toBe(\App\Support\ReportLayoutCommercialProposal::TABLE_MODE);
    expect((int) ($schema['signature_slots_count'] ?? 0))->toBe(0);
    expect($schema['signature_roles'] ?? [])->toBe([]);
    expect((string) ($schema['signature_template'] ?? ''))->toBe(RequestLayoutSignatureLine::mark());

    $meta = \App\Support\ReportLayoutCommercialProposal::measurementMetaForUi();
    expect($meta['typeOptions'])->toHaveKey('piece');
    expect($meta['unitsByType']['piece'] ?? null)->toContain('шт');
});

test('layout application create hides application equipment block for commercial proposal', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();
    $chief = User::query()->create([
        'surname' => 'Начальников',
        'name' => 'Начальник',
        'patronymic' => 'Начальник',
        'email' => 'nachalnik-kp-'.uniqid('', true).'@nachalnik.local',
        'password' => Hash::make('password'),
        'role_id' => 7,
    ]);
    $this->seed(CommercialProposalRequestLayoutSeeder::class);

    $layout = RequestLayout::query()
        ->where('title', CommercialProposalRequestLayoutSeeder::TITLE)
        ->firstOrFail();

    $response = $this->actingAs($chief)->get(
        route('boiler-chief.layout-applications.create', ['layout' => $layout->id])
    );

    $response->assertOk();
    $response->assertSee('x-show="allowApplicationEquipmentInsert"', false);
    $response->assertSee('Выберите склад', false);
    $response->assertDontSee('name="signer_1_user_id"', false);
});

test('layout applications fill catalog includes installation act and commercial proposal', function (): void {
    $this->seed(\Database\Seeders\InstallationActRequestLayoutSeeder::class);
    $this->seed(CommercialProposalRequestLayoutSeeder::class);

    $titles = \App\Support\LayoutApplicationCatalog::layoutsForFillCatalog()->pluck('title')->all();

    expect($titles)->toContain(
        \Database\Seeders\InstallationActRequestLayoutSeeder::TITLE,
        CommercialProposalRequestLayoutSeeder::TITLE,
    );
    expect($titles)->not->toContain('Коммерческое предложение (смета видеонаблюдения)');
});

test('commercial proposal subdivision picker is limited to foreman assignments', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $allowed = \App\Models\Subdivision::query()->create(['name' => 'Подразделение мастера']);
    $other = \App\Models\Subdivision::query()->create(['name' => 'Чужое подразделение']);
    \App\Models\Warehouse::query()->create(['name' => 'Склад мастера', 'subdivision_id' => $allowed->id]);
    \App\Models\Warehouse::query()->create(['name' => 'Чужой склад', 'subdivision_id' => $other->id]);

    $foreman = User::query()->create([
        'surname' => 'Мастер',
        'name' => 'Тест',
        'patronymic' => 'Тест',
        'email' => 'foreman-kp-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 4,
    ]);
    $foreman->assignedSubdivisions()->attach($allowed->id);

    $options = \App\Support\ReportLayoutCommercialProposal::subdivisionWarehouseOptionsForUser($foreman);
    $labels = collect($options)->pluck('label')->all();

    expect(collect($options)->where('kind', 'subdivision')->count())->toBe(0);
    expect($labels)->toContain('Склад «Склад мастера» (Подразделение мастера)');
    expect($labels)->not->toContain('Чужой склад');
});

test('commercial proposal warehouse picker shows warehouses from all active subdivisions for director', function (): void {
    FunctionalScenarioFixture::seedRolesAndUnits();

    $subA = \App\Models\Subdivision::query()->create(['name' => 'Подразделение А']);
    $subB = \App\Models\Subdivision::query()->create(['name' => 'Подразделение Б']);
    \App\Models\Warehouse::query()->create(['name' => 'Склад А1', 'subdivision_id' => $subA->id]);
    \App\Models\Warehouse::query()->create(['name' => 'Склад Б1', 'subdivision_id' => $subB->id]);

    $director = User::query()->create([
        'surname' => 'Директор',
        'name' => 'Тест',
        'patronymic' => 'Тест',
        'email' => 'director-kp-'.uniqid('', true).'@test.local',
        'password' => Hash::make('password'),
        'role_id' => 1,
    ]);

    $labels = collect(\App\Support\ReportLayoutCommercialProposal::subdivisionWarehouseOptionsForUser($director))
        ->where('kind', 'warehouse')
        ->pluck('label')
        ->all();

    expect($labels)->toContain('Склад «Склад А1» (Подразделение А)', 'Склад «Склад Б1» (Подразделение Б)');
});

test('commercial proposal pdf header shows subdivision and warehouse without html tags', function (): void {
    $this->seed(CommercialProposalRequestLayoutSeeder::class);

    $layout = RequestLayout::query()
        ->where('title', CommercialProposalRequestLayoutSeeder::TITLE)
        ->with('documentHeaderLayout')
        ->firstOrFail();

    $parts = app(RequestLayoutDocumentBuilder::class)->pdfParts($layout, [
        'подразделение' => '<div>Лаборатория технического контроля, Склад «Склад №1»</div>',
        'адрес' => '<div>664056, Иркутская обл., г. Иркутск</div>',
        'таблица_оборудование' => '[]',
        'итого_оборудование' => '0',
        'итого_вся_смета' => '0',
    ]);

    $html = (string) ($parts['structuredHeaderHtml'] ?? '');
    expect($html)->toContain('Подразделение и склад:');
    expect($html)->toContain('Лаборатория технического контроля, Склад «Склад №1»');
    expect($html)->toContain('664056, Иркутская обл., г. Иркутск');
    expect($html)->not->toContain('<div>');
});

test('commercial proposal pdf shows blank signature line without selecting a signer', function (): void {
    $this->seed(CommercialProposalRequestLayoutSeeder::class);

    $layout = RequestLayout::query()
        ->where('title', CommercialProposalRequestLayoutSeeder::TITLE)
        ->firstOrFail();

    $parts = app(RequestLayoutDocumentBuilder::class)->pdfParts($layout, [
        'подразделение' => 'Лаборатория, Склад №1',
        'адрес' => 'г. Иркутск',
        'таблица_оборудование' => '[]',
        'итого_оборудование' => '0',
        'итого_вся_смета' => '0',
    ]);

    expect($parts['signatureText'])->toBe(RequestLayoutSignatureLine::mark());
    expect($parts['footerLeftText'])->toContain('Дата:');
});
