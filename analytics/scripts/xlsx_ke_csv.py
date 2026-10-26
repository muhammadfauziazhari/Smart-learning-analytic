"""Ubah file Excel (.xlsx) menjadi CSV biasa.

Kidonaktifkan kanal data Excel ke Laravel: daftar mahasiswa per kelas
(Pagi/Malam dan A/B/C/D) biasanya disusun di Excel, sementara PHP di
komputer ini tidak punya ekstensi `zip` sehingga tidak bisa membuka .xlsx
sendiri (format xlsx sebenarnya arsip zip).

Skrip ini sengaja terpisah dari layanan analytics: tugasnya hanya
membaca dan menulis berkas, tidak ada hubungannya dengan perhitungan
engagement/risiko.

Pemakaian:

    .venv\\Scripts\\python.exe scripts\\xlsx_ke_csv.py <masukan.xlsx> <keluaran.csv>

Keluarannya CSV dengan pemisah koma, enkoding UTF-8, dan kolom tambahan:

    _baris     nomor baris asal di Excel (untuk pesan galat)
    _lembar    nama sheet tempat data diambil

Kolom tambahan itu dibuang lagi oleh perintah Laravel
`kelas:impor-dari-roster`, jadi file CSV-nya tetap aman dibaca manusia.
"""

from __future__ import annotations

import csv
import sys
from pathlib import Path

import openpyxl


def konversi(masuk: Path, keluar: Path) -> int:
    """Tulis sheet pertama dari `masuk` ke `keluar` sebagai CSV."""
    if not masuk.is_file():
        raise SystemExit(f"berkas tidak ditemukan: {masuk}")

    buku = openpyxl.load_workbook(masuk, read_only=True, data_only=True)
    lembar = buku.worksheets[0]

    keluar.parent.mkdir(parents=True, exist_ok=True)

    jumlah = 0
    with keluar.open("w", newline="", encoding="utf-8-sig") as berkas:
        penulis = csv.writer(berkas)
        penulis.writerow(["_baris", "_lembar"])

        for nomor, baris in enumerate(lembar.iter_rows(values_only=True), start=1):
            if all(nilai is None or str(nilai).strip() == "" for nilai in baris):
                continue

            penulis.writerow([nomor, lembar.title, *["" if v is None else str(v).strip() for v in baris]])
            jumlah += 1

    buku.close()
    return jumlah


def main() -> int:
    if len(sys.argv) != 3:
        print(__doc__)
        return 2

    masuk = Path(sys.argv[1])
    keluar = Path(sys.argv[2])

    jumlah = konversi(masuk, keluar)
    print(f"{jumlah} baris ditulis ke {keluar}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())