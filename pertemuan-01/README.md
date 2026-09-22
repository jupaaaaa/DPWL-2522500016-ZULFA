# pertemuan-01
1. kesinambungan PWD, DPW, dan DPWL
Ketiga mata kuliah ini adalah tahapan belajar membuat web dari dasar sampai tingkat lanjut. di PWD, belajar dasarnya dulu  masuk ke dpw, mulai belajar membuat web yang bisa menyimpan dan mengolah data pengguna ke dalam database.dan di dpwl, belajar cara membuat web standar profesional menggunakan arsitektur yang rapi.

2. perbedaan php terstruktur dan mvc
php terstruktur Kode tampilan, logika, dan perintah database dicampur jadi satu di dalam satu berkas, jadi kalau websitenya makin besar kodenya makin susah dibaca dan rawan membingungkan. Sedangkan MVC itu Hasilnya, kode jadi jauh lebih rapi, gampang diperbaiki kalau ada error, dan lebih mudah dikerjakan bareng tim.

3. fungsi model, view, dan controller
di dalam konsep MVC, tugas dibagi menjadi tiga bagian. model bertugas mengurus semua data dan hubungan ke database, seperti mengambil, menyimpan, atau menghapus data. view bertugas mengurus tampilan visual yang dilihat oleh pengguna di layar, seperti bentuk tombol, warna, dan tata letak teks. sedangkan Controller bertugas sebagai otak atau penghubung yang menerima perintah pengguna, meminta data ke Model, lalu menyuruh View untuk menampilkan hasilnya.

4. Alur Request dan Response MVC
Prosesnya dimulai saat pengguna melakukan aksi di web, misalnya mengklik sebuah tombol. Permintaan itu akan ditangkap pertama kali oleh Controller. Controller lalu mengecek apakah butuh data dari database atau tidak. Jika butuh, Controller akan menyuruh Model untuk mengambilkan data tersebut. Setelah Model memberikan datanya, Controller akan mengolah data itu lalu mengoper hasilnya ke View. Terakhir, View akan menyusun tampilan barunya dan memunculkannya di layar pengguna.

5. Pemetaan Fitur Aplikasi ke Model, Controller, dan View
Sebagai contoh pada fitur login. Bagian View adalah halaman login itu sendiri yang berisi kotak isian username, password, dan tombol masuk. bagian Controller bertugas menerima tulisan username dan password yang di ketik, lalu memeriksa alur logikanya: jika benar  berhasil masuk, jika salah diberi pesan peringatan. bagian Model bertugas mengecek langsung ke database apakah username dan password yang di ketik tadi memang benar-benar terdaftar di sistem.

6. Kesimpulan P1
penggunaan arsitektur MVC sangat membantu kita dalam membuat aplikasi web yang rapi dan terstruktur. Dengan memisahkan urusan data, tampilan, dan logika ke dalam tiga bagian yang berbeda, kode program jadi jauh lebih mudah dirawat, mudah diperbaiki jika ada kesalahan, dan memudahkan kita untuk bekerja sama dalam sebuah tim pengembang.



i