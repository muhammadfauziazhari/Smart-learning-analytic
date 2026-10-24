-- Halaman Upload Log Aktivitas CSV (FR-02, FR-06) --}}

<div class="space-y-6">

    <!-- Judul -->
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-white">Upload Log Aktivitas</h2>
            <p class="text-xs text-slate-400 mt-0.5">Unggah file CSV berisi log aktivitas mahasiswa dan dosen.</p>
        </div>
        <a href="{{ route('admin.logs.index') }}"
            class="text-xs font-medium text-indigo-400 hover:text-indigo-300 transition-colors flex items-center gap-2">
            <i class="fa-solid fa-clock-rotate-left"></i>
            Riwayat Import
        </a>
    </div>

    <form id="uploadLogForm" action="{{ route('admin.logs.import') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
        @csrf
        @error('file_csv')
            <div role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ $message }}
            </div>
        @enderror

        <!-- AREA UPLOAD -->
        <div id="dropZone"
            class="border-2 border-dashed border-slate-800 rounded-2xl bg-slate-900/40 p-10 text-center transition-colors duration-200 hover:border-indigo-500/50 cursor-pointer">
            <input type="file" name="file_csv" id="fileInput" accept=".csv,text/csv" class="hidden">

            <div id="dropZoneEmpty">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-indigo-600/10 flex items-center justify-center mb-3">
                    <i class="fa-solid fa-cloud-arrow-up text-2xl text-indigo-400"></i>
                </div>
                <p class="text-sm font-semibold text-white">Klik atau seret file CSV ke sini</p>
                <p class="text-xs text-slate-400 mt-1">Format .csv. Header baris pertama otomatis dijadikan nama kolom.</p>
            </div>

            <div id="dropZoneFilled" class="hidden">
                <div class="flex items-center justify-center space-x-3">
                    <i class="fa-solid fa-file-csv text-2xl text-emerald-400"></i>
                    <div class="text-left">
                        <p id="fileName" class="text-sm font-semibold text-white"></p>
                        <p id="fileMeta" class="text-xs text-slate-400 mt-0.5"></p>
                    </div>
                </div>
                <p class="text-xs text-slate-500 mt-3">Klik untuk memilih file lain</p>
            </div>
        </div>

        <!-- RINGKASAN FILE -->
        <div id="summaryPanel" class="hidden grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-4">
                <p class="text-[11px] uppercase tracking-wider text-slate-500">Total Baris</p>
                <p id="statRows" class="text-xl font-bold text-white mt-1">0</p>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-4">
                <p class="text-[11px] uppercase tracking-wider text-slate-500">Jumlah Kolom</p>
                <p id="statCols" class="text-xl font-bold text-white mt-1">0</p>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-4">
                <p class="text-[11px] uppercase tracking-wider text-slate-500">Pemisah Kolom</p>
                <p id="statDelim" class="text-xl font-bold text-white mt-1">-</p>
            </div>
            <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-4">
                <p class="text-[11px] uppercase tracking-wider text-slate-500">Status Validasi</p>
                <p id="statStatus" class="text-xl font-bold text-white mt-1">-</p>
            </div>
        </div>

        <!-- CATATAN VALIDASI -->
        <div id="validationPanel" class="hidden rounded-xl border border-amber-600/40 bg-amber-500/5 p-4">
            <div class="flex items-start space-x-3">
                <i class="fa-solid fa-triangle-exclamation text-amber-400 mt-0.5"></i>
                <div class="flex-1">
                    <p id="validationTitle" class="text-sm font-semibold text-amber-300"></p>
                    <ul id="validationList" class="mt-2 space-y-1 text-xs text-amber-200/80 list-disc pl-4"></ul>
                </div>
            </div>
        </div>

        <!-- TABEL PREVIEW -->
        <div id="previewPanel" class="hidden rounded-2xl border border-slate-800 overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 border-b border-slate-800 bg-slate-900/60">
                <p class="text-sm font-semibold text-white">Preview Data</p>
                <p id="previewNote" class="text-xs text-slate-500"></p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead id="previewHead" class="bg-slate-900 text-slate-400 uppercase tracking-wider"></thead>
                    <tbody id="previewBody" class="divide-y divide-slate-800"></tbody>
                </table>
            </div>
        </div>

        <!-- AKSI -->
        <div id="actionPanel" class="hidden flex items-center justify-end space-x-3">
            <button type="button" id="btnReset"
                class="px-4 py-2.5 rounded-xl text-xs font-medium text-slate-300 hover:text-white bg-slate-900 border border-slate-800 transition-all">
                Batalkan
            </button>
            <button type="submit" id="btnSubmit"
                class="px-5 py-2.5 rounded-xl text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-500 transition-all shadow-lg shadow-indigo-600/20 disabled:opacity-40 disabled:cursor-not-allowed disabled:shadow-none">
                <i class="fa-solid fa-cloud-arrow-up mr-1.5"></i>
                Simpan ke Database
            </button>
        </div>

        <p id="frontendNotice" class="hidden text-xs text-slate-500 text-center">
            Perbaiki dulu masalah pada file sebelum menyimpan ke database.
        </p>
    </form>

    <!-- RIWAYAT UPLOAD (contoh) -->
    <div class="rounded-2xl border border-slate-800 overflow-hidden">
        <div class="flex items-center justify-between px-5 py-3 border-b border-slate-800 bg-slate-900/60">
            <p class="text-sm font-semibold text-white">Riwayat Upload</p>
            <span class="text-[10px] uppercase tracking-wider bg-slate-800 text-slate-400 px-2 py-1 rounded-md">Contoh data</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-900 text-slate-400 uppercase tracking-wider">
                    <tr>
                        <th class="text-left font-medium px-5 py-3">File</th>
                        <th class="text-left font-medium px-5 py-3">Baris</th>
                        <th class="text-left font-medium px-5 py-3">Diunggah</th>
                        <th class="text-left font-medium px-5 py-3">Oleh</th>
                        <th class="text-left font-medium px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-300">
                    <tr>
                        <td class="px-5 py-3">log_aktivitas_maret.csv</td>
                        <td class="px-5 py-3">1.204</td>
                        <td class="px-5 py-3">01 Sep 2026, 08:12</td>
                        <td class="px-5 py-3">admin</td>
                        <td class="px-5 py-3"><span class="text-emerald-400">Berhasil</span></td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3">log_aktivitas_februari.csv</td>
                        <td class="px-5 py-3">1.178</td>
                        <td class="px-5 py-3">01 Agu 2026, 07:55</td>
                        <td class="px-5 py-3">admin</td>
                        <td class="px-5 py-3"><span class="text-emerald-400">Berhasil</span></td>
                    </tr>
                    <tr>
                        <td class="px-5 py-3">log_aktivitas_januari.csv</td>
                        <td class="px-5 py-3">0</td>
                        <td class="px-5 py-3">01 Jan 2026, 09:02</td>
                        <td class="px-5 py-3">admin</td>
                        <td class="px-5 py-3"><span class="text-amber-400">Gagal - format kolom</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

@once
    @push('upload-log-script')
    <script>
        (function () {
            const form = document.getElementById('uploadLogForm');
            if (!form) return;

            // Batas ukuran file TIDAK ada, dengan sengaja. File log nyata bisa
            // ratusan MB dan keseluruhan tetap dibaca untuk dihitung.
            const MAX_PREVIEW_ROWS = 10;
            const MAX_PREVIEW_COLS = 10;

            const fileInput = document.getElementById('fileInput');
            const dropZone = document.getElementById('dropZone');
            const dropZoneEmpty = document.getElementById('dropZoneEmpty');
            const dropZoneFilled = document.getElementById('dropZoneFilled');
            const fileName = document.getElementById('fileName');
            const fileMeta = document.getElementById('fileMeta');
            const summaryPanel = document.getElementById('summaryPanel');
            const validationPanel = document.getElementById('validationPanel');
            const validationTitle = document.getElementById('validationTitle');
            const validationList = document.getElementById('validationList');
            const previewPanel = document.getElementById('previewPanel');
            const previewHead = document.getElementById('previewHead');
            const previewBody = document.getElementById('previewBody');
            const previewNote = document.getElementById('previewNote');
            const statRows = document.getElementById('statRows');
            const statCols = document.getElementById('statCols');
            const statDelim = document.getElementById('statDelim');
            const statStatus = document.getElementById('statStatus');
            const actionPanel = document.getElementById('actionPanel');
            const btnReset = document.getElementById('btnReset');
            const btnSubmit = document.getElementById('btnSubmit');
            const frontendNotice = document.getElementById('frontendNotice');

            const DELIM_NAMES = { ',': 'Koma (,)', ';': 'Semicolon (;)', '\t': 'Tab' };

            function escapeHtml(value) {
                return String(value)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            function formatBytes(bytes) {
                if (bytes < 1024) return bytes + ' B';
                if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
                return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
            }

            // Deteksi pemisah kolom dari baris header (Excel Indonesia sering pakai ;)
            function detectDelimiter(headerLine) {
                const candidates = [',', ';', '\t'];
                let best = ',';
                let bestCount = 0;
                candidates.forEach(function (delim) {
                    const count = headerLine.split(delim).length;
                    if (count > bestCount) {
                        bestCount = count;
                        best = delim;
                    }
                });
                return best;
            }

            // Parser CSV yang aman: kolom berisi koma, tanda kutip, dan newline
            function parseCsv(text, delimiter) {
                const rows = [];
                let row = [];
                let field = '';
                let inQuotes = false;

                text = text.replace(/^\uFEFF/, ''); // buang BOM dari Excel

                for (let i = 0; i < text.length; i++) {
                    const char = text[i];
                    const next = text[i + 1];

                    if (inQuotes) {
                        if (char === '"' && next === '"') {
                            field += '"';
                            i++;
                        } else if (char === '"') {
                            inQuotes = false;
                        } else {
                            field += char;
                        }
                        continue;
                    }

                    if (char === '"') {
                        inQuotes = true;
                    } else if (char === delimiter) {
                        row.push(field);
                        field = '';
                    } else if (char === '\n' || char === '\r') {
                        if (char === '\r' && next === '\n') i++;
                        row.push(field);
                        rows.push(row);
                        row = [];
                        field = '';
                    } else {
                        field += char;
                    }
                }

                // Baris terakhir tanpa newline
                if (field !== '' || row.length > 0) {
                    row.push(field);
                    rows.push(row);
                }

                return rows.filter(function (r) {
                    return r.some(function (cell) { return String(cell).trim() !== ''; });
                });
            }

            function validate(rows) {
                const errors = [];
                if (rows.length < 2) {
                    errors.push('File tidak memiliki data. Minimal harus ada 1 baris data di bawah header.');
                    return errors;
                }

                const header = rows[0];
                const width = header.length;

                const blankHeader = header
                    .map(function (h, i) { return { h: h, i: i }; })
                    .filter(function (item) { return String(item.h).trim() === ''; });
                if (blankHeader.length > 0) {
                    errors.push('Ada ' + blankHeader.length + ' kolom tanpa nama pada baris header.');
                }

                if (width < 2) {
                    errors.push('Hanya terdeteksi 1 kolom. Pastikan file CSV memakai koma atau semicolon sebagai pemisah.');
                }

                let mismatch = 0;
                let firstMismatch = null;
                for (let i = 1; i < rows.length; i++) {
                    if (rows[i].length !== width) {
                        mismatch++;
                        if (firstMismatch === null) firstMismatch = i + 1;
                    }
                }
                if (mismatch > 0) {
                    errors.push(mismatch + ' baris punya jumlah kolom berbeda dari header. Contoh: baris ke-' + firstMismatch + '.');
                }

                return errors;
            }

            function renderPreview(rows) {
                const header = rows[0];
                const dataRows = rows.slice(1);
                const shownCols = header.slice(0, MAX_PREVIEW_COLS);

                previewHead.innerHTML = '<tr>' + shownCols.map(function (h, i) {
                    return '<th class="text-left font-medium px-4 py-3 whitespace-nowrap">' + escapeHtml(h || 'Kolom ' + (i + 1)) + '</th>';
                }).join('') + '</tr>';

                const shownRows = dataRows.slice(0, MAX_PREVIEW_ROWS);
                previewBody.innerHTML = shownRows.map(function (row) {
                    return '<tr>' + shownCols.map(function (_, i) {
                        return '<td class="px-4 py-3 whitespace-nowrap text-slate-300">' + escapeHtml(row[i] === undefined ? '' : row[i]) + '</td>';
                    }).join('') + '</tr>';
                }).join('');

                const notes = [];
                notes.push('Menampilkan ' + shownRows.length + ' dari ' + dataRows.length + ' baris data');
                if (header.length > MAX_PREVIEW_COLS) {
                    notes.push('dan ' + shownCols.length + ' dari ' + header.length + ' kolom');
                }
                previewNote.textContent = notes.join(', ') + '.';
            }

            function showValidation(errors) {
                if (errors.length === 0) {
                    validationPanel.classList.add('hidden');
                    return;
                }
                validationTitle.textContent = errors.length + ' masalah ditemukan pada file ini:';
                validationList.innerHTML = errors.map(function (e) {
                    return '<li>' + escapeHtml(e) + '</li>';
                }).join('');
                validationPanel.classList.remove('hidden');
            }

            function resetPanels() {
                summaryPanel.classList.add('hidden');
                previewPanel.classList.add('hidden');
                validationPanel.classList.add('hidden');
                actionPanel.classList.add('hidden');
                frontendNotice.classList.add('hidden');
                statStatus.textContent = '-';
            }

            function handleFile(file) {
                dropZoneEmpty.classList.add('hidden');
                dropZoneFilled.classList.remove('hidden');
                fileName.textContent = file.name;
                fileMeta.textContent = formatBytes(file.size) + ' - klik untuk mengganti file';

                const reader = new FileReader();
                reader.onload = function (event) {
                    const text = String(event.target.result || '');
                    const delimiter = detectDelimiter(text.split('\n')[0] || '');
                    const rows = parseCsv(text, delimiter);
                    const errors = validate(rows);

                    summaryPanel.classList.remove('hidden');
                    previewPanel.classList.remove('hidden');
                    actionPanel.classList.remove('hidden');

                    statRows.textContent = Math.max(rows.length - 1, 0);
                    statCols.textContent = rows.length ? rows[0].length : 0;
                    statDelim.textContent = DELIM_NAMES[delimiter] || '-';
                    statStatus.textContent = errors.length === 0 ? 'Valid' : 'Perlu Perhatian';
                    statStatus.className = 'text-xl font-bold mt-1 ' + (errors.length === 0 ? 'text-emerald-400' : 'text-amber-400');

                    showValidation(errors);
                    renderPreview(rows);

                    btnSubmit.disabled = errors.length > 0;
                    frontendNotice.classList.toggle('hidden', errors.length > 0);
                };
                reader.onerror = function () {
                    resetPanels();
                    showValidation(['File gagal dibaca. Coba pilih ulang file.']);
                    summaryPanel.classList.remove('hidden');
                    statStatus.textContent = 'Gagal Dibaca';
                    statStatus.className = 'text-xl font-bold mt-1 text-red-400';
                };
                reader.readAsText(file);
            }

            dropZone.addEventListener('click', function () {
                fileInput.click();
            });

            fileInput.addEventListener('change', function () {
                if (fileInput.files && fileInput.files.length > 0) {
                    handleFile(fileInput.files[0]);
                }
            });

            ['dragenter', 'dragover'].forEach(function (evt) {
                dropZone.addEventListener(evt, function (event) {
                    event.preventDefault();
                    dropZone.classList.add('border-indigo-500', 'bg-indigo-500/5');
                });
            });

            ['dragleave', 'drop'].forEach(function (evt) {
                dropZone.addEventListener(evt, function (event) {
                    event.preventDefault();
                    dropZone.classList.remove('border-indigo-500', 'bg-indigo-500/5');
                });
            });

            dropZone.addEventListener('drop', function (event) {
                const files = event.dataTransfer && event.dataTransfer.files;
                if (files && files.length > 0) {
                    fileInput.files = files;
                    handleFile(files[0]);
                }
            });

            btnReset.addEventListener('click', function () {
                form.reset();
                resetPanels();
                dropZoneEmpty.classList.remove('hidden');
                dropZoneFilled.classList.add('hidden');
                statRows.textContent = '0';
                statCols.textContent = '0';
                statDelim.textContent = '-';
                frontendNotice.classList.add('hidden');
            });

            form.addEventListener('submit', function (event) {
                // File dikirim ke server (FR-02). Cegah hanya kalau
                // validasi browser menemukan masalah, supaya tidak
                // mengirim file yang jelas-jelas tidak beres.
                if (btnSubmit.disabled) {
                    event.preventDefault();
                }
            });
        })();
    </script>
    @endpush
@endonce