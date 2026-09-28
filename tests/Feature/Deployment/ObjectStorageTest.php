<?php

namespace Tests\Feature\Deployment;

use App\Enums\EnrollmentSource;
use App\Models\CourseModule;
use App\Models\Formation;
use App\Models\Lesson;
use App\Models\Purchase;
use App\Models\User;
use App\Support\FileStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Stockage des fichiers choisi par .env : dossiers du serveur (Hostinger) ou stockage objet
 * compatible S3 (Laravel Cloud), avec des liens temporaires signés pour les fichiers privés.
 */
class ObjectStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_folders_are_kept_by_default(): void
    {
        config(['filesystems.private_files_disk' => 'local', 'filesystems.public_files_disk' => 'local']);
        FileStorage::configure();

        foreach (array_keys(FileStorage::PRIVATE_DISKS) as $disk) {
            $this->assertTrue(FileStorage::isLocal($disk));
        }

        $this->assertTrue(FileStorage::isLocal('public'));
    }

    public function test_an_unknown_disk_name_changes_nothing(): void
    {
        config(['filesystems.private_files_disk' => 'inexistant', 'filesystems.public_files_disk' => 'inexistant']);
        FileStorage::configure();

        $this->assertTrue(FileStorage::isLocal('payment_proofs'));
        $this->assertTrue(FileStorage::isLocal('public'));
    }

    public function test_files_go_to_the_object_storage_buckets_chosen_in_env(): void
    {
        // Disques ajoutés par Laravel Cloud (variable LARAVEL_CLOUD_DISK_CONFIG) : un bucket privé, un public.
        config([
            'filesystems.disks.prive' => $this->bucket('lome-prive', null),
            'filesystems.disks.public-cloud' => $this->bucket('lome-public', 'https://fichiers.example.com'),
            'filesystems.private_files_disk' => 'prive',
            'filesystems.public_files_disk' => 'public-cloud',
        ]);
        FileStorage::configure();
        Storage::forgetDisk(['payment_proofs', 'lesson_resources', 'chat_media', 'public']);

        foreach (FileStorage::PRIVATE_DISKS as $disk => $folder) {
            $this->assertSame(['driver' => 'scoped', 'disk' => 'prive', 'prefix' => $folder, 'visibility' => 'private', 'throw' => false], config("filesystems.disks.{$disk}"));
            $this->assertFalse(FileStorage::isLocal($disk));
        }

        // Lien temporaire signé, dans le sous-dossier du disque privé (signature calculée sans réseau).
        $url = Storage::disk('payment_proofs')->temporaryUrl('12/preuve.jpg', now()->addMinutes(5));
        $this->assertStringContainsString('lome-prive', $url);
        $this->assertStringContainsString('/payment-proofs/12/preuve.jpg', $url);
        $this->assertStringContainsString('X-Amz-Signature=', $url);

        // Fichiers publics : adresse publique du bucket public, sans « visibility » (refusée par Cloudflare R2).
        $this->assertSame('https://fichiers.example.com/formations/image.jpg', Storage::disk('public')->url('formations/image.jpg'));
        $this->assertArrayNotHasKey('visibility', (array) config('filesystems.disks.public'));
        $this->assertSame('https://fichiers.example.com/avatars/1.jpg', FileStorage::publicUrl('avatars/1.jpg'));
    }

    public function test_payment_proof_is_served_by_a_signed_temporary_link_to_its_owner_only(): void
    {
        $this->simulateObjectStorage('payment_proofs');
        $student = User::factory()->create();
        $purchase = Purchase::factory()->for($student)->create(['proof_path' => $student->id.'/preuve.jpg']);
        Storage::disk('payment_proofs')->put($student->id.'/preuve.jpg', 'image');

        $response = $this->actingAs($student)->get(route('student.purchases.proof', $purchase));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://stockage.example/'.$student->id.'/preuve.jpg?signature=test', (string) $response->headers->get('Location'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('inline', (string) $response->headers->get('Location'));

        $this->actingAs(User::factory()->create())->get(route('student.purchases.proof', $purchase))->assertForbidden();
    }

    public function test_missing_private_file_is_not_found(): void
    {
        $this->simulateObjectStorage('payment_proofs');
        $student = User::factory()->create();
        $purchase = Purchase::factory()->for($student)->create(['proof_path' => $student->id.'/absente.jpg']);

        $this->actingAs($student)->get(route('student.purchases.proof', $purchase))->assertNotFound();
    }

    public function test_lesson_resource_is_downloaded_through_a_signed_link_by_enrolled_students_only(): void
    {
        $this->simulateObjectStorage('lesson_resources');
        $formation = Formation::factory()->create();
        $lesson = Lesson::factory()->for(CourseModule::factory()->for($formation), 'module')->create(['is_published' => true]);
        Storage::disk('lesson_resources')->put($lesson->id.'/fiche.pdf', 'contenu');
        $resource = $lesson->resources()->create(['name' => 'Fiche pratique.pdf', 'path' => $lesson->id.'/fiche.pdf', 'size' => 7]);
        $url = route('lessons.resources.download', [$formation, $lesson, $resource]);

        $student = User::factory()->create();
        $student->enroll($formation, EnrollmentSource::Admin);

        $response = $this->actingAs($student)->get($url);
        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://stockage.example/'.$lesson->id.'/fiche.pdf', $location);
        $this->assertStringContainsString(rawurlencode('attachment; filename="Fiche pratique.pdf"'), $location);

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    public function test_uploaded_share_image_on_object_storage_is_redirected_to_its_public_address(): void
    {
        config(['filesystems.disks.public' => $this->bucket('lome-public', 'https://fichiers.example.com'), 'site.brand.og_image' => 'site/partage.jpg']);
        Storage::forgetDisk('public');

        $this->get(route('og.site'))->assertRedirect('https://fichiers.example.com/site/partage.jpg');
    }

    /**
     * Disque déclaré comme stockage objet, simulé en local, avec des liens temporaires reconnaissables.
     */
    private function simulateObjectStorage(string $disk): void
    {
        config(["filesystems.disks.{$disk}" => ['driver' => 'scoped', 'disk' => 'prive', 'prefix' => 'test']]);
        Storage::fake($disk);
        Storage::disk($disk)->buildTemporaryUrlsUsing(
            fn (string $path, $expiration, array $options): string => 'https://stockage.example/'.$path.'?signature=test&disposition='.rawurlencode((string) ($options['ResponseContentDisposition'] ?? '')),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function bucket(string $bucket, ?string $url): array
    {
        return [
            'driver' => 's3',
            'key' => 'cle-de-test',
            'secret' => 'secret-de-test',
            'bucket' => $bucket,
            'url' => $url,
            'endpoint' => 'https://compte.r2.cloudflarestorage.com',
            'region' => 'auto',
            'use_path_style_endpoint' => false,
            'throw' => false,
            'report' => false,
        ];
    }
}
