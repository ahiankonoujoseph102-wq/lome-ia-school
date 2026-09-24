<?php

namespace Database\Seeders;

use App\Enums\FormationCategory;
use App\Enums\FormationFormat;
use App\Models\Formation;
use Illuminate\Database\Seeder;

/**
 * Catalogue initial des formations.
 *
 * Relancer ce seeder met à jour les formations existantes (repérées par leur slug)
 * sans créer de doublons.
 */
class FormationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ($this->formations() as $index => $formation) {
            Formation::updateOrCreate(
                ['slug' => $formation['slug']],
                $formation + [
                    'sort_order' => ($index + 1) * 10,
                    'formats' => [FormationFormat::Recorded],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function formations(): array
    {
        return [
            [
                'title' => 'Formation ChatGPT & Claude',
                'slug' => 'chatgpt-claude-initiation-ia',
                'subtitle' => 'Initiation à l\'intelligence artificielle : partez de zéro et apprenez à utiliser l\'IA au quotidien.',
                'description' => 'Vous entendez parler de l\'IA partout mais vous ne savez pas par où commencer ? Cette formation vous apprend, pas à pas et en français simple, à utiliser ChatGPT et Claude dans votre quotidien : écrire, résumer, traduire, chercher des idées, préparer un document ou résoudre un problème. C\'est le point de départ recommandé avant nos autres formations.',
                'price' => 5500,
                'category' => FormationCategory::AiBasics,
                'is_featured' => true,
                'outcomes' => [
                    'Comprendre simplement ce qu\'est l\'IA et ce qu\'elle peut faire pour vous',
                    'Créer vos comptes et prendre en main ChatGPT et Claude sur téléphone et ordinateur',
                    'Rédiger des prompts clairs qui donnent des réponses vraiment utiles',
                    'Utiliser l\'IA pour écrire, résumer, traduire et organiser votre travail',
                    'Éviter les erreurs courantes et vérifier les réponses de l\'IA',
                ],
                'curriculum' => [
                    ['title' => 'Découvrir l\'intelligence artificielle', 'lessons' => ['L\'IA expliquée simplement', 'ChatGPT, Claude : quelles différences ?', 'Créer ses comptes et configurer ses outils']],
                    ['title' => 'L\'art du prompt', 'lessons' => ['La structure d\'un bon prompt', 'Donner du contexte et un rôle à l\'IA', 'Améliorer une réponse par étapes']],
                    ['title' => 'L\'IA au quotidien', 'lessons' => ['Écrire des messages, e-mails et courriers', 'Résumer et analyser des documents', 'Traduire et reformuler']],
                    ['title' => 'Aller plus loin', 'lessons' => ['Organiser ses idées et ses projets', 'Limites de l\'IA et bonnes pratiques', 'Choisir sa prochaine compétence IA']],
                ],
                'audience' => [
                    'Les débutants complets qui veulent enfin comprendre et utiliser l\'IA',
                    'Les étudiants, salariés et entrepreneurs qui veulent gagner du temps',
                    'Toute personne qui prévoit de suivre une formation IA plus avancée',
                ],
            ],
            [
                'title' => 'Création d\'affiches pro avec l\'IA',
                'slug' => 'creation-affiches-pro-ia',
                'subtitle' => 'Apprenez à créer des visuels professionnels pour vos clients, vos événements et votre activité.',
                'description' => 'Affiches d\'événements, flyers, visuels pour les réseaux sociaux, publicités : apprenez à créer des designs professionnels avec l\'IA, même sans être graphiste. Vous apprendrez à utiliser les outils de génération d\'images et de mise en page pour obtenir un rendu soigné, et à proposer ce service à des clients.',
                'price' => 36000,
                'category' => FormationCategory::Creative,
                'is_featured' => true,
                'outcomes' => [
                    'Générer des images de qualité professionnelle avec l\'IA',
                    'Composer une affiche lisible et percutante : textes, couleurs, hiérarchie',
                    'Adapter vos visuels à chaque réseau social et au format imprimé',
                    'Créer une identité visuelle cohérente pour une marque',
                    'Présenter et vendre vos créations à des clients',
                ],
                'curriculum' => [
                    ['title' => 'Les bases du design', 'lessons' => ['Les règles d\'une affiche efficace', 'Couleurs, typographies et contrastes', 'Analyser des affiches qui fonctionnent']],
                    ['title' => 'Générer des images avec l\'IA', 'lessons' => ['Choisir le bon outil de génération', 'Écrire des prompts visuels précis', 'Retoucher et améliorer une image']],
                    ['title' => 'Mise en page professionnelle', 'lessons' => ['Assembler image et texte', 'Formats réseaux sociaux et impression', 'Exporter en haute qualité']],
                    ['title' => 'Projets et clients', 'lessons' => ['Réaliser des affiches de A à Z', 'Constituer un portfolio', 'Fixer ses prix et trouver ses premiers clients']],
                ],
                'audience' => [
                    'Les entrepreneurs qui veulent créer eux-mêmes leurs visuels',
                    'Les graphistes qui veulent produire plus vite grâce à l\'IA',
                    'Toute personne qui veut vendre un service de création d\'affiches',
                ],
            ],
            [
                'title' => 'Création de vidéos publicitaires avec l\'IA',
                'slug' => 'videos-publicitaires-ia',
                'subtitle' => 'Apprenez à créer des publicités vidéo accrocheuses, sans caméra ni studio.',
                'description' => 'Une vidéo publicitaire bien construite met un produit en valeur. Apprenez à écrire un script accrocheur, générer les images et les séquences avec l\'IA, ajouter une voix off et une musique, puis monter une publicité prête à être diffusée sur Facebook, TikTok, Instagram ou WhatsApp.',
                'price' => 5500,
                'category' => FormationCategory::Creative,
                'is_featured' => true,
                'outcomes' => [
                    'Écrire un script publicitaire court et convaincant avec l\'IA',
                    'Générer des séquences vidéo et des images pour votre publicité',
                    'Ajouter une voix off naturelle et une musique adaptée',
                    'Monter une vidéo rythmée au bon format pour chaque réseau',
                    'Proposer ce service à des commerçants et entreprises',
                ],
                'curriculum' => [
                    ['title' => 'Penser une publicité qui vend', 'lessons' => ['Les ingrédients d\'une bonne publicité', 'Écrire le script avec l\'IA', 'Construire l\'accroche des 3 premières secondes']],
                    ['title' => 'Créer les images et séquences', 'lessons' => ['Générer des visuels produits', 'Animer des images en vidéo', 'Garder un style cohérent']],
                    ['title' => 'Voix, musique et montage', 'lessons' => ['Créer une voix off avec l\'IA', 'Choisir la musique', 'Monter et exporter la publicité']],
                    ['title' => 'Diffuser et vendre', 'lessons' => ['Adapter la vidéo à chaque plateforme', 'Réaliser une publicité complète', 'Vendre ses services de publicité vidéo']],
                ],
                'audience' => [
                    'Les commerçants et entrepreneurs qui veulent promouvoir leurs produits',
                    'Les créateurs de contenu et community managers',
                    'Toute personne qui veut proposer un service de publicité vidéo',
                ],
            ],
            [
                'title' => 'Création de vidéos d\'histoire avec l\'IA',
                'slug' => 'videos-histoire-ia',
                'subtitle' => 'Racontez des histoires captivantes en vidéo : contes, récits historiques, histoires inspirantes.',
                'description' => 'Apprenez à écrire une histoire prenante avec l\'IA, à générer des illustrations cohérentes, une narration expressive et à assembler le tout en une vidéo prête à publier sur YouTube, TikTok ou Facebook.',
                'price' => 5500,
                'category' => FormationCategory::Creative,
                'outcomes' => [
                    'Trouver des idées d\'histoires qui plaisent au public',
                    'Écrire un récit structuré et captivant avec l\'IA',
                    'Générer des illustrations et des personnages cohérents d\'une scène à l\'autre',
                    'Créer une narration expressive avec une voix IA',
                    'Monter une vidéo d\'histoire prête à publier',
                ],
                'curriculum' => [
                    ['title' => 'L\'art de raconter', 'lessons' => ['Trouver des idées d\'histoires', 'Structurer un récit', 'Écrire le script avec l\'IA']],
                    ['title' => 'Illustrer l\'histoire', 'lessons' => ['Créer des personnages cohérents', 'Générer les scènes clés', 'Donner du mouvement aux images']],
                    ['title' => 'Narration et ambiance', 'lessons' => ['Créer la voix du narrateur', 'Musique et effets sonores', 'Synchroniser voix et images']],
                    ['title' => 'Montage et publication', 'lessons' => ['Monter la vidéo complète', 'Sous-titres et miniature', 'Publier et fidéliser son audience']],
                ],
                'audience' => [
                    'Les créateurs qui veulent lancer une chaîne d\'histoires',
                    'Les passionnés de contes, d\'histoire et de récits',
                    'Les enseignants et parents qui veulent créer des contenus éducatifs',
                ],
            ],
            [
                'title' => 'Création de vidéos films avec l\'IA',
                'slug' => 'videos-films-ia',
                'subtitle' => 'Réalisez des courts-métrages et des scènes cinématographiques avec l\'intelligence artificielle.',
                'description' => 'Le cinéma n\'est plus réservé aux grands studios. Avec l\'IA, vous pouvez écrire un scénario, créer des personnages, générer des plans cinématographiques et monter un court-métrage complet. Cette formation vous guide de l\'idée jusqu\'au film final, étape par étape.',
                'price' => 5500,
                'category' => FormationCategory::Creative,
                'outcomes' => [
                    'Écrire un scénario et un découpage en scènes avec l\'IA',
                    'Créer des personnages et des décors cohérents',
                    'Générer des plans vidéo au rendu cinématographique',
                    'Ajouter dialogues, voix et musique de film',
                    'Monter et exporter un court-métrage complet',
                ],
                'curriculum' => [
                    ['title' => 'Du scénario au découpage', 'lessons' => ['Trouver une idée de film', 'Écrire le scénario avec l\'IA', 'Découper le film en plans']],
                    ['title' => 'Personnages et décors', 'lessons' => ['Concevoir ses personnages', 'Créer les décors', 'Garder la cohérence visuelle']],
                    ['title' => 'Tourner avec l\'IA', 'lessons' => ['Générer les plans vidéo', 'Mouvements de caméra et ambiance', 'Dialogues et voix des personnages']],
                    ['title' => 'Post-production', 'lessons' => ['Montage et rythme', 'Musique, sons et étalonnage', 'Diffuser son film']],
                ],
                'audience' => [
                    'Les passionnés de cinéma qui veulent réaliser leurs propres films',
                    'Les créateurs de contenu qui veulent se démarquer',
                    'Les vidéastes qui veulent intégrer l\'IA à leur travail',
                ],
            ],
            [
                'title' => 'Création de chansons personnalisées avec l\'IA',
                'slug' => 'chansons-personnalisees-ia',
                'subtitle' => 'Créez des chansons uniques pour les anniversaires, mariages, entreprises et événements.',
                'description' => 'Une chanson personnalisée est un cadeau original, pour un anniversaire, un mariage ou une entreprise. Apprenez à écrire des paroles sur mesure avec l\'IA, à générer la musique et la voix dans le style voulu, puis à livrer une chanson prête à partager. Vous pourrez ensuite proposer ce service autour de vous.',
                'price' => 5100,
                'category' => FormationCategory::Creative,
                'outcomes' => [
                    'Écrire des paroles personnalisées et émouvantes avec l\'IA',
                    'Générer une chanson complète dans le style musical souhaité',
                    'Améliorer la qualité et la cohérence de vos morceaux',
                    'Créer une vidéo avec les paroles pour les réseaux sociaux',
                    'Vendre des chansons personnalisées à vos clients',
                ],
                'curriculum' => [
                    ['title' => 'Écrire les paroles', 'lessons' => ['Recueillir les bonnes informations', 'Écrire des paroles avec l\'IA', 'Couplets, refrain et rimes']],
                    ['title' => 'Créer la musique', 'lessons' => ['Découvrir les outils de musique IA', 'Choisir le style et l\'ambiance', 'Générer et sélectionner les versions']],
                    ['title' => 'Finaliser et livrer', 'lessons' => ['Améliorer le rendu final', 'Créer une vidéo avec les paroles', 'Livrer au bon format']],
                    ['title' => 'En faire un service', 'lessons' => ['Trouver des clients', 'Fixer ses prix', 'Gérer les commandes']],
                ],
                'audience' => [
                    'Toute personne qui veut offrir une chanson unique à un proche',
                    'Les entrepreneurs qui veulent vendre un service original',
                    'Les passionnés de musique, même sans formation musicale',
                ],
            ],
            [
                'title' => 'Rédaction d\'ebooks et livres professionnels avec l\'IA',
                'slug' => 'ebooks-livres-professionnels-ia',
                'subtitle' => 'Mise en page pro, couverture, édition et publication en librairie : de l\'idée au livre publié.',
                'description' => 'Publier un livre, c\'est partager votre savoir et affirmer votre expertise. Cette formation complète vous accompagne de l\'idée jusqu\'à la publication : plan, rédaction assistée par l\'IA, correction, mise en page professionnelle, couverture, édition et diffusion en librairie et en ligne.',
                'price' => 300000,
                'category' => FormationCategory::Writing,
                'outcomes' => [
                    'Transformer votre idée en un plan de livre solide',
                    'Rédiger plus vite avec l\'IA tout en gardant votre voix et votre style',
                    'Corriger, relire et améliorer votre manuscrit',
                    'Réaliser une mise en page professionnelle et une couverture attractive',
                    'Publier votre livre en format papier et numérique, et le diffuser en librairie',
                ],
                'curriculum' => [
                    ['title' => 'Concevoir le livre', 'lessons' => ['Choisir son sujet et son public', 'Construire le plan détaillé', 'Définir son style d\'écriture']],
                    ['title' => 'Rédiger avec l\'IA', 'lessons' => ['Méthode de rédaction chapitre par chapitre', 'Garder une voix authentique', 'Relecture et correction']],
                    ['title' => 'Mise en page et couverture', 'lessons' => ['Mise en page professionnelle du manuscrit', 'Créer une couverture qui donne envie', 'Préparer les fichiers d\'impression et numériques']],
                    ['title' => 'Édition et publication', 'lessons' => ['Les options d\'édition et d\'impression', 'Publier en ligne et en librairie', 'Démarches et droits d\'auteur']],
                    ['title' => 'Lancer et vendre son livre', 'lessons' => ['Préparer le lancement', 'Promouvoir son livre', 'Transformer son livre en source de revenus']],
                ],
                'audience' => [
                    'Les experts, formateurs et entrepreneurs qui veulent publier leur savoir',
                    'Les auteurs qui veulent écrire et publier plus efficacement',
                    'Toute personne qui rêve de publier un livre professionnel',
                ],
            ],
            [
                'title' => 'Création de site vitrine web avec l\'IA',
                'slug' => 'site-vitrine-web-ia',
                'subtitle' => 'Créez un site web professionnel pour vous ou vos clients, sans être développeur.',
                'description' => 'Un site vitrine est indispensable pour être pris au sérieux et trouvé sur Internet. Apprenez à concevoir, créer et mettre en ligne un site professionnel avec l\'aide de l\'IA : textes, design, pages, nom de domaine et hébergement. Vous pourrez créer votre propre site ou en réaliser pour des clients.',
                'price' => 5500,
                'category' => FormationCategory::Web,
                'outcomes' => [
                    'Planifier les pages et le contenu d\'un site vitrine',
                    'Générer textes, images et design avec l\'IA',
                    'Construire un site professionnel adapté au mobile',
                    'Acheter un nom de domaine et mettre le site en ligne',
                    'Réaliser et vendre des sites vitrines à des clients',
                ],
                'curriculum' => [
                    ['title' => 'Préparer son site', 'lessons' => ['Les pages essentielles d\'un site vitrine', 'Rédiger les textes avec l\'IA', 'Choisir le style et les couleurs']],
                    ['title' => 'Construire le site', 'lessons' => ['Créer les pages avec l\'IA', 'Adapter le site au téléphone', 'Ajouter formulaires et liens WhatsApp']],
                    ['title' => 'Mettre en ligne', 'lessons' => ['Nom de domaine et hébergement', 'Publier le site', 'Être visible sur Google']],
                    ['title' => 'Vendre des sites', 'lessons' => ['Trouver ses premiers clients', 'Fixer ses tarifs', 'Livrer un site clé en main']],
                ],
                'audience' => [
                    'Les entrepreneurs et commerçants qui veulent leur propre site',
                    'Les débutants qui veulent se lancer dans la création web',
                    'Les freelances qui veulent proposer un nouveau service',
                ],
            ],
            [
                'title' => 'Création de sites web avec base de données et projets web avancés',
                'slug' => 'sites-web-base-de-donnees-projets-avances',
                'subtitle' => 'Allez au-delà du site vitrine : espaces membres, formulaires, base de données et projets complets.',
                'description' => 'Passez au niveau supérieur et créez des sites web dynamiques : comptes utilisateurs, formulaires enregistrés, catalogues, tableaux de bord. Avec l\'IA comme assistant de développement, vous apprendrez à concevoir, construire et mettre en ligne des projets web avancés qui répondent à de vrais besoins.',
                'price' => 15500,
                'category' => FormationCategory::Web,
                'outcomes' => [
                    'Comprendre comment fonctionne un site web avec base de données',
                    'Concevoir la structure de données d\'un projet',
                    'Développer avec l\'IA des fonctionnalités : comptes, formulaires, catalogue',
                    'Tester, corriger et sécuriser votre projet',
                    'Déployer un projet web complet en ligne',
                ],
                'curriculum' => [
                    ['title' => 'Comprendre le web dynamique', 'lessons' => ['Site vitrine et site dynamique', 'Serveur, base de données et interface', 'Choisir ses outils']],
                    ['title' => 'Concevoir le projet', 'lessons' => ['Définir les fonctionnalités', 'Modéliser les données', 'Préparer le cahier des charges avec l\'IA']],
                    ['title' => 'Développer avec l\'IA', 'lessons' => ['Générer et comprendre le code', 'Comptes utilisateurs et formulaires', 'Tableaux de bord et listes']],
                    ['title' => 'Qualité et mise en ligne', 'lessons' => ['Tester et corriger les erreurs', 'Les bases de la sécurité', 'Déployer et maintenir le projet']],
                ],
                'audience' => [
                    'Les personnes qui savent déjà créer un site vitrine',
                    'Les freelances qui veulent proposer des projets plus ambitieux',
                    'Les entrepreneurs qui ont un projet web précis',
                ],
            ],
            [
                'title' => 'Création d\'applications et logiciels avec l\'IA',
                'slug' => 'applications-logiciels-ia',
                'subtitle' => 'Transformez vos idées en applications utiles, même en partant de zéro en programmation.',
                'description' => 'Gestion de stock, prise de rendez-vous, suivi de clients, outils internes : de nombreuses entreprises ont besoin d\'applications adaptées. Apprenez à concevoir et construire des applications et logiciels avec l\'IA comme assistant, de l\'idée jusqu\'à une version utilisable.',
                'price' => 15500,
                'category' => FormationCategory::Web,
                'outcomes' => [
                    'Transformer un besoin en cahier des charges clair',
                    'Choisir la bonne technologie selon le projet',
                    'Construire une application étape par étape avec l\'IA',
                    'Tester, corriger et améliorer votre application',
                    'Livrer une application à un client ou l\'utiliser dans votre activité',
                ],
                'curriculum' => [
                    ['title' => 'De l\'idée au projet', 'lessons' => ['Identifier un besoin réel', 'Écrire le cahier des charges avec l\'IA', 'Choisir ses outils']],
                    ['title' => 'Construire l\'application', 'lessons' => ['Créer les écrans', 'Gérer les données', 'Ajouter les fonctionnalités clés']],
                    ['title' => 'Qualité', 'lessons' => ['Tester son application', 'Corriger les erreurs avec l\'IA', 'Améliorer l\'expérience utilisateur']],
                    ['title' => 'Livrer et valoriser', 'lessons' => ['Mettre l\'application à disposition', 'Former les utilisateurs', 'Vendre ses services de développement']],
                ],
                'audience' => [
                    'Les entrepreneurs qui ont une idée d\'application',
                    'Les débutants motivés qui veulent apprendre à créer des logiciels',
                    'Les freelances qui veulent développer plus vite',
                ],
            ],
            [
                'title' => 'Création de SaaS avec l\'IA',
                'slug' => 'creation-saas-ia',
                'subtitle' => 'Apprenez à créer un logiciel en ligne proposé par abonnement.',
                'description' => 'Un SaaS est un logiciel accessible en ligne que vos clients paient chaque mois. Apprenez à trouver et tester une idée, à construire votre produit avec l\'IA, à gérer les abonnements et à présenter votre SaaS à vos premiers utilisateurs.',
                'price' => 20500,
                'category' => FormationCategory::Web,
                'is_featured' => true,
                'outcomes' => [
                    'Trouver et valider une idée de SaaS qui répond à un vrai besoin',
                    'Construire un produit minimum viable avec l\'IA',
                    'Mettre en place les comptes clients et les abonnements',
                    'Lancer votre SaaS et obtenir vos premiers clients',
                    'Faire évoluer votre produit à partir des retours',
                ],
                'curriculum' => [
                    ['title' => 'L\'idée et le marché', 'lessons' => ['Comprendre le modèle SaaS', 'Trouver une idée rentable', 'Valider l\'idée avant de construire']],
                    ['title' => 'Construire le produit', 'lessons' => ['Définir le produit minimum viable', 'Développer avec l\'IA', 'Comptes clients et espace membre']],
                    ['title' => 'Abonnements et paiements', 'lessons' => ['Définir ses offres et ses prix', 'Mettre en place les paiements', 'Gérer les abonnements']],
                    ['title' => 'Lancer et grandir', 'lessons' => ['Préparer le lancement', 'Trouver ses premiers clients', 'Améliorer le produit en continu']],
                ],
                'audience' => [
                    'Les entrepreneurs qui veulent créer un produit numérique rentable',
                    'Les développeurs et créateurs web qui veulent lancer leur propre logiciel',
                    'Les personnes qui ont suivi une formation web et veulent aller plus loin',
                ],
            ],
            [
                'title' => 'Création d\'agents IA',
                'slug' => 'creation-agents-ia',
                'subtitle' => 'Créez des assistants intelligents qui travaillent pour vous et pour vos clients.',
                'description' => 'Les agents IA peuvent répondre aux clients, traiter des demandes, rechercher des informations et accomplir des tâches de façon autonome. Apprenez à concevoir et créer des agents IA utiles pour une entreprise : service client, assistant commercial, assistant interne, et à les connecter à vos outils.',
                'price' => 30000,
                'category' => FormationCategory::Automation,
                'is_featured' => true,
                'outcomes' => [
                    'Comprendre ce qu\'est un agent IA et ce qu\'il peut accomplir',
                    'Concevoir un agent adapté à un besoin précis',
                    'Donner des connaissances et des instructions fiables à votre agent',
                    'Connecter votre agent à des outils (WhatsApp, e-mail, documents)',
                    'Proposer la création d\'agents IA comme service aux entreprises',
                ],
                'curriculum' => [
                    ['title' => 'Comprendre les agents IA', 'lessons' => ['Chatbot, assistant, agent : les différences', 'Exemples d\'agents utiles en entreprise', 'Les outils pour créer des agents']],
                    ['title' => 'Concevoir son agent', 'lessons' => ['Définir le rôle et les limites', 'Rédiger les instructions', 'Ajouter une base de connaissances']],
                    ['title' => 'Connecter et automatiser', 'lessons' => ['Relier l\'agent à ses outils', 'Déclencher des actions', 'Tester et fiabiliser l\'agent']],
                    ['title' => 'Déployer et vendre', 'lessons' => ['Mettre l\'agent à disposition', 'Suivre et améliorer ses réponses', 'Vendre des agents IA aux entreprises']],
                ],
                'audience' => [
                    'Les entrepreneurs qui veulent automatiser leur relation client',
                    'Les freelances qui veulent proposer un service très demandé',
                    'Les personnes à l\'aise avec l\'IA qui veulent passer au niveau supérieur',
                ],
            ],
            [
                'title' => 'Automatisation avec l\'IA',
                'slug' => 'automatisation-ia',
                'subtitle' => 'Apprenez à automatiser vos tâches répétitives avec l\'IA.',
                'description' => 'Relances clients, traitement des e-mails, publication sur les réseaux, rapports, saisie de données : de nombreuses tâches du quotidien peuvent être automatisées. Apprenez à créer des automatisations qui combinent vos outils et l\'IA pour alléger votre quotidien et proposer ce savoir-faire aux entreprises.',
                'price' => 25500,
                'category' => FormationCategory::Automation,
                'is_featured' => true,
                'outcomes' => [
                    'Repérer les tâches qui peuvent être automatisées',
                    'Créer des automatisations sans coder avec des outils visuels',
                    'Intégrer l\'IA dans vos automatisations pour traiter textes et données',
                    'Connecter e-mail, tableurs, réseaux sociaux et messageries',
                    'Vendre des automatisations à des entreprises',
                ],
                'curriculum' => [
                    ['title' => 'Penser automatisation', 'lessons' => ['Identifier les tâches répétitives', 'Découvrir les outils d\'automatisation', 'Déclencheurs et actions']],
                    ['title' => 'Premières automatisations', 'lessons' => ['Automatiser ses e-mails', 'Automatiser un tableur', 'Publier automatiquement sur les réseaux']],
                    ['title' => 'Ajouter l\'IA', 'lessons' => ['Faire rédiger et résumer par l\'IA', 'Classer et trier des demandes', 'Construire un flux complet']],
                    ['title' => 'Professionnaliser', 'lessons' => ['Fiabiliser et surveiller ses automatisations', 'Documenter pour un client', 'Vendre ses services d\'automatisation']],
                ],
                'audience' => [
                    'Les entrepreneurs et salariés débordés par les tâches répétitives',
                    'Les freelances qui veulent proposer un service à forte valeur',
                    'Toute personne organisée qui aime optimiser son travail',
                ],
            ],
            [
                'title' => 'Créer des formations et apprendre n\'importe quelle compétence avec l\'IA',
                'slug' => 'creer-formations-apprendre-competences-ia',
                'subtitle' => 'Apprenez avec l\'IA et transformez votre savoir en formation à proposer en ligne.',
                'description' => 'L\'IA peut devenir votre professeur particulier et votre assistant de création. Découvrez d\'abord comment utiliser l\'IA pour acquérir une nouvelle compétence, puis à concevoir, produire et vendre votre propre formation en ligne à partir de ce que vous savez.',
                'price' => 12500,
                'category' => FormationCategory::Business,
                'outcomes' => [
                    'Utiliser l\'IA comme professeur particulier pour apprendre plus vite',
                    'Construire un plan d\'apprentissage efficace sur n\'importe quel sujet',
                    'Concevoir le programme d\'une formation qui apporte des résultats',
                    'Produire vos supports : vidéos, documents, exercices',
                    'Lancer et vendre votre formation en ligne',
                ],
                'curriculum' => [
                    ['title' => 'Apprendre avec l\'IA', 'lessons' => ['L\'IA comme professeur particulier', 'Construire son plan d\'apprentissage', 'Mémoriser et pratiquer efficacement']],
                    ['title' => 'Concevoir sa formation', 'lessons' => ['Choisir son sujet et son public', 'Construire le programme', 'Définir les résultats promis']],
                    ['title' => 'Produire les contenus', 'lessons' => ['Écrire les scripts avec l\'IA', 'Enregistrer ses vidéos', 'Créer exercices et supports']],
                    ['title' => 'Vendre sa formation', 'lessons' => ['Fixer le prix', 'Créer sa page de vente', 'Trouver ses premiers élèves']],
                ],
                'audience' => [
                    'Les personnes qui veulent apprendre de nouvelles compétences rapidement',
                    'Les experts qui veulent partager et monétiser leur savoir',
                    'Les enseignants et coachs qui veulent passer au numérique',
                ],
            ],
            [
                'title' => 'Décrocher des missions sur Upwork avec les compétences IA',
                'slug' => 'missions-upwork-competences-ia',
                'subtitle' => 'Apprenez à proposer vos services et à décrocher des missions auprès de clients internationaux.',
                'description' => 'Upwork met en relation des freelances avec des clients du monde entier. Apprenez à créer un profil qui inspire confiance, à choisir les bonnes missions, à rédiger des propositions qui convainquent et à soigner chaque mission pour construire votre réputation.',
                'price' => 35000,
                'category' => FormationCategory::Business,
                'outcomes' => [
                    'Créer un profil Upwork professionnel et bien positionné',
                    'Choisir les services IA les plus demandés à proposer',
                    'Rédiger des propositions qui se démarquent',
                    'Communiquer avec des clients internationaux avec l\'aide de l\'IA',
                    'Obtenir de bonnes évaluations et fidéliser vos clients',
                ],
                'curriculum' => [
                    ['title' => 'Bien démarrer', 'lessons' => ['Comprendre le fonctionnement d\'Upwork', 'Choisir ses services IA', 'Créer son compte']],
                    ['title' => 'Un profil qui convainc', 'lessons' => ['Titre et présentation', 'Portfolio et exemples de travaux', 'Fixer ses tarifs']],
                    ['title' => 'Décrocher des missions', 'lessons' => ['Trouver les bonnes offres', 'Rédiger des propositions efficaces', 'Réussir les échanges avec le client']],
                    ['title' => 'Livrer et évoluer', 'lessons' => ['Livrer un travail de qualité', 'Obtenir de bonnes évaluations', 'Recevoir ses paiements et développer son activité']],
                ],
                'audience' => [
                    'Les personnes qui ont une compétence IA et veulent des clients',
                    'Les freelances qui veulent travailler avec l\'international',
                    'Les étudiants et jeunes diplômés qui veulent un revenu en ligne',
                ],
            ],
            [
                'title' => 'Création de contenu TikTok, Facebook et YouTube avec les vidéos IA',
                'slug' => 'contenu-tiktok-facebook-youtube-videos-ia',
                'subtitle' => 'Apprenez à publier régulièrement des vidéos créées avec l\'IA sur vos réseaux.',
                'description' => 'Sur les réseaux sociaux, la régularité compte. Apprenez à trouver des idées, à produire des vidéos avec l\'IA et à publier régulièrement sur TikTok, Facebook et YouTube. Vous découvrirez aussi les différentes façons de monétiser une audience.',
                'price' => 15500,
                'category' => FormationCategory::Business,
                'outcomes' => [
                    'Choisir une thématique et un positionnement porteurs',
                    'Trouver des idées de vidéos à l\'infini avec l\'IA',
                    'Produire des vidéos rapidement : script, visuels, voix, montage',
                    'Adapter votre contenu à TikTok, Facebook et YouTube',
                    'Comprendre les différentes façons de monétiser une audience',
                ],
                'curriculum' => [
                    ['title' => 'Stratégie de contenu', 'lessons' => ['Choisir sa thématique', 'Comprendre les algorithmes', 'Planifier son calendrier de publication']],
                    ['title' => 'Produire avec l\'IA', 'lessons' => ['Trouver des idées et écrire les scripts', 'Créer les visuels et la voix', 'Monter rapidement ses vidéos']],
                    ['title' => 'Publier sur chaque plateforme', 'lessons' => ['Spécificités de TikTok', 'Spécificités de Facebook', 'Spécificités de YouTube']],
                    ['title' => 'Grandir et monétiser', 'lessons' => ['Analyser ses statistiques', 'Fidéliser son audience', 'Les sources de revenus d\'un créateur']],
                ],
                'audience' => [
                    'Les créateurs de contenu qui veulent publier plus et mieux',
                    'Les entrepreneurs qui veulent développer leur visibilité',
                    'Toute personne qui veut lancer une page ou une chaîne',
                ],
            ],
            [
                'title' => 'E-commerce de produits digitaux avec l\'IA',
                'slug' => 'e-commerce-produits-digitaux-ia',
                'subtitle' => 'Créez et vendez des produits numériques : ebooks, modèles, guides, formations.',
                'description' => 'Un produit digital se crée une fois et se livre en ligne, sans stock ni transport. Apprenez à trouver des idées de produits qui répondent à une demande, à les créer avec l\'IA, à mettre en place votre boutique et vos paiements, puis à les faire connaître.',
                'price' => 20500,
                'category' => FormationCategory::Business,
                'outcomes' => [
                    'Trouver des idées de produits digitaux qui répondent à une demande',
                    'Créer vos produits avec l\'IA : guides, modèles, ebooks',
                    'Mettre en place une boutique en ligne et les paiements',
                    'Rédiger des pages de vente qui convertissent',
                    'Attirer des acheteurs grâce aux réseaux sociaux',
                ],
                'curriculum' => [
                    ['title' => 'Comprendre les produits digitaux', 'lessons' => ['Pourquoi vendre des produits digitaux', 'Les types de produits qui se vendent', 'Trouver et valider une idée']],
                    ['title' => 'Créer ses produits', 'lessons' => ['Créer un guide ou un ebook avec l\'IA', 'Créer des modèles et outils', 'Soigner la présentation']],
                    ['title' => 'La boutique', 'lessons' => ['Choisir sa plateforme de vente', 'Mettre en place les paiements', 'Écrire une page de vente efficace']],
                    ['title' => 'Vendre', 'lessons' => ['Attirer des acheteurs', 'Utiliser les réseaux sociaux', 'Augmenter ses ventes dans la durée']],
                ],
                'audience' => [
                    'Les personnes qui veulent créer un revenu en ligne',
                    'Les experts et créateurs qui veulent vendre leur savoir-faire',
                    'Les entrepreneurs qui veulent diversifier leurs revenus',
                ],
            ],
        ];
    }
}
