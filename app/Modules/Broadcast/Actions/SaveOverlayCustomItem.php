<?php

namespace App\Modules\Broadcast\Actions;

use App\Modules\Broadcast\Events\OverlayUpdated;
use App\Modules\Broadcast\Models\OverlayChannel;
use App\Modules\Broadcast\Models\OverlayCustomItem;
use App\Modules\Broadcast\Support\OverlayCustomElements;
use InvalidArgumentException;

/**
 * Cria, edita, duplica ou exclui um overlay livre (Builder). Os elementos sempre passam por {@see OverlayCustomElements};
 * o nome é único na organização. Toda mudança atualiza os canais abertos no OBS.
 */
final class SaveOverlayCustomItem
{
    public function handle(?OverlayCustomItem $item, string $name, mixed $width, mixed $height, mixed $elements): OverlayCustomItem
    {
        $name = mb_substr(trim($name), 0, 60);

        if ($name === '') {
            throw new InvalidArgumentException('Informe o nome do overlay.');
        }

        $taken = OverlayCustomItem::query()->where('name', $name)->when($item !== null, fn ($query) => $query->whereKeyNot($item?->getKey()))->exists();

        if ($taken) {
            throw new InvalidArgumentException('Já existe um overlay com esse nome.');
        }

        if ($item === null && OverlayCustomItem::query()->count() >= OverlayCustomItem::LIMIT) {
            throw new InvalidArgumentException('A organização já tem '.OverlayCustomItem::LIMIT.' overlays livres. Exclua algum para criar outro.');
        }

        $attributes = [
            'name' => $name,
            'width' => OverlayCustomElements::size($width, $item !== null ? $item->width : 1920),
            'height' => OverlayCustomElements::size($height, $item !== null ? $item->height : 1080),
            'elements' => OverlayCustomElements::sanitize($elements),
        ];

        $item = $item === null ? OverlayCustomItem::query()->create($attributes) : tap($item)->update($attributes);

        self::refresh();

        return $item;
    }

    /** Cópia com nome livre ("Nome (cópia)", "Nome (cópia 2)"...). */
    public function duplicate(OverlayCustomItem $item): OverlayCustomItem
    {
        $base = mb_substr($item->name, 0, 50).' (cópia';
        $name = $base.')';

        for ($n = 2; OverlayCustomItem::query()->where('name', $name)->exists(); $n++) {
            $name = $base.' '.$n.')';
        }

        return $this->handle(null, $name, $item->width, $item->height, $item->elements);
    }

    public function delete(OverlayCustomItem $item): void
    {
        $id = (string) $item->getKey();
        $item->delete();

        // Tira o overlay do ar nos canais que o tinham ligado.
        OverlayChannel::query()->get()->each(function (OverlayChannel $channel) use ($id): void {
            $custom = is_array($channel->layers['custom'] ?? null) ? $channel->layers['custom'] : [];

            if (array_key_exists($id, $custom)) {
                unset($custom[$id]);
                $channel->layers = array_merge($channel->layers ?? [], ['custom' => $custom]);
                $channel->save();
            }
        });

        self::refresh();
    }

    private static function refresh(): void
    {
        OverlayChannel::query()->get()->each(fn (OverlayChannel $channel) => OverlayUpdated::dispatchFor($channel));
    }
}
