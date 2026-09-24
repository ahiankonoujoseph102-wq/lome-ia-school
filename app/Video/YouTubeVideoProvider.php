<?php

namespace App\Video;

use App\Contracts\VideoProvider;
use App\Support\YouTube;

/**
 * Vidéos YouTube (non répertoriées), lues dans le lecteur youtube-nocookie.
 */
class YouTubeVideoProvider implements VideoProvider
{
    public function key(): string
    {
        return 'youtube';
    }

    public function name(): string
    {
        return 'YouTube';
    }

    public function parse(string $input): ?string
    {
        return YouTube::id($input);
    }

    public function invalidLinkMessage(string $input): string
    {
        return 'Ce lien YouTube n\'est pas reconnu. Sur YouTube : « Partager », puis « Copier le lien ».';
    }

    public function linkHelp(): string
    {
        return 'Sur YouTube : « Partager », puis « Copier le lien ». Le lien normal ou court fonctionne. Vidéo en « non répertoriée ».';
    }

    public function adminReminder(): ?string
    {
        return null;
    }

    public function shareUrl(string $videoId): string
    {
        return 'https://youtu.be/'.$videoId;
    }

    /**
     * rel=0 : suggestions limitées à la même chaîne ; iv_load_policy=3 : pas d'annotations ;
     * playsinline=1 : lecture dans la page sur iPhone ; fs=1 : plein écran autorisé.
     */
    public function embedUrl(string $videoId): string
    {
        return 'https://www.youtube-nocookie.com/embed/'.rawurlencode($videoId).'?'.http_build_query([
            'rel' => 0,
            'modestbranding' => 1,
            'iv_load_policy' => 3,
            'playsinline' => 1,
            'fs' => 1,
            'color' => 'white',
        ]);
    }

    public function thumbnailUrl(string $videoId): ?string
    {
        return YouTube::thumbnailUrl($videoId);
    }
}
