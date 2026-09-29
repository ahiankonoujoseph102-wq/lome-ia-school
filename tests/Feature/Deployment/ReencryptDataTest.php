<?php

namespace Tests\Feature\Deployment;

use App\Models\Consultation;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Consultations\ConsultationTestCase;

/**
 * Changement de APP_KEY (clé exposée) sans perdre la double authentification ni les liens de
 * gestion des consultations : ancienne clé dans APP_PREVIOUS_KEYS, puis app:reencrypt-data.
 */
class ReencryptDataTest extends ConsultationTestCase
{
    private static function newKey(): string
    {
        return 'base64:'.base64_encode(random_bytes(32));
    }

    /**
     * @param  list<string>  $previous
     */
    private function useKeys(string $key, array $previous = []): void
    {
        config(['app.key' => $key, 'app.previous_keys' => $previous]);
        $this->app->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
    }

    public function test_data_encrypted_with_the_old_key_is_rewritten_with_the_new_one(): void
    {
        $old = self::newKey();
        $new = self::newKey();

        $this->useKeys($old);
        $admin = User::factory()->admin()->create();
        $admin->forceFill([
            'two_factor_secret' => encrypt('SECRET2FA'),
            'two_factor_recovery_codes' => encrypt(json_encode(['code-1', 'code-2'])),
        ])->save();
        [$consultation, $token] = $this->booked('2026-10-06 10:15');

        // Nouvelle clé seule, sans l'ancienne : tout serait illisible (c'est ce qu'on évite).
        $this->useKeys($new);
        $this->assertThrows(fn () => decrypt((string) DB::table('users')->where('id', $admin->id)->value('two_factor_secret')), DecryptException::class);

        // Étape 1 et 2 : nouvelle clé + ancienne dans APP_PREVIOUS_KEYS, puis re-chiffrement.
        $this->useKeys($new, [$old]);
        $this->artisan('app:reencrypt-data')->assertSuccessful();

        // Étape 3 : ancienne clé retirée, tout reste lisible.
        $this->useKeys($new);
        $row = DB::table('users')->where('id', $admin->id)->first();
        $this->assertSame('SECRET2FA', decrypt($row->two_factor_secret));
        $this->assertSame(['code-1', 'code-2'], json_decode(decrypt($row->two_factor_recovery_codes), true));
        $this->assertSame($token, Consultation::query()->findOrFail($consultation->id)->manage_token);
        $this->get(route('consultation.manage', $token))->assertOk();

        // Relancer la commande ne change rien.
        $this->artisan('app:reencrypt-data')->assertSuccessful();
        $this->assertSame('SECRET2FA', decrypt((string) DB::table('users')->where('id', $admin->id)->value('two_factor_secret')));
    }

    public function test_it_fails_loudly_when_the_old_key_is_missing(): void
    {
        $this->useKeys(self::newKey());
        User::factory()->admin()->create()->forceFill(['two_factor_secret' => encrypt('SECRET2FA')])->save();

        $this->useKeys(self::newKey());
        $this->artisan('app:reencrypt-data')->assertFailed();
    }
}
