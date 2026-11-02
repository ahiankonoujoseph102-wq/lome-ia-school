<?php

namespace App\Support;

use App\Models\Formation;

/**
 * Photos d'illustration des formations et des offres d'accompagnement (Pexels, licence Pexels ; crédits dans
 * CREDITS-PHOTOS.md). Fichiers dans le code du site : public/images/photos/formations/{adresse}-{largeur}.webp
 * (16:10, largeurs WIDTHS), produits par scripts/build-formation-photos.sh.
 *
 * Associées par l'adresse (slug), jamais par la base : aucune donnée de production n'est modifiée.
 * Ordre d'affichage (x-site.formation-cover) : image envoyée dans l'admin, sinon cette photo, sinon l'illustration
 * dessinée, sinon le visuel CSS. Ce sont des illustrations : jamais présentées comme nos élèves ou nos formateurs.
 */
final class FormationPhotos
{
    /** Texte alternatif de toutes les photos. */
    public const string ALT = "Photo d'illustration";

    /** @var list<int> */
    public const array WIDTHS = [480, 800, 1200, 1600];

    public const string DIRECTORY = 'images/photos/formations';

    /** Clé de la photo d'en-tête de la page des offres d'accompagnement. */
    public const string PACKS_PAGE = 'accompagnement';

    /**
     * Adresse de la formation => numéro de la photo sur Pexels (même liste que le script).
     *
     * @var array<string, int>
     */
    public const array PHOTOS = [
        'chatgpt-claude-initiation-ia' => 7005397,
        'creation-affiches-pro-ia' => 326502,
        'videos-publicitaires-ia' => 6954098,
        'videos-histoire-ia' => 7971543,
        'videos-films-ia' => 4164088,
        'chansons-personnalisees-ia' => 2531728,
        'ebooks-livres-professionnels-ia' => 3884406,
        'site-vitrine-web-ia' => 7550542,
        'sites-web-base-de-donnees-projets-avances' => 19805876,
        'applications-logiciels-ia' => 9429449,
        'creation-saas-ia' => 30689114,
        'creation-agents-ia' => 30678211,
        'automatisation-ia' => 6744352,
        'creer-formations-apprendre-competences-ia' => 5905751,
        'missions-upwork-competences-ia' => 5198239,
        'contenu-tiktok-facebook-youtube-videos-ia' => 8370484,
        'e-commerce-produits-digitaux-ia' => 5711715,
        'formation-gratuite-ia' => 3769021,
        // Novembre 2026 : même photo que l'ancienne offre niveau 3 (offres retirées du site).
        'formation-complete-intelligence-artificielle' => 30688592,
        'accompagnement-ia-niveau-1' => 30688591,
        'accompagnement-ia-niveau-2' => 30688593,
        'accompagnement-ia-niveau-3' => 30688592,
        // En-tête de la page /accompagnement (sans affiche envoyée dans l'admin).
        self::PACKS_PAGE => 5749153,
    ];

    /**
     * Photo à afficher pour cette formation, ou null (image envoyée dans l'admin, ou aucune photo prévue).
     *
     * @return array{src: string, srcset: string, alt: string}|null
     */
    public static function for(Formation $formation): ?array
    {
        // Une image envoyée dans l'admin (chemin sur le disque « public ») passe toujours avant la photo.
        if (str_contains((string) $formation->image, '/')) {
            return null;
        }

        return self::forSlug($formation->slug);
    }

    /**
     * @return array{src: string, srcset: string, alt: string}|null
     */
    public static function forSlug(?string $slug): ?array
    {
        // Formation pas encore enregistrée (formulaire de création de l'admin) : pas encore d'adresse.
        if ($slug === null || ! isset(self::PHOTOS[$slug]) || ! is_file(public_path(self::path($slug, 800)))) {
            return null;
        }

        return [
            'src' => asset(self::path($slug, 800)),
            'srcset' => collect(self::WIDTHS)->map(fn (int $width): string => asset(self::path($slug, $width)).' '.$width.'w')->implode(', '),
            'alt' => self::ALT,
        ];
    }

    public static function path(string $slug, int $width): string
    {
        return self::DIRECTORY.'/'.$slug.'-'.$width.'.webp';
    }

    public static function pexelsUrl(int $id): string
    {
        return 'https://www.pexels.com/photo/'.$id.'/';
    }
}
