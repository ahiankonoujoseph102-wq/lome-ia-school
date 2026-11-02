<?php

namespace App\Support;

/**
 * Talkami : plateforme créée par Joseph Ahiankonou pour apprendre à parler anglais (novembre 2026).
 * Réglages : clé site.talkami (valeurs de départ dans config/site.php, modifiables dans l'admin).
 * Textes de présentation : faits fournis par Joseph uniquement ; jamais de chiffre d'élèves, d'avis, de note,
 * de promesse de résultat, ni « garanti », « 100 % » ou « sans engagement ».
 */
final class Talkami
{
    public const string NAME = 'Talkami';

    /**
     * Visuels de Talkami, repris tels quels de talkami.net (demande de Joseph) : logo blanc (fond transparent, pour
     * le fond sombre du site), Teacher Joe et Teacher Rose (les professeurs virtuels de Talkami).
     */
    public const string LOGO = 'images/talkami/logo-blanc.webp';

    public const string TEACHER_JOE = 'images/talkami/teacher-joe.webp';

    public const string TEACHER_ROSE = 'images/talkami/teacher-rose.webp';

    /**
     * Textes repris de talkami.net (novembre 2026).
     */
    public const string PROMISE = 'Parlez anglais avec Teacher Joe, chaque jour';

    public const string PROMISE_TEXT = 'Teacher Joe vous fait parler sans gêne ni jugement, depuis votre téléphone. Vous apprenez l\'anglais américain, compris partout dans le monde.';

    public const string BUTTON = 'Passer mon évaluation gratuite';

    public const string FOUNDER_QUOTE = 'J\'ai créé Talkami pour que chaque francophone puisse enfin oser parler anglais, depuis son téléphone, sans gêne.';

    /**
     * « Quatre étapes simples » de talkami.net.
     *
     * @var list<array{title: string, text: string}>
     */
    public const array STEPS = [
        ['title' => 'Créez votre compte', 'text' => 'Avec votre nom et votre numéro de téléphone.'],
        ['title' => 'Passez l\'évaluation gratuite', 'text' => '5 minutes à l\'oral pour trouver votre niveau, de A1 à C2.'],
        ['title' => 'Choisissez votre formule', 'text' => 'Payez par Mobile Money. Rien n\'est prélevé automatiquement.'],
        ['title' => 'Parlez chaque jour', 'text' => 'Teacher Joe vous accueille et votre programme est prêt.'],
    ];

    public const string DEFAULT_URL = 'https://talkami.net/?utm_source=lome-ia-school';

    public const string HEADLINE = 'Évaluez gratuitement votre anglais en 5 minutes avec Talkami';

    public const string CREATOR = 'Talkami a été conçu et créé par Joseph Ahiankonou, fondateur de Lomé IA School.';

    /**
     * Ce que propose Talkami (faits fournis par Joseph).
     *
     * @var list<array{icon: string, title: string, text: string}>
     */
    public const array FEATURES = [
        ['icon' => 'microphone', 'title' => 'Parler avec un professeur virtuel', 'text' => 'Vous parlez anglais à l\'oral avec Teacher Joe ou Teacher Rose, au choix.'],
        ['icon' => 'chart-bar', 'title' => 'Évaluation orale gratuite de 5 minutes', 'text' => 'Elle donne votre niveau d\'anglais, de A1 à C2. Une seule évaluation par compte.'],
        ['icon' => 'play-circle', 'title' => 'Séance d\'essai gratuite de 15 minutes', 'text' => 'Après l\'évaluation, une séance d\'essai gratuite, une fois par compte.'],
        ['icon' => 'map', 'title' => 'Un programme selon votre objectif', 'text' => 'Travail, études, entretien, voyage… Une séance orale chaque jour, avec corrections et révision.'],
        ['icon' => 'book-open', 'title' => 'Mes cours', 'text' => 'De l\'aide pour vos cours d\'anglais de l\'école, à partir de photos ou d\'un PDF.'],
        ['icon' => 'device-phone-mobile', 'title' => 'Tout sur votre téléphone', 'text' => 'Paiement par Mobile Money. Rien n\'est prélevé automatiquement.'],
    ];

    public static function enabled(): bool
    {
        return (bool) config('site.talkami.enabled', true);
    }

    /**
     * Lien vers Talkami : seulement une adresse https de talkami.net (sinon le lien de départ).
     */
    public static function url(): string
    {
        $url = trim((string) config('site.talkami.url'));

        return self::isValidUrl($url) ? $url : self::DEFAULT_URL;
    }

    public static function isValidUrl(string $url): bool
    {
        if (! str_starts_with(strtolower($url), 'https://')) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'talkami.net' || str_ends_with($host, '.talkami.net');
    }

    public static function whatsapp(): string
    {
        return trim((string) config('site.talkami.whatsapp'));
    }

    /**
     * Lien wa.me du numéro WhatsApp de Talkami (chiffres seulement), ou null.
     */
    public static function whatsappUrl(): ?string
    {
        $digits = preg_replace('/\D+/', '', self::whatsapp()) ?? '';

        return strlen($digits) >= 8 ? 'https://wa.me/'.$digits : null;
    }

    /**
     * Formules complètes (nom et prix) seulement.
     *
     * @return list<array{name: string, price: int, period: string, detail: string}>
     */
    public static function plans(): array
    {
        $plans = [];

        foreach ((array) config('site.talkami.plans', []) as $plan) {
            if (! is_array($plan) || blank($plan['name'] ?? null) || ! is_numeric($plan['price'] ?? null)) {
                continue;
            }

            $plans[] = [
                'name' => trim((string) $plan['name']),
                'price' => (int) $plan['price'],
                'period' => trim((string) ($plan['period'] ?? '')),
                'detail' => trim((string) ($plan['detail'] ?? '')),
            ];
        }

        return $plans;
    }

    /**
     * Texte pour Ama (connaissances) : faits seulement.
     */
    public static function assistantText(): string
    {
        if (! self::enabled()) {
            return '';
        }

        $lines = [self::CREATOR, 'Site : '.self::url()];

        foreach (self::FEATURES as $feature) {
            $lines[] = '- '.$feature['title'].' : '.$feature['text'];
        }

        foreach (self::plans() as $plan) {
            $lines[] = '- Formule '.$plan['name'].' : '.Money::fcfa($plan['price']).($plan['period'] !== '' ? ' '.$plan['period'] : '').($plan['detail'] !== '' ? ' ('.$plan['detail'].')' : '');
        }

        if (self::whatsapp() !== '') {
            $lines[] = 'WhatsApp de Talkami : '.self::whatsapp();
        }

        return implode("\n", $lines);
    }
}
