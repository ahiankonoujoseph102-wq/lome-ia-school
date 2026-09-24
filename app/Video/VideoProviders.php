<?php

namespace App\Video;

use App\Contracts\VideoProvider;
use InvalidArgumentException;

/**
 * Accès aux fournisseurs vidéo déclarés dans config/video.php.
 */
class VideoProviders
{
    public static function default(): VideoProvider
    {
        return self::get((string) config('video.default', 'youtube'));
    }

    /**
     * Tous les fournisseurs déclarés, dans l'ordre de la configuration.
     *
     * @return array<string, VideoProvider>
     */
    public static function all(): array
    {
        $providers = [];

        foreach (array_keys((array) config('video.providers', [])) as $key) {
            $providers[(string) $key] = self::get((string) $key);
        }

        return $providers;
    }

    public static function get(string $key): VideoProvider
    {
        $class = config("video.providers.{$key}");

        if (! is_string($class) || ! is_subclass_of($class, VideoProvider::class)) {
            throw new InvalidArgumentException("Fournisseur vidéo inconnu : {$key}");
        }

        /** @var VideoProvider */
        return app($class);
    }

    public static function find(?string $key): ?VideoProvider
    {
        try {
            return $key ? self::get($key) : null;
        } catch (InvalidArgumentException) {
            return null;
        }
    }
}
