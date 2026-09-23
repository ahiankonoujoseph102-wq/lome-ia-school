<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

class Placeholder
{
    /**
     * Met en évidence les emplacements à remplir, écrits entre crochets : « [Témoignage à remplacer] ».
     */
    public static function highlight(?string $text): HtmlString
    {
        $escaped = e((string) $text);

        return new HtmlString((string) preg_replace('/\[[^\]]+\]/u', '<span class="placeholder-text">$0</span>', $escaped));
    }
}
