<?php

/*
|==========================================================================
| GROUPES DE DISCUSSION (un par formation)
|==========================================================================
|
| Limites des fichiers, rythme de mise à jour et règles du groupe par défaut.
| Les règles de chaque groupe se modifient ensuite depuis l'écran admin « Groupes ».
|
*/

return [

    // Messages chargés à l'ouverture d'un groupe, puis à chaque remontée dans l'historique.
    'page_size' => 40,

    // Messages non lus chargés au maximum à l'ouverture (au-delà, l'historique se charge en remontant).
    'max_initial_messages' => 300,

    // Longueur maximale d'un message texte (en caractères).
    'max_text_length' => 4000,

    // Mise à jour régulière quand le temps réel (Pusher) n'est pas disponible (en secondes).
    'poll_interval' => 4,

    // Vérification de sécurité quand le temps réel fonctionne (en secondes).
    'safety_poll_interval' => 30,

    // Messages épinglés au maximum par groupe.
    'max_pinned' => 5,

    'attachments' => [
        // Images et captures : réduites à 1600 px et compressées en JPEG.
        'image' => ['max_kb' => 15 * 1024, 'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'gif'], 'max_side' => 1600, 'quality' => 82],

        // Notes vocales enregistrées dans le navigateur (MP4/AAC sur iPhone et Android récents, WebM sinon).
        'voice' => [
            'max_kb' => 15 * 1024,
            'max_seconds' => 300,
            'mimetypes' => ['audio/mp4', 'audio/x-m4a', 'audio/m4a', 'audio/aac', 'audio/x-hx-aac-adts', 'audio/webm', 'audio/ogg', 'audio/mpeg', 'video/mp4', 'video/webm'],
        ],

        // Vidéos courtes : envoyées telles quelles (pas de compression sur le serveur).
        'video' => ['max_kb' => 50 * 1024, 'mimetypes' => ['video/mp4', 'video/quicktime', 'video/webm', 'video/3gpp']],

        // Fichiers : liste blanche uniquement, aucun exécutable.
        'file' => ['max_kb' => 20 * 1024, 'mimes' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'txt']],
    ],

    // Notes vocales et vidéos : fichier supprimé automatiquement après ce nombre de mois (tâche planifiée
    // chaque nuit). Le message reste avec la mention « Fichier expiré ». Textes, images et documents sont conservés.
    // Mettre 0 pour ne jamais supprimer.
    'media_retention_months' => 12,

    // Durées de mise en sourdine proposées aux modérateurs (en heures).
    'mute_durations' => [
        1 => '1 heure',
        24 => '24 heures',
        72 => '3 jours',
        168 => '7 jours',
        720 => '30 jours',
    ],

    // Heure d'envoi de l'e-mail récapitulatif des messages non lus (heure de Lomé), au plus une fois par jour.
    'digest_time' => '18:00',

    // Règles affichées à l'entrée de chaque nouveau groupe (modifiables ensuite par l'admin, groupe par groupe).
    'default_rules' => <<<'TXT'
Bienvenue dans le groupe de la formation ! Ce groupe est réservé aux élèves et à l'équipe de Lomé IA School.

• Restez respectueux et bienveillant envers tous les membres.
• Parlez de la formation : questions, entraide, partage de vos réalisations.
• Pas de publicité, de vente, de liens d'affiliation ni de messages répétés.
• Ne partagez pas le contenu de la formation (vidéos, fichiers) en dehors du groupe.
• Ne demandez pas et ne publiez pas de numéros de téléphone ou de données personnelles.
• Utilisez le bouton « Signaler » si un message pose problème : l'équipe s'en occupe.
• Les notes vocales et les vidéos sont supprimées automatiquement 12 mois après leur envoi : enregistrez celles que vous voulez garder.

L'équipe peut supprimer un message, mettre un membre en sourdine ou l'exclure du groupe en cas de non-respect de ces règles.
TXT,

];
