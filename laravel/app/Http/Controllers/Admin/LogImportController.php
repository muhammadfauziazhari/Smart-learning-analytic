<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Services\LogCsvImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LogImportController extends Controller
{
    public function __construct(private readonly LogCsvImporter $importer) {}

    /**
     * Riwayat import log LMS (FR-10).
     */
    public function index(): View
    {
        $batch = ImportBatch::query()
            ->with('admin')
            ->latest()
            ->paginate(10);

        return view('Admin.LogImportHistory', ['batches' => $batch]);
    }

    /**
     * Terima file CSV/XLSX log LMS dari form Upload Log (FR-06).
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file_csv' => [
                'required',
                'file',
            ],
        ], [], [
            'file_csv' => 'file log',
        ]);

        $file = $validated['file_csv'];
        $format = strtolower($file->getClientOriginalExtension());

        if (! in_array($format, ['csv', 'xlsx'], true)) {
            return back()->with('error', 'Format file tidak sesuai. Unggah file CSV atau XLSX.');
        }

        $batch = $this->importer->import(
            $file->getRealPath(),
            $request->user(),
            $format,
            $file->getClientOriginalName(),
        );

        if ($batch->status === ImportBatch::STATUS_GAGAL) {
            return back()
                ->withInput()
                ->with('error', 'Import gagal: '.$batch->pesan);
        }

        return back()->with('success', sprintf(
            'Import %s selesai. %s',
            $batch->nama_file,
            $batch->pesan
        ));
    }

    /**
     * Unduh file asli yang tersimpan saat import (FR-10).
     */
    public function unduh(ImportBatch $batch): BinaryFileResponse
    {
        abort_unless($batch->path !== null && Storage::exists($batch->path), 404);

        return response()->download(Storage::path($batch->path), $batch->nama_file);
    }
}
