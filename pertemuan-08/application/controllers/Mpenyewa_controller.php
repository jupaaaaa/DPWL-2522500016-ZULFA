<?php
// P8: Manajemen Penyewa (CRUD oleh admin) - pola mengikuti Mobil_controller.
class Mpenyewa_controller extends Controller
{
  private Penyewa_model $model;

  private const FIELD_FOTO = ['ft_ktp', 'ft_sim_a'];

  public function __construct()
  {
    require_once APPPATH . 'models/Penyewa_model.php';
    require_once APPPATH . 'helpers/auth_helper.php';
    require_once APPPATH . 'helpers/flash_helper.php';
    $this->model = new Penyewa_model();
  }

  public function index(): void
  {
    require_admin_login();
    $this->render(null, null, null, get_flash_message());
  }

  public function tambah(): void
  {
    require_admin_login();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      $this->render('tambah', []);
      return;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      http_response_code(405);
      exit('Metode request tidak diizinkan.');
    }

    $in = $this->readInput();
    $password = (string) ($_POST['sandi'] ?? '');

    $error = $this->validateInput($in, $password, false);
    if ($error === null && $this->model->findByUserPlg($in['user_plg']) !== null) {
      $error = 'Username sudah digunakan.';
    }
    if ($error !== null) {
      $this->render('tambah', $in, $error);
      return;
    }

    // Unggah foto (opsional). Jika salah satu gagal, file yang sudah masuk dibersihkan.
    $uploaded = [];
    $foto = ['ft_ktp' => null, 'ft_sim_a' => null];
    foreach (self::FIELD_FOTO as $field) {
      $up = $this->uploadFoto($field);
      if (!$up['success']) {
        $this->hapusFoto($uploaded);
        $this->render('tambah', $in, $up['error']);
        return;
      }
      $foto[$field] = $up['filename'];
      if ($up['filename'] !== null) {
        $uploaded[] = $up['filename'];
      }
    }

    try {
      $ok = $this->model->create([
        'user_plg'   => $in['user_plg'],
        'sandi'      => password_hash($password, PASSWORD_DEFAULT),
        'nik'        => $in['nik'],
        'nm_lngkp'   => $in['nm_lngkp'],
        'no_hp'      => $in['no_hp'],
        'almt_lngkp' => $in['almt_lngkp'],
        'ft_ktp'     => $foto['ft_ktp'],
        'ft_sim_a'   => $foto['ft_sim_a'],
      ]);
    } catch (Throwable $e) {
      error_log('Tambah penyewa gagal: ' . $e->getMessage());
      $ok = false;
    }
    if (!$ok) {
      $this->hapusFoto($uploaded);
      $this->render('tambah', $in, 'Data penyewa gagal disimpan. Periksa username dan NIK (mungkin sudah terdaftar).');
      return;
    }

    // PRG: setelah POST berhasil, redirect ke halaman daftar.
    set_flash_message('success', 'Data penyewa berhasil ditambahkan.');
    header('Location: ' . site_url('admin/mpenyewa'));
    exit;
  }

  public function ubah(string $userPlg): void
  {
    require_admin_login();

    $penyewa = $this->model->getProfil($userPlg);
    if ($penyewa === null) {
      set_flash_message('danger', 'Data penyewa tidak ditemukan.');
      header('Location: ' . site_url('admin/mpenyewa'));
      exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
      $this->render('ubah', $penyewa);
      return;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      http_response_code(405);
      exit('Metode request tidak diizinkan.');
    }

    $in = $this->readInput();
    $in['user_plg'] = $userPlg; // kunci dari route, bukan dari form
    $password = (string) ($_POST['sandi'] ?? '');

    $error = $this->validateInput($in, $password, true);
    if ($error !== null) {
      $this->render('ubah', array_merge($penyewa, $in), $error);
      return;
    }

    // Foto lama dipertahankan bila tidak ada file baru.
    $foto = [
      'ft_ktp'   => $penyewa['ft_ktp'] ?? null,
      'ft_sim_a' => $penyewa['ft_sim_a'] ?? null,
    ];
    $uploaded = [];
    $fotoLama = [];
    foreach (self::FIELD_FOTO as $field) {
      $up = $this->uploadFoto($field);
      if (!$up['success']) {
        $this->hapusFoto($uploaded);
        $this->render('ubah', array_merge($penyewa, $in), $up['error']);
        return;
      }
      if ($up['filename'] !== null) {
        $uploaded[] = $up['filename'];
        if (!empty($penyewa[$field])) {
          $fotoLama[] = $penyewa[$field];
        }
        $foto[$field] = $up['filename'];
      }
    }

    $hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;

    try {
      $ok = $this->model->updateByAdmin(
        $userPlg,
        $in['nik'],
        $in['nm_lngkp'],
        $in['no_hp'],
        $in['almt_lngkp'],
        $foto['ft_ktp'],
        $foto['ft_sim_a'],
        $hash
      );
    } catch (Throwable $e) {
      error_log('Ubah penyewa gagal: ' . $e->getMessage());
      $ok = false;
    }
    if (!$ok) {
      $this->hapusFoto($uploaded);
      $this->render('ubah', array_merge($penyewa, $in), 'Data penyewa gagal diperbarui. Periksa NIK (mungkin sudah dipakai).');
      return;
    }

    // Foto lama baru dihapus setelah database berhasil diperbarui.
    $this->hapusFoto($fotoLama);

    set_flash_message('success', 'Data penyewa berhasil diperbarui.');
    header('Location: ' . site_url('admin/mpenyewa'));
    exit;
  }

  public function hapus(string $userPlg): void
  {
    require_admin_login();

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      http_response_code(405);
      exit('Metode request tidak diizinkan.');
    }

    $penyewa = $this->model->getProfil($userPlg);
    if ($penyewa === null) {
      set_flash_message('danger', 'Data penyewa tidak ditemukan.');
      header('Location: ' . site_url('admin/mpenyewa'));
      exit;
    }

    try {
      $ok = $this->model->delete($userPlg);
    } catch (Throwable $e) {
      // Biasanya ditolak foreign key: penyewa masih punya data di t_sewa.
      error_log('Hapus penyewa gagal: ' . $e->getMessage());
      $ok = false;
    }
    if (!$ok) {
      set_flash_message(
        'danger',
        'Penyewa tidak dapat dihapus. Kemungkinan masih memiliki riwayat transaksi sewa.'
      );
      header('Location: ' . site_url('admin/mpenyewa'));
      exit;
    }

    // Hapus file foto KTP/SIM hanya setelah baris database terhapus.
    $this->hapusFoto(array_filter([
      $penyewa['ft_ktp'] ?? null,
      $penyewa['ft_sim_a'] ?? null,
    ]));

    set_flash_message('success', 'Data penyewa berhasil dihapus.');
    header('Location: ' . site_url('admin/mpenyewa'));
    exit;
  }

  // ---------- helper privat ----------

  private function readInput(): array
  {
    return [
      'user_plg'   => trim((string) ($_POST['user_plg'] ?? '')),
      'nik'        => trim((string) ($_POST['nik'] ?? '')),
      'nm_lngkp'   => trim((string) ($_POST['nm_lngkp'] ?? '')),
      'no_hp'      => trim((string) ($_POST['no_hp'] ?? '')),
      'almt_lngkp' => trim((string) ($_POST['almt_lngkp'] ?? '')),
    ];
  }

  private function render(?string $mode, ?array $dipilih = null, ?string $error = null, ?array $flash = null): void
  {
    $this->view('admin/mpenyewa_view', [
      'username'        => $_SESSION['admin_username'],
      'penyewa'         => $this->model->getAll(),
      'flash'           => $flash,
      'mode'            => $mode,
      'penyewa_dipilih' => $dipilih,
      'error'           => $error,
      'extra_css'       => 'assets/css/penyewa.css',
    ]);
  }

  // Aturan sama dengan Auth::register agar data konsisten.
  private function validateInput(array $in, string $password, bool $isEdit): ?string
  {
    if (!$isEdit && !preg_match('/\A[A-Za-z0-9._-]{1,15}\z/', $in['user_plg'])) {
      return 'Username harus 1–15 karakter: huruf, angka, titik, garis bawah, atau tanda hubung.';
    }
    if (!$isEdit && (strlen($password) < 8 || strlen($password) > 72)) {
      return 'Password harus 8–72 karakter.';
    }
    if ($isEdit && $password !== '' && (strlen($password) < 8 || strlen($password) > 72)) {
      return 'Password baru harus 8–72 karakter (kosongkan jika tidak diganti).';
    }
    if (!preg_match('/\A[0-9]{16}\z/', $in['nik'])) {
      return 'NIK harus terdiri dari 16 digit angka.';
    }
    if ($in['nm_lngkp'] === '' || mb_strlen($in['nm_lngkp']) > 100) {
      return 'Nama lengkap wajib diisi, maksimal 100 karakter.';
    }
    if (
      $in['no_hp'] === '' || strlen($in['no_hp']) > 15 ||
      !preg_match('/\A[0-9+(). -]+\z/', $in['no_hp'])
    ) {
      return 'Nomor HP wajib diisi dan formatnya tidak valid.';
    }
    if ($in['almt_lngkp'] === '' || mb_strlen($in['almt_lngkp']) > 300) {
      return 'Alamat wajib diisi, maksimal 300 karakter.';
    }
    return null;
  }

  // Unggah satu foto (ft_ktp / ft_sim_a) ke uploads/penyewa/.
  // Validasi: error unggah, ukuran (1 byte - 5 MB), MIME asli (finfo), dan getimagesize().
  private function uploadFoto(string $field): array
  {
    $gagal = fn(string $msg) => ['success' => false, 'filename' => null, 'error' => $msg];

    if (!in_array($field, self::FIELD_FOTO, true)) {
      return $gagal('Field foto tidak valid.');
    }
    $label = $field === 'ft_ktp' ? 'Foto KTP' : 'Foto SIM A';

    if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
      return ['success' => true, 'filename' => null, 'error' => null];
    }
    $file = $_FILES[$field];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
      return $gagal($label . ': unggah gagal. Periksa ukuran file dan konfigurasi PHP.');
    }
    if (
      !isset($file['tmp_name'], $file['size']) ||
      !is_uploaded_file($file['tmp_name']) ||
      (int) $file['size'] < 1 ||
      (int) $file['size'] > 5 * 1024 * 1024
    ) {
      return $gagal($label . ' harus berukuran lebih dari 0 byte dan maksimal 5 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime]) || @getimagesize($file['tmp_name']) === false) {
      return $gagal($label . ' harus berformat JPEG, PNG, atau WEBP yang valid.');
    }

    $dir = dirname(APPPATH) . DIRECTORY_SEPARATOR . 'uploads'
      . DIRECTORY_SEPARATOR . 'penyewa' . DIRECTORY_SEPARATOR;
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
      return $gagal('Folder penyimpanan foto penyewa tidak dapat dibuat.');
    }
    if (!is_writable($dir)) {
      return $gagal('Folder penyimpanan foto penyewa tidak dapat ditulis.');
    }

    try {
      $filename = $field . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    } catch (Throwable $e) {
      return $gagal('Nama file foto gagal dibuat.');
    }
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
      return $gagal($label . ' gagal dipindahkan ke folder penyimpanan.');
    }
    return ['success' => true, 'filename' => $filename, 'error' => null];
  }

  // Hapus file foto. Hanya nama file berpola buatan uploadFoto() yang boleh dihapus.
  private function hapusFoto(array $filenames): void
  {
    $dir = dirname(APPPATH) . DIRECTORY_SEPARATOR . 'uploads'
      . DIRECTORY_SEPARATOR . 'penyewa' . DIRECTORY_SEPARATOR;

    foreach ($filenames as $filename) {
      if (
        is_string($filename) &&
        preg_match('/^(ft_ktp|ft_sim_a)_\d{14}_[a-f0-9]{16}\.(jpg|png|webp)$/', $filename) &&
        is_file($dir . $filename)
      ) {
        @unlink($dir . $filename);
      }
    }
  }
}
