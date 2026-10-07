#!/usr/bin/env python3
"""
Generator file uji untuk collection TEST-002 (Postman).

Kenapa perlu script: NIP Teacher hasil import harus UNIK. File .xlsx berisi NIP
tetap, jadi kalau collection dijalankan dua kali di database yang sama, import
kedua akan ditolak semua (NIP sudah dipakai). Jalankan ulang script ini sebelum
run berikutnya (atau jalankan `php artisan migrate:fresh --seed`).

Yang dihasilkan (folder test-files/):
  - file .xlsx untuk Import Guru (valid, partial, struktur salah, kosong,
    korup, xlsx rusak sungguhan, NIP kembar + NIP bertepi spasi, 101 baris,
    1.000 baris (opsional, ukur waktu), 1.001 baris, >5 MB) + .csv dan .xls
    (format salah; F-33)
  - file gambar/non-gambar untuk tes upload (logo sekolah, foto Student)
  - meng-update variabel imp_* di TEST-002.postman_environment.json

Kebutuhan:  pip install openpyxl pillow
"""
import json
import os
import random
import zipfile
import string
import time
from pathlib import Path

from openpyxl import Workbook
from PIL import Image

HERE = Path(__file__).resolve().parent
OUT = HERE / "test-files"
ENV_FILE = HERE / "TEST-002.postman_environment.json"
OUT.mkdir(exist_ok=True)

S = str(int(time.time() * 1000))  # 13 digit, unik per eksekusi
HEAD = ["Nama Lengkap", "NIP", "Gender"]


def write_xlsx(name, headings, rows):
    wb = Workbook()
    ws = wb.active
    ws.append(headings)
    for r in rows:
        ws.append(r)
    wb.save(OUT / name)


# ---------- Import Guru ----------
# 1) valid: 3 baris. Gender sengaja beda kapitalisasi -> harus dinormalisasi.
valid = [
    ["Guru Import Satu", f"81{S}1", "laki-laki"],
    ["Guru Import Dua", f"81{S}2", "Perempuan"],
    ["Guru Import Tiga", f"81{S}3", "PEREMPUAN"],
]
write_xlsx("teacher_valid.xlsx", HEAD, valid)

# 2) partial: baris Excel 2..8 (baris 1 = header)
partial = [
    ["Valid A", f"82{S}1", "laki-laki"],          # row 2  OK
    ["Valid B", f"82{S}2", "perempuan"],          # row 3  OK
    ["Gender Salah", f"82{S}3", "pria"],          # row 4  -> gender invalid
    [None, f"82{S}4", "laki-laki"],               # row 5  -> name kosong
    ["NIP Sudah Ada", f"81{S}1", "laki-laki"],    # row 6  -> NIP sudah dipakai (dari teacher_valid)
    ["NIP Kembar Antar Baris", f"82{S}1", "perempuan"],  # row 7 -> kembar dgn row 2 (F-35: duplicate_in_file)
    ["Valid C", f"82{S}5", "perempuan"],          # row 8  OK
]
write_xlsx("teacher_partial.xlsx", HEAD, partial)

# 3) struktur salah: header bukan Nama Lengkap/NIP/Gender
write_xlsx("teacher_bad_structure.xlsx", ["Foo", "Bar", "Baz"],
           [["a", "b", "c"], ["d", "e", "f"]])

# 4) kosong: hanya header
write_xlsx("teacher_empty.xlsx", HEAD, [])

# 5) 101 baris -> F-38: SEMUA ukuran diproses sinkron (tidak ada lagi jalur queue)
write_xlsx("teacher_101_rows.xlsx", HEAD,
           [[f"Guru Besar {i}", f"85{S}{i:03d}", "laki-laki" if i % 2 else "perempuan"]
            for i in range(1, 102)])

# 6) 1.001 baris -> ditolak (>1.000)
write_xlsx("teacher_1001_rows.xlsx", HEAD,
           [[f"Guru Besar {i}", f"86{S}{i:04d}", "laki-laki"] for i in range(1, 1002)])

# 7) >5 MB tapi tetap xlsx valid. Excel membatasi 1 sel maks 32.767 karakter,
#    jadi ukuran dicapai lewat banyak kolom tambahan (diabaikan importer).
rnd = random.Random(42)
alphabet = string.ascii_letters + string.digits
extra_cols = 14
big_heads = HEAD + [f"Extra{c}" for c in range(extra_cols)]
big_rows = [
    ["Guru Besar", f"87{S}{i:02d}", "laki-laki"]
    + ["".join(rnd.choices(alphabet, k=32_000)) for _ in range(extra_cols)]
    for i in range(1, 21)
]
write_xlsx("teacher_oversized.xlsx", big_heads, big_rows)
assert (OUT / "teacher_oversized.xlsx").stat().st_size > 5 * 1024 * 1024, "oversized harus > 5 MB"

# 8) korup: ekstensi .xlsx tapi isinya bukan xlsx
(OUT / "teacher_corrupt.xlsx").write_bytes(os.urandom(4096))

# 8b) xlsx RUSAK SUNGGUHAN (F-34): zip/struktur valid sehingga lolos rule mimes:xlsx,
#     tetapi isi worksheet-nya sampah -> backend harus 422 import_file_unparseable (bukan 500).
with zipfile.ZipFile(OUT / "teacher_valid.xlsx") as zin, \
        zipfile.ZipFile(OUT / "teacher_unparseable.xlsx", "w", zipfile.ZIP_DEFLATED) as zout:
    for item in zin.infolist():
        data = zin.read(item.filename)
        if item.filename == "xl/worksheets/sheet1.xml":
            data = b"<<<bukan xml>>>"
        zout.writestr(item, data)

# 8c) NIP kembar antar-baris (F-35) + NIP bertepi spasi (F-36)
write_xlsx("teacher_dup_trim.xlsx", HEAD, [
    ["Guru Dup A", f"86{S}1", "laki-laki"],         # row 2  OK
    ["Guru Dup B", f"86{S}1", "perempuan"],         # row 3  -> duplicate_in_file (kembar baris 2)
    ["Guru Trim", f"  86{S}2  ", "laki-laki"],      # row 4  OK, harus tersimpan TANPA spasi
])

# 8d) tepat 1.000 baris (batas atas; hanya untuk pengukuran waktu F-38, folder 14 opsional)
write_xlsx("teacher_1000_rows.xlsx", HEAD,
           [[f"Guru Seribu {i}", f"87{S}{i:04d}", "laki-laki" if i % 2 else "perempuan"]
            for i in range(1, 1001)])

# 8e) format .xls lama (F-33): header biner OLE2 khas .xls. Bukan workbook utuh; cukup agar terdeteksi
#     bukan xlsx dan ditolak rule mimes:xlsx.
(OUT / "teacher_legacy.xls").write_bytes(
    bytes.fromhex("D0CF11E0A1B11AE1") + b"\x00" * 504 + b"Nama Lengkap\tNIP\tGender\n")

# 9) format salah: CSV
(OUT / "teacher_wrong_format.csv").write_text(
    "Nama Lengkap,NIP,Gender\nGuru Csv,88" + S + "1,laki-laki\n", encoding="utf-8")

# ---------- Upload gambar ----------
def gradient(w, h):
    img = Image.new("RGB", (w, h))
    px = img.load()
    for x in range(w):
        for y in range(h):
            px[x, y] = (x * 255 // w, y * 255 // h, 128)
    return img

gradient(200, 200).save(OUT / "logo_valid.png")
gradient(200, 200).save(OUT / "photo_valid.png")
gradient(200, 200).save(OUT / "photo_valid.jpg", quality=90)
gradient(200, 200).save(OUT / "..%2f..%2fevil.png")          # nama file "berbahaya"
gradient(200, 200).save(OUT / "logo_disguised.php", format="PNG")  # isi PNG, ekstensi .php

# >2 MB: noise acak tidak bisa dikompres
Image.frombytes("RGB", (1000, 1000), os.urandom(1000 * 1000 * 3)).save(
    OUT / "logo_oversized.png", compress_level=0)

(OUT / "not_an_image.txt").write_text("ini bukan gambar\n", encoding="utf-8")
(OUT / "logo_fake.png").write_text("ini teks biasa, bukan PNG\n", encoding="utf-8")

# ---------- update environment ----------
if ENV_FILE.exists():
    env = json.loads(ENV_FILE.read_text(encoding="utf-8"))
    values = {
        "imp_nip_valid_1": f"81{S}1",
        "imp_nip_valid_2": f"81{S}2",
        "imp_nip_big_1": f"85{S}001",
        "imp_nip_dup_1": f"86{S}1",
        "imp_nip_trim_1": f"86{S}2",
    }
    for item in env["values"]:
        if item["key"] in values:
            item["value"] = values[item["key"]]
    ENV_FILE.write_text(json.dumps(env, indent=2, ensure_ascii=False), encoding="utf-8")
    print("Environment diperbarui:", ", ".join(values))
print("Selesai. Suffix run:", S)
