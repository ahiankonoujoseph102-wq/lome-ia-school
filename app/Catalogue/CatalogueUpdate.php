<?php

namespace App\Catalogue;

/**
 * Mise à jour du catalogue de novembre 2026, demandée par Joseph :
 * - nouvelle formation « Formation complète en intelligence artificielle, de A à Z » (250 000 FCFA) ;
 * - nouveaux prix de quatre formations existantes (seul le prix change) ;
 * - formation gratuite et offres d'accompagnement retirées du site (masquées : rien n'est effacé, les élèves gardent
 *   leurs accès et leurs groupes, l'admin peut les republier).
 *
 * Textes de la nouvelle formation : rédigés par Claude à partir du texte et de la liste fournis par Joseph
 * (à valider par lui). Utilisé par la migration 2026_11_02_100001_update_catalogue_november_2026 et par le
 * FormationSeeder (installations neuves).
 */
final class CatalogueUpdate
{
    public const string COMPLETE_SLUG = 'formation-complete-intelligence-artificielle';

    /**
     * Nouveaux prix (adresse => prix en FCFA).
     *
     * @var array<string, int>
     */
    public const array PRICES = [
        'creation-agents-ia' => 150000,
        'automatisation-ia' => 55000,
        'site-vitrine-web-ia' => 10000,
        'sites-web-base-de-donnees-projets-avances' => 25000,
    ];

    /**
     * Formations retirées du site (masquées) : la formation gratuite terminée et les offres d'accompagnement.
     *
     * @var list<string>
     */
    public const array HIDDEN = [
        'formation-gratuite-ia',
        'accompagnement-ia-niveau-1',
        'accompagnement-ia-niveau-2',
        'accompagnement-ia-niveau-3',
    ];

    /**
     * Textes de la nouvelle formation complète.
     *
     * @return array{title: string, subtitle: string, description: string, price: int, category: string, outcomes: list<string>, audience: list<string>, careers: list<string>}
     */
    public static function complete(): array
    {
        return [
            'title' => 'Formation complète en intelligence artificielle, de A à Z',
            'subtitle' => 'Votre carrière est-elle prête pour l\'intelligence artificielle ? Un seul parcours pour apprendre à travailler avec l\'IA : prompts, contenu, automatisations, agents IA et développement.',
            'description' => 'Les entreprises recherchent de plus en plus des professionnels capables de travailler avec l\'intelligence artificielle, d\'automatiser des tâches et de s\'adapter à des méthodes de travail qui changent vite. Cette formation réunit dans un seul parcours tout ce qu\'il faut pour devenir à l\'aise avec l\'IA : comprendre comment elle fonctionne, lui donner des instructions précises, créer du contenu, automatiser votre travail, construire des agents IA et développer des outils avec l\'IA. Chaque compétence est appliquée à des cas concrets de travail.'
                ."\n\n".'N\'attendez pas que l\'intelligence artificielle transforme votre métier. Préparez-vous dès maintenant aux métiers de demain !',
            'price' => 250000,
            'category' => 'complete',
            'outcomes' => [
                'Comprendre l\'intelligence artificielle : ce qu\'elle sait faire, ses limites et comment l\'utiliser avec méthode',
                'Maîtriser l\'ingénierie des prompts (Prompt Engineering) pour obtenir des réponses précises et utiles',
                'Utiliser l\'IA générative pour produire des textes, des images et des vidéos',
                'Automatiser des tâches répétitives en reliant vos outils à l\'IA',
                'Concevoir des agents IA (Agentic AI) qui accomplissent des tâches pour vous',
                'Développer des sites et des outils avec l\'IA (Vibe Coding)',
                'Créer du contenu pour les réseaux sociaux avec l\'IA',
                'Monter des vidéos avec l\'IA',
                'Créer des affiches professionnelles avec l\'IA',
                'Intégrer l\'IA dans votre activité et apprendre plus vite avec l\'IA',
            ],
            'audience' => [
                'Les salariés, cadres et responsables qui veulent intégrer l\'IA dans leur métier',
                'Les chefs d\'entreprise qui veulent comprendre l\'IA et l\'utiliser dans leur activité',
                'Les étudiants et jeunes diplômés qui veulent se préparer aux métiers liés à l\'IA',
                'Les freelances et créateurs qui veulent proposer de nouveaux services',
            ],
            'careers' => [
                'Spécialiste en intelligence artificielle',
                'Ingénieur en Prompt Engineering',
                'Spécialiste en automatisation avec l\'IA',
                'Spécialiste en IA générative',
                'Développeur IA',
                'Créateur de contenu avec l\'IA',
            ],
        ];
    }
}
