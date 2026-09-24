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
        'hours' => 'Du lundi au samedi, de 8 h à 20 h',
    ],

    'whatsapp' => [
        // Numéro au format international, sans « + » ni espaces.
        'number' => '22872240231',
        'message' => 'Bonjour Lomé IA School, je souhaite avoir des informations sur vos formations.',
        'consultation_message' => 'Bonjour Lomé IA School, je souhaite réserver une consultation gratuite.',
    ],

    // Liens vers vos pages (adresse complète commençant par https://).
    // Un réseau dont le lien est vide ('') est masqué automatiquement.
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
        'title' => 'Maîtrisez l\'IA et transformez vos compétences en opportunités',
        'subtitle' => 'Des formations pratiques en français pour apprendre à utiliser l\'intelligence artificielle, créer, vendre vos services et développer votre activité. Paiement par Mobile Money, accès immédiat.',
        // Petits arguments affichés sous les boutons de l'accueil.
        'points' => [
            'Accès à vie',
            'Accompagnement à vie',
            'Paiement T-Money et Flooz',
        ],
    ],

    // Le nombre de formations est calculé automatiquement.
    'stats' => [
        ['value' => '1 000+', 'label' => 'personnes formées'],
        ['value' => '2023', 'label' => 'année de création de l\'école'],
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
        ['icon' => 'bolt', 'title' => 'Accès immédiat', 'text' => 'Dès que votre paiement est confirmé, votre formation est disponible dans votre espace élève.'],
        ['icon' => 'device-phone-mobile', 'title' => 'Paiement Mobile Money', 'text' => 'Payez simplement avec T-Money (Yas) ou Flooz (Moov), en toute sécurité via FedaPay.'],
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
            'text' => 'Toutes nos formations sont en vidéo. Dès que votre paiement est confirmé, vous les suivez à votre rythme, depuis votre téléphone ou votre ordinateur, autant de fois que vous voulez.',
            'points' => [
                'Accès immédiat après paiement',
                'À votre rythme, sur téléphone ou ordinateur',
                'Accès à vie et accompagnement à vie',
            ],
            // Phrase affichée en haut du catalogue.
            'catalogue' => 'Toutes les formations sont enregistrées : accès immédiat après paiement.',
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
        ['title' => 'Je paie par Mobile Money', 'text' => 'Réglez en quelques secondes avec T-Money (Yas) ou Flooz (Moov), paiement sécurisé par FedaPay.'],
        ['title' => 'J\'accède immédiatement', 'text' => 'Votre formation et votre groupe privé s\'ouvrent aussitôt dans votre espace élève.'],
    ],

    /*
    |----------------------------------------------------------------------
    | Témoignages
    |----------------------------------------------------------------------
    | Uniquement de vrais témoignages d'élèves, publiés avec leur accord.
    | Tant que la liste est vide, la section « Témoignages » est masquée
    | partout sur le site.
    |
    | Trois types sont possibles (voir les exemples ci-dessous) :
    | - « text » : nom, formation suivie, texte, photo facultative ;
    | - « screenshot » : capture d'écran WhatsApp ;
    | - « video » : vidéo YouTube intégrée (même non répertoriée).
    | Les images vont dans public/images/temoignages/ (indiquez seulement
    | le nom du fichier). Pour la capture et la vidéo, « name » et
    | « formation » sont facultatifs.
    |
    | Pour ajouter un témoignage : copiez la ligne d'exemple voulue, retirez
    | les deux barres « // » du début et remplacez les informations.
    */

    'testimonials' => [
        // ['type' => 'text', 'name' => 'Afi K.', 'formation' => 'Formation ChatGPT & Claude', 'text' => 'Grâce à cette formation, je rédige mes rapports deux fois plus vite.', 'photo' => 'afi.jpg'],
        // ['type' => 'screenshot', 'image' => 'whatsapp-kossi.jpg', 'name' => 'Kossi A.', 'formation' => 'Création d\'affiches pro avec l\'IA'],
        // ['type' => 'video', 'youtube' => 'https://www.youtube.com/watch?v=XXXXXXXXXXX', 'name' => 'Ama D.', 'formation' => 'Création de site vitrine web avec l\'IA'],
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
        'founded' => '2023',
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
            ['title' => 'Vous réservez', 'text' => 'Cliquez sur le bouton de réservation et envoyez-nous votre demande.'],
            ['title' => 'Nous fixons un créneau', 'text' => 'Nous convenons ensemble d\'un moment qui vous arrange, du lundi au samedi, entre 8 h et 20 h.'],
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
        ['icon' => 'bolt', 'title' => 'Accès immédiat', 'text' => 'Votre formation s\'ouvre dès la confirmation du paiement.'],
        ['icon' => 'shield-check', 'title' => 'Paiement sécurisé', 'text' => 'Paiement Mobile Money traité par FedaPay.'],
    ],

    /*
    |----------------------------------------------------------------------
    | Programme des formations (modules et leçons)
    |----------------------------------------------------------------------
    | Masqué partout (page formation, récapitulatif de commande, espace
    | élève) tant que cette valeur est « false ». À l'étape 5, le programme
    | sera généré à partir des vraies leçons enregistrées dans la plateforme.
    */

    'show_curriculum' => false,

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

    /*
    |----------------------------------------------------------------------
    | Bonus des formations
    |----------------------------------------------------------------------
    | Une liste par formation (repérée par son slug, l'adresse de sa page).
    | Liste vide [] : la section « Bonus » est complètement masquée.
    | N'ajoutez que des bonus réellement fournis aux élèves.
    |
    | Exemple pour ajouter deux bonus à la formation ChatGPT & Claude :
    | 'chatgpt-claude-initiation-ia' => [
    |     'Texte du premier bonus',
    |     'Texte du deuxième bonus',
    | ],
    */

    'bonuses' => [
        // Formation ChatGPT & Claude
        'chatgpt-claude-initiation-ia' => [],

        // Création d'affiches pro avec l'IA
        'creation-affiches-pro-ia' => [],

        // Création de vidéos publicitaires avec l'IA
        'videos-publicitaires-ia' => [],

        // Création de vidéos d'histoire avec l'IA
        'videos-histoire-ia' => [],

        // Création de vidéos films avec l'IA
        'videos-films-ia' => [],

        // Création de chansons personnalisées avec l'IA
        'chansons-personnalisees-ia' => [],

        // Rédaction d'ebooks et livres professionnels avec l'IA
        'ebooks-livres-professionnels-ia' => [],

        // Création de site vitrine web avec l'IA
        'site-vitrine-web-ia' => [],

        // Création de sites web avec base de données et projets web avancés
        'sites-web-base-de-donnees-projets-avances' => [],

        // Création d'applications et logiciels avec l'IA
        'applications-logiciels-ia' => [],

        // Création de SaaS avec l'IA
        'creation-saas-ia' => [],

        // Création d'agents IA
        'creation-agents-ia' => [],

        // Automatisation avec l'IA
        'automatisation-ia' => [],

        // Créer des formations et apprendre n'importe quelle compétence avec l'IA
        'creer-formations-apprendre-competences-ia' => [],

        // Décrocher des missions sur Upwork avec les compétences IA
        'missions-upwork-competences-ia' => [],

        // Création de contenu TikTok, Facebook et YouTube avec les vidéos IA
        'contenu-tiktok-facebook-youtube-videos-ia' => [],

        // E-commerce de produits digitaux avec l'IA
        'e-commerce-produits-digitaux-ia' => [],
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
            ['question' => 'Combien de temps ai-je accès à une formation ?', 'answer' => 'À vie. Une fois achetée, la formation reste dans votre espace élève : vous la revoyez autant que vous voulez. Vous bénéficiez aussi d\'un accompagnement à vie dans le groupe privé de la formation (tant que la plateforme est en activité).'],
            ['question' => 'Y a-t-il des formations en direct ou en présentiel ?', 'answer' => 'Toutes nos formations sont enregistrées, avec un accès immédiat après paiement. De temps en temps, une formation est aussi proposée en direct sur Google Meet ou en présentiel à Lomé : ces sessions ponctuelles sont annoncées sur le site au moment où elles sont organisées.'],
            ['question' => 'Comment fonctionne le programme d\'affiliation ?', 'answer' => 'Chaque élève dispose d\'un lien personnel. Pour chaque formation achetée grâce à votre lien, vous gagnez 10 % du prix. Vous pouvez demander le retrait de vos gains par Mobile Money ; chaque retrait est vérifié puis validé par notre équipe.'],
            ['question' => 'Puis-je être remboursé ?', 'answer' => 'Oui, dans les 48 heures qui suivent l\'achat, à condition de ne pas avoir commencé la formation (aucune leçon consultée). Il suffit de nous écrire par e-mail ou sur WhatsApp.'],
            ['question' => 'Comment se passe la consultation gratuite ?', 'answer' => 'Elle se réserve depuis la page « Consultation gratuite » du site et se déroule en direct en ligne, par appel vidéo. Elle dure 30 minutes, du lundi au samedi, entre 8 h et 20 h.'],
        ],
        'formation' => [
            ['question' => 'Quand est-ce que j\'accède à la formation ?', 'answer' => 'Immédiatement après la confirmation de votre paiement, depuis votre espace élève.'],
            ['question' => 'Comment payer ?', 'answer' => 'Par Mobile Money (T-Money / Yas ou Flooz / Moov), via la plateforme sécurisée FedaPay.'],
            ['question' => 'Et si j\'ai une question pendant la formation ?', 'answer' => 'Vous la posez dans le groupe privé de la formation, réservé aux élèves inscrits. Vous pouvez aussi nous écrire sur WhatsApp.'],
            ['question' => 'Combien de temps ai-je accès ?', 'answer' => 'À vie, avec un accompagnement à vie dans le groupe privé de la formation (tant que la plateforme est en activité).'],
            ['question' => 'Puis-je être remboursé ?', 'answer' => 'Oui, dans les 48 heures qui suivent l\'achat, si vous n\'avez encore consulté aucune leçon de la formation.'],
            ['question' => 'La formation est-elle en direct ?', 'answer' => 'Non : elle est enregistrée, avec un accès immédiat après paiement. Si une session ponctuelle en direct ou en présentiel à Lomé est organisée, elle est annoncée sur le site.'],
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
        // Responsable du site (mentions légales).
        'owner' => [
            'name' => 'Joseph Ahiankonou',
            'address' => 'Adidogomé, Lycée moderne 2, Lomé, Togo',
            'phone' => '+228 72 24 02 31',
            'email' => 'academyjoseph71@gmail.com',
        ],
        'host' => 'Hostinger International Ltd., 61 Lordou Vironos Street, 6023 Larnaca, Chypre (https://www.hostinger.fr)',
        'updated_at' => '24 septembre 2026',
        'access_duration' => 'L\'accès à la formation est accordé à vie, avec un accompagnement à vie dans le groupe privé de la formation, tant que la plateforme Lomé IA School est en activité.',
        'refund_policy' => 'Vous pouvez demander le remboursement d\'une formation dans les 48 heures qui suivent son achat, uniquement si elle n\'a pas été commencée, c\'est-à-dire si aucune leçon n\'a été consultée. La demande se fait par e-mail ou sur WhatsApp. Passé ce délai, ou dès qu\'une leçon a été consultée, aucun remboursement n\'est possible.',
        'data_retention' => 'Vos données personnelles sont conservées 3 ans après votre dernier achat, puis supprimées.',
    ],

];
