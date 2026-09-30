<?php

/*
|==========================================================================
| FORMATIONS PROPOSÉES EN DIRECT (ÉDITION LIMITÉE DANS LE TEMPS)
|==========================================================================
|
| Clé = adresse (slug) de la formation. Tant que « ends_at » n'est pas passé
| (heure de Lomé), la page de la formation affiche l'encadré « Formation en direct »
| (dates, horaires, déroulement, programme, questions fréquentes), l'accueil
| affiche un bandeau qui y mène, et Ama connaît ces informations.
| Après « ends_at », tout disparaît automatiquement.
|
| Tous les textes ci-dessous ont été fournis par Joseph : ne rien y inventer
| (ni chiffre, ni témoignage, ni promesse de résultat). Jamais « certificat
| reconnu », « diplôme » ni « certifié » : on parle d'« attestation de participation ».
|
| Le lien Google Meet n'est jamais écrit ici : il est saisi par l'admin dans
| l'écran « Sessions » (une session par séance) et n'est montré qu'aux élèves.
|
*/

return [

    'creation-agents-ia' => [
        // Heures de Lomé (GMT). Fin de la dernière séance : l'offre disparaît du site ensuite.
        'starts_at' => '2026-10-01 20:00',
        'ends_at' => '2026-10-07 23:00',
        'start_time' => '20:00',
        'end_time' => '23:00',

        // Une séance par soir : date (AAAA-MM-JJ) et thème.
        'sessions' => [
            ['date' => '2026-10-01', 'title' => 'Comprendre les agents IA et choisir son projet d\'agent'],
            ['date' => '2026-10-02', 'title' => 'Écrire les instructions d\'un agent (rôle, ton, règles, limites)'],
            ['date' => '2026-10-03', 'title' => 'Créer son premier agent avec Gemini (Gems) et lui donner des documents'],
            ['date' => '2026-10-04', 'title' => 'Créer un agent avec Claude (Projets), le tester et le corriger'],
            ['date' => '2026-10-05', 'title' => 'Un agent pour répondre aux clients sur WhatsApp Business'],
            ['date' => '2026-10-06', 'title' => 'Proposer ses services d\'agents IA (trouver des clients, fixer ses prix)'],
            ['date' => '2026-10-07', 'title' => 'Projet final, présentation des agents, remise des attestations'],
        ],

        // Déroulement (encadré de la page de la formation).
        'details' => [
            ['icon' => 'video-camera', 'title' => 'En direct sur Google Meet', 'text' => 'Chaque séance a lieu en direct sur Google Meet et elle est enregistrée.'],
            ['icon' => 'archive-box', 'title' => 'Enregistrements et documents à vie', 'text' => 'L\'enregistrement et un document de chaque séance sont disponibles à vie dans le groupe d\'accompagnement de la formation, sur le site.'],
            ['icon' => 'arrow-path', 'title' => 'Inscription toute la semaine', 'text' => 'Vous pouvez vous inscrire pendant toute la semaine et rattraper les séances manquées grâce aux enregistrements. Places non limitées.'],
            ['icon' => 'device-phone-mobile', 'title' => 'Téléphone ou ordinateur', 'text' => 'L\'un ou l\'autre suffit pour suivre les séances.'],
            ['icon' => 'lifebuoy', 'title' => 'Accompagnement à vie', 'text' => 'Posez vos questions dans le groupe d\'accompagnement de la formation, aussi longtemps que vous en avez besoin.'],
            ['icon' => 'document-check', 'title' => 'Attestation de participation', 'text' => 'Une attestation de participation délivrée par Lomé IA School vous est remise à la fin de la formation.'],
        ],

        // « Inclus avec la formation » (remplace la liste habituelle pendant l'offre).
        'included' => [
            'Les 7 séances en direct sur Google Meet',
            'L\'enregistrement et un document de chaque séance, disponibles à vie dans le groupe d\'accompagnement',
            'L\'accès au groupe d\'accompagnement de la formation',
            'L\'accompagnement à vie',
            'Une attestation de participation délivrée par Lomé IA School à la fin de la formation',
        ],
    ],

];
