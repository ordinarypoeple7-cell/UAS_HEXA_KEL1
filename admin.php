<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Guard: hanya admin
if (!isset($_SESSION['login']) || $_SESSION['login'] !== true) {
    header("Location: login.php"); exit;
}
$user = $_SESSION['user'];
if (($user['role'] ?? '') !== 'admin') {
    header("Location: index.php"); exit;
}

require_once 'koneksi.php';

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// --- LOGOUT ---
if (isset($_POST['action']) && $_POST['action'] == 'confirm_logout') {
    session_destroy();
    if (isset($_COOKIE['user_login'])) setcookie('user_login', '', time()-3600, '/');
    header("Location: login.php"); exit;
}
$show_logout_modal = isset($_GET['action']) && $_GET['action'] == 'logout_trigger';

// --- MANAJEMEN USER (CRUD) ---
$user_msg = '';

// Tambah User
if (isset($_POST['action']) && $_POST['action'] == 'add_user') {
    $nama     = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $email    = mysqli_real_escape_string($conn, trim($_POST['email']));
    $password = md5($_POST['password']);
    $role_new = in_array($_POST['role'], ['admin','individu']) ? $_POST['role'] : 'individu';

    if ($nama === '' || $email === '' || empty($_POST['password'])) {
        $user_msg = 'error:Semua field wajib diisi.';
    } else {
        $check = mysqli_query($conn, "SELECT id_user FROM account WHERE email='$email'");
        if ($check && mysqli_num_rows($check) > 0) {
            $user_msg = 'error:Email sudah terdaftar, gunakan email lain.';
        } else {
            $insert = mysqli_query($conn, "INSERT INTO account (nama, email, password, role) VALUES ('$nama', '$email', '$password', '$role_new')");
            if ($insert) {
                $user_msg = 'ok:User baru berhasil ditambahkan.';
            } else {
                $user_msg = 'error:Gagal menambahkan user: ' . mysqli_error($conn);
            }
        }
    }
}

// Edit User
if (isset($_POST['action']) && $_POST['action'] == 'edit_user') {
    $id      = intval($_POST['id_user'] ?? 0);
    $nama     = mysqli_real_escape_string($conn, trim($_POST['nama']));
    $role_new = in_array($_POST['role'], ['admin','individu']) ? $_POST['role'] : 'individu';
    $pw_sql   = '';
    if (!empty($_POST['password'])) $pw_sql = ", password='" . md5($_POST['password']) . "'";
    
    $update = mysqli_query($conn, "UPDATE account SET nama='$nama', role='$role_new'$pw_sql WHERE id_user=$id");
    if ($update) {
        $user_msg = 'ok:Data user berhasil diperbarui.';
    } else {
        $user_msg = 'error:Gagal memperbarui data: ' . mysqli_error($conn);
    }
}

// Hapus User
if (isset($_GET['action']) && $_GET['action'] == 'delete_user') {
    $uid = intval($_GET['id_user'] ?? 0);
    // Mengamankan pencocokan ID session admin (baik menggunakan 'id' atau 'id_user')
    $current_admin_id = intval($user['id_user'] ?? $user['id_user'] ?? 0);

    if ($uid === $current_admin_id) {
        header("Location: admin.php?page=users&msg=error_self"); exit;
    } else {
        $delete_query = mysqli_query($conn, "DELETE FROM account WHERE id_user=$uid");
        if ($delete_query) {
            header("Location: admin.php?page=users&msg=deleted"); exit;
        } else {
            die("Gagal menghapus: " . mysqli_error($conn));
        }
    }
}

// Edit trigger user
$edit_user_data = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit_user_trigger') {
    $uid_e = intval($_GET['id_user']);
    $r = mysqli_query($conn, "SELECT id_user,nama,email,role FROM account WHERE id_user=$uid_e");
    $edit_user_data = mysqli_fetch_assoc($r);
}

// --- DATA NAIK KELAS ---
$total_users    = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM account"))[0];
$total_admin    = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM account WHERE role='admin'"))[0];
$total_individu = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM account WHERE role='individu'"))[0];
$total_reviews  = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM reviews"))[0];

$all_reviews = mysqli_query($conn, "SELECT * FROM reviews ORDER BY date DESC");

// Edit review trigger (admin)
$edit_review = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit_review_trigger') {
    $id = intval($_GET['id_user'] ?? 0);
    $edit_review = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM reviews WHERE id_user=$id"));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>HEXA — Admin Panel</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="index.css"/>
  <style>
    :root { --admin-red: #b23b3b; --admin-green: #27ae60; }
    .admin-badge { display:inline-flex; align-items:center; gap:6px; background:rgba(178,59,59,0.15); border:1px solid #b23b3b; color:#e07070; font-size:9px; letter-spacing:2px; text-transform:uppercase; padding:3px 10px; border-radius:2px; }
    .user-badge { display:flex; align-items:center; gap:8px; padding:10px 16px; background:rgba(255,255,255,0.04); border:1px solid var(--border); border-radius:4px; margin-bottom:20px; }
    .user-badge-avatar { width:32px; height:32px; border-radius:50%; background:rgba(178,59,59,0.2); border:1px solid #b23b3b; display:flex; align-items:center; justify-content:center; font-size:14px; }
    .user-badge-name { font-size:11px; font-weight:600; letter-spacing:1px; color:var(--text); }
    .user-badge-role { font-size:9px; letter-spacing:2px; text-transform:uppercase; color:#e07070; }
    .stat-cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:16px; margin-bottom:32px; }
    .stat-card { background:rgba(255,255,255,0.04); border:1px solid var(--border); border-radius:6px; padding:20px 18px; }
    .stat-card-num { font-family:'Cormorant Garamond',serif; font-size:36px; color:var(--gold); line-height:1; }
    .stat-card-lbl { font-size:9px; letter-spacing:2px; text-transform:uppercase; color:var(--muted); margin-top:6px; }
    .stat-card.red .stat-card-num { color:#e07070; }
    .stat-card.green .stat-card-num { color:#6dbf6d; }
    .admin-table { width:100%; border-collapse:collapse; font-family:'Montserrat',sans-serif; font-size:12px; color:var(--text); }
    .admin-table th { padding:12px 10px; border-bottom:2px solid var(--border); color:var(--accent); text-align:left; font-size:10px; letter-spacing:1.5px; text-transform:uppercase; }
    .admin-table td { padding:12px 10px; border-bottom:1px solid var(--border); vertical-align:middle; }
    .role-pill { display:inline-block; font-size:9px; letter-spacing:1.5px; text-transform:uppercase; padding:2px 8px; border-radius:20px; }
    .role-pill.admin { background:rgba(178,59,59,0.15); border:1px solid #b23b3b; color:#e07070; }
    .role-pill.individu { background:rgba(204,164,59,0.12); border:1px solid var(--gold); color:var(--gold); }
    .btn-sm { font-size:11px; padding:4px 10px; border-radius:4px; border:none; cursor:pointer; text-decoration:none; display:inline-block; }
    .btn-edit { background:#cca43b; color:#fff; }
    .btn-del  { background:#b23b3b; color:#fff; }
    .btn-back { background:#444; color:#fff; }
    .form-card { background:rgba(255,255,255,0.04); border:1px solid var(--border); border-radius:6px; padding:28px 24px; margin-bottom:28px; }
    .form-card h3 { font-family:'Cormorant Garamond',serif; font-size:20px; color:var(--gold); margin-bottom:20px; }
    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .form-group { margin-bottom:14px; }
    .form-label { font-size:9px; letter-spacing:2px; text-transform:uppercase; color:var(--muted); display:block; margin-bottom:6px; }
    .form-input { width:100%; background:rgba(255,255,255,0.05); border:1px solid var(--border); color:var(--text); padding:9px 12px; font-family:'Montserrat',sans-serif; font-size:12px; border-radius:3px; box-sizing:border-box; }
    .form-input:focus { outline:none; border-color:var(--gold); }
    .form-select { width:100%; background:rgba(20,18,14,0.9); border:1px solid var(--border); color:var(--text); padding:9px 12px; font-family:'Montserrat',sans-serif; font-size:12px; border-radius:3px; }
    .btn-primary { background:var(--gold); color:var(--bg); font-size:11px; letter-spacing:2px; text-transform:uppercase; padding:10px 22px; border:none; cursor:pointer; border-radius:3px; }
    .alert-adm { padding:10px 14px; border-radius:4px; font-size:12px; margin-bottom:16px; }
    .alert-adm.ok  { background:rgba(39,174,96,0.12); border:1px solid #27ae60; color:#6dbf6d; }
    .alert-adm.err { background:rgba(178,59,59,0.12); border:1px solid #b23b3b; color:#e07070; }
    .nav-admin-tag { font-size:8px; letter-spacing:1.5px; text-transform:uppercase; background:rgba(178,59,59,0.15); color:#e07070; border:1px solid #b23b3b; padding:1px 6px; border-radius:2px; margin-left:6px; }
  </style>
</head>
<body>
<nav class="sidebar">
  <div class="sidebar-logo">
    <div class="brand">HEXA</div>
    <div class="tagline">Admin Panel</div>
  </div>

  <div class="user-badge">
    <div class="user-badge-avatar">🛡</div>
    <div style="flex:1;min-width:0;">
      <div class="user-badge-name"><?php echo htmlspecialchars($user['nama']); ?></div>
      <div class="user-badge-role">⬡ Administrator</div>
    </div>
  </div>

  <ul class="nav-list">
    <li class="nav-item"><a href="admin.php?page=dashboard" class="nav-link <?php echo $page=='dashboard'?'active':''; ?>" style="text-decoration:none;display:flex;align-items:center;width:100%;">
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Dashboard</a></li>
    <li class="nav-item"><a href="admin.php?page=users" class="nav-link <?php echo $page=='users'?'active':''; ?>" style="text-decoration:none;display:flex;align-items:center;width:100%;">
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
      Kelola User <span class="nav-admin-tag">ADMIN</span></a></li>
    <li class="nav-item"><a href="admin.php?page=reviews" class="nav-link <?php echo $page=='reviews'?'active':''; ?>" style="text-decoration:none;display:flex;align-items:center;width:100%;">
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
      Kelola Review <span class="nav-admin-tag">ADMIN</span></a></li>
  </ul>

  <div class="nav-bottom">
    <a href="admin.php?page=<?php echo $page; ?>&action=logout_trigger" class="nav-logout" style="text-decoration:none;display:flex;align-items:center;">
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout</a>
  </div>
</nav>

<main class="main">

<?php if ($page == 'dashboard'): ?>
<section class="page active">
  <div class="page-header">
    <div>
      <h2 class="page-title">Dash<span>board</span></h2>
      <p class="page-subtitle">Selamat datang di panel administrator HEXA</p>
    </div>
    <div class="admin-badge">⬡ Admin Mode</div>
  </div>

  <div class="stat-cards">
    <div class="stat-card"><div class="stat-card-num"><?php echo $total_users; ?></div><div class="stat-card-lbl">Total User</div></div>
    <div class="stat-card red"><div class="stat-card-num"><?php echo $total_admin; ?></div><div class="stat-card-lbl">Admin</div></div>
    <div class="stat-card"><div class="stat-card-num"><?php echo $total_individu; ?></div><div class="stat-card-lbl">Individu</div></div>
    <div class="stat-card green"><div class="stat-card-num"><?php echo $total_reviews; ?></div><div class="stat-card-lbl">Review</div></div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
    <div class="form-card" style="cursor:pointer;" onclick="location.href='admin.php?page=users'">
      <h3 style="margin:0 0 8px;">👥 Kelola User</h3>
      <p style="color:var(--muted);font-size:12px;margin:0;">Tambah, edit, atau hapus akun. Atur role admin/individu.</p>
    </div>
    <div class="form-card" style="cursor:pointer;" onclick="location.href='admin.php?page=reviews'">
      <h3 style="margin:0 0 8px;">⭐ Kelola Review</h3>
      <p style="color:var(--muted);font-size:12px;margin:0;">Moderasi, edit, dan hapus semua ulasan pelanggan.</p>
    </div>
  </div>
</section>

<?php elseif ($page == 'users'): ?>
<section class="page active">
  <div class="page-header">
    <div><h2 class="page-title">Kelola <span>User</span></h2><p class="page-subtitle">Manajemen akun seluruh pengguna HEXA</p></div>
    <div class="admin-badge">⬡ Admin Only</div>
  </div>

  <?php
  if (!empty($user_msg)) {
      $parts = explode(':', $user_msg, 2);
      $cls = $parts[0]; $msg = $parts[1] ?? '';
      echo "<div class='alert-adm $cls'>$msg</div>";
  }
  if (isset($_GET['msg']) && $_GET['msg'] == 'deleted') echo "<div class='alert-adm ok'>User berhasil dihapus.</div>";
  if (isset($_GET['msg']) && $_GET['msg'] == 'error_self') echo "<div class='alert-adm err'>Tidak bisa menghapus akun sendiri.</div>";
  ?>

  <div class="form-card">
    <?php if ($edit_user_data): ?>
      <h3>✏️ Edit User: <?php echo htmlspecialchars($edit_user_data['nama']); ?></h3>
      <form method="POST" action="admin.php?page=users">
        <input type="hidden" name="action" value="edit_user">
        <input type="hidden" name="id_user" value="<?php echo $edit_user_data['id_user']; ?>">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nama</label>
            <input class="form-input" name="nama" value="<?php echo htmlspecialchars($edit_user_data['nama']); ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email (tidak bisa diubah)</label>
            <input class="form-input" value="<?php echo htmlspecialchars($edit_user_data['email']); ?>" disabled style="opacity:0.5;">
          </div>
          <div class="form-group">
            <label class="form-label">Password Baru (kosongkan jika tidak diubah)</label>
            <input class="form-input" name="password" type="password" placeholder="••••••••">
          </div>
          <div class="form-group">
            <label class="form-label">Role</label>
            <select class="form-select" name="role">
              <option value="individu" <?php echo $edit_user_data['role']=='individu'?'selected':''; ?>>Individu</option>
              <option value="admin"    <?php echo $edit_user_data['role']=='admin'?'selected':''; ?>>Admin</option>
            </select>
          </div>
        </div>
        <div style="display:flex;gap:10px;">
          <button type="submit" class="btn-primary">Simpan Perubahan</button>
          <a href="admin.php?page=users" class="btn-sm btn-back" style="padding:10px 18px;">Batal</a>
        </div>
      </form>
    <?php else: ?>
      <h3>➕ Tambah User Baru</h3>
      <form method="POST" action="admin.php?page=users">
        <input type="hidden" name="action" value="add_user">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Nama</label>
            <input class="form-input" name="nama" placeholder="Nama lengkap" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input class="form-input" name="email" type="email" placeholder="email@domain.com" required>
          </div>
          <div class="form-group">
            <label class="form-label">Password</label>
            <input class="form-input" name="password" type="password" placeholder="••••••••" required>
          </div>
          <div class="form-group">
            <label class="form-label">Role</label>
            <select class="form-select" name="role">
              <option value="individu">Individu</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
        <button type="submit" class="btn-primary">Tambah User</button>
      </form>
    <?php endif; ?>
  </div>

  <div style="overflow-x:auto; background:rgba(255,255,255,0.03); padding:15px; border-radius:8px;">
    <table class="admin-table">
      <thead>
        <tr>
          <th>#</th>
          <th>NAMA</th>
          <th>EMAIL</th>
          <th>ROLE</th>
          <th>DIBUAT</th>
          <th style="text-align:center;">AKSI</th>
        </tr>
      </thead>
      <tbody>
        <?php
        // PERBAIKAN: Mengubah ORDER BY id menjadi ORDER BY id_user
        $query = "SELECT * FROM account ORDER BY id_user DESC";
        $result = mysqli_query($conn, $query);

        if (!$result) {
            die("<tr><td colspan='6' class='alert-adm err'>Query Error: " . mysqli_error($conn) . "</td></tr>");
        }

        if (mysqli_num_rows($result) == 0) {
            echo "<tr><td colspan='6' style='padding:20px;text-align:center;color:var(--muted);'>Belum ada data user.</td></tr>";
        } else {
            // Mengambil ID Admin yang login untuk validasi tanda (Anda)
            $current_admin_id = intval($user['id_user'] ?? $user['id_user'] ?? 0);
            while ($u = mysqli_fetch_assoc($result)) { 
        ?>
          <tr>
            <td style="color:var(--muted);"><?php echo $u['id_user']; ?></td>
            <td>
                <strong><?php echo htmlspecialchars($u['nama']); ?></strong>
                <?php if(intval($u['id_user']) === $current_admin_id): ?>
                    <span style="font-size:9px;color:var(--muted);">(Anda)</span>
                <?php endif; ?>
            </td>
            <td style="color:var(--muted);"><?php echo htmlspecialchars($u['email']); ?></td>
            <td>
                <span class="role-pill <?php echo $u['role']; ?>">
                    <?php echo $u['role']; ?>
                </span>
            </td>
            <td style="color:var(--muted);font-size:11px;">
                <?php echo $u['created_at'] ?? '-'; ?>
            </td>
            <td style="text-align:center; white-space:nowrap;">
                <a href="admin.php?page=users&action=edit_user_trigger&id_user=<?php echo $u['id_user']; ?>" class="btn-sm btn-edit" style="margin-right:5px;">Edit</a>
                <?php if (intval($u['id_user']) !== $current_admin_id): ?>
                    <a href="admin.php?page=users&action=delete_user&id_user=<?php echo $u['id_user']; ?>" onclick="return confirm('Hapus user ini?')" class="btn-sm btn-del">Hapus</a>
                <?php else: ?>
                    <span class="btn-sm" style="background:#333;color:var(--muted);cursor:default;">—</span>
                <?php endif; ?>
            </td>
          </tr>
        <?php 
            } 
        } 
        ?>
      </tbody>
    </table>
  </div>
</section>

<?php elseif ($page == 'reviews'): ?>
<section class="page active">
  <div class="page-header">
    <div><h2 class="page-title">Kelola <span>Review</span></h2><p class="page-subtitle">Admin dapat mengedit dan menghapus semua ulasan</p></div>
    <div class="admin-badge">⬡ Admin Only</div>
  </div>

  <?php if ($edit_review): ?>
  <div class="form-card">
    <h3 style="color:#cca43b;">✏️ Edit Ulasan #<?php echo $edit_review['id_user']; ?></h3>
    <form method="POST" action="proses_review.php" enctype="multipart/form-data" onsubmit="return validateReviewForm(this);">
      <input type="hidden" name="action" value="update_review">
      <input type="hidden" name="id_user" value="<?php echo $edit_review['id_user']; ?>">
      <div class="form-group">
        <label class="form-label">Rating</label>
        <div id="star-picker" style="font-size:22px;cursor:pointer;">
          <?php for($i=1;$i<=5;$i++): ?>
            <span class="star-pick <?php echo $i<=$edit_review['stars']?'selected':''; ?>" onclick="setRating(<?php echo $i; ?>)" style="color:<?php echo $i<=$edit_review['stars']?'#ffcc00':'#555'; ?>;margin-right:4px;">★</span>
          <?php endfor; ?>
        </div>
        <input type="hidden" name="rating" id="rating-value" value="<?php echo $edit_review['stars']; ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Teks Ulasan</label>
        <textarea class="review-textarea" name="review_text" required style="min-height:100px;"><?php echo htmlspecialchars($edit_review['text']); ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Foto Ulasan</label>
        <?php if (!empty($edit_review['image'])): ?>
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
            <img src="img/reviews/<?php echo htmlspecialchars($edit_review['image']); ?>" alt="Foto ulasan" style="width:60px;height:60px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">
            <label style="font-size:11px;color:var(--muted);display:flex;align-items:center;gap:6px;">
              <input type="checkbox" name="remove_image" value="1"> Hapus foto ini
            </label>
          </div>
        <?php endif; ?>
        <input class="form-input" type="file" name="review_image" accept="image/png,image/jpeg,image/webp,image/gif">
        <div style="font-size:10px;color:var(--muted);margin-top:4px;">JPG/PNG/WEBP/GIF, maks 3MB.</div>
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn-primary">Simpan</button>
        <a href="admin.php?page=reviews" class="btn-sm btn-back" style="padding:10px 18px;">Batal</a>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div style="overflow-x:auto;background:rgba(255,255,255,0.03);padding:15px;border-radius:8px;">
    <table class="admin-table">
      <thead><tr><th>#</th><th>User</th><th>Produk</th><th>Rating</th><th>Ulasan</th><th>Foto</th><th>Tanggal</th><th style="text-align:center;">Aksi</th></tr></thead>
      <tbody>
        <?php if (mysqli_num_rows($all_reviews) == 0): ?>
          <tr><td colspan="8" style="padding:20px;text-align:center;color:var(--muted);">Belum ada ulasan.</td></tr>
        <?php else: while ($r = mysqli_fetch_assoc($all_reviews)): ?>
          <tr>
            <td style="color:var(--muted);"><?php echo $r['id_user']; ?></td>
            <td><?php echo $r['avatar']; ?> <strong><?php echo htmlspecialchars($r['name']); ?></strong></td>
            <td style="color:var(--accent);"><?php echo htmlspecialchars($r['product']); ?></td>
            <td style="color:#ffcc00;"><?php for($s=1;$s<=5;$s++) echo $s<=$r['stars']?'★':'☆'; ?></td>
            <td style="max-width:250px;word-wrap:break-word;color:var(--muted);"><?php echo htmlspecialchars($r['text']); ?></td>
            <td>
              <?php if (!empty($r['image'])): ?>
                <a href="img/reviews/<?php echo htmlspecialchars($r['image']); ?>" target="_blank">
                  <img src="img/reviews/<?php echo htmlspecialchars($r['image']); ?>" alt="Foto ulasan" style="width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">
                </a>
              <?php else: ?>
                <span style="color:var(--muted);font-size:11px;">—</span>
              <?php endif; ?>
            </td>
            <td style="font-size:11px;color:var(--muted);"><?php echo $r['date']; ?></td>
            <td style="text-align:center;white-space:nowrap;">
              <a href="admin.php?page=reviews&action=edit_review_trigger&id_user=<?php echo $r['id_user']; ?>#edit_review" class="btn-sm btn-edit" style="margin-right:5px;">Edit</a>
              <a href="proses_review.php?action=delete_review&id_user=<?php echo $r['id_user']; ?>" onclick="return confirm('Hapus ulasan ini?')" class="btn-sm btn-del">Hapus</a>
            </td>
          </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php endif; ?>

</main>

<div class="modal-overlay <?php echo $show_logout_modal?'open':''; ?>">
  <div class="modal-box">
    <div class="modal-icon">◈</div>
    <h3 class="modal-title">Keluar dari Panel Admin?</h3>
    <p class="modal-desc">Anda akan keluar dari sesi administrator.</p>
    <div class="modal-actions">
      <a href="admin.php?page=<?php echo $page; ?>" class="btn-cancel" style="text-decoration:none;text-align:center;display:inline-block;line-height:35px;">Batal</a>
      <form method="POST" action="admin.php" style="display:inline-block;">
        <input type="hidden" name="action" value="confirm_logout">
        <button type="submit" class="btn-confirm-logout">Ya, Keluar</button>
      </form>
    </div>
  </div>
</div>

<script>
  function setRating(val) {
    document.getElementById('rating-value').value = val;
    document.querySelectorAll('.star-pick').forEach((sp, i) => {
      sp.style.color = i < val ? '#ffcc00' : '#555';
    });
  }

  function validateReviewForm(form) {
    const rating = parseInt(form.querySelector('#rating-value').value, 10) || 0;
    if (rating <= 0) {
      alert('Silakan klik bintang untuk memberi rating sebelum menyimpan ulasan.');
      return false;
    }
    return true;
  }
</script>
</body>
</html>