<?php

namespace Tests\Unit;

use App\Support\GoogleDrive;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GoogleDriveTest extends TestCase
{
    private const string ID = '1AbCdEfGhIjKlMnOpQrStUvWxYz012345';

    /**
     * @return array<string, array{0: string}>
     */
    public static function driveLinks(): array
    {
        $id = self::ID;

        return [
            'lien de partage' => ["https://drive.google.com/file/d/{$id}/view?usp=sharing"],
            'lien de partage (drive_link)' => ["https://drive.google.com/file/d/{$id}/view?usp=drive_link"],
            'aperçu' => ["https://drive.google.com/file/d/{$id}/preview"],
            'compte multiple' => ["https://drive.google.com/file/u/0/d/{$id}/view"],
            'ancien format open' => ["https://drive.google.com/open?id={$id}"],
            'téléchargement' => ["https://drive.google.com/uc?export=download&id={$id}"],
            'docs.google.com' => ["https://docs.google.com/file/d/{$id}/edit"],
            'usercontent' => ["https://drive.usercontent.google.com/download?id={$id}&export=download"],
            'identifiant seul' => [$id],
            'avec espaces' => ["  https://drive.google.com/file/d/{$id}/view  "],
        ];
    }

    #[DataProvider('driveLinks')]
    public function test_it_extracts_the_file_id_from_drive_links(string $link): void
    {
        $this->assertSame(self::ID, GoogleDrive::fileId($link));
    }

    public function test_it_rejects_folders_foreign_sites_and_other_links(): void
    {
        $this->assertNull(GoogleDrive::fileId('https://drive.google.com/drive/folders/'.self::ID));
        $this->assertNull(GoogleDrive::fileId('https://exemple.com/file/d/'.self::ID.'/view'));
        $this->assertNull(GoogleDrive::fileId('https://youtu.be/abcDEF12345'));
        $this->assertNull(GoogleDrive::fileId(''));
        $this->assertTrue(GoogleDrive::isFolder('https://drive.google.com/drive/u/0/folders/'.self::ID));
    }

    public function test_a_link_written_with_http_is_read_as_https(): void
    {
        // Drive ne sert que du https : « http:// » (ou rien) est réécrit par le nettoyage des liens collés.
        $this->assertSame(self::ID, GoogleDrive::fileId('http://drive.google.com/file/d/'.self::ID.'/view'));
        $this->assertTrue(GoogleDrive::isGoogleUrl('http://docs.google.com/document/d/'.self::ID.'/edit'));
    }

    public function test_it_builds_the_preview_player_url(): void
    {
        $this->assertSame('https://drive.google.com/file/d/'.self::ID.'/preview', GoogleDrive::previewUrl(self::ID));
    }
}
