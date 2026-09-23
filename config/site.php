<?php

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
*/

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
        'hours' => '[Horaires d\'ouverture à préciser, ex. : du lundi au samedi, de 8 h à 18 h]',
    ],

    'whatsapp' => [
        // Numéro au format international, sans « + » ni espaces.
        'number' => '22872240231',
        'message' => 'Bonjour Lomé IA School, je souhaite avoir des informations sur vos formations.',
        'consultation_message' => 'Bonjour Lomé IA School, je souhaite réserver une consultation gratuite.',
    ],

    // Liens vers vos pages. Laissez vide ('') pour masquer une icône.
    'social' => [
        'facebook' => '',
        'tiktok' => '',
        'youtube' => '',
    ],

    /*
    |----------------------------------------------------------------------
    | Accueil : accroche et chiffres clés
    |----------------------------------------------------------------------
    */

    'hero' => [
        'badge' => 'Formations en intelligence artificielle à Lomé et en ligne',
        'title' => 'Maîtrisez l\'IA et transformez vos compétences en revenus',
        'subtitle' => 'Des formations pratiques en français pour apprendre à utiliser l\'intelligence artificielle, créer, vendre vos services et développer votre activité. Paiement par Mobile Money, accès immédiat.',
    ],

    // Le nombre de formations est calculé automatiquement.
    'stats' => [
        ['value' => '[Nombre d\'élèves formés]', 'label' => 'élèves formés'],
        ['value' => '3', 'label' => 'formats : en ligne, en direct, présentiel'],
        ['value' => '10 %', 'label' => 'de commission en recommandant nos formations'],
    ],

    /*
    |----------------------------------------------------------------------
    | Pourquoi nous choisir
    |----------------------------------------------------------------------
    | « icon » : nom d'une icône Heroicons (https://heroicons.com).
    */

    'why_us' => [
        ['icon' => 'bolt', 'title' => 'Accès immédiat', 'text' => 'Dès que votre paiement est confirmé, votre formation est disponible dans votre espace élève.'],
        ['icon' => 'device-phone-mobile', 'title' => 'Paiement Mobile Money', 'text' => 'Payez simplement avec T-Money (Yas) ou Flooz (Moov), en toute sécurité via FedaPay.'],
        ['icon' => 'academic-cap', 'title' => '100 % pratique, en français', 'text' => 'Des formations concrètes, orientées résultats, expliquées simplement et pensées pour notre réalité.'],
        ['icon' => 'chat-bubble-left-right', 'title' => 'Groupe privé d\'entraide', 'text' => 'Chaque formation a son groupe de discussion réservé aux élèves pour poser vos questions et avancer ensemble.'],
        ['icon' => 'map-pin', 'title' => 'Une école basée à Lomé', 'text' => 'Un formateur joignable et une équipe proche de vous, à Adidogomé.'],
        ['icon' => 'banknotes', 'title' => 'Des compétences qui rapportent', 'text' => 'Nous vous apprenons aussi à vendre vos nouvelles compétences à des clients.'],
    ],

    /*
    |----------------------------------------------------------------------
    | Comment ça marche
    |----------------------------------------------------------------------
    */

    'steps' => [
        ['title' => 'Je choisis ma formation', 'text' => 'Parcourez le catalogue et choisissez la formation qui correspond à votre objectif.'],
        ['title' => 'Je paie par Mobile Money', 'text' => 'Réglez en quelques secondes avec T-Money (Yas) ou Flooz (Moov), paiement sécurisé par FedaPay.'],
        ['title' => 'J\'accède immédiatement', 'text' => 'Votre formation et votre groupe privé s\'ouvrent aussitôt dans votre espace élève.'],
    ],

    /*
    |----------------------------------------------------------------------
    | Témoignages
    |----------------------------------------------------------------------
    | Remplacez ces emplacements par de vrais témoignages d'élèves (avec leur accord).
    | « photo » : fichier facultatif déposé dans public/images/temoignages/.
    */

    'testimonials' => [
        ['name' => '[Prénom et nom de l\'élève]', 'role' => '[Formation suivie ou métier]', 'quote' => '[Témoignage à remplacer : ce que la formation a changé pour cet élève.]', 'photo' => null],
        ['name' => '[Prénom et nom de l\'élève]', 'role' => '[Formation suivie ou métier]', 'quote' => '[Témoignage à remplacer : un résultat concret obtenu après la formation.]', 'photo' => null],
        ['name' => '[Prénom et nom de l\'élève]', 'role' => '[Formation suivie ou métier]', 'quote' => '[Témoignage à remplacer : l\'avis de l\'élève sur l\'accompagnement.]', 'photo' => null],
    ],

    /*
    |----------------------------------------------------------------------
    | Programme d'affiliation
    |----------------------------------------------------------------------
    */

    'affiliate' => [
        'rate' => 10,
        'title' => 'Gagnez 10 % en recommandant nos formations',
        'text' => 'Chaque élève reçoit un lien de parrainage personnel. Partagez-le sur WhatsApp, Facebook ou TikTok : pour chaque formation achetée grâce à votre lien, vous gagnez 10 % du prix, retirables par Mobile Money.',
        'points' => [
            'Un lien personnel disponible dans votre espace élève',
            '10 % sur chaque vente réalisée grâce à votre lien',
            'Retrait de vos gains sur T-Money (Yas) ou Flooz (Moov)',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Formateur
    |----------------------------------------------------------------------
    | Photo : déposez-la dans public/images/formateur.jpg (format carré conseillé).
    */

    'trainer' => [
        'name' => 'Joseph Ahiankonou',
        'role' => 'Fondateur et formateur principal de Lomé IA School',
        'photo' => 'images/formateur.jpg',
        'short_bio' => 'Joseph Ahiankonou accompagne ses élèves dans la maîtrise des outils d\'intelligence artificielle et leur apprend à transformer ces compétences en opportunités concrètes. [Une ou deux phrases sur son expérience à compléter.]',
        'bio' => [
            '[Paragraphe 1 : parcours de Joseph, comment il s\'est formé à l\'intelligence artificielle.]',
            '[Paragraphe 2 : ses spécialités, ses réalisations et les projets menés pour des clients.]',
            '[Paragraphe 3 : sa façon d\'enseigner et ce qu\'il souhaite apporter à ses élèves.]',
        ],
        'highlights' => [
            '[Point fort 1, ex. : nombre d\'années d\'expérience]',
            '[Point fort 2, ex. : nombre de projets réalisés]',
            '[Point fort 3, ex. : certification ou distinction]',
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | À propos de Lomé IA School
    |----------------------------------------------------------------------
    */

    'about' => [
        'mission' => 'Lomé IA School a pour mission de rendre l\'intelligence artificielle accessible à tous, en français, avec des formations pratiques et abordables. Nous voulons que chaque élève reparte avec des compétences concrètes, capables de lui faire gagner du temps, de créer de nouveaux services et de générer des revenus.',
        'values' => [
            ['title' => 'Accessibilité', 'text' => 'Des prix abordables, un paiement par Mobile Money et des formations qui se suivent depuis un téléphone.'],
            ['title' => 'Pratique avant tout', 'text' => 'Chaque formation est tournée vers des réalisations concrètes et des résultats mesurables.'],
            ['title' => 'Accompagnement', 'text' => 'Personne n\'apprend seul : chaque formation a son groupe privé pour poser ses questions.'],
        ],
        'founded' => '[Année de création de Lomé IA School]',
    ],

    /*
    |----------------------------------------------------------------------
    | Consultation gratuite
    |----------------------------------------------------------------------
    */

    'consultation' => [
        'duration' => '[Durée de la consultation, ex. : 20 minutes]',
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
        ['icon' => 'bolt', 'title' => 'Accès immédiat', 'text' => 'Votre formation s\'ouvre dès la confirmation du paiement.'],
        ['icon' => 'chat-bubble-left-right', 'title' => 'Accompagnement en groupe privé', 'text' => 'Posez vos questions dans le groupe réservé aux élèves de la formation.'],
        ['icon' => 'shield-check', 'title' => 'Paiement sécurisé', 'text' => 'Paiement Mobile Money traité par FedaPay.'],
    ],

    /*
    |----------------------------------------------------------------------
    | FAQ
    |----------------------------------------------------------------------
    | « home » : questions de la page d'accueil.
    | « formation » : questions affichées sur chaque page de formation.
    */

    'faq' => [
        'home' => [
            ['question' => 'Comment se passe le paiement ?', 'answer' => 'Le paiement se fait par Mobile Money (T-Money / Yas ou Flooz / Moov) via FedaPay, une plateforme de paiement sécurisée. Vous validez simplement le paiement sur votre téléphone.'],
            ['question' => 'Quand est-ce que j\'accède à ma formation ?', 'answer' => 'Immédiatement. Dès que votre paiement est confirmé, la formation et son groupe privé sont disponibles dans votre espace élève.'],
            ['question' => 'Je suis débutant, puis-je suivre les formations ?', 'answer' => 'Oui. Les formations sont expliquées pas à pas, en français simple. Si vous partez de zéro, nous vous conseillons de commencer par la formation ChatGPT & Claude.'],
            ['question' => 'Puis-je suivre les formations sur mon téléphone ?', 'answer' => 'Oui, le site et les vidéos sont pensés pour le téléphone. Pour certaines formations pratiques (sites web, applications), un ordinateur rend l\'exercice plus confortable.'],
            ['question' => 'Combien de temps ai-je accès à une formation ?', 'answer' => '[Durée d\'accès à préciser, ex. : accès illimité aux vidéos.]'],
            ['question' => 'Comment fonctionnent les formations en direct et en présentiel ?', 'answer' => 'Les formations en direct ont lieu sur Google Meet : le lien de chaque session est communiqué aux inscrits. Les formations en présentiel ont lieu à Adidogomé, Lycée moderne 2, à Lomé. Les dates des sessions sont communiquées aux inscrits.'],
            ['question' => 'Comment fonctionne le programme d\'affiliation ?', 'answer' => 'Chaque élève dispose d\'un lien personnel. Pour chaque formation achetée grâce à votre lien, vous gagnez 10 % du prix. Vous pouvez demander le retrait de vos gains par Mobile Money ; chaque retrait est vérifié puis validé par notre équipe.'],
            ['question' => 'Puis-je être remboursé ?', 'answer' => '[Politique de remboursement à préciser.]'],
        ],
        'formation' => [
            ['question' => 'Quand est-ce que j\'accède à la formation ?', 'answer' => 'Immédiatement après la confirmation de votre paiement, depuis votre espace élève.'],
            ['question' => 'Comment payer ?', 'answer' => 'Par Mobile Money (T-Money / Yas ou Flooz / Moov), via la plateforme sécurisée FedaPay.'],
            ['question' => 'Et si j\'ai une question pendant la formation ?', 'answer' => 'Vous la posez dans le groupe privé de la formation, réservé aux élèves inscrits. Vous pouvez aussi nous écrire sur WhatsApp.'],
            ['question' => 'Combien de temps ai-je accès ?', 'answer' => '[Durée d\'accès à préciser, ex. : accès illimité aux vidéos.]'],
            ['question' => 'Ai-je besoin de connaissances particulières ?', 'answer' => 'Non, sauf indication contraire sur la page. Il suffit de savoir utiliser un téléphone ou un ordinateur et d\'avoir une connexion Internet.'],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Informations légales (mentions légales, CGV, confidentialité)
    |----------------------------------------------------------------------
    */

    'legal' => [
        'company_name' => 'Lomé IA School',
        'legal_form' => '[Forme juridique à préciser, ex. : entreprise individuelle]',
        'registration' => '[Numéro RCCM à préciser]',
        'tax_id' => '[Numéro d\'identification fiscale (NIF) à préciser]',
        'publisher' => 'Joseph Ahiankonou',
        'host' => 'Hostinger International Ltd., 61 Lordou Vironos Street, 6023 Larnaca, Chypre (https://www.hostinger.fr)',
        'updated_at' => '[Date de dernière mise à jour]',
        'refund_policy' => '[Politique de remboursement à préciser.]',
        'access_duration' => '[Durée d\'accès aux formations à préciser.]',
    ],

];
