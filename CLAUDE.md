# CLAUDE.md : Lomé IA School

Ce fichier donne à Claude le contexte du projet. Le lire avant toute modification.

## La personne qui porte le projet

- N'est **pas développeuse ou développeur** et travaille **uniquement depuis un téléphone**, via Claude Code sur le web.
- Toujours expliquer simplement, en français, ce qui a été fait et pourquoi.
- Signaler clairement tout ce qu'elle doit faire elle-même : créer un compte, fournir une clé d'API, cliquer sur un bouton dans un tableau de bord, valider une pull request…
- Aucune commande ne peut être lancée de son côté sur un ordinateur : Claude exécute, vérifie et pousse tout.
- Finir chaque étape par un résumé : fait / échoué / à faire de son côté.

## Le produit

Plateforme de vente et de suivi de formations en intelligence artificielle, basée à Lomé (Togo).

- **Achat puis accès immédiat** : l'élève paie et accède tout de suite à la formation.
- **Trois formats** :
  - enregistré : vidéos intégrées (YouTube aujourd'hui) ;
  - en direct : sessions Google Meet ;
  - en présentiel : à Lomé.
- **Groupe de discussion par formation**, réservé aux acheteurs, dans le style de WhatsApp : texte, images, captures d'écran, notes vocales, vidéos courtes, fichiers. Il doit être très professionnel et confortable à utiliser, sur mobile d'abord.
- **Affiliation** : un élève gagne 10 % de chaque vente faite via son lien. Retrait par Mobile Money (T-Money/Yas, Flooz/Moov). Au début, les retraits sont validés manuellement par l'admin.
- **Consultation gratuite** sur réservation.
- **Tableau de bord admin** complet.
- **Paiement** : FedaPay.
- **Exigences** : très professionnel, rapide, pensé mobile en priorité, conçu pour convertir les visiteurs en acheteurs.
- **Langue** : français. **Monnaie** : FCFA (XOF). **Fuseau horaire** : `Africa/Lome` (UTC+0, sans heure d'été).

## Stack technique (imposée)

- Laravel 13, PHP 8.4+ (starter kit officiel Livewire, authentification Fortify).
- Livewire 4 (composants monofichiers dans `resources/views/pages`, préfixe ⚡) + Flux UI + Alpine.js + Tailwind CSS 4 (Vite, via `vite-plus`).
- Base de données : SQLite en développement (`database/database.sqlite`), **MySQL en production**. Tout le code doit rester compatible MySQL.
- Temps réel du chat : Pusher (prévoir plus tard un mode de secours par rafraîchissement périodique, sans WebSocket).
- Paiement : FedaPay.
- Hébergement final : Hostinger mutualisé avec SSH. **Pas de processus permanent** : files d'attente et tâches planifiées passent par cron (`schedule:run` chaque minute, `queue:work --stop-when-empty`).
- Fournisseur vidéo **interchangeable** : YouTube maintenant, Bunny.net plus tard.

## Conventions de code

- **Nommage** : code (classes, méthodes, variables, tables, colonnes) en anglais ; tout texte visible par l'utilisateur en français.
- **Textes d'interface** : le starter kit utilise des clés anglaises traduites dans `lang/fr.json`. Garder ce mécanisme pour ces écrans ; pour les nouveaux écrans, le français peut être écrit directement dans les vues Blade. Messages de validation : `lang/fr/validation.php`.
- **Style** : suivre le style existant ; `composer lint` (Pint) avant chaque commit.
- **Montants** : en FCFA, stockés en **entiers** (le XOF n'a pas de décimales). Jamais de float pour l'argent. Affichage : `25 000 FCFA`.
- **Dates** : l'application tourne en `Africa/Lome`. Affichage en français (`translatedFormat`).
- **Compatibilité MySQL** : uniquement via les migrations et le query builder / Eloquent. Pas de SQL brut propre à SQLite. Attention aux types (`enum` natifs à éviter, préférer `string` + enum PHP), aux index uniques et aux clés étrangères.
- **Services externes** derrière une interface (ex. `VideoProvider` avec une implémentation `YouTubeVideoProvider`), choisis par la configuration, pour pouvoir changer de fournisseur sans toucher au reste du code.
- **Clés et secrets** uniquement dans `.env` (jamais commités) ; documenter chaque nouvelle variable dans `.env.example`.
- **Mobile d'abord** : concevoir chaque écran pour un petit écran, puis l'agrandir.
- **Tâches longues** (e-mails, traitements de médias) : en file d'attente (driver `database`), jamais pendant la requête.
- **Tests** : un test de fonctionnalité (PHPUnit) pour chaque parcours important (achat, accès, affiliation, retrait).

## Site vitrine : où se trouve quoi

- **Textes modifiables** (coordonnées, chiffres, témoignages, FAQ, formateur, mentions légales) : `config/site.php`. Les emplacements à remplir sont écrits entre crochets `[...]` et surlignés en doré sur le site.
- **Formations** : table `formations` (modèle `App\Models\Formation`), remplie par `database/seeders/FormationSeeder.php` (relançable sans doublon : `php artisan db:seed --class=FormationSeeder`). Catégories et formats : enums `App\Enums\FormationCategory` et `App\Enums\FormationFormat`.
- **Images** : logo dans `public/images/logo.svg` (ou `.png`), photo du formateur dans `public/images/formateur.jpg`, images des formations dans `public/images/formations/` (nom du fichier à renseigner dans la colonne `image`), photos des témoignages dans `public/images/temoignages/`. Sans image, un visuel en dégradé est dessiné automatiquement.
- **Vues** : `resources/views/site/` (pages), `resources/views/components/site/` (composants), mise en page `resources/views/layouts/site.blade.php`.
- **CSS/JS du site vitrine**, séparés de l'espace membre pour rester légers : `resources/css/site.css` (Tailwind 4, couleurs `night`, `electric`, `gold`) et `resources/js/site.js` (Alpine.js : menu, filtres du catalogue, animations au défilement).
- **Images de partage** (Open Graph, 1200 × 630) générées en PHP (GD) par `App\Services\OgImageGenerator`, mises en cache dans `storage/framework/cache/og`. Routes `/og/site.png` et `/og/formations/{slug}.png`.
- **SEO** : titre, description, Open Graph et données structurées par page ; `/sitemap.xml` et `/robots.txt` (indexation bloquée hors production).
- Le bouton « Acheter maintenant » mène au récapitulatif de commande `/commande/{slug}` ; le bouton « Payer par Mobile Money » y affiche « bientôt disponible » en attendant FedaPay (étape 4). La réservation de consultation passe par WhatsApp en attendant l'étape 9.

## Comptes, rôles et accès

- **Inscription** : nom, numéro WhatsApp (obligatoire, unique, stocké au format international `+228…` via `App\Support\PhoneNumber`, règle `App\Rules\WhatsAppNumber`), e-mail, mot de passe (pas de confirmation, bouton « Afficher »). Pages d'authentification au design du site : `resources/views/site/auth/`.
- **Rôles** : `App\Enums\UserRole` (élève, admin), colonne `users.role` non modifiable par formulaire. Créer ou promouvoir un admin : `php artisan app:make-admin email@exemple.com` (le mot de passe est demandé au clavier, jamais écrit dans le code). Zone admin protégée par le middleware `admin` et la permission `access-admin` (pages à l'étape 10, préfixe `/admin`).
- **Inscriptions aux formations** : table `enrollments` (élève, formation, `source` = `purchase` ou `admin`, `granted_at`). Règle d'accès au contenu : `App\Policies\FormationPolicy::access` (élève inscrit ou admin), à utiliser avec `can:access,formation` aux étapes 5 et 6. Donner ou retirer un accès : `php artisan app:grant-access email slug [--revoke]`.
- **Retour après connexion** : un visiteur qui passe par `/commande/{slug}` y revient automatiquement après inscription ou connexion (URL « intended » de la session).
- **Espace élève** « Mon espace » (`/mon-espace`) : vues `resources/views/site/student/`, variante `student` du layout du site (barre de navigation en bas sur téléphone). Profil et photo : `/mon-espace/profil` (photo recadrée en 320 × 320 par `App\Services\AvatarProcessor`, disque `public` : nécessite `php artisan storage:link` sur le serveur). Les pages Flux du kit ne servent plus que pour la sécurité (`/settings/security`) et l'apparence.
- **E-mails** : `App\Mail\WelcomeMail` (file d'attente) envoyé à l'inscription par `App\Listeners\SendWelcomeEmail`. En local, `MAIL_MAILER=log` : les e-mails sont écrits dans `storage/logs/laravel.log` quand la file est traitée (`php artisan queue:work --stop-when-empty`).

## Commandes utiles

```bash
composer setup     # installation complète
composer dev       # serveur de développement
composer test      # Pint + PHPStan + PHPUnit (à lancer avant chaque push)
npm run build      # compilation CSS/JS
php artisan migrate
```

## Notes sur l'environnement cloud de Claude

- Dans l'environnement Claude Code web, les téléchargements Composer via l'API GitHub peuvent être refusés (403). Solution qui marche :
  `composer config -g use-github-api false` puis `composer install --prefer-source`.
  Le paquet `phpstan/phpstan` n'existe qu'en archive : le reconstruire depuis son dépôt git avec `git archive` et le placer dans le cache Composer (`~/.cache/composer/files/phpstan/phpstan/<sha1 de l'URL>.zip`).
- `fonts.bunny.net` est bloqué : la police Instrument Sans est donc servie en local via le paquet npm `@fontsource-variable/instrument-sans`. C'est aussi plus rapide pour les visiteurs.
- PHP 8.4 minimum (les dépendances Symfony 8 l'exigent) : sélectionner PHP 8.4 dans le panneau Hostinger.
- Hostinger mutualisé ne dispose pas forcément de Node.js : prévoir à l'étape 11 de compiler les fichiers CSS/JS ailleurs (GitHub Actions) avant le déploiement.

## Feuille de route

1. **Mise en place** : projet Laravel, starter kit Livewire, français, fuseau de Lomé, SQLite. ✅
2. **Site vitrine** : accueil, catalogue des formations, pages de formation orientées conversion. ✅
3. **Comptes et espace élève** : inscription, connexion, tableau de bord élève, « mes formations ». ✅
4. **Paiement FedaPay et accès automatique** : commande, paiement, webhook, accès immédiat.
5. **Lecteur de cours** : modules, leçons, vidéos (fournisseur interchangeable), progression.
6. **Groupes de discussion** : chat par formation (texte, images, vocaux, vidéos courtes, fichiers), Pusher.
7. **Sessions en direct et présentiel** : planning, liens Google Meet, sessions à Lomé, rappels.
8. **Affiliation** : liens, suivi des ventes, commissions de 10 %, demandes de retrait Mobile Money.
9. **Consultation gratuite** : réservation de créneaux.
10. **Tableau de bord admin** : formations, élèves, ventes, retraits, chat, statistiques.
11. **Mise en production** : Hostinger, MySQL, cron, e-mails, sauvegardes, nom de domaine.
