<?php

namespace Tests\Unit;

use App\Assistant\PersonalData;
use PHPUnit\Framework\TestCase;

/**
 * Données personnelles retirées d'un message du visiteur avant l'envoi à Gemini et l'enregistrement.
 */
class PersonalDataTest extends TestCase
{
    public function test_emails_links_phones_and_codes_are_removed(): void
    {
        $this->assertSame('Écrivez à [adresse e-mail]', PersonalData::redact('Écrivez à kossi.afi@gmail.com'));
        $this->assertSame('Voir [lien]', PersonalData::redact('Voir https://exemple.com/page?jeton=abc'));
        $this->assertSame('Mon numéro : [numéro]', PersonalData::redact('Mon numéro : +228 90 12 34 56'));
        $this->assertSame('Appelez le [numéro]', PersonalData::redact('Appelez le 0022890123456'));
        $this->assertSame('Bénin [numéro]', PersonalData::redact('Bénin 01 51 53 39 35'));
        $this->assertSame('Le code [code] ne marche pas', PersonalData::redact('Le code 483920 ne marche pas'));
        $this->assertSame('Mon PIN [masqué], mot de passe : [masqué]', PersonalData::redact('Mon PIN 1234, mot de passe : Soleil2026!'));
        $this->assertSame('code secret [masqué] svp', PersonalData::redact('code secret 7788 svp'));
    }

    public function test_amounts_and_years_are_kept(): void
    {
        $this->assertSame('J\'ai payé 25 000 FCFA', PersonalData::redact('J\'ai payé 25 000 FCFA'));
        $this->assertSame('Envoyé 25000 F le 3', PersonalData::redact('Envoyé 25000 F le 3'));
        $this->assertSame('Prix 150000 francs', PersonalData::redact('Prix 150000 francs'));
        $this->assertSame('Créée en 2023', PersonalData::redact('Créée en 2023'));
        $this->assertSame('Étape 3 sur 4', PersonalData::redact('Étape 3 sur 4'));
        $this->assertSame('J\'ai oublié mon mot de passe, que faire ?', PersonalData::redact('J\'ai oublié mon mot de passe, que faire ?'));
        $this->assertSame('Mot de passe oublié sur Pinterest', PersonalData::redact('Mot de passe oublié sur Pinterest'));
    }
}
