<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien untuk layanan analytics Python (FR-12).
 *
 * Layanan ini menerima data agregat dan mengembalikan hasil perhitungan.
 * Layanan ini tidak pernah terhubung ke database: seluruh penyimpanan hasil
 * tetap dilakukan Laravel, supaya aturan bisnis dan jejak audit hanya ada
 * di satu tempat.
 */
class AnalyticsService
{
    /**
     * Cek apakah layanan analytics hidup.
     *
     * @return array{status: string, api_version?: string}
     */
    public function ping(): array
    {
        return $this->client()->get('/health')->throw()->json();
    }

    /**
     * Hitung analitik untuk satu batch import.
     *
     * @param  array<int, array{nim: string, kelas_id: int, metrics: array<string, mixed>}>  $students
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function analyze(int $batchId, string $period, array $students, array $params = []): array
    {
        if ($students === []) {
            throw new RuntimeException('Tidak ada data mahasiswa yang dikirim untuk dianalisis.');
        }

        return $this->client()
            ->post('/analyze', [
                'batch_id' => $batchId,
                'period' => $period,
                'params' => $params ?: (object) [],
                'students' => $students,
            ])
            ->throw()
            ->json();
    }

    /**
     * Error dari layanan analytics.
     *
     * FR-12a: pemanggil wajib menahan exception ini dan tetap bisa
     * menampilkan dashboard dengan data mentah.
     */
    public function isUnavailable(ConnectionException|Response $e): bool
    {
        return $e instanceof ConnectionException || $e->response?->serverError();
    }

    private function client(): PendingRequest
    {
        $config = config('services.analytics');

        return Http::baseUrl($config['url'])
            ->timeout($config['timeout'])
            ->connectTimeout($config['connect_timeout'])
            ->acceptJson()
            ->asJson();
    }
}
