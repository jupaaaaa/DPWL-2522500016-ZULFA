# pertemuan-10
1. Identitas mahasiswa.
nama : zulfa nur adibah
nim : 2522500016
2. Artefak dan kondisi awal aplikasi P9.
- fitur tersedia: katalog, login admin dan penyewa, manajemen admin, mobil, dan penyewa, keranjang, serta checkout.
- checkout menghasilkan satu record DP 30% pada t_bayar dengan sttus_byr = pending, dan t_sewa.status_sewa menjadi diajukan
- P9 menambahkan pembayaran dp oleh penyewa dan verifikasi oleh admin.
- skema t_bayar yang dipakai: sttus_byr bernilai pending, valid, atau invalid; ft_byr menyimpan nama berkas bukti; tgl_wkt_ver menyimpan waktu verifikasi.
3. Hasil pemeriksaan fitur-fitur dari P9.
1 Login admin dan halaman admin tampil
2 Login penyewa dan halaman penyewa tampil 
3 Menu Pembayaran menampilkan daftar dan detail DP
4 Penyewa dapat mengunggah bukti pembayaran DP 
5 Admin dapat memverifikasi pembayaran DP 
6 Status berubah sesuai hasil verifikasi
fitur P9 berfungsi dengan baik dan tampil 
4. Tahapan pengembangan P10.
1.tahap awal menambahkan status lunas di database pada transaksi p10.
2.a. Penambahan method pelunasanIndex(). 
b. Penambahan method pelunasanDetail(). 
c. Penambahan method prosesPelunasan(). 
d. Penambahan method dokumenTransaksi(). 
e. Pemertahanan fungsi pembayaran P9.
3.- Penambahan route daftar pelunasan. 
- Penambahan route detail pelunasan. 
- Penambahan route proses pelunasan. 
- Penambahan route dokumen transaksi. 
- Integrasi menu Pelunasan pada sidebar admin. 
- Hasil pemeriksaan route dan navigasi. 
5. Daftar berkas yang ditambahkan atau diubah.
6. Perubahan struktur basis data.
7. Hasil pengujian setiap tahap.
8. Kendala, proses debugging, dan perbaikan yang dilakukan.