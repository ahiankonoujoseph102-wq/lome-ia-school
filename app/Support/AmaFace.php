<?php

namespace App\Support;

/**
 * Visage d'Ama (composant x-site.ama-face) : jeune femme africaine souriante, dessin animé simple aux couleurs du site.
 *
 * Le dessin est fait de calques HTML : chaque calque est un fond de couleur découpé par un masque SVG (formes noires
 * dans un carré de 200 × 200). Pourquoi pas un simple SVG ? Le mode sombre forcé de Chrome (téléphone en mode clair)
 * éclaircit toutes les couleurs foncées d'un SVG (cheveux et yeux devenaient blancs), alors qu'il ne touche jamais un
 * fond foncé ni un masque : vérifié couleur par couleur, 0 pixel de différence. Seule contrainte : aucune couleur plus
 * claire que le reflet des yeux (#d4c9b8), au-delà Chrome assombrit les fonds.
 *
 * Trois propositions (« style ») : a = cheveux naturels et bandeau doré, b = tresses à perles dorées, c = foulard violet
 * et doré. Les masques ne sont écrits qu'une fois par page (balise <style>, seulement pour le visage affiché).
 */
final class AmaFace
{
    public const string BG = '#2a2877';

    private const string HAIR = '#1d1411';

    private const string SKIN = '#9b6a47';

    private const string GOLD = '#f5a70b';

    private const string VIOLET = '#6d28d9';

    /**
     * Calques communs aux trois visages : nom => [couleur, formes]. Les groupes animés (tête, yeux, bouche, main, bulle)
     * sont choisis par le composant.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const array COMMON = [
        'blouse' => [self::VIOLET, '<path d="M18 200C20 170 52 158 86 154L100 170L114 154C148 158 180 170 182 200Z"/>'],
        'neck' => ['#7a4a2f', '<path d="M86 124H114V154L100 170L86 154Z"/>'],
        'collar' => [self::GOLD, '<path d="M86 154L100 170L114 154" fill="none" stroke="#000" stroke-width="3" stroke-linejoin="round"/><circle cx="100" cy="174" r="3.4"/>'],
        'ears' => ['#8a5b3b', '<ellipse cx="61" cy="94" rx="6" ry="9"/><ellipse cx="139" cy="94" rx="6" ry="9"/>'],
        'skin' => [self::SKIN, '<ellipse cx="100" cy="90" rx="39" ry="45"/>'],
        'cheeks' => ['#b0694a', '<circle cx="77" cy="106" r="7.5"/><circle cx="123" cy="106" r="7.5"/>'],
        'nose' => ['#7a4a2f', '<path d="M100 98q-4 8 0 10q3 1 5-1" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round"/>'],
        'brows' => [self::HAIR, '<path d="M74 79Q84 73 94 77M106 77Q116 73 126 79" fill="none" stroke="#000" stroke-width="3.4" stroke-linecap="round"/>'],
        'eyes' => ['#1a0f0b', '<ellipse cx="84" cy="92" rx="5.6" ry="7"/><ellipse cx="116" cy="92" rx="5.6" ry="7"/><path d="M77 87q-3-3-5-2M123 87q3-3 5-2" fill="none" stroke="#000" stroke-width="2" stroke-linecap="round"/>'],
        'glints' => ['#d4c9b8', '<circle cx="86" cy="89.5" r="2"/><circle cx="118" cy="89.5" r="2"/>'],
        'smile' => ['#6b1f2c', '<path d="M80 111Q100 134 120 111Q100 119 80 111Z" stroke="#000" stroke-width="2" stroke-linejoin="round"/>'],
        'mouth' => ['#4a1420', '<path d="M82 112Q100 118 118 112Q116 134 100 134Q84 134 82 112Z"/>'],
        'tongue' => ['#c9505f', '<ellipse cx="100" cy="128" rx="8" ry="4"/>'],
        'bubble' => ['#0b1030', '<rect x="124" y="14" width="56" height="28" rx="14"/>'],
        'dots' => ['#a78bfa', '<rect x="124" y="14" width="56" height="28" rx="14" fill="none" stroke="#000" stroke-width="2"/><circle cx="141" cy="28" r="4"/><circle cx="152" cy="28" r="4"/><circle cx="163" cy="28" r="4"/>'],
        'sleeve' => [self::VIOLET, '<path d="M176 200L170 142" fill="none" stroke="#000" stroke-width="16" stroke-linecap="round"/>'],
        'palm' => [self::SKIN, '<path d="M159 119l-3-9M164 114l-1-10M170 114l2-10M175 119l5-8" fill="none" stroke="#000" stroke-width="4.6" stroke-linecap="round"/><path d="M170 150L168 134" fill="none" stroke="#000" stroke-width="11" stroke-linecap="round"/><circle cx="167" cy="124" r="10"/>'],
    ];

    /**
     * Calques propres à chaque visage : derrière la tête (« back ») et devant le front (« front »).
     *
     * @var array<string, array{back: array<string, array{0: string, 1: string}>, earrings: string, front: array<string, array{0: string, 1: string}>}>
     */
    private const array STYLES = [
        'a' => [
            'back' => [
                'hair' => [self::HAIR, '<ellipse cx="100" cy="64" rx="60" ry="48"/><circle cx="46" cy="62" r="15"/><circle cx="154" cy="62" r="15"/><circle cx="58" cy="36" r="15"/><circle cx="142" cy="36" r="15"/><circle cx="84" cy="22" r="15"/><circle cx="116" cy="22" r="15"/>'],
            ],
            'earrings' => '<circle cx="60" cy="105" r="3.6"/><circle cx="140" cy="105" r="3.6"/>',
            'front' => [
                'fringe' => [self::HAIR, '<path d="M62 74C64 50 82 42 100 42C118 42 136 50 138 74C128 58 114 54 100 54C86 54 72 58 62 74Z"/>'],
                'band' => [self::GOLD, '<path d="M62 70Q100 50 138 70" fill="none" stroke="#000" stroke-width="5" stroke-linecap="round"/>'],
            ],
        ],
        'b' => [
            'back' => [
                'hair' => [self::HAIR, '<path d="M62 72C52 100 50 140 55 174M138 72C148 100 150 140 145 174" fill="none" stroke="#000" stroke-width="13" stroke-linecap="round"/><path d="M56 96C50 42 74 22 100 22C126 22 150 42 144 96C142 66 128 44 100 44C72 44 58 66 56 96Z"/>'],
                'braids' => ['#4a362b', '<path d="M62 72C52 100 50 140 55 174M138 72C148 100 150 140 145 174" fill="none" stroke="#000" stroke-width="13" stroke-dasharray="2 8"/>'],
                'beads' => [self::GOLD, '<circle cx="55" cy="177" r="4"/><circle cx="145" cy="177" r="4"/>'],
            ],
            'earrings' => '<circle cx="60" cy="105" r="3.6"/><circle cx="140" cy="105" r="3.6"/>',
            'front' => [],
        ],
        'c' => [
            'back' => [],
            'earrings' => '<circle cx="60" cy="112" r="7" fill="none" stroke="#000" stroke-width="3"/><circle cx="140" cy="112" r="7" fill="none" stroke="#000" stroke-width="3"/>',
            'front' => [
                'wrap' => [self::VIOLET, '<path d="M55 76C46 38 76 14 108 16C142 18 160 46 145 76C133 60 118 52 100 52C80 52 66 60 55 76Z"/>'],
                'stripes' => [self::GOLD, '<path d="M64 56Q100 30 138 52M60 66Q100 40 143 64" fill="none" stroke="#000" stroke-width="3.4" stroke-linecap="round"/><path d="M118 22C128 4 152 8 154 20C146 28 130 28 118 22Z"/>'],
                'knot' => ['#d98a06', '<path d="M118 22C106 10 94 16 96 26C104 32 114 30 118 22Z"/>'],
            ],
        ],
    ];

    /**
     * Contour doré tracé à l'entrée du mode appel (SVG : l'or reste identique avec le mode sombre forcé).
     *
     * @var array<string, string>
     */
    public const array INK = [
        'a' => 'M34 62a66 54 0 1 1 132 0',
        'b' => 'M58 92C54 40 76 24 100 24C124 24 146 40 142 92M60 70C50 100 48 140 54 176M140 70C150 100 152 140 146 176',
        'c' => 'M56 74C48 36 76 14 108 16C142 18 160 46 144 76',
    ];

    public static function style(?string $style): string
    {
        return is_string($style) && isset(self::STYLES[$style]) ? $style : 'a';
    }

    /**
     * Calques du visage, dans l'ordre de dessin, rangés par groupe animé.
     *
     * @return array{back: list<string>, front: list<string>}
     */
    public static function styleLayers(string $style): array
    {
        $style = self::STYLES[self::style($style)];

        return ['back' => array_keys($style['back']), 'front' => array_keys($style['front'])];
    }

    /**
     * Masques et couleurs d'un visage, une seule fois par page (même s'il est affiché plusieurs fois).
     */
    public static function stylesOnce(string $style): string
    {
        $style = self::style($style);
        $key = 'ama-face-css-'.$style;
        $attributes = request()->attributes;

        if ($attributes->get($key)) {
            return '';
        }

        $attributes->set($key, true);

        return '<style>'.self::css($style).'</style>';
    }

    public static function css(string $style): string
    {
        $style = self::style($style);
        $layers = [...self::COMMON, ...self::STYLES[$style]['back'], ...self::STYLES[$style]['front'], 'earrings' => [self::GOLD, self::STYLES[$style]['earrings']]];
        $css = '';

        foreach ($layers as $name => [$color, $shapes]) {
            // Encodage court : seuls %, #, < et > sont encodés, les guillemets deviennent des apostrophes.
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200">'.$shapes.'</svg>';
            $mask = 'url("data:image/svg+xml,'.strtr($svg, ['%' => '%25', '#' => '%23', '<' => '%3C', '>' => '%3E', '"' => "'"]).'")';
            $css .= '.ama-face[data-style="'.$style.'"] .ama-l-'.$name.'{background:'.$color.';--m:'.$mask.'}';
        }

        return $css;
    }
}
