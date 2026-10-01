<?php

namespace App\Support;

/**
 * « Installer l'application » (bannière et lien, voir resources/js/install-app.js).
 *
 * La bannière automatique n'apparaît que sur cette liste fermée de pages : jamais sur les pages de paiement
 * (récapitulatif de commande, supplément d'une session, suivi d'un achat), de connexion, d'inscription ni de
 * mot de passe, jamais dans le lecteur de cours, l'admin, la modération ni la conversation d'un groupe.
 * Toute nouvelle page en est donc exclue tant qu'elle n'est pas ajoutée ici. Le lien du pied de page, lui,
 * est sur toutes les pages qui ont un pied de page.
 */
class InstallApp
{
    /**
     * @var list<string>
     */
    public const array BANNER_PAGES = [
        'home',
        'formations.index',
        'formations.show',
        'consultation',
        'about',
        'contact',
        'business',
        'affiliate.program',
        'legal.notice',
        'legal.terms',
        'legal.privacy',
        'dashboard',
        'student.formations.index',
        'student.formations.show',
        'student.consultation',
        'student.purchases.index',
        'student.profile',
        'student.notifications',
        'student.affiliate.index',
        'student.affiliate.commissions',
        'student.affiliate.withdrawals',
        'student.sessions.index',
        'student.sessions.show',
        'chat.index',
    ];

    public const string BANNER_TEXT = 'Installez Lomé IA School sur votre téléphone pour y accéder en un toucher';

    public const string IOS_HELP = 'Touchez le bouton Partager, puis « Sur l\'écran d\'accueil »';

    public static function bannerShownOn(?string $routeName, string $variant = 'marketing'): bool
    {
        return $routeName !== null && $variant !== 'chat' && in_array($routeName, self::BANNER_PAGES, true);
    }
}
