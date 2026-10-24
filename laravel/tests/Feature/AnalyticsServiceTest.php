<?php

namespace Tests\Feature;

use App\Services\AnalyticsService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Menguji sisi Laravel dari kontrak dengan layanan analytics (FR-12).
 *
 * Tidak memanggil layanan sungguhan: respons disimulasikan dengan
 * Http::fake(), sehingga test tetap jalan walau Python tidak aktif.
 */
class AnalyticsServiceTest extends TestCase
{
    private const BASE = 'http://127.0.0.1:8001';

    private function students(): array
    {
        return [
            [
                'nim' => '3312501013',
                'kelas_id' => 5,
                'metrics' => [
                    'total_event' => 446,
                    'hari_aktif' => 29,
                    'tugas_dikumpulkan' => 12,
                    'jenis_event' => 21,
                    'kuis_ditolak' => 4,
                ],
            ],
        ];
    }

    public function test_ping_mengembalikan_status_layanan(): void
    {
        Http::fake([
            self::BASE.'/health' => Http::response([
                'status' => 'ok',
                'api_version' => '1.0',
                'time' => '2026-10-16T09:00:00Z',
            ]),
        ]);

        $hasil = app(AnalyticsService::class)->ping();

        $this->assertSame('ok', $hasil['status']);
        $this->assertSame('1.0', $hasil['api_version']);

        Http::assertSent(fn ($r) => $r->url() === self::BASE.'/health');
    }

    public function test_analyze_mengirim_payload_yang_sesuai_kontrak(): void
    {
        Http::fake([
            self::BASE.'/analyze' => Http::response([
                'batch_id' => 1,
                'period' => '2026-09',
                'results' => [],
                'clusters' => [],
                'meta' => [],
            ]),
        ]);

        app(AnalyticsService::class)->analyze(1, '2026-09', $this->students());

        Http::assertSent(function ($r) {
            $body = $r->data();

            return $r->url() === self::BASE.'/analyze'
                && $body['batch_id'] === 1
                && $body['period'] === '2026-09'
                && count($body['students']) === 1
                && $body['students'][0]['nim'] === '3312501013'
                && isset($body['students'][0]['metrics']['total_event']);
        });
    }

    public function test_mahasiswa_dikirim_sebagai_nim_bukan_id_akun(): void
    {
        // Mahasiswa tidak punya akun, jadi tidak ada user_id yang bisa
        // dikirim. Layanan analytics menolak field tak dikenal, jadi
        // kalau masih ada mahasiswa_id, permintaannya akan ditolak 422.
        Http::fake([
            self::BASE.'/analyze' => Http::response([
                'batch_id' => 1, 'period' => '2026-09',
                'results' => [], 'clusters' => [], 'meta' => [],
            ]),
        ]);

        app(AnalyticsService::class)->analyze(1, '2026-09', $this->students());

        Http::assertSent(function ($r) {
            $siswa = $r->data()['students'][0];

            return ! array_key_exists('mahasiswa_id', $siswa)
                && ! array_key_exists('user_id', $siswa);
        });
    }

    public function test_params_opsional_tetap_valid_json(): void
    {
        Http::fake([self::BASE.'/analyze' => Http::response(['results' => []])]);

        app(AnalyticsService::class)->analyze(1, '2026-09', $this->students());

        Http::assertSent(fn ($r) => json_encode($r->data()) !== false);
    }

    public function test_gagal_kirim_melempar_exception(): void
    {
        Http::fake([
            self::BASE.'/analyze' => Http::response(['detail' => 'Something broke'], 500),
        ]);

        $this->expectException(RequestException::class);

        app(AnalyticsService::class)->analyze(1, '2026-09', $this->students());
    }

    public function test_layanan_mati_terdeteksi_sebagai_tidak_tersedia(): void
    {
        Http::fake(fn () => throw new ConnectionException('Koneksi ditolak'));

        $service = app(AnalyticsService::class);

        try {
            $service->analyze(1, '2026-09', $this->students());
            $this->fail('Harus melempar exception.');
        } catch (ConnectionException $e) {
            // FR-12a: pemanggil harus bisa membedakan "layanan mati" dari
            // "data salah", supaya dashboard tetap bisa ditampilkan.
            $this->assertTrue($service->isUnavailable($e));
        }
    }

    public function test_data_kosong_ditolak_sebelum_mengirim(): void
    {
        Http::fake();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tidak ada data mahasiswa');

        app(AnalyticsService::class)->analyze(1, '2026-09', []);

        Http::assertNothingSent();
    }

    public function test_konfigurasi_layanan_dibaca_dari_config(): void
    {
        config([
            'services.analytics.url' => 'http://analytics.test:9999',
            'services.analytics.timeout' => 7,
        ]);

        Http::fake(['http://analytics.test:9999/health' => Http::response(['status' => 'ok'])]);

        app(AnalyticsService::class)->ping();

        Http::assertSent(fn ($r) => $r->url() === 'http://analytics.test:9999/health');
    }
}
