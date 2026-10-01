<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ImageStorage
{
    public static function enabled(): bool
    {
        return filled(config('services.cloudinary.cloud_name'))
            && filled(config('services.cloudinary.api_key'))
            && filled(config('services.cloudinary.api_secret'));
    }

    /**
     * Sube la imagen y devuelve la referencia a guardar en la BD:
     * una URL https (Cloudinary) o, si no está configurado, una ruta local.
     */
    public static function store(UploadedFile $file, string $folder): string
    {
        if (!self::enabled()) {
            return $file->store($folder, 'public');
        }

        $cfg = config('services.cloudinary');
        $timestamp = time();
        $folderPath = "inventario/{$folder}";
        $signature = self::sign(['folder' => $folderPath, 'timestamp' => $timestamp], $cfg['api_secret']);

        try {
            $response = (new Client(['timeout' => 30]))->post(
                "https://api.cloudinary.com/v1_1/{$cfg['cloud_name']}/image/upload",
                ['multipart' => [
                    ['name' => 'file', 'contents' => fopen($file->getRealPath(), 'r'), 'filename' => $file->getClientOriginalName()],
                    ['name' => 'api_key', 'contents' => $cfg['api_key']],
                    ['name' => 'timestamp', 'contents' => (string) $timestamp],
                    ['name' => 'folder', 'contents' => $folderPath],
                    ['name' => 'signature', 'contents' => $signature],
                ]]
            );

            $data = json_decode((string) $response->getBody(), true);

            return $data['secure_url'];
        } catch (\Throwable $e) {
            report($e);

            /*throw ValidationException::withMessages([
                'photo' => 'No se pudo subir la imagen. Intenta de nuevo.',
            ]);*/
            throw $e;
        }
    }

    public static function delete(?string $reference): void
    {
        if (blank($reference)) {
            return;
        }

        // Foto antigua guardada en disco local
        if (!str_starts_with($reference, 'http')) {
            Storage::disk('public')->delete($reference);
            return;
        }

        if (!self::enabled()) {
            return;
        }

        $publicId = self::publicIdFromUrl($reference);
        if (!$publicId) {
            return;
        }

        $cfg = config('services.cloudinary');
        $timestamp = time();

        try {
            (new Client(['timeout' => 30]))->post(
                "https://api.cloudinary.com/v1_1/{$cfg['cloud_name']}/image/destroy",
                ['form_params' => [
                    'public_id' => $publicId,
                    'timestamp' => $timestamp,
                    'api_key'   => $cfg['api_key'],
                    'signature' => self::sign(['public_id' => $publicId, 'timestamp' => $timestamp], $cfg['api_secret']),
                ]]
            );
        } catch (\Throwable $e) {
            report($e); // si falla el borrado remoto, no bloqueamos la operación
        }
    }

    private static function sign(array $params, string $secret): string
    {
        ksort($params);

        $toSign = collect($params)->map(fn ($v, $k) => "{$k}={$v}")->implode('&');

        return sha1($toSign . $secret);
    }

    private static function publicIdFromUrl(string $url): ?string
    {
        // .../upload/v1700000000/inventario/products/abc123.jpg -> inventario/products/abc123
        if (!preg_match('#/upload/(?:v\d+/)?(.+)\.[A-Za-z0-9]+$#', $url, $m)) {
            return null;
        }

        return $m[1];
    }
}