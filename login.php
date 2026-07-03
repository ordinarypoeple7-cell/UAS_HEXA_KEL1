<?php
session_start();
include 'koneksi.php';

$db = isset($conn) ? $conn : null;
$error_msg = "";
$success = false;
$email_value = "";
$remember_checked = "";

if (isset($_SESSION['login']) && $_SESSION['login'] === true) {
    $role = $_SESSION['user']['role'] ?? 'individu';
    header("Location: " . ($role === 'admin' ? 'admin.php' : 'index.php'));
    exit();
}

if (isset($_COOKIE['user_login'])) {
    $email_value = $_COOKIE['user_login'];
    $remember_checked = "checked";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email    = trim($_POST['email']);
    $password = md5($_POST['password']);
    $remember = isset($_POST['remember']);

    if (empty($email)) {
        $error_msg = "Email tidak boleh kosong.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_msg = "Format email tidak valid.";
    } else {
        $email_esc = mysqli_real_escape_string($db, $email);
        $result = mysqli_query($db, "SELECT * FROM account WHERE email = '$email_esc' AND password = '$password'");

        if ($result && mysqli_num_rows($result) > 0) {
            $data = mysqli_fetch_assoc($result);
            $_SESSION['login'] = true;
            $_SESSION['user']  = $data;   // array lengkap termasuk role
            $_SESSION['username'] = $data['nama'];

            if ($remember) {
                setcookie('user_login', $email, time() + (86400 * 30), "/");
            } else {
                if (isset($_COOKIE['user_login'])) setcookie('user_login', '', time() - 3600, "/");
            }

            $success = true;
            $redirect = ($data['role'] === 'admin') ? 'admin.php' : 'index.php';
            header("refresh:1; url=$redirect");
        } else {
            $error_msg = "Email atau password salah.";
        }
    }
    $email_value = $email;
    $remember_checked = isset($_POST['remember']) ? "checked" : "";
}

// Login Tamu
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login_guest'])) {
    $_SESSION['login'] = true;
    $_SESSION['user'] = ['id'=>0, 'nama'=>'Tamu HEXA', 'email'=>'guest@hexa.local', 'role'=>'individu'];
    $_SESSION['username'] = 'Tamu HEXA';
    header("refresh:1; url=index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>HEXA — Login</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="login.css"/>
</head>
<body>
<div class="login-wrap">
  <div class="panel-left">
    <div class="left-top">
      <div class="logo-mark">HEXA</div>
      <div class="logo-sub">Maison de Mode</div>
    </div>
    <div class="left-mid">
      <h1 class="panel-headline">Mode yang <em>Berbicara</em> Sendiri.</h1>
      <p class="panel-body">Bergabunglah dengan ribuan pelanggan HEXA yang telah menemukan harmoni antara gaya, kenyamanan, dan kemewahan sejati dalam setiap helai kain pilihan kami.</p>
    </div>
    <div class="left-bot">
      <div class="deco-line"><div class="deco-hr"></div><div class="deco-diamond">◈</div><div class="deco-hr"></div></div>
      <div class="quote-text">"HEXA bukan sekadar pakaian — ia adalah pernyataan tentang siapa Anda."</div>
      <div class="quote-attr">— Editorial, Vogue Indonesia</div>
    </div>
  </div>

  <div class="panel-right">
    <div class="corner corner-tl"></div><div class="corner corner-tr"></div>
    <div class="corner corner-bl"></div><div class="corner corner-br"></div>
    <div class="login-box">
      <h2 class="login-title">Selamat Datang</h2>
      <p class="login-sub">Masuk untuk melanjutkan ke HEXA Store</p>

      <?php if (!empty($error_msg)): ?>
        <div class="alert alert-error show"><span>⚠</span><span><?php echo $error_msg; ?></span></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-ok show"><span>✓</span><span>Login berhasil! Mengalihkan...</span></div>
      <?php endif; ?>

      <form method="POST" action="login.php">
        <div class="field-group">
          <label class="field-label" for="email">Alamat Email</label>
          <div class="field-wrap">
            <span class="field-icon">✉</span>
            <input class="field-input" id="email" name="email" type="email" placeholder="email@domain.com" value="<?php echo htmlspecialchars($email_value); ?>"/>
            <div class="field-line"></div>
          </div>
        </div>
        <div class="field-group">
          <label class="field-label" for="password">Password</label>
          <div class="field-wrap">
            <span class="field-icon">🔑</span>
            <input class="field-input" id="password" name="password" type="password" placeholder="••••••••"/>
            <button class="toggle-pw" id="toggle-pw" type="button">👁</button>
            <div class="field-line"></div>
          </div>
        </div>
        <div class="field-extra">
          <label class="check-label">
            <input class="check-input" type="checkbox" name="remember" id="remember" <?php echo $remember_checked; ?>/>
            <div class="check-box"></div>
            Ingat saya
          </label>
          <span class="forgot-link" onclick="handleForgot()">Lupa password?</span>
        </div>
        <button type="submit" name="login" class="btn-login">Masuk</button>
      </form>

      <div class="divider"><hr/><span>atau</span><hr/></div>
      <form method="POST" action="login.php">
      </form>
      <p class="register-hint">Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
    </div>
  </div>
</div>
<div class="toast" id="toast"></div>
<script>
  document.getElementById('toggle-pw').addEventListener('click', function() {
    const pw = document.getElementById('password');
    const show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    this.textContent = show ? '🙈' : '👁';
  });
  function handleForgot() {
    const em = document.getElementById('email').value.trim();
    showToast(em ? 'Link reset dikirim ke ' + em : 'Masukkan email Anda dulu.');
  }
  let toastT;
  function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg; t.classList.add('show');
    clearTimeout(toastT); toastT = setTimeout(() => t.classList.remove('show'), 3000);
  }
</script>
</body>
</html>
