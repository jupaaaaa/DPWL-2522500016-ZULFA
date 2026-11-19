<?php
// P9 BARU - application/models/Pembayaran_model.php
class Pembayaran_model
{
  private mysqli $db;

  public function __construct()
  {
    global $db;
    $this->db = $db;
  }

  private function ambilBaris(
    string $sql,
    string $tipe,
    array $param,
    string $konteks
  ): array {
    $stmt = $this->db->prepare($sql);
    if (!$stmt) {
      throw new RuntimeException($konteks . ' gagal disiapkan.');
    }
    if ($tipe !== '') {
      $stmt->bind_param($tipe, ...$param);
    }
    if (!$stmt->execute()) {
      $stmt->close();
      throw new RuntimeException($konteks . ' gagal dijalankan.');
    }
    $hasil = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $hasil;
  }

  private function ambilSatu(
    string $sql,
    string $tipe,
    array $param,
    string $konteks
  ): ?array {
    $baris = $this->ambilBaris($sql, $tipe, $param, $konteks);
    return $baris[0] ?? null;
  }

  /** Penyewa dapat mengunggah bukti jika transaksi masih diajukan dan
   *  DP belum berbukti (pending tanpa file) atau sebelumnya ditolak (invalid). */
  public function bisaUnggah(array $dp): bool
  {
    if (($dp['status_sewa'] ?? '') !== 'diajukan') {
      return false;
    }
    $status = (string) ($dp['sttus_byr'] ?? '');
    $adaBukti = trim((string) ($dp['ft_byr'] ?? '')) !== '';
    return $status === 'invalid' || ($status === 'pending' && !$adaBukti);
  }

  /** Admin dapat memverifikasi jika DP pending, sudah berbukti, dan transaksi diajukan. */
  public function bisaDiverifikasi(array $dp): bool
  {
    return ($dp['status_sewa'] ?? '') === 'diajukan'
      && ($dp['sttus_byr'] ?? '') === 'pending'
      && trim((string) ($dp['ft_byr'] ?? '')) !== '';
  }

  private function ambilDetailKendaraan(string $kodeSewa): array
  {
    return $this->ambilBaris(
      "SELECT p.no_plat, p.hrg_sewa, p.n_hari, m.nm_mobil
FROM t_pilih p
LEFT JOIN t_mobil m ON m.no_plat = p.no_plat
WHERE p.kode_sewa = ?
ORDER BY p.no_plat ASC",
      's',
      [$kodeSewa],
      'Pengambilan detail kendaraan'
    );
  }

  private function lengkapi(array $dp): array
  {
    $dp['detail'] = $this->ambilDetailKendaraan((string) $dp['kode_sewa']);
    $total = 0;
    foreach ($dp['detail'] as $item) {
      $total += (int) $item['hrg_sewa'] * (int) $item['n_hari'];
    }
    $dp['total'] = $total;
    $dp['dp_seharusnya'] = (int) round($total * 30 / 100);
    return $dp;
  }

  // ---------------------------------------------------------------
  // Sisi penyewa
  // ---------------------------------------------------------------
  public function getDaftarDpPenyewa(string $userPlg): array
  {
    return $this->ambilBaris(
      "SELECT b.kode_bayar, b.tgl_wkt_byr, b.ft_byr, b.nominal,
b.sttus_byr, b.tgl_wkt_ver,
s.kode_sewa, s.tgl_mulai, s.tgl_selesai, s.wkt_ambil, s.status_sewa
FROM t_bayar b
INNER JOIN t_sewa s ON s.kode_sewa = b.kode_sewa
WHERE s.user_plg = ? AND b.jns_byr = 'dp'
ORDER BY b.tgl_wkt_byr DESC, b.kode_bayar DESC",
      's',
      [$userPlg],
      'Pengambilan daftar pembayaran DP'
    );
  }

  public function getDpPenyewa(string $kodeSewa, string $userPlg): ?array
  {
    $dp = $this->ambilSatu(
      "SELECT b.kode_bayar, b.tgl_wkt_byr, b.ft_byr, b.mtode_byr,
b.nominal, b.sttus_byr, b.tgl_wkt_ver,
s.kode_sewa, s.tgl_mulai, s.tgl_selesai, s.wkt_ambil,
s.status_sewa, s.user_plg
FROM t_bayar b
INNER JOIN t_sewa s ON s.kode_sewa = b.kode_sewa
WHERE b.kode_sewa = ? AND s.user_plg = ? AND b.jns_byr = 'dp'
ORDER BY b.kode_bayar DESC
LIMIT 1",
      'ss',
      [$kodeSewa, $userPlg],
      'Pengambilan pembayaran DP penyewa'
    );
    return $dp === null ? null : $this->lengkapi($dp);
  }

  /**
   * Menyimpan nama file bukti pada record DP yang SUDAH ADA (UPDATE).
   * Mengembalikan nama file lama (atau null) agar controller dapat menghapusnya
   * setelah commit berhasil.
   */
  public function simpanBukti(
    string $kodeSewa,
    string $userPlg,
    string $namaFile
  ): ?string {
    $this->db->begin_transaction();
    try {
      $dp = $this->ambilSatu(
        "SELECT b.kode_bayar, b.ft_byr, b.sttus_byr, s.status_sewa
FROM t_bayar b
INNER JOIN t_sewa s ON s.kode_sewa = b.kode_sewa
WHERE b.kode_sewa = ? AND s.user_plg = ? AND b.jns_byr = 'dp'
ORDER BY b.kode_bayar DESC
LIMIT 1
FOR UPDATE",
        'ss',
        [$kodeSewa, $userPlg],
        'Penguncian pembayaran DP'
      );
      if ($dp === null) {
        throw new InvalidArgumentException(
          'Data pembayaran DP tidak ditemukan atau bukan milik akun ini.'
        );
      }
      if (!$this->bisaUnggah($dp)) {
        throw new InvalidArgumentException(
          'Bukti pembayaran tidak dapat diunggah pada status saat ini.'
        );
      }
      $statusLama = (string) $dp['sttus_byr'];
      $kodeBayar = (string) $dp['kode_bayar'];
      $stmt = $this->db->prepare(
        "UPDATE t_bayar
SET ft_byr = ?, mtode_byr = 'transfer', tgl_wkt_byr = NOW(),
sttus_byr = 'pending', tgl_wkt_ver = NULL
WHERE kode_bayar = ? AND jns_byr = 'dp' AND sttus_byr = ?"
      );
      if (!$stmt) {
        throw new RuntimeException('Pembaruan bukti pembayaran gagal disiapkan.');
      }
      $stmt->bind_param('sss', $namaFile, $kodeBayar, $statusLama);
      if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Pembaruan bukti pembayaran gagal dijalankan.');
      }
      $berubah = $stmt->affected_rows === 1;
      $stmt->close();
      if (!$berubah) {
        throw new RuntimeException('Record DP tidak berubah; proses dibatalkan.');
      }
      $this->db->commit();
      $lama = trim((string) ($dp['ft_byr'] ?? ''));
      return $lama === '' ? null : $lama;
    } catch (Throwable $e) {
      $this->db->rollback();
      throw $e;
    }
  }

  // ---------------------------------------------------------------
  // Sisi admin
  // ---------------------------------------------------------------
  public function getDaftarAdmin(?string $status): array
  {
    $sql = "SELECT b.kode_bayar, b.tgl_wkt_byr, b.ft_byr, b.mtode_byr,
b.nominal, b.sttus_byr, b.tgl_wkt_ver,
s.kode_sewa, s.status_sewa, s.user_plg, p.nm_lngkp
FROM t_bayar b
INNER JOIN t_sewa s ON s.kode_sewa = b.kode_sewa
LEFT JOIN t_penyewa p ON p.user_plg = s.user_plg
WHERE b.jns_byr = 'dp'";
    $tipe = '';
    $param = [];
    if ($status !== null) {
      $sql .= ' AND b.sttus_byr = ?';
      $tipe = 's';
      $param = [$status];
    }
    $sql .= " ORDER BY FIELD(b.sttus_byr, 'pending', 'invalid', 'valid'),
b.tgl_wkt_byr DESC, b.kode_bayar DESC";
    return $this->ambilBaris($sql, $tipe, $param, 'Pengambilan daftar verifikasi');
  }

  public function getRingkasanStatus(): array
  {
    $ringkas = ['pending' => 0, 'valid' => 0, 'invalid' => 0];
    $baris = $this->ambilBaris(
      "SELECT sttus_byr, COUNT(*) AS jumlah
FROM t_bayar
WHERE jns_byr = 'dp'
GROUP BY sttus_byr",
      '',
      [],
      'Penghitungan status pembayaran'
    );
    foreach ($baris as $item) {
      if (isset($ringkas[$item['sttus_byr']])) {
        $ringkas[$item['sttus_byr']] = (int) $item['jumlah'];
      }
    }
    return $ringkas;
  }

  public function getDetailAdmin(string $kodeBayar): ?array
  {
    $dp = $this->ambilSatu(
      "SELECT b.kode_bayar, b.tgl_wkt_byr, b.ft_byr, b.mtode_byr,
b.nominal, b.sttus_byr, b.tgl_wkt_ver,
s.kode_sewa, s.tgl_mulai, s.tgl_selesai, s.wkt_ambil,
s.status_sewa, s.user_plg, p.nm_lngkp, p.no_hp
FROM t_bayar b
INNER JOIN t_sewa s ON s.kode_sewa = b.kode_sewa
LEFT JOIN t_penyewa p ON p.user_plg = s.user_plg
WHERE b.kode_bayar = ? AND b.jns_byr = 'dp'
LIMIT 1",
      's',
      [$kodeBayar],
      'Pengambilan detail verifikasi'
    );
    return $dp === null ? null : $this->lengkapi($dp);
  }

  /**
   * Verifikasi DP. valid => DP diterima dan transaksi disetujui.
   * invalid => DP ditolak; transaksi tetap diajukan agar penyewa dapat
   * mengunggah ulang bukti. Hanya UPDATE; tidak ada INSERT.
   */
  public function verifikasi(string $kodeBayar, string $keputusan): void
  {
    if (!in_array($keputusan, ['valid', 'invalid'], true)) {
      throw new InvalidArgumentException('Keputusan verifikasi tidak valid.');
    }
    $this->db->begin_transaction();
    try {
      $dp = $this->ambilSatu(
        "SELECT b.kode_bayar, b.kode_sewa, b.ft_byr, b.sttus_byr,
s.status_sewa, s.tgl_mulai, s.tgl_selesai
FROM t_bayar b
INNER JOIN t_sewa s ON s.kode_sewa = b.kode_sewa
WHERE b.kode_bayar = ? AND b.jns_byr = 'dp'
LIMIT 1
FOR UPDATE",
        's',
        [$kodeBayar],
        'Penguncian verifikasi'
      );
      if ($dp === null) {
        throw new InvalidArgumentException('Data pembayaran tidak ditemukan.');
      }
      if ($dp['sttus_byr'] !== 'pending') {
        throw new InvalidArgumentException(
          'Pembayaran ini sudah diverifikasi sebelumnya.'
        );
      }
      if (trim((string) ($dp['ft_byr'] ?? '')) === '') {
        throw new InvalidArgumentException(
          'Penyewa belum mengunggah bukti pembayaran.'
        );
      }
      if ($dp['status_sewa'] !== 'diajukan') {
        throw new InvalidArgumentException(
          'Status transaksi bukan diajukan; pembayaran tidak dapat diverifikasi.'
        );
      }
      $kodeSewa = (string) $dp['kode_sewa'];
      if ($keputusan === 'valid') {
        // Cegah double booking: kendaraan yang sama pada periode beririsan
        // dengan transaksi lain yang sudah disetujui atau aktif.
        $bentrok = $this->ambilSatu(
          "SELECT s2.kode_sewa
FROM t_pilih p1
INNER JOIN t_pilih p2
  ON p2.no_plat = p1.no_plat AND p2.kode_sewa <> p1.kode_sewa
INNER JOIN t_sewa s2 ON s2.kode_sewa = p2.kode_sewa
WHERE p1.kode_sewa = ?
AND s2.status_sewa IN ('disetujui', 'aktif')
AND s2.tgl_mulai < ?
AND s2.tgl_selesai > ?
LIMIT 1",
          'sss',
          [$kodeSewa, $dp['tgl_selesai'], $dp['tgl_mulai']],
          'Pemeriksaan bentrok kendaraan'
        );
        if ($bentrok !== null) {
          throw new InvalidArgumentException(
            'Kendaraan bentrok dengan transaksi ' . $bentrok['kode_sewa']
            . ' yang sudah disetujui/aktif pada periode yang sama.'
          );
        }
      }
      $stmt = $this->db->prepare(
        "UPDATE t_bayar
SET sttus_byr = ?, tgl_wkt_ver = NOW()
WHERE kode_bayar = ? AND jns_byr = 'dp' AND sttus_byr = 'pending'"
      );
      if (!$stmt) {
        throw new RuntimeException('Pembaruan status pembayaran gagal disiapkan.');
      }
      $stmt->bind_param('ss', $keputusan, $kodeBayar);
      if (!$stmt->execute()) {
        $stmt->close();
        throw new RuntimeException('Pembaruan status pembayaran gagal dijalankan.');
      }
      $berubah = $stmt->affected_rows === 1;
      $stmt->close();
      if (!$berubah) {
        throw new RuntimeException('Status pembayaran tidak berubah.');
      }
      if ($keputusan === 'valid') {
        $stmt = $this->db->prepare(
          "UPDATE t_sewa SET status_sewa = 'disetujui'
WHERE kode_sewa = ? AND status_sewa = 'diajukan'"
        );
        if (!$stmt) {
          throw new RuntimeException('Persetujuan transaksi gagal disiapkan.');
        }
        $stmt->bind_param('s', $kodeSewa);
        if (!$stmt->execute()) {
          $stmt->close();
          throw new RuntimeException('Persetujuan transaksi gagal dijalankan.');
        }
        $berubah = $stmt->affected_rows === 1;
        $stmt->close();
        if (!$berubah) {
          throw new RuntimeException('Status transaksi tidak berubah.');
        }
      }
      $this->db->commit();
    } catch (Throwable $e) {
      $this->db->rollback();
      throw $e;
    }
  }

  /** Data minimum untuk menyajikan berkas bukti dengan pemeriksaan akses. */
  public function getBuktiAkses(string $kodeBayar): ?array
  {
    return $this->ambilSatu(
      "SELECT b.ft_byr, s.user_plg
FROM t_bayar b
INNER JOIN t_sewa s ON s.kode_sewa = b.kode_sewa
WHERE b.kode_bayar = ?
LIMIT 1",
      's',
      [$kodeBayar],
      'Pengambilan akses bukti pembayaran'
    );
  }
/** 
 * P10: Mengambil daftar transaksi yang dapat diproses pelunasannya. 
 * 
 * Total transaksi menggunakan snapshot tarif pada t_pilih. 
 * Pembayaran yang mengurangi kewajiban hanya yang berstatus valid. 
 */ 
public function getDaftarPelunasan(): array 
{ 
  $stmt = $this->db->prepare( 
    "SELECT 
      s.kode_sewa, 
      s.tgl_mulai, 
      s.tgl_selesai, 
      s.status_sewa, 
      p.user_plg, 
      p.nm_lngkp, 
      p.no_hp, 
      COALESCE(( 
        SELECT SUM(tp.hrg_sewa * tp.n_hari) 
        FROM t_pilih tp 
        WHERE tp.kode_sewa = s.kode_sewa 
      ), 0) AS total_transaksi, 
      COALESCE(( 
        SELECT SUM(b.nominal) 
        FROM t_bayar b 
        WHERE b.kode_sewa = s.kode_sewa 
          AND b.sttus_byr = 'valid' 
      ), 0) AS total_terbayar 
    FROM t_sewa s 
    INNER JOIN t_penyewa p 
      ON p.user_plg = s.user_plg 
    WHERE s.status_sewa IN ('disetujui', 'lunas') 
      AND EXISTS ( 
        SELECT 1 
        FROM t_pilih tp 
        WHERE tp.kode_sewa = s.kode_sewa 
      ) 
    ORDER BY s.tgl_mulai DESC, s.kode_sewa DESC" 
  ); 
 
  if (!$stmt) { 
    throw new RuntimeException( 
      'Query daftar pelunasan gagal disiapkan.' 
    ); 
  } 
 
  if (!$stmt->execute()) { 
    $stmt->close(); 
    throw new RuntimeException( 
      'Daftar pelunasan gagal diambil.' 
    ); 
  } 
 
  $daftar = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
  $stmt->close(); 
 
  foreach ($daftar as &$item) { 
    $item['total_transaksi'] = (int) $item['total_transaksi']; 
    $item['total_terbayar'] = (int) $item['total_terbayar']; 
    $item['sisa_kewajiban'] = max(
       0, 
      $item['total_transaksi'] - $item['total_terbayar'] 
    ); 
  } 
  unset($item); 
 
return array_values(array_filter( 
    $daftar, 
    static fn(array $item): bool => 
    $item['sisa_kewajiban'] > 0 || 
      $item['status_sewa'] === 'lunas' 
  )); 
} 
/** 
 * P10: Mengambil detail transaksi, penyewa, dan riwayat pembayaran 
 * untuk halaman pelunasan. 
 */ 
public function getDetailPelunasan(string $kodeSewa): ?array 
{ 
  $kodeSewa = trim($kodeSewa); 
 
  if ($kodeSewa === '') { 
    throw new InvalidArgumentException( 
      'Kode transaksi sewa wajib diisi.' 
    ); 
  } 
 
  $stmt = $this->db->prepare( 
    "SELECT 
      s.kode_sewa, 
      s.tgl_mulai, 
      s.tgl_selesai, 
      s.wkt_ambil, 
      s.status_sewa, 
      p.user_plg, 
      p.nm_lngkp, 
      p.no_hp, 
      p.nik, 
      p.almt_lngkp, 
      COALESCE(( 
        SELECT SUM(tp.hrg_sewa * tp.n_hari) 
        FROM t_pilih tp 
        WHERE tp.kode_sewa = s.kode_sewa 
      ), 0) AS total_transaksi, 
      COALESCE(( 
        SELECT SUM(b.nominal) 
        FROM t_bayar b 
        WHERE b.kode_sewa = s.kode_sewa 
          AND b.sttus_byr = 'valid' 
      ), 0) AS total_terbayar 
    FROM t_sewa s 
 INNER JOIN t_penyewa p 
      ON p.user_plg = s.user_plg 
    WHERE s.kode_sewa = ? 
    LIMIT 1" 
  ); 
 
  if (!$stmt) { 
    throw new RuntimeException( 
      'Query detail pelunasan gagal disiapkan.' 
    ); 
  } 
 
  $stmt->bind_param('s', $kodeSewa); 
 
  if (!$stmt->execute()) { 
    $stmt->close(); 
    throw new RuntimeException( 
      'Detail transaksi pelunasan gagal diambil.' 
    ); 
  } 
 
  $detail = $stmt->get_result()->fetch_assoc(); 
  $stmt->close(); 
 
  if ($detail === null) { 
    return null; 
  } 
 
  $detail['total_transaksi'] = (int) $detail['total_transaksi']; 
  $detail['total_terbayar'] = (int) $detail['total_terbayar']; 
  $detail['sisa_kewajiban'] = max( 
    0, 
    $detail['total_transaksi'] - $detail['total_terbayar'] 
  ); 
 
  $stmt = $this->db->prepare( 
    "SELECT 
      kode_bayar, 
      tgl_wkt_byr, 
      mtode_byr, 
      jns_byr, 
      nominal, 
      sttus_byr, 
      ft_byr, 
      tgl_wkt_ver 
    FROM t_bayar 
    WHERE kode_sewa = ? 
    ORDER BY tgl_wkt_byr ASC, kode_bayar ASC" 
  ); 
 
  if (!$stmt) { 
    throw new RuntimeException( 
      'Query riwayat pembayaran gagal disiapkan.' 
    ); 
  } 
 
  $stmt->bind_param('s', $kodeSewa); 
 
  if (!$stmt->execute()) { 
    $stmt->close(); 
    throw new RuntimeException( 
      'Riwayat pembayaran gagal diambil.' 
    ); 
  } 
 
  $detail['riwayat_pembayaran'] = 
    $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
 
  $stmt->close(); 
 
  $stmt = $this->db->prepare( 
    "SELECT 
      m.no_plat, 
      m.nm_mobil,
   tp.hrg_sewa, 
      tp.n_hari, 
      (tp.hrg_sewa * tp.n_hari) AS subtotal 
    FROM t_pilih tp 
    INNER JOIN t_mobil m 
      ON m.no_plat = tp.no_plat 
    WHERE tp.kode_sewa = ? 
    ORDER BY m.nm_mobil ASC, m.no_plat ASC" 
  ); 
 
  if (!$stmt) { 
    throw new RuntimeException( 
      'Query detail kendaraan pelunasan gagal disiapkan.' 
    ); 
  } 
 
  $stmt->bind_param('s', $kodeSewa); 
 
  if (!$stmt->execute()) { 
    $stmt->close(); 
    throw new RuntimeException( 
      'Detail kendaraan pelunasan gagal diambil.' 
    ); 
  } 
 
  $detail['daftar_kendaraan'] = 
    $stmt->get_result()->fetch_all(MYSQLI_ASSOC); 
 
  $stmt->close(); 
 
  return $detail; 
}   
 /** 
 * P10: Membuat kode pembayaran dengan format PAY/YYYYMMDD/NN. 
 * Pemanggil harus memegang named lock pembayaran harian. 
 */ 
private function buatKodeBayarPelunasan(string $tanggalKode): string 
{ 
  $pola = 'PAY/' . $tanggalKode . '/%'; 
 
  $stmt = $this->db->prepare( 
    "SELECT kode_bayar 
      FROM t_bayar 
      WHERE kode_bayar LIKE ? 
      ORDER BY kode_bayar DESC 
      LIMIT 1" 
  );
    if (!$stmt) { 
    throw new RuntimeException( 
      'Pembuatan kode pembayaran gagal disiapkan.' 
    ); 
  } 
 
  $stmt->bind_param('s', $pola); 
 
  if (!$stmt->execute()) { 
    $stmt->close(); 
    throw new RuntimeException( 
      'Pembuatan kode pembayaran gagal dijalankan.' 
    ); 
  } 
 
  $terakhir = $stmt->get_result()->fetch_assoc(); 
  $stmt->close(); 
 
  $nomorBerikutnya = 1; 
 
  if ($terakhir !== null) { 
    $bagianKode = explode('/', (string) $terakhir['kode_bayar']); 
    $nomorBerikutnya = ((int) end($bagianKode)) + 1; 
  } 
 
  if ($nomorBerikutnya > 99) { 
    throw new RuntimeException( 
      'Nomor pembayaran harian telah mencapai batas 99.' 
    ); 
  } 
 
  return sprintf('PAY/%s/%02d', $tanggalKode, $nomorBerikutnya); 
} 
/** 
 * P10: Mencatat pelunasan langsung oleh admin. 
 * 
 * Pembayaran langsung dicatat sebagai valid. 
 * Status transaksi diperbarui menjadi lunas dalam transaksi database 
 * yang sama. Status kendaraan tidak diubah pada P10. 
 */ 
public function simpanPelunasan( 
  string $kodeSewa, 
  string $metodeBayar, 
  ?string $namaFileBukti = null 
): string { 
  $kodeSewa = trim($kodeSewa); 
  $metodeBayar = trim($metodeBayar); 
 
  if ($kodeSewa === '') { 
    throw new InvalidArgumentException( 
      'Kode transaksi sewa wajib diisi.' 
    ); 
  } 
 
  if (!in_array($metodeBayar, ['tunai', 'transfer'], true)) { 
    throw new InvalidArgumentException( 
      'Metode pembayaran tidak valid.' 
    ); 
  } 
 
  if ($metodeBayar === 'transfer') { 
    $namaFileBukti = trim((string) $namaFileBukti); 
 
    if ($namaFileBukti === '') { 
      throw new InvalidArgumentException( 
        'Bukti transfer wajib tersedia.' 
      ); 
    } 
  } else { 
    $namaFileBukti = null; 
  } 
 
  // Harus sama dengan kunci yang digunakan Transaksi_model::checkout() 
  // ketika membuat kode pembayaran DP. 
  $namaKunci = 'dpwl_payment_code_sequence'; 
 
  $kunciDiperoleh = false; 
  $transaksiDimulai = false; 
 
  try { 
    $stmt = $this->db->prepare( 
      'SELECT GET_LOCK(?, 10) AS lock_status' 
    ); 
 
    if (!$stmt) { 
      throw new RuntimeException( 
        'Kunci pembuatan kode pembayaran gagal disiapkan.' 
      ); 
    } 
 
    $stmt->bind_param('s', $namaKunci); 
 
    if (!$stmt->execute()) { 
      $stmt->close(); 
      throw new RuntimeException( 
        'Kunci pembuatan kode pembayaran gagal dijalankan.' 
      ); 
    } 
 
    $hasilKunci = $stmt->get_result()->fetch_assoc(); 
    $stmt->close(); 
 
    if ((int) ($hasilKunci['lock_status'] ?? 0) !== 1) { 
      throw new RuntimeException( 
        'Proses pembayaran sedang digunakan. Silakan coba kembali.' 
      ); 
    } 
 
    $kunciDiperoleh = true; 
 
    $this->db->begin_transaction(); 
    $transaksiDimulai = true; 
 
    // Kunci transaksi agar dua permintaan pelunasan tidak berjalan 
    // bersamaan untuk transaksi yang sama. 
    $stmt = $this->db->prepare( 
      "SELECT 
        s.kode_sewa, 
        s.status_sewa, 
        COALESCE(( 
          SELECT SUM(tp.hrg_sewa * tp.n_hari) 
          FROM t_pilih tp 
          WHERE tp.kode_sewa = s.kode_sewa 
        ), 0) AS total_transaksi, 
        COALESCE(( 
          SELECT SUM(b.nominal) 
          FROM t_bayar b 
             WHERE b.kode_sewa = s.kode_sewa 
            AND b.sttus_byr = 'valid' 
        ), 0) AS total_terbayar 
      FROM t_sewa s 
      WHERE s.kode_sewa = ? 
      LIMIT 1 
      FOR UPDATE" 
    ); 
 
    if (!$stmt) { 
      throw new RuntimeException( 
        'Pemeriksaan transaksi pelunasan gagal disiapkan.' 
      ); 
    } 
 
    $stmt->bind_param('s', $kodeSewa); 
 
    if (!$stmt->execute()) { 
      $stmt->close(); 
      throw new RuntimeException( 
        'Pemeriksaan transaksi pelunasan gagal dijalankan.' 
      ); 
    } 
 
    $transaksi = $stmt->get_result()->fetch_assoc(); 
    $stmt->close(); 
 
    if ($transaksi === null) { 
      throw new RuntimeException( 
        'Transaksi sewa tidak ditemukan.' 
      ); 
    } 
 
    if (!in_array( 
      $transaksi['status_sewa'], 
      ['disetujui', 'lunas'], 
      true 
    )) { 
      throw new RuntimeException( 
        'Status transaksi belum memenuhi syarat pelunasan.' 
      ); 
    } 
 
    $totalTransaksi = (int) $transaksi['total_transaksi']; 
    $totalTerbayar = (int) $transaksi['total_terbayar']; 
    $sisaKewajiban = $totalTransaksi - $totalTerbayar; 
 
    if ($totalTransaksi <= 0 || $sisaKewajiban <= 0) { 
      throw new RuntimeException( 
        'Transaksi tidak memiliki sisa kewajiban untuk dilunasi.' 
      ); 
    } 
 
    if ($sisaKewajiban > 8388607) { 
      throw new RuntimeException( 
        'Sisa kewajiban melebihi kapasitas kolom nominal pada database.' 
      ); 
    } 
 
    // Pastikan transaksi belum memiliki record pelunasan. 
    $stmt = $this->db->prepare( 
      "SELECT kode_bayar 
        FROM t_bayar 
        WHERE kode_sewa = ? 
          AND jns_byr = 'pelunasan' 
        LIMIT 1 
        FOR UPDATE" 
    ); 
 
    if (!$stmt) { 
      throw new RuntimeException( 
        'Pemeriksaan pelunasan sebelumnya gagal disiapkan.' 
      ); 
    } 
 $stmt->bind_param('s', $kodeSewa); 
 
    if (!$stmt->execute()) { 
      $stmt->close(); 
      throw new RuntimeException( 
        'Pemeriksaan pelunasan sebelumnya gagal dijalankan.' 
      ); 
    } 
 
    $pelunasanAda = $stmt->get_result()->fetch_assoc() !== null; 
    $stmt->close(); 
 
    if ($pelunasanAda) { 
      throw new RuntimeException( 
        'Transaksi ini sudah memiliki record pelunasan.' 
      ); 
    } 
 
    $hasilWaktu = $this->db->query( 
      "SELECT 
        DATE_FORMAT(NOW(), '%Y%m%d') AS tanggal_kode, 
        NOW() AS waktu_pembayaran" 
    ); 
 
    if (!$hasilWaktu) { 
      throw new RuntimeException( 
        'Waktu pembayaran gagal dibaca.' 
      ); 
    } 
 
    $waktu = $hasilWaktu->fetch_assoc(); 
    $hasilWaktu->free(); 
 
    $tanggalKode = (string) ($waktu['tanggal_kode'] ?? ''); 
    $waktuPembayaran = (string) ($waktu['waktu_pembayaran'] ?? ''); 
 
    if ( 
      !preg_match('/^\d{8}$/', $tanggalKode) || 
      $waktuPembayaran === '' 
    ) { 
      throw new RuntimeException( 
        'Waktu pembayaran tidak valid.' 
      ); 
    } 
 
    $kodeBayar = $this->buatKodeBayarPelunasan($tanggalKode); 
 
    $stmt = $this->db->prepare( 
      "INSERT INTO t_bayar ( 
        kode_bayar, 
        tgl_wkt_byr, 
        ft_byr, 
        mtode_byr, 
        jns_byr, 
        nominal, 
        sttus_byr, 
        tgl_wkt_ver, 
        kode_sewa 
      ) VALUES (?, ?, ?, ?, 'pelunasan', ?, 'valid', NOW(), ?)" 
    ); 
 
    if (!$stmt) { 
      throw new RuntimeException( 
        'Pencatatan pelunasan gagal disiapkan.' 
      ); 
    } 
 
    $stmt->bind_param( 
      'ssssis', 
      $kodeBayar, 
      $waktuPembayaran, 
      $namaFileBukti, 
      $metodeBayar, 
 $sisaKewajiban, 
      $kodeSewa 
    ); 
 
    if (!$stmt->execute()) { 
      $stmt->close(); 
      throw new RuntimeException( 
        'Pelunasan gagal disimpan.' 
      ); 
    } 
 
    $stmt->close(); 
 
    // Pelunasan berhasil dicatat; perbarui status transaksi 
    // dalam transaksi database yang sama. 
    $stmt = $this->db->prepare( 
      "UPDATE t_sewa 
        SET status_sewa = 'lunas' 
        WHERE kode_sewa = ? 
          AND status_sewa IN ('disetujui', 'lunas')" 
    ); 
 
    if (!$stmt) { 
      throw new RuntimeException( 
        'Pembaruan status transaksi menjadi lunas gagal disiapkan.' 
      ); 
    } 
 
    $stmt->bind_param('s', $kodeSewa); 
 
    if (!$stmt->execute()) { 
      $stmt->close(); 
      throw new RuntimeException( 
        'Status transaksi gagal diperbarui menjadi lunas.' 
      ); 
    } 
 
    if ($stmt->affected_rows !== 1) { 
      $stmt->close(); 
      throw new RuntimeException( 
        'Status transaksi tidak berubah menjadi lunas. ' 
        . 'Periksa kembali status transaksi.' 
      ); 
    } 
 
    $stmt->close(); 
 
    $this->db->commit(); 
    $transaksiDimulai = false; 
 
    return $kodeBayar; 
  } catch (Throwable $e) { 
    if ($transaksiDimulai) { 
      $this->db->rollback(); 
    } 
 
    throw $e; 
  } finally { 
    if ($kunciDiperoleh) { 
      $stmt = $this->db->prepare( 
        'SELECT RELEASE_LOCK(?)' 
      ); 
 
      if ($stmt) { 
        $stmt->bind_param('s', $namaKunci); 
        $stmt->execute(); 
        $stmt->close(); 
      } 
    } 
  } 
} 
} 