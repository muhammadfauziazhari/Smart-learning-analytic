<?php
$base = dirname(__DIR__).'/laravel';
require $base.'/vendor/autoload.php';
$app = require_once $base.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Ringkas isi hasil_analitik per mata kuliah
echo "=== hasil_analitik per mata kuliah ===\n";
foreach (DB::table('hasil_analitik')
    ->selectRaw('kode_mata_kuliah, periode_awal, periode_akhir, count(*) jml, min(updated_at) terbaru')
    ->groupBy('kode_mata_kuliah','periode_awal','periode_akhir')
    ->get() as $b) {
    echo "{$b->kode_mata_kuliah} | {$b->periode_awal}..{$b->periode_akhir} | {$b->jml} baris | update {$b->terbaru}\n";
}

echo "\n=== Sebaran per kelas (IF209 + IF207) ===\n";
foreach (DB::table('kelas as k')
    ->join('kelas_mahasiswa as km', 'km.kelas_id', '=', 'k.id')
    ->join('mata_kuliahs as m', 'm.id', '=', 'k.mata_kuliah_id')
    ->leftJoin('hasil_analitik as h', function ($j) {
        $j->on('h.nim', '=', 'km.nim');
    })
    ->selectRaw("m.kode, k.id kelas_id, k.nama kelas, count(*) jml, sum(h.id is not null) sudah, sum(h.id is null) belum, min(h.risk_level) contoh_level")
    ->where('m.kode', 'IF209')
    ->groupBy('m.kode','k.id','k.nama')
    ->orderBy('k.nama')
    ->get() as $r) {
    echo sprintf("%s | kelas %d %-12s | %3d mhs | %3d sudah | %3d belum\n", $r->kode, $r->kelas_id, $r->kelas, $r->jml, $r->sudah, $r->belum);
}
