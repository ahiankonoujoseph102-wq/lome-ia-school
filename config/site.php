<?php

use App\Assistant\AmaKnowledge;
use App\Assistant\AmaPrompt;

/*
|==========================================================================
| TEXTES MODIFIABLES DU SITE VITRINE
|==========================================================================
|
| Ce fichier regroupe tous les textes que vous pouvez modifier vous-même :
| coordonnées, chiffres clés, témoignages, FAQ, présentation du formateur…
|
| Les passages entre crochets, par exemple « [Témoignage à remplacer] »,
| sont des emplacements à remplir avec vos vraies informations.
|
| Règles simples pour modifier sans rien casser :
| - ne modifiez que le texte entre apostrophes : 'mon texte' ;
| - pour écrire une apostrophe dans un texte, ajoutez une barre devant : \' ;
| - gardez les virgules en fin de ligne.
|
| Les formations elles-mêmes (titres, prix, programmes) sont dans la base de
| données, remplie à partir de database/seeders/FormationSeeder.php.
|
| DEPUIS L'ÉTAPE 10, CES VALEURS SONT LES VALEURS DE DÉPART : la plupart se
| modifient dans l'admin (Réglages du site) et sont alors enregistrées en base
| (table « site_settings », voir App\Settings\SiteSettings), où elles
| remplacent celles de ce fichier.
|
| Marqueurs remplacés automatiquement dans les textes : {seuil} (seuil de
| vérification des paiements), {affiliation_taux}, {affiliation_jours_lien},
| {affiliation_jours_dispo}, {affiliation_minimum} (réglages d'affiliation).
|
*/

// Prix à partir duquel l'accès n'est ouvert qu'après validation du paiement par l'équipe (en FCFA).
$verificationThreshold = 50000;

return [

    /*
    |----------------------------------------------------------------------
    | Coordonnées
    |----------------------------------------------------------------------
    */

    'contact' => [
        'phones' => [
            '+228 72 24 02 31',
            '+228 79 16 23 90',
            '+228 79 74 21 27',
        ],
        'email' => 'academyjoseph71@gmail.com',
        'address' => 'Adidogomé, Lycée moderne 2, Lomé, Togo',
        'map_url' => 'https://www.google.com/maps/search/?api=1&query=Adidogom%C3%A9+Lyc%C3%A9e+moderne+2+Lom%C3%A9+Togo',
        'hours' => 'Du lundi au samedi, de 8 h à 20 h',
    ],

    'whatsapp' => [
        // Numéro au format international, sans « + » ni espaces.
        'number' => '22872240231',
        'message' => 'Bonjour Lomé IA School, je souhaite avoir des informations sur vos formations.',
        'consultation_message' => 'Bonjour Lomé IA School, je souhaite réserver une consultation gratuite.',
    ],

    // Étiquettes à côté du nom dans les groupes (App\Chat\ChatBadges), modifiables dans l'admin (Équipe).
    // principal_user_id vide : l'administrateur dont l'adresse est celle du responsable du site (legal.owner.email).
    'chat_badges' => [
        'principal_user_id' => null,
        'principal' => 'Formateur principal',
        'admin' => 'Administrateur',
    ],

    // Nouvelles fonctions des groupes de discussion (App\Chat\ChatFeatures), chacune avec son interrupteur dans
    // l'admin (Réglages du site > Fonctions des groupes). Éteintes au départ : bouton absent, groupe inchangé.
    'chat_features' => [
        'voice_pause' => false,
    ],

    // Quota d'e-mails du fournisseur (Brevo, offre gratuite : 300 par jour) et réserve gardée pour les codes de
    // confirmation, inscriptions et paiements : une annonce de groupe par e-mail est refusée si elle entamerait
    // cette réserve (App\Mail\MailQuota). Modifiables dans l'admin (Réglages du site > E-mails envoyés).
    'mail_quota' => [
        'daily_limit' => 300,
        'reserve' => 100,
    ],

    // Messages préremplis des boutons WhatsApp, adaptés à la page (App\Support\WhatsAppMessages).
    // Modifiables dans l'admin (Réglages du site > Messages WhatsApp). Chacun se termine par « : » pour que
    // la personne écrive sa question juste après. Repères remplacés à l'envoi : {formation}, {etape}, {date}, {recu}.
    'whatsapp_messages' => [
        'general' => 'Bonjour Coach Joseph, je suis sur le site Lomé IA School. Ma question :',
        'formation' => 'Bonjour Coach Joseph, je suis sur la page de la formation « {formation} ». Ma question :',
        'payment' => 'Bonjour Coach Joseph, j\'ai besoin d\'aide pour payer la formation « {formation} » (étape : {etape}). Mon problème :',
        'session_payment' => 'Bonjour Coach Joseph, j\'ai besoin d\'aide pour payer le supplément de la session du {date} de la formation « {formation} » (étape : {etape}). Mon problème :',
        'free' => 'Bonjour Coach Joseph, j\'ai une question sur la formation gratuite du {date}. Ma question :',
        'free_full' => 'Bonjour Coach Joseph, la formation gratuite du {date} est complète. Ma question :',
        'consultation' => 'Bonjour Coach Joseph, j\'ai une question sur la consultation gratuite. Ma question :',
        'consultation_booked' => 'Bonjour Coach Joseph, j\'ai une question sur ma consultation gratuite du {date}. Ma question :',
        'purchase_refused' => 'Bonjour Coach Joseph, mon paiement {recu} pour « {formation} » a été refusé. Ma question :',
    ],

    // Liens vers vos pages (adresse complète commençant par https://).
    // Un réseau dont le lien est vide ('') est masqué automatiquement.
    'social' => [
        'facebook' => '',
        'tiktok' => '',
        'youtube' => '',
    ],

    // Logo, icônes et image de partage. Valeurs de départ : fichiers générés depuis les originaux de
    // resources/brand/ (commande « php artisan app:brand-assets »). Modifiables dans l'admin (Réglages du
    // site > Logo et image de partage) : un fichier envoyé est alors enregistré sur le disque « public ».
    'brand' => [
        'logo' => 'images/brand/logo-horizontal.png',
        'logo_vertical' => 'images/brand/logo-vertical.jpg',
        'icons' => 'icons',
        'og_image' => 'images/brand/partage.jpg',
    ],

    /*
    |----------------------------------------------------------------------
    | Accueil : accroche et chiffres clés
    |----------------------------------------------------------------------
    */

    'hero' => [
        'badge' => 'Formations en intelligence artificielle à Lomé et en ligne',
        'title' => 'Maîtrisez l\'IA et transformez vos compétences en opportunités',
        'subtitle' => 'Des formations pratiques en français pour apprendre à utiliser l\'intelligence artificielle, créer, vendre vos services et développer votre activité. Paiement par Mobile Money.',
        // Petits arguments affichés sous les boutons de l'accueil.
        'points' => [
            'Accès à vie',
            'Accompagnement à vie',
            'Paiement Mobile Money',
        ],
    ],

    // Le nombre de formations est calculé automatiquement.
    'stats' => [
        ['value' => '1 000+', 'label' => 'personnes formées'],
        // Année où Joseph a commencé à former (l'école, elle, est récente : aucune date de création n'est affichée).
        ['value' => '2023', 'label' => 'année où Joseph a commencé à former'],
        ['value' => 'À vie', 'label' => 'accès et accompagnement'],
    ],

    /*
    |----------------------------------------------------------------------
    | Accès à vie et accompagnement à vie
    |----------------------------------------------------------------------
    | Mis en avant sur l'accueil et sur chaque page de formation.
    */

    'lifetime' => [
        'title' => 'Payez une fois, progressez toute votre vie',
        'text' => 'Chaque formation achetée vous appartient : vous la revoyez autant que vous voulez et vous restez accompagné, même des années plus tard.',
        'items' => [
            ['icon' => 'lock-open', 'title' => 'Accès à vie', 'text' => 'Revoyez les vidéos et les ressources de votre formation quand vous voulez, sans abonnement ni date limite.'],
            ['icon' => 'lifebuoy', 'title' => 'Accompagnement à vie', 'text' => 'Posez vos questions dans le groupe privé de la formation ou sur WhatsApp, aussi longtemps que vous en avez besoin.'],
        ],
        'note' => 'Valable tant que la plateforme Lomé IA School est en activité.',
    ],

    /*
    |----------------------------------------------------------------------
    | Pourquoi nous choisir
    |----------------------------------------------------------------------
    | « icon » : nom d'une icône Heroicons (https://heroicons.com).
    */

    'why_us' => [
        ['icon' => 'bolt', 'title' => 'Accès immédiat', 'text' => 'Dès l\'envoi de votre preuve de paiement, votre formation s\'ouvre dans votre espace élève. À partir de {seuil}, elle s\'ouvre après vérification du paiement.'],
        ['icon' => 'device-phone-mobile', 'title' => 'Paiement Mobile Money', 'text' => 'Payez depuis le Togo, le Burkina Faso ou le Bénin avec votre compte Mobile Money, puis envoyez votre preuve de paiement sur le site.'],
        ['icon' => 'academic-cap', 'title' => '100 % pratique, en français', 'text' => 'Des formations concrètes, orientées résultats, expliquées simplement et pensées pour notre réalité.'],
        ['icon' => 'chat-bubble-left-right', 'title' => 'Accompagnement à vie', 'text' => 'Chaque formation a son groupe de discussion réservé aux élèves : posez vos questions et avancez ensemble, sans limite de durée.'],
        ['icon' => 'map-pin', 'title' => 'Une école basée à Lomé', 'text' => 'Un formateur joignable et une équipe proche de vous, à Adidogomé.'],
        ['icon' => 'briefcase', 'title' => 'Des compétences concrètes, directement applicables', 'text' => 'Nous vous apprenons aussi à proposer vos nouvelles compétences à des clients.'],
    ],

    /*
    |----------------------------------------------------------------------
    | Déroulement des formations
    |----------------------------------------------------------------------
    | Règle : toutes les formations sont enregistrées, accès immédiat après
    | paiement. Le direct et le présentiel sont des sessions ponctuelles,
    | annoncées sur le site au moment où elles sont organisées.
    */

    'formats' => [
        'eyebrow' => 'Comment se déroulent les formations',
        'title' => 'Des formations enregistrées, disponibles tout de suite',
        'recorded' => [
            'title' => 'Formations enregistrées',
            'badge' => 'Accès immédiat après paiement',
            'text' => 'Toutes nos formations sont en vidéo. Dès l\'envoi de votre preuve de paiement, vous les suivez à votre rythme, depuis votre téléphone ou votre ordinateur, autant de fois que vous voulez. À partir de {seuil}, l\'accès s\'ouvre après vérification du paiement.',
            'points' => [
                'Accès immédiat après paiement',
                'À votre rythme, sur téléphone ou ordinateur',
                'Accès à vie et accompagnement à vie',
            ],
            // Phrase affichée en haut du catalogue.
            'catalogue' => 'Toutes les formations sont enregistrées : vous les suivez à votre rythme.',
        ],
        'sessions' => [
            'title' => 'Sessions ponctuelles en direct ou à Lomé',
            'text' => 'De temps en temps, une formation est aussi proposée en direct sur Google Meet ou en présentiel à Lomé. Ces sessions sont annoncées sur le site au moment où elles sont organisées.',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Comment ça marche
    |----------------------------------------------------------------------
    */

    'steps' => [
        ['title' => 'Je choisis ma formation', 'text' => 'Parcourez le catalogue et choisissez la formation qui correspond à votre objectif.'],
        ['title' => 'Je paie par Mobile Money', 'text' => 'J\'envoie le montant sur l\'un des numéros Mobile Money de l\'école (Togo, Burkina Faso ou Bénin), puis je dépose ma preuve de paiement.'],
        ['title' => 'J\'accède à ma formation', 'text' => 'Ma formation et son groupe privé s\'ouvrent dans mon espace élève : aussitôt, ou après vérification du paiement à partir de {seuil}.'],
    ],

    // Les témoignages se gèrent dans l'admin (Réglages > Témoignages, table « testimonials »).

    /*
    |----------------------------------------------------------------------
    | Programme d'affiliation (textes de l'accueil)
    |----------------------------------------------------------------------
    | Les règles (10 %, 30 jours, 7 jours, minimum de retrait) se règlent dans
    | config/affiliate.php : si vous les changez, adaptez aussi ces textes et
    | la question de la FAQ sur l'affiliation. Aucune promesse de gains.
    */

    'affiliate' => [
        'title' => 'Recommandez nos formations, recevez {affiliation_taux} % de chaque achat validé',
        'text' => 'Tout inscrit peut activer son espace affilié, même sans avoir acheté. Partagez vos liens sur WhatsApp, Facebook ou TikTok : pour chaque achat validé passé par votre lien, vous recevez une commission de {affiliation_taux} % du prix payé, retirable par Mobile Money.',
        'points' => [
            'Un lien vers le site et un lien pour chaque formation',
            '{affiliation_taux} % du prix payé sur chaque achat validé passé par votre lien',
            'Retrait par Mobile Money à partir de {affiliation_minimum}',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Formateur
    |----------------------------------------------------------------------
    | Photo : public/images/formateur.jpg (carrée, environ 800 × 800 px).
    | « photo_alt » : description de la photo pour les lecteurs d'écran et Google.
    */

    'trainer' => [
        'name' => 'Joseph Ahiankonou',
        'role' => 'Fondateur et formateur principal de Lomé IA School',
        'photo' => 'images/formateur.jpg',
        'photo_alt' => 'Portrait de Joseph Ahiankonou, fondateur et formateur de Lomé IA School',
        'short_bio' => 'Expert et consultant en intelligence artificielle, Joseph Ahiankonou forme depuis 2023 étudiants, professionnels et entreprises à utiliser l\'IA pour obtenir des résultats concrets.',
        'bio' => [
            'Passionné de technologie et de digital, Joseph s\'est spécialisé en intelligence artificielle dès l\'obtention de son bac, en se formant en ligne auprès d\'experts en France et aux États-Unis.',
            'Depuis 2023, l\'IA est son métier au quotidien. Il a formé plus de 1 000 personnes : étudiants, salariés, entrepreneurs, personnes en recherche d\'emploi, entreprises et centres de formation. Ses formations sont pratiques et directement applicables.',
            'Il accompagne également des entreprises et des porteurs de projets : sites web, applications, automatisations et conseil en IA. Avec Lomé IA School, il rend ces compétences accessibles à tous, avec un accompagnement à vie.',
        ],
        'highlights' => [
            'Plus de 1 000 personnes formées depuis 2023',
            'Formé auprès d\'experts en France et aux États-Unis',
            'Praticien au quotidien : sites, applications et automatisations pour ses clients',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Page personnelle de Joseph Ahiankonou (/joseph-ahiankonou)
    |----------------------------------------------------------------------
    | Page strictement professionnelle, centrée sur l'IA (parcours professionnel seulement, aucune liste de
    | livres). Faits fournis par Joseph (octobre 2026) : ne rien ajouter qui ne vienne pas de lui (ni témoignage, ni
    | client, ni récompense, ni chiffre). Tout se modifie dans l'admin (Réglages du site > Page Joseph
    | Ahiankonou) ; lecture : App\Support\Founder. « title » sert aussi de balise <title>, sans suffixe.
    | Photo vide : la photo du formateur (trainer.photo) est utilisée, sinon ses initiales.
    | Liens vides : masqués (jamais inventés), et absents des données structurées.
    */

    'founder' => [
        'name' => 'Joseph Ahiankonou',
        'nickname' => 'Coach Joseph',
        'title' => 'Joseph Ahiankonou (Coach Joseph) : formateur et consultant en IA à Lomé',
        'description' => 'Joseph Ahiankonou, dit Coach Joseph : expert et consultant en IA à Lomé (Togo), formateur depuis 2023, fondateur de Lomé IA School et auteur de 6 livres.',
        'job_title' => 'Formateur et consultant en intelligence artificielle',
        'tagline' => 'Expert et consultant en intelligence artificielle, formateur depuis 2023, plus de 1 000 personnes formées.',
        'intro' => [
            'Joseph Ahiankonou, que l\'on appelle aussi « Coach Joseph », est originaire d\'Agoè-Nyivé, à Lomé (Togo). Expert et consultant en intelligence artificielle, il forme depuis 2023 et a déjà formé plus de 1 000 personnes.',
            'Il est le fondateur de Lomé IA School, qui propose des formations en intelligence artificielle, et de Joseph Academy, son activité d\'écriture de livres pour les autres.',
            'Il crée aussi des sites web et des applications pour des entreprises avec l\'intelligence artificielle.',
        ],
        'journey' => [
            'Formateur en intelligence artificielle depuis 2023.',
            'Plus de 1 000 personnes formées.',
            'Auteur de 6 livres.',
            'Fondateur de Lomé IA School et de Joseph Academy.',
            'Créateur de sites web et d\'applications pour des entreprises.',
        ],
        'services' => [
            ['title' => 'Formations en intelligence artificielle', 'text' => 'Avec Lomé IA School : des formations pratiques en intelligence artificielle, en français, en ligne et à Lomé.'],
            ['title' => 'Création de sites web et d\'applications', 'text' => 'Des sites web et des applications pour les entreprises, créés avec l\'intelligence artificielle.'],
            ['title' => 'Écriture de livres pour les autres', 'text' => 'Avec Joseph Academy, il écrit des livres pour les autres.'],
        ],
        // Photo envoyée depuis l'admin (disque « public », dossier site/founder/), vide au départ.
        'photo' => '',
        'photo_alt' => 'Portrait de Joseph Ahiankonou (Coach Joseph), formateur et consultant en IA à Lomé',
        // Liens saisis par Joseph dans l'admin (adresses https complètes). Vides au départ : rien n'est inventé.
        'links' => [
            'youtube' => '',
            'tiktok' => '',
            'facebook' => '',
        ],
        'whatsapp_channels' => [],
        'contact' => [
            'whatsapp' => '+228 72 24 02 31',
            'email' => 'academyjoseph71@gmail.com',
            'office' => 'Adidogomé, Lycée moderne 2, Lomé',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Référencement Google
    |----------------------------------------------------------------------
    | Code de la balise de vérification de Google Search Console (public, ce n'est pas un secret).
    | Modifiable dans l'admin (Réglages du site > Référencement Google). Vide = aucune balise.
    */

    'seo' => [
        'google_verification' => '',
    ],

    /*
    |----------------------------------------------------------------------
    | À propos de Lomé IA School
    |----------------------------------------------------------------------
    */

    'about' => [
        'mission' => 'Lomé IA School a pour mission de rendre l\'intelligence artificielle accessible à tous, en français, avec des formations pratiques et abordables. Nous voulons que chaque élève reparte avec des compétences concrètes, directement applicables à son travail, à ses projets et aux services qu\'il souhaite proposer.',
        'values' => [
            ['title' => 'Accessibilité', 'text' => 'Des prix abordables, un paiement par Mobile Money et des formations qui se suivent depuis un téléphone.'],
            ['title' => 'Pratique avant tout', 'text' => 'Chaque formation est tournée vers des réalisations concrètes et des résultats mesurables.'],
            ['title' => 'Accompagnement', 'text' => 'Personne n\'apprend seul : chaque formation a son groupe privé pour poser ses questions.'],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Consultation gratuite
    |----------------------------------------------------------------------
    */

    'consultation' => [
        'duration' => '30 minutes',
        'mode' => 'En direct en ligne, par appel vidéo',
        'steps' => [
            ['title' => 'Vous choisissez un créneau', 'text' => 'Choisissez le jour et l\'heure qui vous arrangent, puis indiquez vos coordonnées. Aucun compte n\'est nécessaire.'],
            ['title' => 'Vous recevez la confirmation', 'text' => 'Par e-mail, avec le lien de l\'appel vidéo et un lien pour déplacer ou annuler votre rendez-vous.'],
            ['title' => 'Nous échangeons en appel vidéo', 'text' => 'En direct en ligne, nous parlons de vos objectifs et vous repartez avec une recommandation claire.'],
        ],
        'benefits' => [
            'Faire le point sur votre niveau et vos objectifs',
            'Identifier la formation la plus adaptée à votre projet',
            'Obtenir des réponses à toutes vos questions avant de vous inscrire',
            'Repartir avec des premières pistes concrètes',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Garanties affichées sur chaque formation
    |----------------------------------------------------------------------
    */

    'guarantees' => [
        ['icon' => 'lock-open', 'title' => 'Accès à vie', 'text' => 'Revoyez la formation quand vous voulez, sans date limite.'],
        ['icon' => 'lifebuoy', 'title' => 'Accompagnement à vie', 'text' => 'Vos questions ont toujours une réponse, dans le groupe privé ou sur WhatsApp.'],
        // « instant » : remplacé par « verification_guarantee » pour les formations à partir du seuil de vérification.
        ['icon' => 'bolt', 'title' => 'Accès immédiat', 'text' => 'Votre formation s\'ouvre dès l\'envoi de votre preuve de paiement.', 'instant' => true],
        ['icon' => 'device-phone-mobile', 'title' => 'Paiement Mobile Money', 'text' => 'Togo, Burkina Faso et Bénin. Chaque paiement est vérifié par notre équipe.'],
    ],

    'verification_guarantee' => ['icon' => 'shield-check', 'title' => 'Accès après vérification', 'text' => 'Votre formation s\'ouvre dès que votre paiement a été vérifié, du lundi au samedi, de 8 h à 20 h.'],

    /*
    |----------------------------------------------------------------------
    | Inclus avec chaque formation
    |----------------------------------------------------------------------
    | Toujours affiché sur la page de chaque formation.
    */

    'included' => [
        'Les vidéos de la formation, accessibles dès le paiement confirmé',
        'L\'accès au groupe privé de discussion de la formation',
        'L\'accès à vie et l\'accompagnement à vie',
    ],

    // Les bonus de chaque formation se modifient dans l'admin (Formations > Modifier), vides par défaut.

    /*
    |----------------------------------------------------------------------
    | FAQ
    |----------------------------------------------------------------------
    | « home » : questions de la page d'accueil.
    | « formation » : questions affichées sur chaque page de formation.
    */

    'faq' => [
        'home' => [
            ['question' => 'Comment se passe le paiement ?', 'answer' => 'Par Mobile Money : vous envoyez le montant exact sur l\'un de nos numéros (Togo, Burkina Faso ou Bénin) depuis votre téléphone, puis vous envoyez sur le site la capture d\'écran de votre paiement. Rien d\'autre à remplir. Les frais de transfert sont à la charge de l\'expéditeur.'],
            ['question' => 'Quand est-ce que j\'accède à ma formation ?', 'answer' => 'Pour les formations de moins de {seuil} : immédiatement après l\'envoi de votre preuve de paiement (accès provisoire, puis vérification par notre équipe). À partir de {seuil} : dès que votre paiement a été vérifié, du lundi au samedi, de 8 h à 20 h.'],
            ['question' => 'Je suis débutant, puis-je suivre les formations ?', 'answer' => 'Oui. Les formations sont expliquées pas à pas, en français simple. Si vous partez de zéro, nous vous conseillons de commencer par la formation ChatGPT & Claude.'],
            ['question' => 'Puis-je suivre les formations sur mon téléphone ?', 'answer' => 'Oui, le site et les vidéos sont pensés pour le téléphone. Pour certaines formations pratiques (sites web, applications), un ordinateur rend l\'exercice plus confortable.'],
            ['question' => 'Combien de temps ai-je accès à une formation ?', 'answer' => 'À vie. Une fois achetée, la formation reste dans votre espace élève : vous la revoyez autant que vous voulez. Vous bénéficiez aussi d\'un accompagnement à vie dans le groupe privé de la formation (tant que la plateforme est en activité).'],
            ['question' => 'Y a-t-il des formations en direct ou en présentiel ?', 'answer' => 'Toutes nos formations sont enregistrées : vous les suivez à votre rythme. De temps en temps, une formation est aussi proposée en direct sur Google Meet ou en présentiel à Lomé : ces sessions ponctuelles sont annoncées sur le site au moment où elles sont organisées.'],
            ['question' => 'Comment fonctionne le programme d\'affiliation ?', 'answer' => 'Tout inscrit peut activer son espace affilié, même sans avoir acheté. Vous partagez vos liens ; pour chaque achat validé passé par l\'un d\'eux dans les {affiliation_jours_lien} jours qui suivent le clic, vous recevez {affiliation_taux} % du prix payé. La commission devient disponible {affiliation_jours_dispo} jours après la validation du paiement, et vous la retirez par Mobile Money à partir de {affiliation_minimum} (frais d\'envoi déduits). Chaque retrait est vérifié puis payé par notre équipe.'],
            ['question' => 'Et si mon paiement n\'est pas reçu ?', 'answer' => 'Chaque paiement est vérifié par notre équipe. Si le paiement n\'est pas reçu sur notre compte, l\'accès provisoire est retiré et vous êtes prévenu par e-mail, avec le motif.'],
            ['question' => 'Puis-je être remboursé ?', 'answer' => 'Oui, dans les 48 heures qui suivent l\'achat, à condition de ne pas avoir commencé la formation (aucune leçon consultée). Il suffit de nous écrire par e-mail ou sur WhatsApp.'],
            ['question' => 'Comment se passe la consultation gratuite ?', 'answer' => 'Elle se réserve depuis la page « Consultation gratuite » du site et se déroule en direct en ligne, par appel vidéo. Elle dure 30 minutes, du lundi au samedi, entre 8 h et 20 h.'],
        ],
        'formation' => [
            ['question' => 'Quand est-ce que j\'accède à la formation ?', 'answer' => 'Dès l\'envoi de votre preuve de paiement pour les formations de moins de {seuil}. À partir de {seuil}, dès que votre paiement a été vérifié, du lundi au samedi, de 8 h à 20 h.'],
            ['question' => 'Comment payer ?', 'answer' => 'Par Mobile Money : envoyez le montant exact sur l\'un de nos numéros (Togo, Burkina Faso ou Bénin), puis déposez votre preuve de paiement sur la page de commande.'],
            ['question' => 'Et si j\'ai une question pendant la formation ?', 'answer' => 'Vous la posez dans le groupe privé de la formation, réservé aux élèves inscrits. Vous pouvez aussi nous écrire sur WhatsApp.'],
            ['question' => 'Combien de temps ai-je accès ?', 'answer' => 'À vie, avec un accompagnement à vie dans le groupe privé de la formation (tant que la plateforme est en activité).'],
            ['question' => 'Puis-je être remboursé ?', 'answer' => 'Oui, dans les 48 heures qui suivent l\'achat, si vous n\'avez encore consulté aucune leçon de la formation.'],
            ['question' => 'La formation est-elle en direct ?', 'answer' => 'Non : elle est enregistrée et se suit à votre rythme. Si une session ponctuelle en direct ou en présentiel à Lomé est organisée, elle est annoncée sur le site.'],
            ['question' => 'Ai-je besoin de connaissances particulières ?', 'answer' => 'Non, sauf indication contraire sur la page. Il suffit de savoir utiliser un téléphone ou un ordinateur et d\'avoir une connexion Internet.'],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Paiement Mobile Money (manuel, avec preuve de paiement)
    |----------------------------------------------------------------------
    | L'élève envoie le montant sur l'un de nos numéros, puis envoie seulement la
    | capture d'écran de son paiement sur le site (page de paiement en 4 étapes).
    |
    | - « verification_threshold » : en dessous de ce prix, l'accès est ouvert
    |   aussitôt à titre provisoire ; à partir de ce prix, il n'est ouvert
    |   qu'après votre validation. À modifier en haut de ce fichier.
    | - Chaque moyen de paiement peut être désactivé avec 'enabled' => false.
    | - « color » : couleur de la pastille affichée (pas de logo officiel).
    */

    'payments' => [
        'verification_threshold' => $verificationThreshold,

        // Taille maximale de la capture envoyée (en kilo-octets). Elle est ensuite compressée.
        'proof_max_kb' => 8192,

        // Adresse qui reçoit un e-mail à chaque nouveau paiement à vérifier.
        'admin_email' => 'academyjoseph71@gmail.com',

        'countries' => [
            'tg' => ['name' => 'Togo', 'flag' => '🇹🇬', 'dial_code' => '+228', 'note' => null],
            'bf' => ['name' => 'Burkina Faso', 'flag' => '🇧🇫', 'dial_code' => '+226', 'note' => 'Paiement reçu par notre représentant au Burkina Faso'],
            'bj' => ['name' => 'Bénin', 'flag' => '🇧🇯', 'dial_code' => '+229', 'note' => 'Paiement reçu par notre représentant au Bénin'],
        ],

        // « ussd » : code USSD officiel qui ouvre le menu de transfert de l'opérateur (bouton « Payer
        // maintenant » de la page de paiement). Repères facultatifs : {numero} (notre numéro sans
        // l'indicatif) et {montant}. Vide : pas de bouton (Wave : paiement par l'application).
        // Codes relevés en septembre 2026 : à revérifier régulièrement auprès des opérateurs.
        // Jamais de code secret dans ce champ.
        'methods' => [
            'tg-mixx' => ['country' => 'tg', 'name' => 'Mixx by Yas', 'number' => '+228 72 24 02 31', 'beneficiary' => 'Ahiankonou Kokou Joseph', 'color' => '#facc15', 'ussd' => '*145*1#', 'enabled' => true],
            'tg-flooz' => ['country' => 'tg', 'name' => 'Flooz / Moov Money', 'number' => '+228 79 16 23 90', 'beneficiary' => 'Ahiankonou Kokou Joseph', 'color' => '#3b82f6', 'ussd' => '*155*1#', 'enabled' => true],
            'bf-orange' => ['country' => 'bf', 'name' => 'Orange Money', 'number' => '+226 07 27 63 81', 'beneficiary' => 'Anderson Passoule Oyo', 'color' => '#f97316', 'ussd' => '*144*2#', 'enabled' => true],
            'bf-wave' => ['country' => 'bf', 'name' => 'Wave', 'number' => '+226 03 53 21 76', 'beneficiary' => 'Anderson Passoule Oyo', 'color' => '#38bdf8', 'ussd' => '', 'enabled' => true],
            'bj-mtn' => ['country' => 'bj', 'name' => 'MTN', 'number' => '+229 01 51 53 39 35', 'beneficiary' => 'KOUAGOU Noutcha Adam Fernando', 'color' => '#eab308', 'ussd' => '*880#', 'enabled' => true],
            'bj-moov' => ['country' => 'bj', 'name' => 'Moov', 'number' => '+229 01 94 55 59 14', 'beneficiary' => 'KOUAGOU Noutcha Adam Fernando', 'color' => '#2563eb', 'ussd' => '*855*1#', 'enabled' => true],
            'bj-celtiis' => ['country' => 'bj', 'name' => 'Celtiis', 'number' => '+229 01 40 28 57 61', 'beneficiary' => 'KOUAGOU Noutcha Adam Fernando', 'color' => '#a855f7', 'ussd' => '*889#', 'enabled' => true],
        ],

        'fees_notice' => 'Les frais de transfert sont à la charge de l\'expéditeur.',

        // Délai de vérification annoncé à l'élève.
        'verification_hours' => 'du lundi au samedi, de 8 h à 20 h',
    ],

    /*
    |----------------------------------------------------------------------
    | Vidéo d'accueil (section entre le haut de page et le catalogue)
    |----------------------------------------------------------------------
    | Tout se modifie dans l'admin (Réglages du site > Vidéo d'accueil). Aucun lien n'est écrit dans le
    | code : lien YouTube vide ou invalide = section masquée. Vidéo verticale (9:16). Le bouton TikTok
    | n'apparaît que si son lien est renseigné.
    */

    'home_video' => [
        'enabled' => true,
        'video_url' => '',
        'title' => 'L\'intelligence artificielle expliquée simplement',
        'subtitle' => 'Une courte vidéo de Coach Joseph',
        'tiktok_url' => '',
        // Image d'aperçu verticale (9:16) envoyée depuis l'admin ; vide = vignette YouTube entière sur fond flouté.
        'poster' => '',
    ],

    /*
    |----------------------------------------------------------------------
    | Fond animé (accueil et haut du catalogue)
    |----------------------------------------------------------------------
    | Halo lumineux, réseau de points et icônes discrètes animés en CSS. Interrupteur dans l'admin
    | (Réglages du site > Fond animé) : éteint = fond fixe d'origine, sans nouvelle mise en ligne.
    */

    'animated_background' => [
        'enabled' => true,
    ],

    /*
    |----------------------------------------------------------------------
    | Page « Entreprises et centres de formation » (/entreprises)
    |----------------------------------------------------------------------
    | Tout se modifie dans l'admin (Réglages du site > Page Entreprises). Vidéo YouTube vide = bloc
    | vidéo masqué. Le bouton « Réserver un appel gratuit » mène à la consultation gratuite.
    */

    'business' => [
        'video_url' => '',
        // Format de la vidéo : 'horizontal' (16:9) ou 'vertical' (9:16, filmée au téléphone).
        // Vide = automatique : vertical pour un lien YouTube Shorts (/shorts/), horizontal sinon.
        'video_format' => '',
        'title' => 'Intégrer l\'intelligence artificielle dans votre organisation',
        'intro' => [
            'L\'intelligence artificielle transforme le monde du travail, y compris en Afrique de l\'Ouest : de nouveaux outils apparaissent, les métiers évoluent et les compétences attendues changent.',
            'J\'accompagne les centres de formation et les entreprises dans cette transition : comprendre ce que l\'IA peut apporter à votre activité, former vos équipes et mettre en place des solutions adaptées à vos besoins.',
        ],
        // Liste fournie par Joseph (ne pas modifier sans son accord).
        'services' => [
            'L\'entraînement et l\'amélioration des systèmes d\'IA',
            'La création de contenu avec l\'intelligence artificielle',
            'L\'automatisation des tâches et des processus',
            'La création de sites web',
            'Le développement d\'agents IA',
            'Le copywriting',
            'Le marketing digital',
            'L\'intégration de l\'IA dans les entreprises',
            'La formation des employés',
            'L\'intégration de formations en IA dans les programmes des centres de formation',
        ],
        // Petit bloc de l'accueil.
        'home_title' => 'Vous êtes un centre de formation ou une entreprise ?',
        'home_text' => 'Formation de vos équipes, automatisation, agents IA, intégration de l\'IA dans vos programmes : découvrez comment je vous accompagne.',
    ],

    /*
    |----------------------------------------------------------------------
    | Informations légales (mentions légales, CGV, confidentialité)
    |----------------------------------------------------------------------
    */

    /*
    |----------------------------------------------------------------------
    | Assistante IA « Ama » (Gemini, ou l'agent ElevenLabs)
    |----------------------------------------------------------------------
    | Bouton flottant « Parler avec notre assistante » sur les pages publiques (App\Support\VoiceAssistant).
    | Modifiable dans l'admin (Réglages du site > Assistante vocale). Désactivée par défaut.
    | L'identifiant de l'agent est public (ce n'est pas un secret).
    */

    'voice_assistant' => [
        'enabled' => false,
        // « gemini » : notre propre assistante Ama (API Gemini de Google, clé GEMINI_API_KEY dans .env), par défaut ;
        // « elevenlabs » : l'ancien agent ElevenLabs (code gardé, à choisir dans l'admin).
        'provider' => 'gemini',
        // Instructions d'Ama (Gemini), modifiables dans l'admin ; vide = texte d'origine (App\Assistant\AmaPrompt).
        'instructions' => AmaPrompt::DEFAULT_INSTRUCTIONS,
        // Limites d'Ama (Gemini), sous le quota gratuit de Google (voir App\Support\VoiceAssistant::LIMITS).
        'visitor_daily_limit' => 30,
        'max_length' => 500,
        'daily_limit' => 400,
        'visitor_minute_limit' => 5,
        'minute_limit' => 10,
        'agent_id' => 'agent_9901m3phrpdwedt88vsdqmnwk09z',
        // Bouton aussi dans le lecteur de cours (désactivé par défaut).
        'lessons' => false,
        // Aide proactive : bulle « Vous semblez bloqué ? » quand le site repère une difficulté.
        'proactive' => true,
        // Mode appel : interruption à la voix (expérimental). Éteinte par défaut : toucher le cercle coupe la parole.
        'voice_interrupt' => false,
        // Secondes sur une même étape du paiement avant de proposer l'aide.
        'help_delay' => 90,
        // Visage animé d'Ama (dessin SVG) à la place du bouton rond, et grand personnage du mode appel. Éteint : l'ancien bouton.
        'character' => true,
        // Visage choisi parmi les trois propositions : a (cheveux naturels), b (tresses), c (foulard).
        'character_style' => 'a',
        // Ama se présente toute seule (une bulle courte après environ 8 secondes, une fois par visite). Éteint : jamais.
        'nudge' => true,
        // Textes des bulles (vide = texte d'origine, App\Support\VoiceAssistant::NUDGES).
        'nudges' => [],
    ],

    /*
    |----------------------------------------------------------------------
    | Base de connaissances d'Ama
    |----------------------------------------------------------------------
    | Modifiable dans l'admin (Réglages du site > Assistante Ama) : l'école et son fondateur (texte de départ),
    | questions fréquentes et autres informations (vides au départ). Envoyée à Ama avec chaque message
    | (App\Assistant\AmaKnowledge). Rien à inventer : ne saisir que des informations exactes.
    */

    'assistant_knowledge' => [
        'school' => AmaKnowledge::DEFAULT_SCHOOL,
        'faq' => [],
        'other' => '',
    ],

    'legal' => [
        'company_name' => 'Lomé IA School',
        // Responsable du site (mentions légales).
        'owner' => [
            'name' => 'Joseph Ahiankonou',
            'address' => 'Adidogomé, Lycée moderne 2, Lomé, Togo',
            'phone' => '+228 72 24 02 31',
            'email' => 'academyjoseph71@gmail.com',
        ],
        // Hébergeur du site et lieu de stockage des données (mentions légales et politique de confidentialité).
        // Modifiables dans l'admin (Réglages du site > Pages légales) : à changer lors du passage sur Hostinger.
        'host' => 'Laravel Cloud (https://cloud.laravel.com)',
        'data_location' => 'Europe (Londres, Royaume-Uni)',
        'updated_at' => '1er octobre 2026',
        'access_duration' => 'L\'accès à la formation est accordé à vie, avec un accompagnement à vie dans le groupe privé de la formation, tant que la plateforme Lomé IA School est en activité.',
        'refund_policy' => 'Vous pouvez demander le remboursement d\'une formation dans les 48 heures qui suivent son achat, uniquement si elle n\'a pas été commencée, c\'est-à-dire si aucune leçon n\'a été consultée. La demande se fait par e-mail ou sur WhatsApp. Passé ce délai, ou dès qu\'une leçon a été consultée, aucun remboursement n\'est possible.',
        'data_retention' => 'Vos données personnelles, y compris les preuves de paiement (captures d\'écran, identifiants de transaction), sont conservées 3 ans après votre dernier achat, puis supprimées.',
    ],

];
