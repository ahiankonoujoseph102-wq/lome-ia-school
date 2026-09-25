# Lomé IA School

Plateforme de vente et de suivi de formations en intelligence artificielle, basée à Lomé (Togo).

## Fonctionnement

- **Formations enregistrées** : toutes les formations sont en vidéo, à suivre à son rythme. Les vidéos sont intégrées depuis **YouTube** (vidéos non répertoriées) ou **Google Drive** (lecteur de secours), derrière un fournisseur vidéo interchangeable (Bunny.net possible plus tard). Chaque leçon peut avoir des fichiers ou des liens Google Drive.
- **Paiement Mobile Money manuel avec preuve** : l'élève paie sur l'un des numéros affichés (Togo, Burkina Faso, Bénin), puis envoie l'identifiant de transaction et une capture. Des contrôles automatiques bloquent les doublons. Sous 50 000 FCFA, l'accès est immédiat (accès provisoire) ; au-delà, il est ouvert après vérification par l'admin. Reçu imprimable dans « Mes achats ».
- **FedaPay est reporté** : il sera ajouté plus tard comme nouveau moyen de paiement, sans refaire le reste.
- **Sessions ponctuelles en direct et en présentiel** : de temps en temps, l'admin ou un modérateur annonce une session pour une formation, en direct sur Google Meet ou en présentiel à Lomé. L'annonce est épinglée dans le groupe de la formation et affichée sur le site (le lien Meet reste réservé aux élèves). Le présentiel se fait sur inscription, avec places limitées, liste d'attente, supplément éventuel payé par Mobile Money, rappels et feuille de présence.
- **Groupe de discussion par formation**, réservé aux acheteurs : texte, images, notes vocales, vidéos courtes, fichiers, modération, notifications sur téléphone.
- **Consultation gratuite** de 30 minutes par appel vidéo : réservation en ligne sans compte (jour, créneau, formulaire), lien Google Meet fixe, confirmation et rappels par e-mail, déplacement ou annulation par lien sécurisé. L'admin règle ses disponibilités, ses congés, et suit les rendez-vous (présence, notes, recommandation de formation) ; une alerte le prévient tant que le lien Meet n'est pas renseigné.
- **Affiliation** : tout inscrit peut activer son espace affilié et partager ses liens. Il reçoit 10 % (réglable) du montant réellement payé sur chaque achat validé passé par son lien (formations et suppléments de sessions), disponible 7 jours après la validation, retirable par Mobile Money à partir de 2 000 FCFA (retraits payés manuellement par l'admin, frais d'envoi déduits). Alertes anti-fraude pour l'admin.
- **Tableau de bord admin** : chiffres des ventes (validées, et provisoires à part), graphique sur 30 jours, formations les plus vendues, « À traiter », menu regroupé. Tout se modifie depuis l'admin, sans toucher au code : formations (prix, textes, image, bonus, ordre, visibilité), élèves (fiche complète, blocage, accès manuel pour un paiement reçu hors du site), codes promo, réglages du site (coordonnées, logo, moyens de paiement, seuils et délais, affiliation, textes et FAQ), témoignages. Exports CSV pour Excel. Sécurité : double authentification obligatoire pour l'équipe (codes de récupération à noter hors du téléphone), mot de passe redemandé et e-mail d'alerte pour les réglages sensibles, journal d'activité.
- **À venir** : mise en production.

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

Sur le serveur, une seule ligne de cron suffit : `* * * * * php artisan schedule:run`. Elle traite la file d'attente (e-mails, notifications), les rappels des sessions, l'e-mail récapitulatif des groupes, la suppression des fichiers lourds expirés, la disponibilité des commissions d'affiliation et les rappels des consultations.

## Logo et icônes

Les originaux du logo et de l'icône sont dans `resources/brand/`. Pour régénérer les versions du site (favicons, icônes de l'écran d'accueil, logos allégés, image de partage) : `php artisan app:brand-assets`.

## Documentation interne

Le fichier [`CLAUDE.md`](CLAUDE.md) décrit le contexte complet du projet, les conventions de code, l'état d'avancement et la feuille de route.
