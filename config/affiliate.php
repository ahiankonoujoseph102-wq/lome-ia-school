<?php

/*
|==========================================================================
| PROGRAMME D'AFFILIATION
|==========================================================================
|
| Règles validées par Joseph (étape 8). Les textes du site (page « Devenez
| affilié », CGV, espace affilié) affichent automatiquement ces valeurs.
|
| - Tout inscrit peut devenir affilié, même sans avoir acheté.
| - Commission sur chaque achat VALIDÉ par l'admin (formations et suppléments
|   de sessions en présentiel), jamais sur un accès provisoire.
| - Le dernier lien cliqué compte, pendant « cookie_days » jours.
| - Aucune commission sur ses propres achats.
|
*/

return [

    // Commission, en pourcentage du prix payé.
    'rate' => 10,

    // Durée de validité d'un clic sur un lien d'affiliation (en jours).
    'cookie_days' => 30,

    // La commission devient disponible ce nombre de jours après la validation du paiement.
    'availability_days' => 7,

    // Montant minimum d'une demande de retrait (en FCFA).
    'min_withdrawal' => 2000,

    // Alerte pour l'admin : nombre d'inscriptions sans aucun achat à partir duquel un affilié est signalé.
    'alert_signups_without_purchase' => 10,

    // Nom des cookies (dernier lien cliqué, identifiant anonyme du visiteur pour compter les clics une seule fois).
    'cookie' => 'lis_ref',
    'visitor_cookie' => 'lis_visitor',

];
