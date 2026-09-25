<?php

namespace App\Admin;

use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fichier CSV compatible Excel : accents corrects (UTF-8 avec BOM) et point-virgule comme séparateur.
 */
class CsvExport
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, scalar|null>>|LazyCollection<int, array<int, scalar|null>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fwrite($output, "\u{FEFF}");
            fputcsv($output, $headers, ';', '"', '');

            foreach ($rows as $row) {
                fputcsv($output, array_map(fn ($value): string => self::cell($value), $row), ';', '"', '');
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * Protège contre les formules : une cellule qui commence par = + - @ est préfixée d'une apostrophe.
     */
    private static function cell(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! is_numeric($value) ? "'".$value : $value;
    }
}
