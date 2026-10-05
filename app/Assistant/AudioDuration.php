<?php

namespace App\Assistant;

/**
 * Durée d'un audio fabriqué pour la voix naturelle d'Ama (millisecondes), pour compter les minutes d'audio de
 * Cloudflare (l'offre gratuite se compte en neurones par minute d'audio).
 *
 * - MP3 (Cloudflare MeloTTS) : somme des trames MPEG (échantillons par trame / fréquence), balise ID3 ignorée.
 * - WAV : taille des données / octets par seconde de l'en-tête.
 * - Fichier illisible : estimation d'après le texte (CHARS_PER_SECOND caractères par seconde), toujours au moins 1 s.
 */
final class AudioDuration
{
    /**
     * Débit de parole estimé en français, quand la durée ne peut pas être lue dans le fichier.
     */
    public const int CHARS_PER_SECOND = 14;

    private const array MPEG1_BITRATES = [
        1 => [0, 32, 64, 96, 128, 160, 192, 224, 256, 288, 320, 352, 384, 416, 448],
        2 => [0, 32, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 384],
        3 => [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320],
    ];

    private const array MPEG2_BITRATES = [
        1 => [0, 32, 48, 56, 64, 80, 96, 112, 128, 144, 160, 176, 192, 224, 256],
        2 => [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
        3 => [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
    ];

    private const array SAMPLE_RATES = [
        3 => [44100, 48000, 32000], // MPEG 1
        2 => [22050, 24000, 16000], // MPEG 2
        0 => [11025, 12000, 8000],  // MPEG 2.5
    ];

    /**
     * Durée de l'audio, lue dans le fichier, sinon estimée d'après le texte lu.
     */
    public static function milliseconds(SpeechAudio $audio, string $text = ''): int
    {
        $measured = str_starts_with($audio->bytes, 'RIFF') ? self::wav($audio->bytes) : self::mp3($audio->bytes);

        return $measured ?? self::estimate($text);
    }

    /**
     * Durée estimée d'un texte lu à voix haute (au moins une seconde).
     */
    public static function estimate(string $text): int
    {
        return max(1000, (int) round(mb_strlen($text) / self::CHARS_PER_SECOND * 1000));
    }

    /**
     * Durée d'un MP3 (somme de ses trames), ou null si aucune trame n'est reconnue.
     */
    public static function mp3(string $bytes): ?int
    {
        $length = strlen($bytes);
        $offset = 0;

        // Balise ID3v2 au début : taille sur 4 octets de 7 bits, plus 10 octets d'en-tête (et 10 de pied si annoncé).
        if ($length >= 10 && str_starts_with($bytes, 'ID3')) {
            $size = ((ord($bytes[6]) & 0x7F) << 21) | ((ord($bytes[7]) & 0x7F) << 14) | ((ord($bytes[8]) & 0x7F) << 7) | (ord($bytes[9]) & 0x7F);
            $offset = 10 + $size + ((ord($bytes[5]) & 0x10) !== 0 ? 10 : 0);
        }

        $seconds = 0.0;
        $frames = 0;

        while ($offset + 4 <= $length) {
            $header = unpack('N', substr($bytes, $offset, 4));
            $word = is_array($header) ? (int) $header[1] : 0;
            $frame = self::frame($word);

            if ($frame === null) {
                // Pas une trame : on avance d'un octet (données parasites entre deux trames).
                $offset++;

                continue;
            }

            [$frameLength, $samples, $rate] = $frame;
            $seconds += $samples / $rate;
            $frames++;
            $offset += $frameLength;
        }

        return $frames === 0 ? null : (int) round($seconds * 1000);
    }

    /**
     * Longueur en octets, échantillons et fréquence d'une trame MPEG dont l'en-tête vaut $word, ou null.
     *
     * @return array{0: int, 1: int, 2: int}|null
     */
    private static function frame(int $word): ?array
    {
        if (($word & 0xFFE00000) !== 0xFFE00000) {
            return null;
        }

        $version = ($word >> 19) & 0x3;   // 0 : MPEG 2.5, 2 : MPEG 2, 3 : MPEG 1
        $layerBits = ($word >> 17) & 0x3; // 1 : couche III, 2 : couche II, 3 : couche I
        $bitrateIndex = ($word >> 12) & 0xF;
        $rateIndex = ($word >> 10) & 0x3;
        $padding = ($word >> 9) & 0x1;

        if ($version === 1 || $layerBits === 0 || $bitrateIndex === 0 || $bitrateIndex === 15 || $rateIndex === 3) {
            return null;
        }

        $layer = 4 - $layerBits;
        $bitrate = ($version === 3 ? self::MPEG1_BITRATES : self::MPEG2_BITRATES)[$layer][$bitrateIndex] * 1000;
        $rate = self::SAMPLE_RATES[$version][$rateIndex];

        if ($layer === 1) {
            return [(intdiv(12 * $bitrate, $rate) + $padding) * 4, 384, $rate];
        }

        $samples = $layer === 3 && $version !== 3 ? 576 : 1152;
        $length = intdiv(intdiv($samples, 8) * $bitrate, $rate) + $padding;

        return $length < 4 ? null : [$length, $samples, $rate];
    }

    /**
     * Durée d'un WAV d'après son en-tête, ou null.
     */
    public static function wav(string $bytes): ?int
    {
        $format = strpos($bytes, 'fmt ');
        $data = strpos($bytes, 'data');

        if ($format === false || $data === false || strlen($bytes) < $format + 24 || strlen($bytes) < $data + 8) {
            return null;
        }

        $byteRate = unpack('V', substr($bytes, $format + 16, 4));
        $size = unpack('V', substr($bytes, $data + 4, 4));
        $byteRate = is_array($byteRate) ? (int) $byteRate[1] : 0;
        $size = is_array($size) ? (int) $size[1] : 0;

        // Taille annoncée plus grande que le fichier (son en continu) : on prend ce qui est réellement là.
        $size = min($size, strlen($bytes) - $data - 8);

        return $byteRate > 0 && $size > 0 ? (int) round($size / $byteRate * 1000) : null;
    }
}
