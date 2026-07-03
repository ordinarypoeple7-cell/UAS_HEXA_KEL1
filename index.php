<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once 'koneksi.php';

$is_logged_in = isset($_SESSION['login']) && $_SESSION['login'] === true;

// JIKA LOGIN LEWAT COOKIE: Ambil id_user asli dari database
if (!$is_logged_in && isset($_COOKIE['user_login'])) {
    $cookie_user = mysqli_real_escape_string($conn, $_COOKIE['user_login']);
    $query_cookie = mysqli_query($conn, "SELECT * FROM account WHERE email='$cookie_user' OR nama='$cookie_user' LIMIT 1");
    
    if ($query_cookie && mysqli_num_rows($query_cookie) > 0) {
        $db_user = mysqli_fetch_assoc($query_cookie);
        $_SESSION['login'] = true;
        $_SESSION['user']  = [
            'id'    => $db_user['id_user'],
            'nama'  => $db_user['nama'],
            'email' => $db_user['email'],
            'role'  => $db_user['role']
        ];
        $is_logged_in = true;
    }
}

if (!$is_logged_in) { header("Location: login.php"); exit; }

$role = $_SESSION['user']['role'] ?? 'individu';
if ($role === 'admin') { header("Location: admin.php"); exit; }

$user     = $_SESSION['user'];
$user_id  = $user['id_user'] ?? $user['id'] ?? 0; 
$username = $_SESSION['username'] ?? ($user['nama'] ?? 'Pengguna');

$page = isset($_GET['page']) ? $_GET['page'] : 'home';

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

if (isset($_POST['action']) && $_POST['action'] == 'add_to_cart') {
    $name  = $_POST['prod_name'];
    $price = $_POST['prod_price'];
    $icon  = $_POST['prod_icon'];
    $found = false;
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['name'] === $name) { $item['qty']++; $found = true; break; }
    }
    if (!$found) $_SESSION['cart'][] = ['name'=>$name,'price'=>$price,'icon'=>$icon,'qty'=>1];
    header("Location: index.php?page=order"); exit;
}

if (isset($_GET['action']) && $_GET['action'] == 'change_qty') {
    $index = intval($_GET['index']); $delta = intval($_GET['delta']);
    if (isset($_SESSION['cart'][$index])) {
        $_SESSION['cart'][$index]['qty'] += $delta;
        if ($_SESSION['cart'][$index]['qty'] < 1) array_splice($_SESSION['cart'], $index, 1);
    }
    header("Location: index.php?page=order"); exit;
}

// PROTEKSI OTOMATIS: Deteksi apakah nama tabelnya 'reviews' atau 'review'
$table_name = 'reviews';
$test_query = mysqli_query($conn, "SHOW TABLES LIKE 'reviews'");
if (!$test_query || mysqli_num_rows($test_query) == 0) {
    $test_query_2 = mysqli_query($conn, "SHOW TABLES LIKE 'review'");
    if ($test_query_2 && mysqli_num_rows($test_query_2) > 0) {
        $table_name = 'review';
    }
}

// Ambil data ulasan secara aman
$query_reviews = "SELECT * FROM $table_name ORDER BY id_user DESC";
$result_reviews = mysqli_query($conn, $query_reviews);
$db_error_msg = !$result_reviews ? mysqli_error($conn) : null;

$edit_data = null;
if (isset($_GET['action']) && $_GET['action'] == 'edit_trigger') {
    $edit_id = intval($_GET['id_user'] ?? 0);
    $result_edit = mysqli_query($conn, "SELECT * FROM $table_name WHERE id_user=$edit_id AND id_user=$user_id");
    if ($result_edit) {
        $edit_data = mysqli_fetch_assoc($result_edit);
    }
}

$show_logout_modal = false;
if (isset($_GET['action']) && $_GET['action'] == 'logout_trigger') $show_logout_modal = true;
if (isset($_POST['action']) && $_POST['action'] == 'confirm_logout') {
    session_destroy();
    if (isset($_COOKIE['user_login'])) setcookie('user_login', '', time() - 3600, '/');
    header("Location: login.php"); exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>HEXA — Brand Store</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="index.css"/>
  <style>
    .user-badge { display:flex; align-items:center; gap:8px; padding:10px 16px; background:rgba(255,255,255,0.04); border:1px solid var(--border); border-radius:4px; margin-bottom:20px; }
    .user-badge-avatar { width:32px; height:32px; border-radius:50%; background:rgba(204,164,59,0.2); border:1px solid var(--gold); display:flex; align-items:center; justify-content:center; font-size:14px; }
    .user-badge-info { flex:1; min-width:0; }
    .user-badge-name { font-size:11px; font-weight:600; letter-spacing:1px; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .user-badge-role { font-size:9px; letter-spacing:2px; text-transform:uppercase; color:var(--gold); }
    .alert-forbidden { background:rgba(178,59,59,0.12); border:1px solid #b23b3b; color:#e07070; padding:10px 14px; border-radius:4px; font-size:12px; margin-bottom:166px; display:none; }
    .alert-forbidden.show { display:block; }
    .my-review-badge { font-size:9px; letter-spacing:1.5px; text-transform:uppercase; background:rgba(204,164,59,0.1); border:1px solid var(--gold); color:var(--gold); padding:2px 8px; border-radius:2px; margin-left:8px; }
  </style>
</head>
<body>
<nav class="sidebar">
  <div class="sidebar-logo">
    <div class="brand">HEXA</div>
    <div class="tagline">Be You With HEXA</div>
  </div>

  <div class="user-badge">
    <div class="user-badge-avatar">👤</div>
    <div class="user-badge-info">
      <div class="user-badge-name"><?php echo htmlspecialchars($username); ?></div>
      <div class="user-badge-role">✦ Individu</div>
    </div>
  </div>

  <ul class="nav-list">
    <li class="nav-item"><a href="index.php?page=home" class="nav-link <?php echo $page=='home'?'active':''; ?>" style="text-decoration:none;display:flex;align-items:center;width:100%;">
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 9.5L12 3l9 6.5V20a1 1 0 01-1 1H4a1 1 0 01-1-1V9.5z"/><path d="M9 21V12h6v9"/></svg>Home</a></li>
    <li class="nav-item"><a href="index.php?page=katalog" class="nav-link <?php echo $page=='katalog'?'active':''; ?>" style="text-decoration:none;display:flex;align-items:center;width:100%;">
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>Katalog</a></li>
    <li class="nav-item"><a href="index.php?page=order" class="nav-link <?php echo $page=='order'?'active':''; ?>" style="text-decoration:none;display:flex;align-items:center;width:100%;">
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>Order</a></li>
    <li class="nav-item"><a href="index.php?page=review" class="nav-link <?php echo $page=='review'?'active':''; ?>" style="text-decoration:none;display:flex;align-items:center;width:100%;">
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>Review</a></li>
  </ul>

  <div class="nav-bottom">
    <a href="index.php?page=<?php echo $page; ?>&action=logout_trigger" class="nav-logout" style="text-decoration:none;display:flex;align-items:center;">
      <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>Logout</a>
  </div>
</nav>

<main class="main">

  <!-- HOME -->
  <section id="page-home" class="page <?php echo $page=='home'?'active':''; ?>">
    <div class="hero">
      <div class="hero-bg"></div><div class="hero-grid"></div>
      <div class="hero-content">
        <p class="hero-eyebrow">Koleksi Terbaru 2026</p>
        <h1 class="hero-title">Temukan <em>Keanggunan</em> Sejati Anda</h1>
        <p class="hero-desc">HEXA menghadirkan koleksi mode premium yang terinspirasi dari keindahan kontemporer. Setiap potongan adalah pernyataan, setiap detail adalah seni.</p>
        <a href="index.php?page=katalog" class="hero-cta" style="text-decoration:none;display:inline-flex;align-items:center;">
          Jelajahi Koleksi
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left:8px;"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
        </a>
      </div>
      <div class="hero-stats">
        <div class="stat-item"><div class="stat-num">9+</div><div class="stat-label">Produk</div></div>
        <div class="stat-item"><div class="stat-num">12K</div><div class="stat-label">Pelanggan</div></div>
        <div class="stat-item"><div class="stat-num">98%</div><div class="stat-label">Puas</div></div>
      </div>
    </div>
  </section>

  <!-- KATALOG -->
  <section id="page-katalog" class="page <?php echo $page=='katalog'?'active':''; ?>">
    <div class="page-header"><div><h2 class="page-title">Kata<span>log</span></h2><p class="page-subtitle">9 produk tersedia</p></div></div>
    <div class="product-grid">
      <?php
      $products = [
        ['code'=>'A002','name'=>'Patter Pan Collar Cardigan','cat'=>'Cardigan','price'=>'Rp 200.000','badge'=>'NEW','icon'=>'🧥'],
        ['code'=>'A004','name'=>'Ruffle Collar Cardigan','cat'=>'Cardigan','price'=>'Rp 320.000','badge'=>'','icon'=>'🧥'],
        ['code'=>'A005','name'=>'Minimalist Contrast','cat'=>'Casual Luxury','price'=>'Rp 200.000','badge'=>'SALE','icon'=>'🧥'],
        ['code'=>'B001','name'=>'Luxury Red Knit','cat'=>'Cardigan','price'=>'Rp 150.000','badge'=>'','icon'=>'🧥'],
        ['code'=>'B002','name'=>'Hooded Ribbed','cat'=>'Cardigan','price'=>'Rp 150.000','badge'=>'NEW','icon'=>'🧥'],
        ['code'=>'B003','name'=>'Ribbed Pearl','cat'=>'Cardigan','price'=>'Rp 150.000','badge'=>'','icon'=>'🧥'],
        ['code'=>'C001','name'=>'Basic Crewneck Cardigan','cat'=>'Cardigan','price'=>'Rp 135.000','badge'=>'','icon'=>'🧥'],
        ['code'=>'C002','name'=>'Waffle Textured Cardigan','cat'=>'Cardigan','price'=>'Rp 135.000','badge'=>'','icon'=>'🧥'],
        ['code'=>'Hexa C003','name'=>'Cable Knit Cardigan','cat'=>'Cardigan','price'=>'Rp 135.000','badge'=>'','icon'=>'🧥'],
      ];
      foreach ($products as $p): ?>
      <div class="product-card">
        <div class="product-img">
          <img src="IMG/<?php echo $p['code']; ?>.jpeg">
          <?php if(!empty($p['badge'])): ?><div class="product-badge"><?php echo $p['badge']; ?></div><?php endif; ?>
        </div>
        <div class="product-name"><?php echo $p['name']; ?></div>
        <div class="product-cat"><?php echo $p['cat']; ?></div>
        <div class="product-price"><?php echo $p['price']; ?></div>
        <div class="product-actions">
          <form method="POST" action="index.php?page=katalog">
            <input type="hidden" name="action" value="add_to_cart">
            <input type="hidden" name="prod_name" value="<?php echo $p['name']; ?>">
            <input type="hidden" name="prod_price" value="<?php echo $p['price']; ?>">
            <input type="hidden" name="prod_icon" value="<?php echo $p['icon']; ?>">
            <button type="submit" class="btn-add">+ Keranjang</button>
          </form>
          <button class="btn-wish">♡</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- ORDER -->
  <section id="page-order" class="page <?php echo $page=='order'?'active':''; ?>">
    <div class="page-header"><div><h2 class="page-title">Or<span>der</span></h2><p class="page-subtitle"><?php echo count($_SESSION['cart']); ?> item di keranjang</p></div></div>
    <div class="order-layout">
      <div class="order-items">
        <p class="section-label">Item Pesanan</p>
        <?php $subtotal = 0; if (empty($_SESSION['cart'])): ?>
          <p style="color:var(--muted);font-size:13px;padding:40px 0;text-align:center;">Keranjang masih kosong.<br><small>Tambahkan produk dari katalog.</small></p>
        <?php else: foreach ($_SESSION['cart'] as $index => $item):
            $clean_price = (int) preg_replace('/\D/', '', $item['price']);
            $subtotal += $clean_price * $item['qty']; ?>
          <div class="order-item">
            <div class="order-item-img"><?php echo $item['icon']; ?></div>
            <div class="order-item-info">
              <div class="order-item-name"><?php echo $item['name']; ?></div>
              <div class="order-item-meta">HEXA — Premium Collection</div>
              <div class="order-item-price"><?php echo $item['price']; ?></div>
              <div class="qty-control">
                <a href="index.php?page=order&action=change_qty&index=<?php echo $index; ?>&delta=-1" class="qty-btn" style="text-decoration:none;text-align:center;line-height:20px;">−</a>
                <span class="qty-val"><?php echo $item['qty']; ?></span>
                <a href="index.php?page=order&action=change_qty&index=<?php echo $index; ?>&delta=1" class="qty-btn" style="text-decoration:none;text-align:center;line-height:20px;">+</a>
              </div>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>
      <div class="order-summary">
        <p class="section-label">Ringkasan</p>
        <div class="summary-row"><span>Subtotal</span><span>Rp <?php echo number_format($subtotal,0,',','.'); ?></span></div>
        <div class="summary-row"><span>Pengiriman</span><span>Rp <?php echo $subtotal>0?'50.000':'0'; ?></span></div>
        <div class="summary-row"><span>Diskon</span><span style="color:#6dbf6d">- Rp 0</span></div>
        <div class="summary-row total" style="margin-top:20px;padding-top:20px;border-top:1px solid var(--border)">
          <span>Total</span><span>Rp <?php echo $subtotal>0?number_format($subtotal+50000,0,',','.'):0; ?></span>
        </div>
        <div class="promo-box"><input class="promo-input" placeholder="Kode promo..."/><button class="promo-apply">Pakai</button></div>
        <div class="form-group"><label class="form-label">Nama Penerima</label><input class="form-input" placeholder="Nama lengkap" value="<?php echo htmlspecialchars($username); ?>"/></div>
        <div class="form-group"><label class="form-label">Alamat Pengiriman</label><input class="form-input" placeholder="Jalan, Kota, Provinsi"/></div>
        <div class="form-group"><label class="form-label">Nomor Telepon</label><input class="form-input" placeholder="+62"/></div>
        <button class="btn-checkout">Bayar Sekarang →</button>
      </div>
    </div>
  </section>

  <!-- REVIEW -->
  <section id="page-review" class="page <?php echo $page=='review'?'active':''; ?>">
    <div class="page-header"><div><h2 class="page-title">Re<span>view</span></h2><p class="page-subtitle">Ulasan pelanggan HEXA</p></div></div>

    <?php if (isset($_GET['err']) && $_GET['err'] === 'forbidden'): ?>
      <div class="alert-forbidden show" style="display:block;">⚠ Anda hanya dapat mengedit atau menghapus ulasan milik Anda sendiri.</div>
    <?php endif; ?>
    <?php if (isset($_GET['err']) && $_GET['err'] === 'image'): ?>
      <div class="alert-forbidden show" style="display:block;">⚠ <?php echo htmlspecialchars($_GET['msg'] ?? 'Gagal mengupload gambar.'); ?></div>
    <?php endif; ?>

    <?php if ($db_error_msg): ?>
      <div style="background:rgba(178,59,59,0.15); border:1px solid #b23b3b; color:#ff7070; padding:15px; border-radius:6px; font-family:monospace; font-size:12px; margin-bottom:20px;">
        <strong>❌ KENDALA DATABASE SINKRONISASI TABEL:</strong><br>
        Gagal memuat daftar ulasan. Pesan internal MySQL: <?php echo htmlspecialchars($db_error_msg); ?><br><br>
        <em>Solusi: Pastikan nama tabel ulasan di phpMyAdmin adalah 'reviews' atau 'review' dan memiliki struktur kolom yang sesuai.</em>
      </div>
    <?php endif; ?>

    <div style="overflow-x:auto;margin-bottom:40px;background:rgba(255,255,255,0.03);padding:15px;border-radius:8px;">
      <table style="width:100%;border-collapse:collapse;text-align:left;font-family:'Montserrat',sans-serif;font-size:13px;color:var(--text);">
        <thead>
          <tr style="border-bottom:2px solid var(--border);color:var(--accent);">
            <th style="padding:12px 8px;">User</th>
            <th style="padding:12px 8px;">Produk</th>
            <th style="padding:12px 8px;">Rating</th>
            <th style="padding:12px 8px;">Ulasan</th>
            <th style="padding:12px 8px;">Foto</th>
            <th style="padding:12px 8px;">Tanggal</th>
            <th style="padding:12px 8px;text-align:center;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$result_reviews || mysqli_num_rows($result_reviews) == 0): ?>
            <tr><td colspan="7" style="padding:20px;text-align:center;color:var(--muted);">Belum ada ulasan yang tersedia.</td></tr>
          <?php else: while ($r = mysqli_fetch_assoc($result_reviews)):
            $is_mine = ($r['id_user'] == $user_id && $user_id > 0); ?>
            <tr style="border-bottom:1px solid var(--border);">
              <td style="padding:12px 8px;white-space:nowrap;">
                <span style="font-size:16px;margin-right:5px;"><?php echo isset($r['avatar']) ? $r['avatar'] : '👤'; ?></span>
                <strong><?php echo htmlspecialchars($r['name'] ?? 'Anonim'); ?></strong>
                <?php if($is_mine): ?><span class="my-review-badge">Anda</span><?php endif; ?>
              </td>
              <td style="padding:12px 8px;color:var(--accent);"><?php echo htmlspecialchars($r['product'] ?? 'Produk'); ?></td>
              <td style="padding:12px 8px;color:#ffcc00;"><?php $stars_cnt = isset($r['stars'])?intval($r['stars']):5; for($s=1;$s<=5;$s++) echo $s<=$stars_cnt?'★':'☆'; ?></td>
              <td style="padding:12px 8px;max-width:300px;word-wrap:break-word;line-height:1.4;color:var(--muted);"><?php echo htmlspecialchars($r['text'] ?? ''); ?></td>
              <td style="padding:12px 8px;">
                <?php if (!empty($r['image'])): ?>
                  <a href="img/reviews/<?php echo htmlspecialchars($r['image']); ?>" target="_blank">
                    <img src="img/reviews/<?php echo htmlspecialchars($r['image']); ?>" alt="Foto" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">
                  </a>
                <?php else: ?>
                  <span style="color:var(--muted);font-size:11px;">—</span>
                <?php endif; ?>
              </td>
              <td style="padding:12px 8px;white-space:nowrap;"><?php echo isset($r['date']) ? $r['date'] : '-'; ?></td>
              <td style="padding:12px 8px;text-align:center;white-space:nowrap;">
                <?php if ($is_mine): ?>
                  <a href="index.php?page=review&action=edit_trigger&id=<?php echo $r['id_user']; ?>#form-box"
                     style="background:#cca43b;color:#fff;padding:4px 10px;border-radius:4px;text-decoration:none;font-size:11px;margin-right:5px;">Edit</a>
                  <a href="./proses_review.php?action=delete_review&id=<?php echo $r['id_user']; ?>"
                     onclick="return confirm('Hapus ulasan ini?')"
                     style="background:#b23b3b;color:#fff;padding:4px 10px;border-radius:4px;text-decoration:none;font-size:11px;">Hapus</a>
                <?php else: ?>
                  <span style="color:var(--muted);font-size:11px;">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="write-review" id="form-box">
      <?php if ($edit_data): ?>
        <h3 class="write-review-title" style="color:#cca43b;">Ubah Ulasan Anda</h3>
        <form method="POST" action="proses_review.php" enctype="multipart/form-data">
          <input type="hidden" name="action" value="update_review">
          <input type="hidden" name="id" value="<?php echo $edit_data['id']; ?>">
          <div class="star-picker" id="star-picker">
            <?php $e_stars = isset($edit_data['stars'])?intval($edit_data['stars']):5; for($i=1;$i<=5;$i++): ?>
              <span class="star-pick <?php echo $i<=$e_stars?'selected':''; ?>" onclick="setRating(<?php echo $i; ?>)">★</span>
            <?php endfor; ?>
          </div>
          <input type="hidden" name="rating" id="rating-value" value="<?php echo $e_stars; ?>">
          <textarea class="review-textarea" name="review_text" required><?php echo htmlspecialchars($edit_data['text'] ?? ''); ?></textarea>

          <div style="margin:14px 0;">
            <label style="display:block;font-size:9px;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Foto Ulasan (opsional)</label>
            <?php if (!empty($edit_data['image'])): ?>
              <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                <img src="img/reviews/<?php echo htmlspecialchars($edit_data['image']); ?>" alt="Foto" style="width:60px;height:60px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">
                <label style="font-size:11px;color:var(--muted);display:flex;align-items:center;gap:6px;">
                  <input type="checkbox" name="remove_image" value="1"> Hapus foto ini
                </label>
              </div>
            <?php endif; ?>
            <input type="file" name="review_image" accept="image/png,image/jpeg,image/webp,image/gif" style="color:var(--text);font-size:12px;">
          </div>

          <div style="display:flex;gap:10px;">
            <button type="submit" class="btn-submit-review" style="background:#cca43b;">Simpan Perubahan</button>
            <a href="index.php?page=review" class="btn-submit-review" style="background:#555;text-decoration:none;text-align:center;line-height:35px;">Batal</a>
          </div>
        </form>
      <?php else: ?>
        <h3 class="write-review-title">Tulis Ulasan Anda</h3>
        <form method="POST" action="proses_review.php" enctype="multipart/form-data">
          <input type="hidden" name="action" value="submit_review">
          <div class="star-picker" id="star-picker">
            <?php for($i=1;$i<=5;$i++): ?><span class="star-pick" onclick="setRating(<?php echo $i; ?>)">★</span><?php endfor; ?>
          </div>
          <input type="hidden" name="rating" id="rating-value" value="0">
          <textarea class="review-textarea" name="review_text" placeholder="Ceritakan pengalaman Anda dengan produk HEXA..." required></textarea>

          <div style="margin:14px 0;">
            <label style="display:block;font-size:9px;letter-spacing:2px;text-transform:uppercase;color:var(--muted);margin-bottom:6px;">Foto Ulasan (opsional)</label>
            <input type="file" name="review_image" accept="image/png,image/jpeg,image/webp,image/gif" style="color:var(--text);font-size:12px;">
          </div>

          <button type="submit" class="btn-submit-review">Kirim Ulasan</button>
        </form>
      <?php endif; ?>
    </div>
  </section>

</main>

<!-- LOGOUT MODAL -->
<div class="modal-overlay <?php echo $show_logout_modal?'open':''; ?>" id="logout-modal">
  <div class="modal-box">
    <div class="modal-icon">◈</div>
    <h3 class="modal-title">Keluar dari HEXA?</h3>
    <p class="modal-desc">Anda akan keluar dari sesi ini.</p>
    <div class="modal-actions">
      <a href="index.php?page=<?php echo $page; ?>" class="btn-cancel" style="text-decoration:none;text-align:center;display:inline-block;line-height:35px;">Batal</a>
      <form method="POST" action="index.php" style="display:inline-block;">
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
      sp.classList.toggle('selected', i < val);
    });
  }
</script>
</body>
</html>