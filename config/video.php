<?php

use App\Video\YouTubeVideoProvider;

/*
|--------------------------------------------------------------------------
| Fournisseur vidéo des leçons
|--------------------------------------------------------------------------
|
| Les leçons enregistrent le fournisseur de leur vidéo : changer ici le
| fournisseur par défaut n'affecte que les nouvelles leçons. Pour ajouter
| Bunny.net plus tard : une classe qui implémente App\Contracts\VideoProvider,
| déclarée dans « providers », puis VIDEO_PROVIDER=bunny dans .env.
|
*/

return [

    'default' => env('VIDEO_PROVIDER', 'youtube'),

    'providers' => [
        'youtube' => YouTubeVideoProvider::class,
    ],

];
