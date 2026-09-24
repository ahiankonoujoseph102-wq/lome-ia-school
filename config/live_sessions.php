<?php

/*
|==========================================================================
| SESSIONS PONCTUELLES EN DIRECT (GOOGLE MEET) ET EN PRÉSENTIEL À LOMÉ
|==========================================================================
|
| Les sessions sont créées par l'admin ou un modérateur (écran « Sessions »).
| Aucune session n'est inventée : ce fichier ne contient que les réglages.
|
*/

return [

    // Lieu proposé par défaut pour une session en présentiel (modifiable à chaque session).
    'default_location' => 'Adidogomé, Lycée moderne 2, Lomé',

    // Durée proposée par défaut (en minutes).
    'default_duration' => 120,

    // Un élève peut annuler son inscription en présentiel jusqu'à ce nombre d'heures avant le début.
    'cancel_deadline_hours' => 24,

    // Le bouton « Rejoindre la session » s'active ce nombre de minutes avant le début.
    'join_window_minutes' => 15,

    // Rappels envoyés avant la session (en minutes) : la veille (24 h) et 1 h avant.
    'reminders' => [
        'day' => 24 * 60,
        'hour' => 60,
    ],

    // Place réservée pour un supplément : délai pour envoyer la preuve de paiement (en heures).
    // Passé ce délai sans paiement, la place est libérée pour la liste d'attente.
    'payment_hold_hours' => 24,

    // Pays voisins dont l'heure diffère de Lomé (GMT) : l'heure locale est précisée à côté.
    'other_timezones' => [
        'Bénin' => 'Africa/Porto-Novo',
    ],

];
