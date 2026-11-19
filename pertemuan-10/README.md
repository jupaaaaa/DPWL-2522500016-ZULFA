# pertemuan-10
1. Identitas mahasiswa.
nama : zulfa nur adibah 
2. Artefak dan kondisi awal aplikasi P9.
- fitur tersedia: katalog, login admin dan penyewa, manajemen admin, mobil, dan penyewa, keranjang, serta checkout.
- checkout menghasilkan satu record DP 30% pada t_bayar dengan sttus_byr = pending, dan t_sewa.status_sewa menjadi diajukan
- P9 menambahkan pembayaran dp oleh penyewa dan verifikasi oleh admin.
- skema t_bayar yang dipakai: sttus_byr bernilai pending, valid, atau invalid; ft_byr menyimpan nama berkas bukti; tgl_wkt_ver menyimpan waktu verifikasi.
3. Hasil pemeriksaan fitur-fitur dari P9.
1. Login admin dan halaman admin tampil , berhasil masuk ke dashboard admin 
2. Login penyewa dan halaman penyewa tampil , berhasil masuk ke katalog 
3. Menu Pembayaran menampilkan daftar dan detail DP , daftar dan detail tampil 
4. Penyewa dapat mengunggah bukti pembayaran DP, status berubah menjadi menunggu verifikasi 
5. Admin dapat memverifikasi pembayaran DP, daftar, detail, dan bukti tampil 
6.  Status berubah sesuai hasil verifikasi, diterima: valid dan disetujui; ditolak: invalid 
7. Informasi kendaraan pada detail tetap tampil nomor polisi dan nama kendaraan tampil 
Kesimpulan: fitur P9 berfungsi dengan baik, sehingga pengembangan P10 dapat dilanjutkan.
4. Tahapan pengembangan P10.
5. Daftar berkas yang ditambahkan atau diubah.
6. Perubahan struktur basis data.
7. Hasil pengujian setiap tahap.
8. Kendala, proses debugging, dan perbaikan yang dilakukan.