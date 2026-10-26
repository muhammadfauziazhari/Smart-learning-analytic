{{-- Halaman Upload Log Aktivitas CSV (FR-02, FR-06) --}}

<div class="space-y-6 rounded-3xl bg-slate-100 p-5 lg:p-7">

    <!-- Judul -->
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h2 class="text-2xl font-extrabold text-[#0B1B3D]">Upload Log Aktivitas</h2>
            <p class="text-sm text-blue-600 mt-1">Unggah file CSV berisi log aktivitas mahasiswa dan dosen.</p>
        </div>
        <a href="{{ route('admin.logs.index') }}"
            class="inline-flex items-center gap-2 rounded-xl bg-white border border-slate-200 hover:border-blue-300 hover:bg-blue-50 px-4 py-2 text-xs font-semibold text-slate-600 hover:text-blue-700 shadow-sm transition-all">
            <i class="fa-solid fa-clock-rotate-left text-amber-500"></i>
            Riwayat Import
        </a>
    </div>

    <form id="uploadLogForm" action="{{ route('admin.logs.import') }}" method="POST" enctype="multipart/form-data" class="space-y-5">
        @csrf
        @error('file_csv')
            <div role="alert" class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                {{ $message }}
            </div>
        @enderror

        <!-- AREA UPLOAD -->
        <div id="dropZone"
            class="border-2 border-dashed border-slate-300 rounded-2xl bg-white p-10 text-center shadow-sm transition-colors duration-200 hover:border-blue-400 hover:bg-blue-50/40 cursor-pointer">
            <input type="file" name="file_csv" id="fileInput" accept=".csv,text/csv" class="hidden">

            <div id="dropZoneEmpty">
                <div class="w-16 h-16 mx-auto rounded-2xl bg-gradient-to-tr from-amber-400 to-amber-300 flex items-center justify-center mb-3 shadow-lg shadow-amber-500/20">
                    <i class="fa-solid fa-cloud-arrow-up text-2xl text-[#0B1B3D]"></i>
                </div>
                <p class="text-sm font-bold text-[#0B1B3D]">Klik atau seret file CSV ke sini</p>
                <p class="text-xs text-slate-500 mt-1">Format .csv. Header baris pertama otomatis dijadikan nama kolom.</p>
            </div>

            <div id="dropZoneFilled" class="hidden">
                <div class="flex items-center justify-center space-x-3">
                    <div class="w-12 h-12 rounded-xl bg-emerald-50 flex items-center justify-center">
                        <i class="fa-solid fa-file-csv text-2xl text-emerald-600"></i>
                    </div>
                    <div class="text-left">
                        <p id="fileName" class="text-sm font-bold text-[#0B1B3D]"></p>
                        <p id="fileMeta" class="text-xs text-slate-500 mt-0.5"></p>
                    </div>
                </div>
                <p class="text-xs text-slate-400 mt-3">Klik untuk memilih file lain</p>
            </div>
        </div>

        <!-- RINGKASAN FILE -->
        <div id="summaryPanel" class="hidden grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="bg-[#0B1B3D] text-white rounded-2xl p-4 shadow-md">
                <p class="text-[11px] uppercase tracking-wider text-slate-300">Total Baris</p>
                <p id="statRows" class="text-2xl font-extrabold mt-1">0</p>
            </div>
            <div class="bg-blue-600 text-white rounded-2xl p-4 shadow-md">
                <p class="text-[11px] uppercase tracking-wider text-blue-100">Jumlah Kolom</p>
                <p id="statCols" class="text-2xl font-extrabold mt-1">0</p>
            </div>
            <div class="bg-gradient-to-br from-amber-300 to-amber-400 text-[#0B1B3D] rounded-2xl p-4 shadow-md">
                <p class="text-[11px] uppercase tracking-wider font-semibold">Pemisah Kolom</p>
                <p id="statDelim" class="text-2xl font-extrabold mt-1">-</p>
            </div>
            <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-md">
                <p class="text-[11px] uppercase tracking-wider text-slate-500">Status Validasi</p>
                <p id="statStatus" class="text-2xl font-extrabold text-[#0B1B3D] mt-1">-</p>
            </div>
        </div>

        <!-- CATATAN VALIDASI -->
        <div id="validationPanel" class="hidden rounded-xl border border-amber-300 bg-amber-50 p-4">
            <div class="flex items-start space-x-3">
                <i class="fa-solid fa-triangle-exclamation text-amber-500 mt-0.5"></i>
                <div class="flex-1">
                    <p id="validationTitle" class="text-sm font-bold text-amber-800"></p>
                    <ul id="validationList" class="mt-2 space-y-1 text-xs text-amber-700 list-disc pl-4"></ul>
                </div>
            </div>
        </div>

        <!-- TABEL PREVIEW -->
        <div id="previewPanel" class="hidden rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-4 border-b border-slate-100">
                <p class="text-sm font-bold text-[#0B1B3D]">Preview Data</p>
                <p id="previewNote" class="text-xs text-slate-500"></p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead id="previewHead" class="bg-slate-50 text-slate-500 uppercase tracking-wider"></thead>
                    <tbody id="previewBody" class="divide-y divide-slate-100"></tbody>
                </table>
            </div>
        </div>

        <!-- AKSI -->
        <div id="actionPanel" class="hidden flex items-center justify-end space-x-3">
            <button type="button" id="btnReset"
                class="px-5 py-2.5 rounded-xl text-xs font-semibold text-slate-600 hover:text-slate-900 bg-white border border-slate-200 hover:border-slate-300 transition-all">
                Batalkan
            </button>
            <button type="submit" id="btnSubmit"
                class="px-6 py-2.5 rounded-xl text-xs font-semibold text-white bg-[#0F3277] hover:bg-[#0B255B] transition-all shadow-md hover:shadow-lg shadow-blue-950/20 disabled:opacity-40 disabled:cursor-not-allowed disabled:shadow-none">
                <i class="fa-solid fa-cloud-arrow-up mr-1.5"></i>
                Simpan ke Database
            </button>
        </div>

        <p id="frontendNotice" class="hidden text-xs text-slate-500 text-center">
            Perbaiki dulu masalah pada file sebelum menyimpan ke database.
        </p>
    </form>

    <!-- RIWAYAT UPLOAD (contoh) -->
    <div class="rounded-2xl bg-white border border-slate-200 shadow-sm overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
            <p class="text-sm font-bold text-[#0B1B3D]">Riwayat Upload</p>
            <span class="text-[10px] uppercase tracking-wider bg-slate-100 text-slate-500 px-2 py-1 rounded-md">Contoh data</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500 uppercase tracking-wider">
                    <tr>
                        <th class="text-left font-medium px-5 py-3">File</th>
                        <th class="text-left font-medium px-5 py-3">Baris</th>
                        <th class="text-left font-medium px-5 py-3">Diunggah</th>
                        <th class="text-left font-medium px-5 py-3">Oleh</th>
                        <th class="text-left font-medium px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3 font-medium"><i class="fa-solid fa-file-csv text-emerald-500 mr-2"></i>log_aktivitas_maret.csv</td>
                        <td class="px-5 py-3">1.204</td>
                        <td class="px-5 py-3 text-slate-500">01 Sep 2026, 08:12</td>
                        <td class="px-5 py-3 text-slate-500">admin</td>
                        <td class="px-5 py-3"><span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-600">Berhasil</span></td>
                    </tr>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3 font-medium"><i class="fa-solid fa-file-csv text-emerald-500 mr-2"></i>log_aktivitas_februari.csv</td>
                        <td class="px-5 py-3">1.178</td>
                        <td class="px-5 py-3 text-slate-500">01 Agu 2026, 07:55</td>
                        <td class="px-5 py-3 text-slate-500">admin</td>
                        <td class="px-5 py-3"><span class="rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-600">Berhasil</span></td>
                    </tr>
                    <tr class="hover:bg-slate-50">
                        <td class="px-5 py-3 font-medium"><i class="fa-solid fa-file-csv text-amber-500 mr-2"></i>log_aktivitas_januari.csv</td>
                        <td class="px-5 py-3">0</td>
                        <td class="px-5 py-3 text-slate-500">01 Jan 2026, 09:02</td>
                        <td class="px-5 py-3 text-slate-500">admin</td>
                        <td class="px-5 py-3"><span class="rounded-full border border-amber-200 bg-amber-50 px-2.5 py-0.5 text-[11px] font-semibold text-amber-600">Gagal - format kolom</span></td>
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
                        return '<td class="px-4 py-3 whitespace-nowrap text-slate-700">' + escapeHtml(row[i] === undefined ? '' : row[i]) + '</td>';
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
                    statStatus.className = 'text-2xl font-extrabold mt-1 ' + (errors.length === 0 ? 'text-emerald-600' : 'text-amber-600');

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
                    statStatus.className = 'text-2xl font-extrabold mt-1 text-rose-600';
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
                    dropZone.classList.add('border-blue-600', 'bg-blue-50');
                });
            });

            ['dragleave', 'drop'].forEach(function (evt) {
                dropZone.addEventListener(evt, function (event) {
                    event.preventDefault();
                    dropZone.classList.remove('border-blue-600', 'bg-blue-50');
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
