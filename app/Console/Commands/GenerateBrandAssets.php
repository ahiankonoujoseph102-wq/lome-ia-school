<?php

namespace App\Console\Commands;

use App\Branding\BrandAssets;
use Illuminate\Console\Command;

/**
 * Régénère les versions allégées du logo et des icônes à partir des originaux de resources/brand/
 * (icone.png, logo-horizontal.png, logo-vertical.png). À relancer seulement si un original change.
 */
class GenerateBrandAssets extends Command
{
    protected $signature = 'app:brand-assets';

    protected $description = 'Génère favicons, icônes du site installable, logos allégés et image de partage à partir de resources/brand/';

    public function handle(BrandAssets $assets): int
    {
        $source = resource_path('brand');
        $files = [
            ...$assets->icons($source.'/icone.png', public_path('icons')),
            $assets->horizontalLogo($source.'/logo-horizontal.png', public_path('images/brand/logo-horizontal.png')),
            $assets->verticalLogo($source.'/logo-vertical.png', public_path('images/brand/logo-vertical.jpg')),
            $assets->shareImage($source.'/logo-vertical.png', public_path('images/brand/partage.jpg')),
        ];

        // Adresses demandées automatiquement par les navigateurs et l'iPhone, à la racine du site.
        copy(public_path('icons/favicon.ico'), public_path('favicon.ico'));
        copy(public_path('icons/apple-touch-icon.png'), public_path('apple-touch-icon.png'));
        $files[] = public_path('favicon.ico');
        $files[] = public_path('apple-touch-icon.png');

        foreach ($files as $file) {
            $this->line(str_replace(base_path().'/', '', $file).' : '.number_format((int) filesize($file) / 1024, 1, ',', ' ').' Ko');
        }

        return self::SUCCESS;
    }
}
