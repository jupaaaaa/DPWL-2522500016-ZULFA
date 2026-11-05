<?php
require APPPATH . '/views/templates/header.php';
require APPPATH . '/views/templates/sidebar.php';

$e = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$penyewa = $penyewa ?? [];
$flash = $flash ?? null;
$flashClass = (($flash['type'] ?? '') === 'success') ? 'alert-success' : 'alert-danger';
$error = $error ?? null;
$mode = $mode ?? null;
$p = $penyewa_dipilih ?? [];

$isForm = in_array($mode, ['tambah', 'ubah'], true);
$isEdit = $mode === 'ubah';

$formAction = $isEdit
  ? site_url('admin/mpenyewa/ubah/' . rawurlencode((string) ($p['user_plg'] ?? '')))
  : site_url('admin/mpenyewa/tambah');

// Tampilkan foto penyewa. Jika nama file ada di database tetapi file fisiknya
// tidak ada di uploads/penyewa/, tampilkan keterangan (bukan ikon gambar rusak).
$fotoDir = dirname(APPPATH) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'penyewa' . DIRECTORY_SEPARATOR;
$tampilFoto = function ($nama, string $alt, string $kelas = 'foto-thumb') use ($e, $fotoDir): string {
  if (!is_string($nama) || trim($nama) === '') {
    return '<span class="text-muted">Belum ada foto</span>';
  }
  $file = basename(str_replace('\\', '/', $nama)); // aman bila yang tersimpan berupa path
  if (!is_file($fotoDir . $file)) {
    return '<span class="text-danger" title="' . $e($file) . '">File tidak ditemukan</span>';
  }
  $url = $e(base_url('uploads/penyewa/' . rawurlencode($file)));
  return '<a href="' . $url . '" target="_blank" rel="noopener">'
    . '<img class="' . $e($kelas) . '" src="' . $url . '" alt="' . $e($alt) . '" loading="lazy">'
    . '</a>';
};
?>

<!-- CSS tema biru halaman Manajemen Penyewa (langsung dimuat di view ini) -->
<link rel="stylesheet" href="<?= $e(base_url('assets/css/penyewa.css')) ?>?v=<?= (int) @filemtime(dirname(APPPATH) . '/assets/css/penyewa.css') ?>">

<div class="content-wrapper penyewa-page">
  <section class="content-header">
    <h1>Manajemen Penyewa <small>Kelola data penyewa</small></h1>
    <ol class="breadcrumb">
      <li><a href="<?= $e(site_url('admin')) ?>"><i class="fa fa-dashboard"></i> Dashboard</a></li>
      <li class="active">Manajemen Penyewa</li>
    </ol>
  </section>

  <section class="content">

    <?php if (!empty($flash)): ?>
      <div class="alert <?= $flashClass ?> alert-dismissible">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <i class="fa <?= (($flash['type'] ?? '') === 'success') ? 'fa-check' : 'fa-warning' ?>"></i>
        <?= $e($flash['message'] ?? '') ?>
      </div>
    <?php endif; ?>

    <?php if ($error !== null): ?>
      <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <i class="fa fa-warning"></i> <?= $e($error) ?>
      </div>
    <?php endif; ?>

    <?php if ($isForm): ?>
      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title"><?= $isEdit ? 'Ubah Data Penyewa' : 'Tambah Penyewa' ?></h3>
        </div>

        <form role="form" method="post" enctype="multipart/form-data" action="<?= $e($formAction) ?>">
          <div class="box-body">
            <div class="row">
              <div class="col-md-6">
                <div class="form-group">
                  <label for="user_plg">Username</label>
                  <input type="text" class="form-control" id="user_plg" name="user_plg" maxlength="15"
                    value="<?= $e($p['user_plg'] ?? '') ?>" <?= $isEdit ? 'readonly' : '' ?> required>
                </div>

                <div class="form-group">
                  <label for="sandi">Password
                    <?= $isEdit ? '<small>(kosongkan jika tidak diganti)</small>' : '' ?></label>
                  <input type="password" class="form-control" id="sandi" name="sandi"
                    minlength="8" maxlength="72" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                  <p class="help-block">8–72 karakter. Disimpan dalam bentuk hash.</p>
                </div>

                <div class="form-group">
                  <label for="nik">NIK (16 digit)</label>
                  <input type="text" class="form-control" id="nik" name="nik" maxlength="16"
                    pattern="[0-9]{16}" inputmode="numeric" value="<?= $e($p['nik'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                  <label for="nm_lngkp">Nama Lengkap</label>
                  <input type="text" class="form-control" id="nm_lngkp" name="nm_lngkp" maxlength="100"
                    value="<?= $e($p['nm_lngkp'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                  <label for="no_hp">No. HP</label>
                  <input type="text" class="form-control" id="no_hp" name="no_hp" maxlength="15"
                    value="<?= $e($p['no_hp'] ?? '') ?>" required>
                </div>

                <div class="form-group">
                  <label for="almt_lngkp">Alamat</label>
                  <textarea class="form-control" id="almt_lngkp" name="almt_lngkp" rows="3"
                    maxlength="300" required><?= $e($p['almt_lngkp'] ?? '') ?></textarea>
                </div>
              </div>

              <div class="col-md-6">
                <?php foreach (['ft_ktp' => 'Foto KTP', 'ft_sim_a' => 'Foto SIM A'] as $field => $label): ?>
                  <div class="form-group">
                    <label for="<?= $e($field) ?>"><?= $e($label) ?></label>
                    <input type="file" class="form-control" id="<?= $e($field) ?>" name="<?= $e($field) ?>"
                      accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                    <p class="help-block">
                      JPG, PNG, atau WEBP; maksimal 5 MB.
                      <?= $isEdit ? 'Kosongkan jika tidak ingin mengganti foto.' : 'Opsional.' ?>
                    </p>
                    <?php if ($isEdit && !empty($p[$field])): ?>
                      <p class="help-block foto-label">Foto saat ini:</p>
                      <?= $tampilFoto($p[$field], $label . ' saat ini', 'foto-form') ?>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="box-footer">
            <button type="submit" class="btn btn-primary">
              <i class="fa fa-save"></i> Simpan Penyewa
            </button>
            <a href="<?= $e(site_url('admin/mpenyewa')) ?>" class="btn btn-default">
              <i class="fa fa-times"></i> Batal
            </a>
          </div>
        </form>
      </div>
    <?php endif; ?>

    <div class="box">
      <div class="box-header">
        <h3 class="box-title">Data Penyewa</h3>
        <div class="box-tools">
          <a href="<?= $e(site_url('admin/mpenyewa/tambah')) ?>" class="btn btn-primary btn-sm">
            <i class="fa fa-plus"></i> Tambah Penyewa
          </a>
        </div>
      </div>

      <div class="box-body">
        <div class="table-responsive">
          <table id="penyewaTable" class="table table-bordered table-striped js-data-table">
            <thead>
              <tr>
                <th>No</th>
                <th>Username</th>
                <th>NIK</th>
                <th>Nama Lengkap</th>
                <th>No. HP</th>
                <th>Alamat</th>
                <th class="no-sort">Foto KTP</th>
                <th class="no-sort">Foto SIM A</th>
                <th class="no-sort">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($penyewa as $no => $item): ?>
                <tr>
                  <td><?= (int) $no + 1 ?></td>
                  <td><?= $e($item['user_plg']) ?></td>
                  <td><?= $e($item['nik']) ?></td>
                  <td><?= $e($item['nm_lngkp']) ?></td>
                  <td><?= $e($item['no_hp']) ?></td>
                  <td><?= $e($item['almt_lngkp']) ?></td>
                  <td><?= $tampilFoto($item['ft_ktp'] ?? null, 'Foto KTP') ?></td>
                  <td><?= $tampilFoto($item['ft_sim_a'] ?? null, 'Foto SIM A') ?></td>
                  <td class="aksi">
                    <a class="btn btn-warning btn-xs"
                      href="<?= $e(site_url('admin/mpenyewa/ubah/' . rawurlencode($item['user_plg']))) ?>">
                      <i class="fa fa-edit"></i> Ubah
                    </a>
                    <form method="post" style="display:inline"
                      action="<?= $e(site_url('admin/mpenyewa/hapus/' . rawurlencode($item['user_plg']))) ?>"
                      onsubmit="return confirm('Hapus penyewa <?= $e($item['user_plg']) ?>? Data dan foto terkait akan dihapus.');">
                      <button type="submit" class="btn btn-danger btn-xs">
                        <i class="fa fa-trash"></i> Hapus
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </section>
</div>

<?php require APPPATH . '/views/templates/footer.php'; ?>