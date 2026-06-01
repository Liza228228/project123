<?php

use App\Models\Application;

test('listing search parses application numbers from common input formats', function (): void {
    expect(Application::listingSearchApplicationIds('35'))->toBe([35]);
    expect(Application::listingSearchApplicationIds('№ 35'))->toBe([35]);
    expect(Application::listingSearchApplicationIds('#35'))->toBe([35]);
    expect(Application::listingSearchApplicationIds('заявка № 28'))->toBe([28]);
    expect(Application::listingSearchApplicationIds('Котёл'))->toBe([]);
});
