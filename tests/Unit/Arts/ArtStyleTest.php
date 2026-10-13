<?php

use App\Modules\Arts\Support\ArtFormats;
use App\Modules\Arts\Support\ArtPresets;
use App\Modules\Arts\Support\ArtStyle;

it('is complete and stable: sanitizing nothing gives the defaults, and sanitizing twice changes nothing', function () {
    $defaults = ArtStyle::defaults();

    expect(ArtStyle::sanitize([]))->toEqual($defaults)
        ->and(ArtStyle::sanitize(ArtStyle::sanitize(['palette' => ['primary' => '#ABC']])))->toEqual(ArtStyle::sanitize(['palette' => ['primary' => '#ABC']]));
});

it('accepts valid values and lowercases colors', function () {
    $style = ArtStyle::sanitize([
        'palette' => ['primary' => '#FFAA00', 'text' => '#fff'],
        'background' => ['mode' => 'solid', 'pattern' => 'grid', 'angle' => 90],
        'layout' => ['variant' => 'poster', 'align' => 'left', 'frame' => true],
        'type' => ['heading' => 'serif', 'uppercase' => false, 'scale' => 1.25],
    ]);

    expect($style['palette']['primary'])->toBe('#ffaa00')
        ->and($style['palette']['text'])->toBe('#fff')
        ->and($style['background'])->toMatchArray(['mode' => 'solid', 'pattern' => 'grid', 'angle' => 90.0])
        ->and($style['layout'])->toMatchArray(['variant' => 'poster', 'align' => 'left', 'frame' => true])
        ->and($style['type'])->toMatchArray(['heading' => 'serif', 'uppercase' => false, 'scale' => 1.25]);
});

it('sends anything invalid back to the default instead of failing', function (array $input, string $group, string $key) {
    $default = ArtStyle::defaults()[$group][$key];

    expect(ArtStyle::sanitize($input)[$group][$key])->toEqual($default);
})->with([
    'cor com nome' => [['palette' => ['primary' => 'red']], 'palette', 'primary'],
    'cor com script' => [['palette' => ['text' => 'url(javascript:alert(1))']], 'palette', 'text'],
    'cor curta demais' => [['palette' => ['muted' => '#12']], 'palette', 'muted'],
    'fonte desconhecida' => [['type' => ['heading' => 'Comic Sans']], 'type', 'heading'],
    'variante desconhecida' => [['layout' => ['variant' => 'hacker']], 'layout', 'variant'],
    'padrão desconhecido' => [['background' => ['pattern' => '../../x']], 'background', 'pattern'],
    'booleano que não é' => [['type' => ['uppercase' => 'sim']], 'type', 'uppercase'],
    'grupo que não é lista' => [['crest' => 'tudo'], 'crest', 'size'],
]);

it('clamps numbers into their ranges', function () {
    $style = ArtStyle::sanitize(['type' => ['scale' => 99, 'spacing' => -9], 'layout' => ['margin' => 0, 'radius' => 500], 'crest' => ['size' => 1], 'background' => ['angle' => 999, 'shade' => 5]]);

    expect($style['type']['scale'])->toBe(1.6)
        ->and($style['type']['spacing'])->toBe(-0.05)
        ->and($style['layout']['margin'])->toBe(2.0)
        ->and($style['layout']['radius'])->toBe(10.0)
        ->and($style['crest']['size'])->toBe(10.0)
        ->and($style['background']['angle'])->toBe(360.0)
        ->and($style['background']['shade'])->toBe(0.9);
});

it('cleans the free texts: no control characters, no line breaks, limited length', function () {
    $style = ArtStyle::sanitize(['text' => ['headline' => "  Hoje\ntem\u{0000} jogo  ", 'footer' => str_repeat('a', 500), 'hashtag' => ['x']]]);

    expect($style['text']['headline'])->toBe('Hoje tem jogo')
        ->and(mb_strlen($style['text']['footer']))->toBe(120)
        ->and($style['text']['hashtag'])->toBe('');
});

it('turns elements on by default and off only when told false', function () {
    $style = ArtStyle::sanitize(['elements' => ['date' => false, 'venue' => 'não', 'desconhecido' => false]]);

    expect($style['elements']['date'])->toBeFalse()
        ->and($style['elements']['venue'])->toBeTrue()
        ->and($style['elements'])->toHaveCount(count(ArtStyle::ELEMENTS))
        ->and($style['elements'])->not->toHaveKey('desconhecido');
});

it('resolves formats: presets, unknown keys and custom sizes within limits', function () {
    expect(ArtFormats::resolve('story'))->toBe(['key' => 'story', 'width' => 1080, 'height' => 1920])
        ->and(ArtFormats::resolve('nada'))->toBe(['key' => 'square', 'width' => 1080, 'height' => 1080])
        ->and(ArtFormats::resolve('custom', 1200, 500))->toBe(['key' => 'custom', 'width' => 1200, 'height' => 500])
        ->and(ArtFormats::resolve('custom', 10, 99999))->toBe(['key' => 'custom', 'width' => 200, 'height' => 4000])
        ->and(ArtFormats::resolve('custom'))->toBe(['key' => 'square', 'width' => 1080, 'height' => 1080]);
});

it('ships system presets that are already valid', function () {
    $presets = ArtPresets::all();

    expect($presets)->not->toBeEmpty();

    foreach ($presets as $preset) {
        expect(ArtStyle::sanitize($preset['style']))->toEqual($preset['style'])
            ->and(ArtFormats::PRESETS)->toHaveKey($preset['format'])
            ->and(ArtPresets::find($preset['key']))->toEqual($preset);
    }

    expect(ArtPresets::find('inexistente'))->toBeNull()
        ->and(count(array_unique(array_column($presets, 'key'))))->toBe(count($presets));
});

it('has the broadcast footer off by default and sanitizes its settings', function () {
    $defaults = ArtStyle::defaults();

    expect($defaults['elements']['broadcast'])->toBeFalse()
        ->and($defaults['broadcast'])->toBe(['platform' => 'youtube', 'label' => 'Transmissão ao vivo', 'channel' => ''])
        ->and(ArtStyle::sanitize(['elements' => ['broadcast' => true]])['elements']['broadcast'])->toBeTrue();

    $style = ArtStyle::sanitize(['broadcast' => ['platform' => 'kick', 'label' => '', 'channel' => "  Canal\nda Liga  "]]);
    expect($style['broadcast'])->toBe(['platform' => 'kick', 'label' => '', 'channel' => 'Canal da Liga'])
        ->and(ArtStyle::sanitize(['broadcast' => ['platform' => 'tiktok']])['broadcast']['platform'])->toBe('youtube')
        ->and(mb_strlen(ArtStyle::sanitize(['broadcast' => ['label' => str_repeat('x', 200)]])['broadcast']['label']))->toBe(40);
});

it('cleans the new free texts too', function () {
    $style = ArtStyle::sanitize(['text' => ['title' => str_repeat('T', 200), 'subtitle' => "Quartas\nde final", 'credit' => ['x']]]);

    expect(mb_strlen($style['text']['title']))->toBe(60)
        ->and($style['text']['subtitle'])->toBe('Quartas de final')
        ->and($style['text']['credit'])->toBe('');
});

it('offers the 3:4 format', function () {
    expect(ArtFormats::resolve('tall'))->toBe(['key' => 'tall', 'width' => 1080, 'height' => 1440])
        ->and(count(ArtFormats::options()))->toBe(6);
});
