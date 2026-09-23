# Lomé IA School

Plateforme de formation en intelligence artificielle basée à Lomé (Togo).

Les élèves achètent une formation, paient via FedaPay (Mobile Money ou carte) et y accèdent immédiatement. Trois formats sont proposés :

- **Formations enregistrées** : vidéos intégrées (YouTube aujourd'hui, Bunny.net plus tard) ;
- **Formations en direct** : sessions Google Meet ;
- **Formations en présentiel** : à Lomé.

Chaque formation dispose d'un groupe de discussion réservé à ses acheteurs (texte, images, notes vocales, vidéos courtes, fichiers). Un programme d'affiliation reverse 10 % de chaque vente réalisée via le lien d'un élève, retirable par Mobile Money (T-Money/Yas, Flooz/Moov). La plateforme propose aussi une consultation gratuite sur réservation et un tableau de bord d'administration.

## Stack

| Élément | Choix |
| --- | --- |
| Framework | Laravel 13 (PHP 8.3+) |
| Interface | Livewire 4, Flux, Alpine.js, Tailwind CSS 4 |
| Authentification | Laravel Fortify (starter kit Livewire) : inscription, double authentification, passkeys |
| Base de données | SQLite en développement, MySQL en production |
| Paiement | FedaPay (XOF) |
| Temps réel (chat) | Pusher |
| Hébergement | Hostinger mutualisé (SSH, cron) |

Langue : français. Monnaie : FCFA (XOF). Fuseau horaire : `Africa/Lome`.

## Installation locale

```bash
composer setup   # dépendances PHP et JS, .env, clé, migrations, compilation
composer dev     # serveur de développement
```

## Vérifications

```bash
composer test    # style (Pint), analyse statique (PHPStan), tests (PHPUnit)
npm run build    # compilation CSS/JS
```

## Documentation interne

Le fichier [`CLAUDE.md`](CLAUDE.md) décrit le contexte complet du projet, les conventions de code et la feuille de route.
