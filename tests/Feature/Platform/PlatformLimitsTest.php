<?php

use App\Support\Images\ImageStore;
use App\Support\PlatformLimits;
use Illuminate\Support\Facades\Route;

/** Todos os limites da rota (o do grupo e o próprio da rota, se houver). */
function routeThrottles(string $name): array
{
    $middleware = Route::getRoutes()->getByName($name)?->gatherMiddleware() ?? [];

    return array_values(array_filter($middleware, fn (mixed $item) => is_string($item) && str_starts_with($item, 'throttle:')));
}

describe('requests per minute', function () {
    it('keeps the original values by default', function () {
        expect(collect(PlatformLimits::PER_MINUTE)->map(fn (int $value, string $group) => PlatformLimits::perMinute($group))->all())->toBe(PlatformLimits::PER_MINUTE)
            ->and(PlatformLimits::throttle('api'))->toBe('throttle:60,1')
            ->and(PlatformLimits::throttle('overlay'))->toBe('throttle:120,1')
            ->and(PlatformLimits::throttle('registration'))->toBe('throttle:10,1');
    });

    it('lets the installation change each group and never goes below one', function () {
        config(['platform.limits.api' => 15, 'platform.limits.push' => 0, 'platform.limits.card' => -4]);

        expect(PlatformLimits::throttle('api'))->toBe('throttle:15,1')
            ->and(PlatformLimits::perMinute('push'))->toBe(1)
            ->and(PlatformLimits::perMinute('card'))->toBe(1)
            ->and(PlatformLimits::perMinute('embed'))->toBe(120);
    });

    it('is what the routes actually use, with the same numbers as before', function () {
        expect(routeThrottles('api.v1.games.show'))->toContain('throttle:60,1')
            ->and(routeThrottles('overlay.state'))->toContain('throttle:120,1')
            ->and(routeThrottles('embed.game'))->toContain('throttle:120,1')
            ->and(routeThrottles('push.subscribe'))->toContain('throttle:30,1')
            ->and(routeThrottles('portal.card'))->toContain('throttle:30,1')
            ->and(routeThrottles('portal.search'))->toContain('throttle:60,1')
            // A inscrição tem o limite do grupo (60) e o próprio da rota (10): vale o mais estrito.
            ->and(routeThrottles('registration.store'))->toContain('throttle:60,1', 'throttle:10,1');
    });

    it('configures every group in config/platform.php', function () {
        expect(array_keys(config('platform.limits')))->toEqualCanonicalizing(array_keys(PlatformLimits::PER_MINUTE));
    });
});

describe('signed links', function () {
    it('keeps the original validity and clamps what the installation sets', function () {
        expect(PlatformLimits::signedMinutes('documents'))->toBe(10)->and(PlatformLimits::signedMinutes('photos'))->toBe(30);

        config(['platform.signed_url_minutes.documents' => 3, 'platform.signed_url_minutes.photos' => 0]);

        expect(PlatformLimits::signedMinutes('documents'))->toBe(3)->and(PlatformLimits::signedMinutes('photos'))->toBe(1);
    });

    it('still lets a caller choose the minutes, as the overlay does for the selected athlete', function () {
        expect(ImageStore::privateUrl(null, 96, 5))->toBeNull();
    });
});
