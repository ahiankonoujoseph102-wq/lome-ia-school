<?php

namespace App\Chat;

use Illuminate\Support\HtmlString;

/**
 * Texte d'un message prêt à afficher : échappé (aucun HTML accepté), liens cliquables, retours à la ligne.
 */
class MessageText
{
    public static function toHtml(?string $text): HtmlString
    {
        $escaped = e((string) $text);

        $linked = preg_replace_callback(
            '~\b(?:https?://|www\.)(?:(?!&quot;|&#039;|&lt;|&gt;)[^\s<])+~iu',
            function (array $match): string {
                $url = $match[0];
                $trailing = '';

                // Ponctuation finale qui ne fait pas partie du lien (« Voir https://exemple.com. »).
                while ($url !== '' && preg_match('~[.,;:!?)\]»"\']$~u', $url) === 1) {
                    $trailing = mb_substr($url, -1).$trailing;
                    $url = mb_substr($url, 0, -1);
                }

                if ($url === '' || preg_match('~^(?:https?://|www\.)$~i', $url) === 1) {
                    return $match[0];
                }

                $href = str_starts_with(strtolower($url), 'www.') ? 'https://'.$url : $url;

                return '<a href="'.$href.'" target="_blank" rel="noopener noreferrer nofollow" class="chat-link">'.$url.'</a>'.$trailing;
            },
            $escaped,
        );

        return new HtmlString(nl2br((string) $linked, false));
    }
}
