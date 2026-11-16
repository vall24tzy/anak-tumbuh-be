# DEPLOY-IMPORT-001: hasil pengukuran di Preview

Tanggal: 16 November 2026
Pelaksana pengukuran: Orang 2 (Pemilik). Kode patch dan test: Orang 1. Review silang kode: Orang 2.
Status: **LULUS**. Syarat K2 (D-36) terpenuhi. Merge dan redeploy menunggu.

## 1. Ringkasan

Import siswa 150 dan 200 baris selesai sinkron di Vercel dalam waktu di bawah batas 10 detik yang ditetapkan Pemilik, tanpa `BCRYPT_ROUNDS=4`. Tidak ada respons 5xx. Dua assertion yang gagal sama dengan run4 dan disebabkan batas body 4,5 MB platform Vercel, bukan Laravel.

## 2. Lingkungan

| Butir | Nilai |
|---|---|
| Kode yang dideploy | cabang `deploy/vercel-import`, commit `ac0b466` (dari `deploy/vercel-pilot` @ `28f4800`) |
| Perubahan kode | helper `RandomSecretHasher` (cost = `min(4, round konfigurasi)`) dipakai di `StudentService` (placeholder token QR) dan `QrTokenService` (token regenerate) |
| Jenis deployment | Preview, runtime Container, Function Region `sin1` |
| `BCRYPT_ROUNDS` di environment Preview | tidak ada (bawaan 12 round) |
| Database | Supabase uji, direset sebelum run (`migrate:fresh` dan `db:seed` lewat Session pooler 5432), kedua bucket dikosongkan |
| Klien | newman dari komputer Orang 2, `--working-dir` folder `sample-files` |
| Keluaran mentah | disimpan di luar repo (`newman-out`): memuat header dan nilai environment, jangan di-commit |

## 3. Hasil TEST-001 (`import001-run1`, 14:53 sampai 14:56 WIB, 3 menit 23 detik)

| Metrik | Run1 (patch, tanpa `BCRYPT_ROUNDS`) | Run4 (`BCRYPT_ROUNDS=4`, 14 Nov) |
|---|---|---|
| Request | 211 | 211 |
| Request gagal | 0 | 0 |
| Respons 5xx | 0 | 0 |
| Assertion | 381, 2 gagal | 2 gagal |
| Median | 116 ms | 120 ms |
| p90 | 319 ms | 260 ms |
| p95 | 441 ms | 373 ms |
| Maksimum | 5.464 ms | belum dicatat di dokumen ini |
| Import siswa 150 baris | 200 sinkron, **5.464 ms**, 150 berhasil, 0 ditolak | 200 sinkron, 5.950 ms |

Dua assertion yang gagal berasal dari satu request (`file > 5 MB`): Vercel menjawab 413 teks polos (`Request Entity Too Large`) sebelum request mencapai Laravel, sehingga assertion 422 dan penguraian JSON gagal. Perilaku ini sudah tercatat untuk `DEPLOY-DOC-001`.

Empat request di atas 2 detik: import 150 baris (5.464 ms), login Super Admin (5.193 ms), login Student A dengan password lama (4.814 ms, hasil 401), dan login QR (4.452 ms). Tiga login lambat itu terjadi setelah jeda 75 detik yang dipasang script untuk pembatas login. Dugaan: cold start instance. Belum dibuktikan; pembandingnya adalah request yang sama pada run4.

## 4. Import 200 baris (batas maksimum)

Diukur dengan `import200.js` pada data hasil run1 (Teacher A dan rombelnya masih ada). File uji berisi 200 siswa dengan NISN unik yang tidak bentrok dengan data TEST-001.

| Percobaan | Login Teacher A | POST /student-imports/commit | Hasil |
|---|---|---|---|
| a (pertama, setelah jeda) | 5.514 ms | HTTP 200, **8.969 ms** | 200 berhasil, 0 ditolak |
| b (kedua, instance sudah hangat) | 317 ms | HTTP 200, **6.572 ms** | 200 berhasil, 0 ditolak |

Percobaan a memiliki margin terkecil terhadap batas 10 detik (sekitar 1,0 detik). Login pertamanya juga lambat (5.514 ms), yang menguatkan dugaan cold start. Percobaan b dan import 150 baris (5.464 ms, sekitar 36 ms per baris) konsisten dengan perkiraan linear sekitar 7 detik untuk 200 baris.

## 5. Keputusan

- **Batas durasi import sinkron (K2): 10 detik**, ditetapkan Pemilik 16 November 2026. Terpenuhi di semua pengukuran.
- Kegagalan sebelum patch (batas sekitar 30 detik pada run 2 dan 3 di `DEPLOY-PILOT-001-result.md`) tetap menjadi acuan: margin terhadap titik gagal sebenarnya sekitar tiga kali lipat, tetapi margin terhadap batas 10 detik pada cold start hanya sekitar 1 detik.
- D-36 (Opsi 1) terbukti: tidak ada perubahan endpoint, bentuk respons, kode error, `MAX_ROWS`, atau skema DB.

## 6. Batasan pengukuran

- Satu run TEST-001 dan dua percobaan 200 baris. Variasi antar-instance belum dikarakterisasi.
- Pengukuran dari satu lokasi klien (waktu respons termasuk jaringan klien ke Singapura).
- Hanya import siswa. Import guru tidak memakai hash token dan sudah terukur di `TEST-002` (200 baris, 4.018 ms).
- Pengukuran tidak mencakup beban bersamaan dari banyak sekolah.
- Tes B (dua klien beda IP publik) dan item R-3 (`memory_limit` untuk foto besar) belum dijalankan; keduanya dilacak di `DEPLOYMENT-001`.

## 7. Tindak lanjut

1. Merge `deploy/vercel-import` ke `deploy/vercel-pilot`, lalu deploy ulang Preview dari `deploy/vercel-pilot` dan beri tahu FE bila URL berganti.
2. Opsional untuk memperkuat bukti: ulangi import 200 baris setelah instance dibiarkan idle beberapa menit untuk melihat pola cold start, dan jalankan `slow-requests.js` pada `test001-run4.json` sebagai pembanding tiga login lambat.
3. Task baru: `DEPLOY-STORAGE-002` (S-2 dan S-3) dan `DEPLOY-STORAGE-003` (S-1, D-38). Lihat Task Board v1.11.
