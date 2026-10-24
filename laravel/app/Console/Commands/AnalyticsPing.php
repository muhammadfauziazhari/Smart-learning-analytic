<?php

namespace App\Console\Commands;

use App\Services\AnalyticsService;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Throwable;

class AnalyticsPing extends Command
{
    protected $signature = 'analytics:ping';

    protected $description = 'Cek apakah layanan analytics Python bisa dihubungi dari Laravel';

    public function handle(AnalyticsService $analytics): int
    {
        $url = config('services.analytics.url');
        $this->line("Menghubungi <info>{$url}</info> ...");

        try {
            $hasil = $analytics->ping();
        } catch (ConnectionException) {
            $this->error('Layanan tidak bisa dihubungi.');
            $this->line('  Pastikan service-nya berjalan:');
            $this->line('    cd analytics');
            $this->line('    .venv\\Scripts\\uvicorn app.main:app --port 8001');
            $this->line('  Dan cek ANALYTICS_SERVICE_URL di laravel/.env');

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error('Layanan menjawab dengan error: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info('Layanan hidup.');
        $this->line('  status      : '.($hasil['status'] ?? '-'));
        $this->line('  api_version : '.($hasil['api_version'] ?? '-'));
        $this->line('  waktu       : '.($hasil['time'] ?? '-'));

        return self::SUCCESS;
    }
}
