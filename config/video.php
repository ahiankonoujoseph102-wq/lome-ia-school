<?php

use App\Video\GoogleDriveVideoProvider;
use App\Video\YouTubeVideoProvider;

/*
|--------------------------------------------------------------------------
| Fournisseur vidéo des leçons
|--------------------------------------------------------------------------
|
| Chaque leçon enregistre le fournisseur de sa vidéo principale et, si
| besoin, de sa vidéo de secours (ex. YouTube + Google Drive). L'ordre de
| « providers » est celui des champs de l'écran admin. Pour ajouter
| Bunny.net plus tard : une classe qui implémente App\Contracts\VideoProvider,
| déclarée dans « providers », puis VIDEO_PROVIDER=bunny dans .env.
|
*/

return [

    'default' => env('VIDEO_PROVIDER', 'youtube'),

    'providers' => [
        'youtube' => YouTubeVideoProvider::class,
        'drive' => GoogleDriveVideoProvider::class,
    ],

];
