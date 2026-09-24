# Lomé IA School

Plateforme de vente et de suivi de formations en intelligence artificielle, basée à Lomé (Togo).

## Fonctionnement

- **Formations enregistrées** : toutes les formations sont en vidéo, à suivre à son rythme. Les vidéos sont intégrées depuis **YouTube** (vidéos non répertoriées) ou **Google Drive** (lecteur de secours), derrière un fournisseur vidéo interchangeable (Bunny.net possible plus tard). Chaque leçon peut avoir des fichiers ou des liens Google Drive.
- **Paiement Mobile Money manuel avec preuve** : l'élève paie sur l'un des numéros affichés (Togo, Burkina Faso, Bénin), puis envoie l'identifiant de transaction et une capture. Des contrôles automatiques bloquent les doublons. Sous 50 000 FCFA, l'accès est immédiat (accès provisoire) ; au-delà, il est ouvert après vérification par l'admin. Reçu imprimable dans « Mes achats ».
- **FedaPay est reporté** : il sera ajouté plus tard comme nouveau moyen de paiement, sans refaire le reste.
- **Sessions ponctuelles en direct et en présentiel** : de temps en temps, l'admin ou un modérateur annonce une session pour une formation, en direct sur Google Meet ou en présentiel à Lomé. L'annonce est épinglée dans le groupe de la formation et affichée sur le site (le lien Meet reste réservé aux élèves). Le présentiel se fait sur inscription, avec places limitées, liste d'attente, supplément éventuel payé par Mobile Money, rappels et feuille de présence.
- **Groupe de discussion par formation**, réservé aux acheteurs : texte, images, notes vocales, vidéos courtes, fichiers, modération, notifications sur téléphone.
- **Consultation gratuite** de 30 minutes par appel vidéo (réservation par WhatsApp en attendant la page de réservation).
- **À venir** : affiliation (10 % par vente, retrait par Mobile Money), réservation de la consultation, tableau de bord admin complet, mise en production.

## Stack

| Élément | Choix |
| --- | --- |
| Framework | Laravel 13 (PHP 8.4+) |
| Interface | Livewire 4, Flux, Alpine.js, Tailwind CSS 4 |
| Authentification | Laravel Fortify (starter kit Livewire) : inscription, double authentification, passkeys |
| Base de données | SQLite en développement, MySQL en production |
| Paiement | Mobile Money manuel avec preuve de paiement (FedaPay plus tard) |
| Vidéos | YouTube et Google Drive (fournisseur interchangeable) |
| Temps réel (chat) | Pusher, avec mise à jour régulière automatique si Pusher est absent |
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

## Tâches planifiées

Sur le serveur, une seule ligne de cron suffit : `* * * * * php artisan schedule:run`. Elle traite la file d'attente (e-mails, notifications), les rappels des sessions, l'e-mail récapitulatif des groupes et la suppression des fichiers lourds expirés.

## Documentation interne

Le fichier [`CLAUDE.md`](CLAUDE.md) décrit le contexte complet du projet, les conventions de code, l'état d'avancement et la feuille de route.
