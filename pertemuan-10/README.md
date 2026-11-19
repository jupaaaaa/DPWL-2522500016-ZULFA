# README Pertemuan 10 (P10) - Pelunasan dan Dokumen Transaksi

## 1. Identitas Mahasiswa

- Nama: Zulfa Nur Adibah
- NIM: 2522500016

## 2. Artefak dan Kondisi Awal Aplikasi P9

- Fitur tersedia: katalog, login admin dan penyewa, manajemen admin, mobil, dan penyewa, keranjang, serta checkout.
- Checkout menghasilkan satu record DP 30% pada `t_bayar` dengan `sttus_byr = pending`, dan `t_sewa.status_sewa` menjadi `diajukan`.
- P9 menambahkan pembayaran DP oleh penyewa dan verifikasi oleh admin.
- Skema `t_bayar` yang dipakai: `sttus_byr` bernilai `pending`, `valid`, atau `invalid`; `ft_byr` menyimpan nama berkas bukti; `tgl_wkt_ver` menyimpan waktu verifikasi.

## 3. Hasil Pemeriksaan Fitur P9

1. Login admin dan halaman admin tampil.
2. Login penyewa dan halaman penyewa tampil.
3. Menu Pembayaran menampilkan daftar dan detail DP.
4. Penyewa dapat mengunggah bukti pembayaran DP.
5. Admin dapat memverifikasi pembayaran DP.
6. Status berubah sesuai hasil verifikasi.

Kesimpulan: fitur P9 berfungsi dengan baik dan tampil.

## 4. Tahapan Pengembangan P10

### 4.1 Menambahkan status lunas di database

Saya menambahkan nilai `lunas` pada kolom `status_sewa` di tabel `t_sewa` lewat phpMyAdmin.

### 4.2 Controller pelunasan

Berkas: `pertemuan-10\application\controllers\Pembayaran_controller.php`

- **a.** Penambahan method `pelunasanIndex()`: menampilkan daftar transaksi yang masih punya sisa kewajiban.
- **b.** Penambahan method `pelunasanDetail()`: menampilkan detail transaksi, riwayat pembayaran, dan sisa kewajiban.
- **c.** Penambahan method `prosesPelunasan()`: mencatat pelunasan tunai atau transfer yang diterima admin.
- **d.** Penambahan method `dokumenTransaksi()`: menampilkan dokumen transaksi yang bisa dicetak.
- **e.** Pemertahanan fungsi pembayaran P9: method P9 tidak dihapus dan tidak diubah, method P10 hanya ditambahkan di bawahnya.

### 4.3 Route dan menu

- Route daftar pelunasan: `admin/pembayaran/pelunasan`
- Route detail pelunasan: `admin/pembayaran/pelunasan/(:any)`
- Route proses pelunasan: `admin/pembayaran/pelunasan/proses/(:any)`
- Route dokumen transaksi: `admin/pembayaran/dokumen/(:any)`
- Integrasi menu Pelunasan di sidebar admin: menu Pelunasan Transaksi ditambahkan di `pertemuan-10\application\views\templates\sidebar.php`
- Hasil pemeriksaan route dan navigasi: semua menu berhasil dan tampil.

### 4.4 Model pelunasan

Saya menambahkan method `getDaftarPelunasan()`, `getDetailPelunasan()`, `buatKodeBayarPelunasan()`, dan `simpanPelunasan()` di `pertemuan-10\application\models\Pembayaran_model.php`.

### 4.5 View daftar dan detail pelunasan

Saya membuat `pelunasan.php` dan `pelunasan_detail.php`.

### 4.6 Dokumen transaksi

Saya membuat `dokumen_transaksi.php` untuk cetak atau simpan PDF.

## 5. Daftar Berkas yang Ditambahkan atau Diubah

| Berkas | Keterangan |
|---|---|
| `pertemuan-10\application\models\Pembayaran_model.php` | Diubah, ditambah method pelunasan P10 |
| `pertemuan-10\application\controllers\Pembayaran_controller.php` | Diubah, ditambah method pelunasan dan dokumen, serta penghubung method P9 |
| `pertemuan-10\application\config\routes.php` | Diubah, ditambah 4 route P10 |
| `pertemuan-10\application\views\templates\sidebar.php` | Diubah, ditambah menu Pelunasan Transaksi |
| `pertemuan-10\application\views\admin\pelunasan.php` | Baru, daftar transaksi yang bisa dilunasi |
| `pertemuan-10\application\views\admin\pelunasan_detail.php` | Baru, detail transaksi dan form pelunasan |
| `pertemuan-10\application\views\admin\dokumen_transaksi.php` | Baru, dokumen transaksi untuk dicetak |

## 6. Perubahan Struktur Basis Data

Hanya satu perubahan: menambah nilai `lunas` pada ENUM kolom `status_sewa` di tabel `t_sewa`.

## 7. Hasil Pengujian Setiap Tahap

1. **Status lunas di database**
   Berhasil. Nilai `lunas` sudah ada di ENUM kolom `status_sewa` dan nilai lama tetap tersedia.

2. **Halaman daftar pelunasan** (`admin/pembayaran/pelunasan`)
   Berhasil. Halaman menampilkan 3 transaksi berstatus disetujui dengan sisa kewajiban Rp 350.000.

3. **Tombol Detail / Proses**
   Awalnya tidak bisa diklik karena atribut `href` salah ketik menjadi `ref`. Setelah diperbaiki, tombol berhasil membuka detail transaksi yang sesuai.

4. **Detail pelunasan**
   Berhasil. Informasi penyewa, rincian kendaraan, ringkasan pembayaran, dan riwayat pembayaran tampil sesuai data.

5. **Pelunasan metode tunai**
   Berhasil. Muncul pesan hijau berisi kode pembayaran `PAY/...`, status pembayaran valid, dan status transaksi berubah menjadi lunas.

6. **Pelunasan metode transfer dengan bukti**
   Berhasil. Bukti transfer terunggah, pelunasan tercatat, dan status transaksi menjadi lunas.

7. **Dokumen transaksi dan cetak PDF**
   Berhasil. Dokumen menampilkan informasi transaksi, identitas penyewa, rincian kendaraan, riwayat pembayaran, dan ringkasan pembayaran. Dokumen bisa disimpan sebagai PDF lewat pratinjau cetak browser.

8. **Fitur P9 (pembayaran DP dan verifikasi)**
   Masih berjalan. Halaman Verifikasi Pembayaran di admin dan Pembayaran Saya di penyewa tampil normal.

9. **Status kendaraan di `t_mobil` setelah pelunasan**
   Tidak berubah. Status kendaraan sama seperti sebelum pelunasan.

## 8. Kendala, Proses Debugging, dan Perbaikan

1. **Parse error: Unclosed '{' on line 11 di `Pembayaran_controller.php`**
   - Penyebab: method P10 ditempel setelah method P9, tetapi kurung kurawal penutup class `}` tidak ada di akhir file.
   - Perbaikan: menambah `}` penutup class di paling bawah file.

2. **Tombol Detail / Proses tidak bisa diklik**
   - Penyebab: di `pelunasan.php` atribut tertulis `ref=` bukan `href=` dan tidak ada spasi sebelumnya.
   - Perbaikan: mengganti menjadi `href=` dan menambah spasi.

3. **Kolom Sisa Kewajiban tampil `Rp 350.000/td>`**
   - Penyebab: tag penutup tertulis `/td>`, kurang tanda `<`.
   - Perbaikan: mengganti menjadi `</td>`.

4. **Kode di `pelunasan_detail.php` terpotong**
   - Penyebab: kode disalin dari PDF modul sehingga baris terputus.
   - Perbaikan: menyatukan baris yang terpotong.