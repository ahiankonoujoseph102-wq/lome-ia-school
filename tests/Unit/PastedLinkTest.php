<?php

namespace Tests\Unit;

use App\Support\GoogleDrive;
use App\Support\GoogleMeet;
use App\Support\PastedLink;
use App\Support\YouTube;
use PHPUnit\Framework\TestCase;

/**
 * Liens collés ou tapés sur téléphone : nettoyés avant la vérification.
 */
class PastedLinkTest extends TestCase
{
    private const string MEET = 'https://meet.google.com/abc-defg-hij';

    public function test_spaces_and_line_breaks_around_the_link_are_removed(): void
    {
        $this->assertSame(self::MEET, PastedLink::clean('  '.self::MEET."  \n"));
        $this->assertSame(self::MEET, PastedLink::clean("\n\t".self::MEET."\r\n"));
        $this->assertSame('', PastedLink::clean("  \n "));
        $this->assertSame('', PastedLink::clean(null));
    }

    public function test_invisible_characters_are_removed(): void
    {
        // Espace insécable, espace fine insécable, largeur nulle, marque de direction, BOM, trait d'union conditionnel.
        $this->assertSame(self::MEET, PastedLink::clean("\u{00A0}".self::MEET."\u{00A0}"));
        $this->assertSame(self::MEET, PastedLink::clean("\u{202F}".self::MEET."\u{200B}"));
        $this->assertSame(self::MEET, PastedLink::clean("\u{FEFF}https://meet.google.com/abc-\u{200B}defg-hij\u{200E}"));
        $this->assertSame(self::MEET, PastedLink::clean("https://meet.google.com/abc\u{00AD}-defg-hij\u{2060}"));
        $this->assertSame('https://youtu.be/dQw4w9WgXcQ', PastedLink::clean("\u{200C}https://youtu.be/dQw4w9WgXcQ\u{200D}"));
    }

    public function test_capital_letters_added_by_the_keyboard_are_removed_from_https_and_the_domain_only(): void
    {
        $this->assertSame(self::MEET, PastedLink::clean('Https://meet.google.com/abc-defg-hij'));
        $this->assertSame(self::MEET, PastedLink::clean('HTTPS://Meet.Google.com/abc-defg-hij'));
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', PastedLink::clean('Https://Www.YouTube.com/watch?v=dQw4w9WgXcQ'));
        // Le chemin garde ses majuscules : les identifiants YouTube et Drive les distinguent.
        $this->assertSame('https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz/view', PastedLink::clean('Https://Drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz/view'));
        $this->assertSame('https://facebook.com/page', PastedLink::clean('http://facebook.com/page'));
    }

    public function test_the_link_is_extracted_from_a_longer_text(): void
    {
        $this->assertSame(self::MEET, PastedLink::clean('Rejoindre avec Google Meet : https://meet.google.com/abc-defg-hij'));
        $this->assertSame(self::MEET, PastedLink::clean("Réunion de Joseph\nRejoindre avec Google Meet\nhttps://meet.google.com/abc-defg-hij\nOu composez le : +228 …"));
        $this->assertSame(self::MEET, PastedLink::clean('Voici le lien (https://meet.google.com/abc-defg-hij).'));
        $this->assertSame(self::MEET, PastedLink::clean('Meet:https://meet.google.com/abc-defg-hij'));
        $this->assertSame(self::MEET, PastedLink::clean('Le lien : meet.google.com/abc-defg-hij, merci'));
        $this->assertSame('https://youtu.be/dQw4w9WgXcQ?si=AbC', PastedLink::clean('Regarde cette vidéo « https://youtu.be/dQw4w9WgXcQ?si=AbC »'));
    }

    public function test_https_is_added_when_it_is_missing(): void
    {
        $this->assertSame(self::MEET, PastedLink::clean('meet.google.com/abc-defg-hij'));
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', PastedLink::clean('www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertSame('https://youtu.be/dQw4w9WgXcQ', PastedLink::clean('youtu.be/dQw4w9WgXcQ'));
        $this->assertSame('https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz/view', PastedLink::clean('drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz/view'));
        $this->assertSame('https://www.facebook.com/lomeiaschool', PastedLink::clean('www.facebook.com/lomeiaschool'));
        $this->assertSame('https://www.tiktok.com/@lomeiaschool', PastedLink::clean('Www.tiktok.com/@lomeiaschool'));
    }

    public function test_a_text_without_any_link_is_only_cleaned(): void
    {
        $this->assertSame('dQw4w9WgXcQ', PastedLink::clean("\u{200B} dQw4w9WgXcQ "));
        $this->assertSame('pas de lien ici', PastedLink::clean(" pas de lien\u{00A0}ici "));
        $this->assertSame('abc-defg-hij', PastedLink::clean('abc-defg-hij'));
        $this->assertSame('écrire à joseph@gmail.com', PastedLink::clean('écrire à joseph@gmail.com'));
    }

    public function test_the_link_readers_accept_the_messy_versions(): void
    {
        $this->assertSame(self::MEET, GoogleMeet::normalize("Rejoindre avec Google Meet :\u{00A0}Meet.google.com/ABC-DEFG-HIJ\u{200B}"));
        $this->assertSame('dQw4w9WgXcQ', YouTube::id("\u{200B}Https://youtu.be/dQw4w9WgXcQ\u{00A0}"));
        $this->assertTrue(YouTube::isShorts("\u{FEFF}Www.youtube.com/shorts/dQw4w9WgXcQ"));
        $this->assertSame('1AbCdEfGhIjKlMnOpQrStUvWxYz', GoogleDrive::fileId("Le support : drive.google.com/file/d/1AbCdEfGhIjKlMnOpQrStUvWxYz/view?usp=sharing\u{200B}"));
        $this->assertTrue(GoogleDrive::isGoogleUrl("\u{00A0}Https://docs.google.com/document/d/1AbC/edit "));
    }

    public function test_a_really_invalid_link_is_still_refused_with_a_clear_message(): void
    {
        $this->assertNull(GoogleMeet::normalize('https://zoom.us/j/123'));
        $this->assertNull(GoogleMeet::normalize('https://meet.google.com/abc-defg'));
        $this->assertNull(GoogleMeet::normalize('pas de lien ici'));
        $this->assertNull(YouTube::id('https://exemple.com/ma-video'));
        $this->assertNull(GoogleDrive::fileId('https://exemple.com/fichier.pdf'));

        $this->assertStringContainsString('Le lien Google Meet doit ressembler à https://meet.google.com/abc-defg-hij', GoogleMeet::invalidMessage('https://zoom.us/j/123'));
        $this->assertStringContainsString('3 lettres, un tiret, 4 lettres', GoogleMeet::invalidMessage('meet.google.com/abc-defg'));
        $this->assertStringContainsString('Le lien YouTube doit ressembler à https://www.youtube.com/watch?v=', YouTube::invalidMessage());
    }
}
