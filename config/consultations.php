<?php

/*
|==========================================================================
| CONSULTATION GRATUITE (réservation en ligne, sans compte)
|==========================================================================
|
| Joseph fait seul les consultations, en direct en ligne par appel vidéo,
| avec un lien Google Meet fixe. Les horaires, les jours bloqués, les
| durées et le lien Google Meet se règlent depuis l'admin (enregistrés en
| base de données) : les valeurs ci-dessous ne servent qu'au premier réglage.
|
*/

return [

    // Premier réglage (modifiable ensuite dans l'admin « Disponibilités »).
    'defaults' => [
        'duration_minutes' => 30,
        'buffer_minutes' => 15,
        'min_notice_hours' => 3,
        'max_days_ahead' => 30,
        'max_per_day' => null,
        'manage_deadline_hours' => 3,
        // Horaires par jour (1 = lundi … 7 = dimanche).
        'hours' => [
            1 => ['08:00', '20:00'],
            2 => ['08:00', '20:00'],
            3 => ['08:00', '20:00'],
            4 => ['08:00', '20:00'],
            5 => ['08:00', '20:00'],
            6 => ['08:00', '20:00'],
        ],
    ],

    // Rappels au visiteur, en minutes avant le rendez-vous (la veille et 1 h avant).
    'reminders' => [
        'day' => 24 * 60,
        'hour' => 60,
    ],

    // Les rendez-vous et les informations du formulaire sont supprimés ce nombre de mois après la date de la consultation.
    'retention_months' => 12,

];
