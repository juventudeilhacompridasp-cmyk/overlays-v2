<?php

namespace App\Modules\Market\Actions;

use App\Modules\Market\Models\MarketMedia;
use App\Modules\Market\Models\MarketProfile;
use App\Modules\Market\Support\VideoLink;
use InvalidArgumentException;

/** Vídeos de jogadas do perfil: só links de YouTube, Vimeo e Instagram, até 3, sem repetir. Nada é baixado. */
final class ManageMarketVideos
{
    public function add(MarketProfile $profile, string $input): MarketMedia
    {
        $link = VideoLink::parse($input);

        if ($link === null) {
            throw new InvalidArgumentException('Use um link https de vídeo do YouTube, Vimeo ou Instagram (post, reel ou tv).');
        }

        $existing = MarketMedia::query()->where('profile_id', $profile->getKey());

        if ((clone $existing)->count() >= VideoLink::LIMIT) {
            throw new InvalidArgumentException('Você pode ter até '.VideoLink::LIMIT.' vídeos. Remova um para adicionar outro.');
        }

        if ((clone $existing)->where('provider', $link['provider'])->where('external_id', $link['external_id'])->exists()) {
            throw new InvalidArgumentException('Este vídeo já está no seu perfil.');
        }

        return MarketMedia::query()->create([
            'profile_id' => $profile->getKey(),
            'kind' => 'video_link',
            'provider' => $link['provider'],
            'external_id' => $link['external_id'],
            'url' => $link['url'],
            'sort' => (int) (clone $existing)->max('sort') + 1,
        ]);
    }

    public function remove(MarketMedia $video): void
    {
        $video->delete();
    }
}
