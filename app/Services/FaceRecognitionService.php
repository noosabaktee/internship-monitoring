<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FaceRecognitionService
{
    public function enroll(array $images): array
    {
        return $this->post('/enroll', [
            'images' => array_values($images),
        ]);
    }

    public function verify(string $image, array $enrolledEmbedding, float $threshold): array
    {
        return $this->post('/verify', [
            'image' => $image,
            'enrolled_embedding' => array_values($enrolledEmbedding),
            'threshold' => $threshold,
        ]);
    }

    private function post(string $path, array $payload): array
    {
        $baseUrl = rtrim((string) config('services.face_recognition.url'), '/');
        $timeout = (int) config('services.face_recognition.timeout', 45);

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->post($baseUrl . $path, $payload);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Face service belum aktif. Jalankan npm run dev:all atau npm run face.', 0, $exception);
        }

        if (! $response->successful()) {
            $message = $response->json('detail')
                ?? $response->json('message')
                ?? 'Face service gagal memproses wajah.';

            throw new RuntimeException(is_array($message) ? json_encode($message) : (string) $message);
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new RuntimeException('Response face service tidak valid.');
        }

        return $data;
    }
}
