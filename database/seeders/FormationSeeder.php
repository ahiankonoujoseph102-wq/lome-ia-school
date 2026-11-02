<?php

namespace Database\Seeders;

use App\Catalogue\CatalogueUpdate;
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
            // Prix de novembre 2026 (App\Catalogue\CatalogueUpdate::PRICES).
            $formation['price'] = CatalogueUpdate::PRICES[$formation['slug']] ?? $formation['price'];

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
        $complete = CatalogueUpdate::complete();

        return [
            [
                'title' => $complete['title'],
                'slug' => CatalogueUpdate::COMPLETE_SLUG,
                'subtitle' => $complete['subtitle'],
                'description' => $complete['description'],
                'price' => $complete['price'],
                'category' => FormationCategory::from($complete['category']),
                'is_featured' => true,
                'outcomes' => $complete['outcomes'],
                'audience' => $complete['audience'],
                'careers' => $complete['careers'],
            ],
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
                    'Lancer votre SaaS et le présenter à vos premiers utilisateurs',
                    'Faire évoluer votre produit à partir des retours',
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
                    'Comprendre ce qu\'est un agent IA et choisir votre projet d\'agent',
                    'Écrire les instructions d\'un agent : rôle, ton, règles et limites',
                    'Créer un agent avec Gemini (Gems) et lui donner des documents',
                    'Créer un agent avec Claude (Projets), le tester et le corriger',
                    'Créer un agent qui répond aux clients sur WhatsApp Business',
                    'Proposer vos services d\'agents IA : trouver des clients et fixer vos prix',
                    'Réaliser un projet final et présenter votre agent',
                ],
                'audience' => [
                    'Les entrepreneurs qui veulent automatiser leur relation client',
                    'Les freelances qui veulent proposer un nouveau service aux entreprises',
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
                    'Soigner chaque mission et construire une relation durable avec vos clients',
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
                    'Trouver régulièrement des idées de vidéos avec l\'IA',
                    'Produire des vidéos rapidement : script, visuels, voix, montage',
                    'Adapter votre contenu à TikTok, Facebook et YouTube',
                    'Comprendre les différentes façons de monétiser une audience',
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
                    'Rédiger des pages de vente claires et persuasives',
                    'Attirer des acheteurs grâce aux réseaux sociaux',
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
