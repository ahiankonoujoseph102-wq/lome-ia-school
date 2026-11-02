<?php

namespace App\Support;

/**
 * Design du site (novembre 2026) : « light » = design clair dans le style de Talkami (demandé par Joseph),
 * « dark » = ancien design sombre. Réglage site.design.theme, interrupteur dans l'admin (Réglages du site >
 * Design du site) : revenir à l'ancien design ne demande aucune mise en ligne.
 * La conversation d'un groupe reste sombre (bulles et outils prévus pour un fond foncé).
 */
final class SiteDesign
{
    public const array THEMES = ['light', 'dark'];

    public static function theme(?string $variant = null): string
    {
        if ($variant === 'chat') {
            return 'dark';
        }

        $theme = (string) config('site.design.theme', 'light');

        return in_array($theme, self::THEMES, true) ? $theme : 'light';
    }

    public static function isLight(?string $variant = null): bool
    {
        return self::theme($variant) === 'light';
    }
}
