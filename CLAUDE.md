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

## Règle sur les contenus (impérative)

- **Ne jamais inventer** de bonus, chiffres, durées, nombres de vidéos ou de leçons, résultats promis, avis, notes ou autres promesses.
- Tout contenu marketing qui n'a pas été fourni par la porteuse du projet doit lui être **signalé explicitement** (liste claire, formation par formation) avant d'être considéré comme validé.
- Les textes décrivent **ce que l'élève apprend à faire**, jamais un résultat garanti (revenus, clients, ventes, gain de temps, popularité). Le ton reste vendeur et dynamique.

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

- **Textes modifiables** (coordonnées, horaires, chiffres, « accès à vie / accompagnement à vie », témoignages, FAQ, formateur, mentions légales, réseaux sociaux) : `config/site.php`. Les emplacements à remplir sont écrits entre crochets `[...]` et surlignés en doré sur le site (il n'en reste aucun aujourd'hui).
- **Témoignages** : liste `testimonials` de `config/site.php`, vide pour l'instant ; tant qu'elle est vide, la section est masquée partout. Trois types (exemples commentés dans le fichier) : `text`, `screenshot` (capture WhatsApp) et `video` (YouTube, lecteur chargé au clic). Lecture et filtrage des entrées incomplètes : `App\Support\Testimonials`, liens YouTube : `App\Support\YouTube`.
- **Réseaux sociaux** : un réseau dont le lien est vide dans `social` est masqué automatiquement.
- **Chiffres officiels** (fournis par la porteuse du projet) : plus de 1 000 personnes formées, école créée en 2023, horaires du lundi au samedi de 8 h à 20 h, consultation gratuite de 30 minutes. Ne jamais inventer de chiffres, notes ou avis.
- **Formations** : table `formations` (modèle `App\Models\Formation`), remplie par `database/seeders/FormationSeeder.php` (relançable sans doublon : `php artisan db:seed --class=FormationSeeder`). Catégories et formats : enums `App\Enums\FormationCategory` et `App\Enums\FormationFormat`. **Bonus** : liste par slug dans `config/site.php` (clé `bonuses`, toutes vides aujourd'hui ; section « Bonus » masquée si vide), lue par l'attribut `Formation::bonuses` ; la colonne `formations.bonuses` a été supprimée. Section « Inclus avec la formation » toujours affichée (clé `included`, dont l'accès au groupe privé).
- **Images** : logo dans `public/images/logo.svg` (ou `.png`), photo du formateur dans `public/images/formateur.jpg` (800 × 800 px, déjà en place ; texte alternatif `trainer.photo_alt`), images des formations dans `public/images/formations/` (nom du fichier à renseigner dans la colonne `image`), photos des témoignages dans `public/images/temoignages/`. Sans image, un visuel en dégradé est dessiné automatiquement.
- **Vues** : `resources/views/site/` (pages), `resources/views/components/site/` (composants), mise en page `resources/views/layouts/site.blade.php`.
- **CSS/JS du site vitrine**, séparés de l'espace membre pour rester légers : `resources/css/site.css` (Tailwind 4, couleurs `night`, `electric`, `gold`) et `resources/js/site.js` (Alpine.js : menu, filtre du catalogue, animations au défilement).
- **Design toujours sombre**, quel que soit le mode du téléphone : `<meta name="color-scheme" content="dark">` dans le layout et, dans `site.css`, un bloc `:root` / `body` (fond `night-950`, texte, police) placé **hors des `@layer` Tailwind** pour garder la priorité sur toute feuille de style extérieure. Ne pas remettre le fond de page dans `@layer base` seulement, et ne pas utiliser de variantes `dark:` sur le site vitrine.
- **Animations** : aucun contenu ne doit dépendre d'une animation pour être visible. `data-reveal` est visible au repos ; `site.js` ajoute `reveal-play` seulement aux éléments encore sous l'écran (désactivé si l'utilisateur préfère moins d'animations).
- **Images de partage** (Open Graph, 1200 × 630) générées en PHP (GD) par `App\Services\OgImageGenerator`, mises en cache dans `storage/framework/cache/og`. Routes `/og/site.png` et `/og/formations/{slug}.png`.
- **SEO** : titre, description, Open Graph et données structurées par page ; `/sitemap.xml` et `/robots.txt` (indexation bloquée hors production).
- Le bouton « Acheter maintenant » mène au récapitulatif de commande `/commande/{slug}` ; le bouton « Payer par Mobile Money » y affiche « bientôt disponible » en attendant FedaPay (étape 4). La réservation de consultation passe par WhatsApp en attendant l'étape 9.

## Comptes, rôles et accès

- **Inscription** : nom, numéro WhatsApp (obligatoire, unique, stocké au format international `+228…` via `App\Support\PhoneNumber`, règle `App\Rules\WhatsAppNumber`), e-mail, mot de passe (pas de confirmation, bouton « Afficher »), case facultative **non cochée par défaut** « Je souhaite être informé(e) des nouvelles formations » (colonne `users.wants_newsletter`, modifiable dans le profil, composant `x-site.newsletter-checkbox`). N'envoyer d'annonces qu'aux élèves dont `wants_newsletter` est vrai. Pages d'authentification au design du site : `resources/views/site/auth/`.
- **Rôles** : `App\Enums\UserRole` (élève, admin), colonne `users.role` non modifiable par formulaire. Créer ou promouvoir un admin : `php artisan app:make-admin email@exemple.com` (le mot de passe est demandé au clavier, jamais écrit dans le code). Zone admin protégée par le middleware `admin` et la permission `access-admin` (pages à l'étape 10, préfixe `/admin`).
- **Inscriptions aux formations** : table `enrollments` (élève, formation, `source` = `purchase` ou `admin`, `granted_at`). Règle d'accès au contenu : `App\Policies\FormationPolicy::access` (élève inscrit ou admin), à utiliser avec `can:access,formation` aux étapes 5 et 6. Donner ou retirer un accès : `php artisan app:grant-access email slug [--revoke]`.
- **Retour après connexion** : un visiteur qui passe par `/commande/{slug}` y revient automatiquement après inscription ou connexion (URL « intended » de la session).
- **Espace élève** « Mon espace » (`/mon-espace`) : vues `resources/views/site/student/`, variante `student` du layout du site (barre de navigation en bas sur téléphone). Profil et photo : `/mon-espace/profil` (photo recadrée en 320 × 320 par `App\Services\AvatarProcessor`, disque `public` : nécessite `php artisan storage:link` sur le serveur). Sécurité du compte (mot de passe, double authentification, passkeys) : `/mon-espace/securite` (`App\Http\Controllers\Student\SecurityController`), via les routes de Fortify et de Laravel Passkeys.
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

## État d'avancement (passation)

**Terminé et fusionné dans `main`** : étapes 1 (mise en place), 2 (site vitrine) et 3 (comptes et espace élève), y compris les écrans « Sécurité du compte » au design du site (PR #1, #2, #3).

**Terminé sur la branche `claude/update-showcase-site-content-qub9o0`, en attente de validation de l'aperçu (pas encore de pull request)** : mise à jour des contenus du site vitrine. Chiffres réels, horaires, durée de la consultation, biographie et points forts du formateur, photo `formateur.jpg` (l'original a été retiré du dépôt), « Accès à vie » et « Accompagnement à vie » mis en avant (accueil, pages formation, FAQ, CGV), bloc formateur sur l'accueil, témoignages vides et masqués (3 types prévus), pages légales complétées (responsable du site, remboursement sous 48 h si aucune leçon consultée, conservation des données 3 ans après le dernier achat ; ni RCCM ni NIF). Puis, après retour de la porteuse du projet : formats clarifiés (l'enregistré est la règle, le direct et le présentiel sont des sessions ponctuelles ; filtre par format retiré du catalogue ; image de partage mise à jour), consultation en direct en ligne par appel vidéo (jamais sur place), case « nouvelles formations » à l'inscription et dans le profil, politique de confidentialité adaptée. Puis : tous les bonus inventés retirés (config vide par formation). Puis : correction de l'affichage sur téléphone (textes presque invisibles dans l'aperçu, recoloration par le mode sombre automatique de Chrome Android, contenu masqué en attendant les animations). Puis : programme (modules, leçons et chiffres associés) masqué partout, promesses de résultat reformulées dans les sous-titres et descriptions des formations. 124 tests verts.

**Reste à fournir par la porteuse du projet** : logo, images des formations, vrais témoignages, liens des réseaux sociaux, validation de l'aperçu. Validés tels quels par la porteuse du projet : « Ce que vous allez apprendre », « Pour qui est cette formation ? », formations mises en avant, « publication en librairie » (ebooks), « même en partant de zéro en programmation » (applications). Bonus éventuels à fournir.

**Prochaine étape** : après validation de l'aperçu et fusion, étape 4 : paiement FedaPay et accès automatique (commencer en mode test/sandbox ; les clés vont dans `.env`, jamais dans le code ni dans la conversation pour les clés de production). Le remboursement sous 48 h « si aucune leçon consultée » suppose de suivre les leçons ouvertes (étape 5).

**Décisions prises** :
- Mot de passe : 8 caractères minimum avec lettres et chiffres en production (`AppServiceProvider`), sans confirmation à l'inscription.
- Vérification de l'e-mail désactivée (accès immédiat après paiement privilégié).
- Double authentification facultative ; une activation commencée reste « en attente » (reprise ou annulation possible) et n'est exigée à la connexion qu'une fois confirmée par un code.
- Compte admin : academyjoseph71@gmail.com, à créer à l'étape 11 avec `php artisan app:make-admin` (mot de passe saisi au clavier).
- Vidéos des formations : YouTube en vidéos **non répertoriées** (étape 5), derrière l'interface de fournisseur vidéo interchangeable.
- **Formats** : toutes les formations sont **enregistrées**, accès immédiat après paiement, aux prix en place. Le direct (Google Meet) et le présentiel (Lomé) sont des **sessions ponctuelles** organisées de temps en temps et annoncées sur le site, jamais présentées comme des options permanentes. Aucun bouton WhatsApp, aucune date ni calendrier pour ces sessions en attendant l'étape 7. Textes : bloc `formats` de `config/site.php` ; libellés : `App\Enums\FormationFormat`.
- **Consultation gratuite** : réservée sur le site, elle se déroule **en direct en ligne, par appel vidéo** (jamais sur place), 30 minutes. Textes : bloc `consultation` de `config/site.php`.
- L'équipe existe : la phrase « une équipe proche de vous » est exacte.

**Contournement des blocages réseau (environnement Claude Code web)** :
- Composer : `composer config -g use-github-api false`, puis `COMPOSER_ALLOW_SUPERUSER=1 composer install --prefer-source`.
- `phpstan/phpstan` (archive uniquement) : cloner `https://github.com/phpstan/phpstan` (`git clone --bare --filter=blob:none`), puis `git archive --format=zip --prefix=phpstan-phpstan-<court>/ <commit> -o ~/.cache/composer/files/phpstan/phpstan/<sha1 de l'URL api.github.com/repos/phpstan/phpstan/zipball/<commit>>.zip` avant de relancer l'installation.
- Polices : `fonts.bunny.net` est bloqué, d'où `@fontsource-variable/instrument-sans` (npm) ; npm fonctionne normalement.
- Aperçus pour la porteuse du projet : copies statiques publiées en artifact claude.ai (jamais dans le dépôt), générées à partir des pages rendues localement (`php artisan serve` + Playwright avec `/opt/pw-browsers/chromium-1194/chrome-linux/chrome`). claude.ai enveloppe la page dans son propre gabarit (fond clair, police système 14 px, `color-scheme: light`) : **vérifier l'aperçu à l'intérieur d'un gabarit simulé**, en mode clair, sombre et sombre forcé (`--enable-features=WebContentsForceDark`), jamais seulement le fichier brut.

## Feuille de route

1. **Mise en place** : projet Laravel, starter kit Livewire, français, fuseau de Lomé, SQLite. ✅
2. **Site vitrine** : accueil, catalogue des formations, pages de formation orientées conversion. ✅
3. **Comptes et espace élève** : inscription, connexion, tableau de bord élève, « mes formations ». ✅
4. **Paiement FedaPay et accès automatique** : commande, paiement, webhook, accès immédiat.
5. **Lecteur de cours** : modules, leçons, vidéos (fournisseur interchangeable), progression. Le **programme affiché sur la page formation** (et le récapitulatif de commande) devra être généré automatiquement à partir des vraies leçons enregistrées dans la plateforme, et n'apparaître que si la formation a des leçons. Aujourd'hui, le programme rédigé dans `FormationSeeder` (colonne `curriculum`) est masqué partout via `config('site.show_curriculum')` = `false` et `Formation::publicCurriculum()` ; le code d'affichage est conservé pour être rebranché sur les vraies leçons.
6. **Groupes de discussion** : chat par formation (texte, images, vocaux, vidéos courtes, fichiers), Pusher.
7. **Sessions ponctuelles en direct et en présentiel** : l'admin pourra annoncer une session en direct ou en présentiel pour une formation ; l'annonce apparaît alors sur la page de la formation. Pour le direct, le lien Google Meet est partagé dans le groupe de discussion de la formation, auquel chaque acheteur est ajouté automatiquement après paiement (étape 6). Rappels éventuels.
8. **Affiliation** : liens, suivi des ventes, commissions de 10 %, demandes de retrait Mobile Money.
9. **Consultation gratuite** : réservation de créneaux.
10. **Tableau de bord admin** : formations, élèves, ventes, retraits, chat, statistiques.
11. **Mise en production** : Hostinger, MySQL, cron, e-mails, sauvegardes, nom de domaine. Créer le compte admin avec `php artisan app:make-admin academyjoseph71@gmail.com` (mot de passe choisi au moment de la commande).
