<?php
ob_start();
session_start();
include 'koneksi.php';

$db = isset($conn) ? $conn : null;
if (!$db) die("Koneksi database gagal.");

$error_msg = "";
$success = false;
$nama_value = "";
$email_value = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $nama     = trim($_POST['nama']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    $nama_value  = $nama;
    $email_value = $email;

    if (empty($nama) || empty($email) || empty($password)) {
        $error_msg = "Semua data wajib diisi.";
    } elseif ($password !== $confirm) {
        $error_msg = "Konfirmasi password tidak cocok.";
    } else {
        $nama_esc  = mysqli_real_escape_string($db, $nama);
        $email_esc = mysqli_real_escape_string($db, $email);
        $pass_esc  = md5($password);

        $check = mysqli_query($db, "SELECT id FROM account WHERE email = '$email_esc'");
        if ($check && mysqli_num_rows($check) > 0) {
            $error_msg = "Email ini sudah terdaftar.";
        } else {
            // Role default selalu 'individu' saat registrasi publik
            $insert = "INSERT INTO account (nama, email, password, role) VALUES ('$nama_esc', '$email_esc', '$pass_esc', 'individu')";
            if (mysqli_query($db, $insert)) {
                $success = true;
                $nama_value = "";
                $email_value = "";
                header("refresh:2; url=login.php");
            } else {
                $error_msg = "Gagal menyimpan: " . mysqli_error($db);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>HEXA — Daftar Akun</title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Montserrat:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="login.css"/>
  <style>
    .panel-right { align-items: flex-start; padding: 52px 70px; }
    .login-box { padding: 20px 0; }
    .strength-wrap { margin-top: 8px; }
    .strength-bar { display: flex; gap: 4px; margin-bottom: 5px; }
    .strength-seg { flex: 1; height: 3px; background: var(--border); transition: background 0.35s ease; }
    .strength-seg.active-weak   { background: #c0392b; }
    .strength-seg.active-fair   { background: #e67e22; }
    .strength-seg.active-good   { background: #f1c40f; }
    .strength-seg.active-strong { background: #27ae60; }
    .strength-label { font-size: 9px; letter-spacing: 1.5px; text-transform: uppercase; color: var(--muted); transition: color 0.3s; }
    .pw-rules { margin-top: 10px; display: flex; flex-direction: column; gap: 5px; }
    .pw-rule { display: flex; align-items: center; gap: 8px; font-size: 10px; color: var(--muted); transition: color 0.3s; }
    .pw-rule-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--border); flex-shrink: 0; transition: background 0.3s; }
    .pw-rule.met { color: #6dbf6d; }
    .pw-rule.met .pw-rule-dot { background: #6dbf6d; }
    .match-hint { font-size: 9px; letter-spacing: 1px; margin-top: 5px; min-height: 14px; color: var(--muted); transition: color 0.3s; }
    .match-hint.ok  { color: #6dbf6d; }
    .match-hint.err { color: #e07070; }
    .terms-wrap .check-label { align-items: flex-start; line-height: 1.7; }
    .terms-wrap .check-box { margin-top: 2px; flex-shrink: 0; }
    .terms-link { color: var(--gold); cursor: pointer; }
    .role-badge { display: inline-block; background: rgba(204,164,59,0.15); border: 1px solid var(--gold); color: var(--gold); font-size: 9px; letter-spacing: 2px; padding: 3px 10px; border-radius: 2px; text-transform: uppercase; margin-bottom: 18px; }
  </style>
</head>
<body>
<div class="login-wrap">
  <div class="panel-left">
    <div class="left-top">
      <div class="logo-mark">HEXA</div>
      <div class="logo-sub">Maison de Mode</div>
    </div>
    <div class="left-mid">
      <h1 class="panel-headline">Jadilah Bagian dari <em>HEXA.</em></h1>
      <p class="panel-body">Daftarkan diri Anda dan nikmati akses eksklusif ke koleksi terbaru, penawaran anggota, serta pengalaman belanja yang dirancang khusus untuk Anda.</p>
    </div>
    <div class="left-bot">
      <div class="deco-line"><div class="deco-hr"></div><div class="deco-diamond">◈</div><div class="deco-hr"></div></div>
      <div class="quote-text">"Gaya sejati dimulai bukan dari lemari pakaian, melainkan dari keyakinan diri."</div>
      <div class="quote-attr">— Creative Director, HEXA</div>
    </div>
  </div>

  <div class="panel-right">
    <div class="corner corner-tl"></div><div class="corner corner-tr"></div>
    <div class="corner corner-bl"></div><div class="corner corner-br"></div>
    <div class="login-box">
      <h2 class="login-title">Buat Akun</h2>
      <p class="login-sub">Lengkapi data di bawah untuk bergabung</p>
      <div class="role-badge">✦ Akun Individu / Pelanggan</div>

      <?php if (!empty($error_msg)): ?>
        <div class="alert alert-error show"><span>⚠</span><span><?php echo htmlspecialchars($error_msg); ?></span></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-ok show"><span>✓</span><span>Akun berhasil dibuat! Mengalihkan ke halaman login...</span></div>
      <?php endif; ?>

      <form method="POST" action="register.php" id="register-form" novalidate>
        <div class="field-group">
          <label class="field-label" for="nama">Nama Lengkap</label>
          <div class="field-wrap">
            <span class="field-icon">✦</span>
            <input class="field-input" id="nama" name="nama" type="text" placeholder="Nama Anda" value="<?php echo htmlspecialchars($nama_value); ?>"/>
            <div class="field-line"></div>
          </div>
        </div>
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
            <input class="field-input" id="password" name="password" type="password" placeholder="Min. 8 karakter"/>
            <button class="toggle-pw" id="toggle-pw" type="button">👁</button>
            <div class="field-line"></div>
          </div>
          <div class="strength-wrap" id="strength-wrap" style="display:none;">
            <div class="strength-bar">
              <div class="strength-seg" id="seg1"></div><div class="strength-seg" id="seg2"></div>
              <div class="strength-seg" id="seg3"></div><div class="strength-seg" id="seg4"></div>
            </div>
            <span class="strength-label" id="strength-label">—</span>
          </div>
          <div class="pw-rules" id="pw-rules" style="display:none;">
            <div class="pw-rule" id="rule-len"><div class="pw-rule-dot"></div>Minimal 8 karakter</div>
            <div class="pw-rule" id="rule-upper"><div class="pw-rule-dot"></div>Minimal 1 huruf kapital</div>
            <div class="pw-rule" id="rule-num"><div class="pw-rule-dot"></div>Minimal 1 angka</div>
          </div>
        </div>
        <div class="field-group">
          <label class="field-label" for="confirm_password">Konfirmasi Password</label>
          <div class="field-wrap">
            <span class="field-icon">🔒</span>
            <input class="field-input" id="confirm_password" name="confirm_password" type="password" placeholder="Ulangi password Anda"/>
            <button class="toggle-pw" id="toggle-pw2" type="button">👁</button>
            <div class="field-line"></div>
          </div>
          <div class="match-hint" id="match-hint"></div>
        </div>
        <div class="terms-wrap" style="margin-bottom:24px;">
          <label class="check-label">
            <input class="check-input" type="checkbox" name="agree" id="agree" required/>
            <div class="check-box"></div>
            <span>Saya menyetujui <span class="terms-link" onclick="showToast('Syarat & Ketentuan segera hadir.')">Syarat &amp; Ketentuan</span> serta <span class="terms-link" onclick="showToast('Kebijakan Privasi segera hadir.')">Kebijakan Privasi</span> HEXA.</span>
          </label>
        </div>
        <button type="submit" name="register" class="btn-login" id="btn-register">Daftar Sekarang</button>
      </form>

      <div class="divider"><hr/><span>atau</span><hr/></div>
      <p class="register-hint">Sudah punya akun? <a href="login.php">Masuk di sini</a></p>
    </div>
  </div>
</div>
<div class="toast" id="toast"></div>
<script>
  function makeToggle(btnId, inputId) {
    document.getElementById(btnId).addEventListener('click', function() {
      const input = document.getElementById(inputId);
      const show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      this.textContent = show ? '🙈' : '👁';
    });
  }
  makeToggle('toggle-pw', 'password');
  makeToggle('toggle-pw2', 'confirm_password');

  const pwInput = document.getElementById('password');
  const strengthWrap = document.getElementById('strength-wrap');
  const pwRules = document.getElementById('pw-rules');
  const segs = [1,2,3,4].map(i => document.getElementById('seg' + i));
  const strengthLbl = document.getElementById('strength-label');
  const ruleLen = document.getElementById('rule-len');
  const ruleUpper = document.getElementById('rule-upper');
  const ruleNum = document.getElementById('rule-num');

  function checkStrength(pw) {
    const hasLen = pw.length >= 8;
    const hasUpper = /[A-Z]/.test(pw);
    const hasNum = /[0-9]/.test(pw);
    const hasSpec = /[^A-Za-z0-9]/.test(pw);
    const score = [hasLen, hasUpper, hasNum, hasSpec].filter(Boolean).length;
    return { hasLen, hasUpper, hasNum, score };
  }

  pwInput.addEventListener('input', function() {
    const pw = this.value;
    if (!pw) { strengthWrap.style.display='none'; pwRules.style.display='none'; return; }
    strengthWrap.style.display='block'; pwRules.style.display='flex';
    const { hasLen, hasUpper, hasNum, score } = checkStrength(pw);
    ruleLen.classList.toggle('met', hasLen);
    ruleUpper.classList.toggle('met', hasUpper);
    ruleNum.classList.toggle('met', hasNum);
    const levels = ['active-weak','active-fair','active-good','active-strong'];
    const labels = ['Lemah','Cukup','Baik','Kuat'];
    segs.forEach((seg, i) => { seg.className='strength-seg'; if(i<score) seg.classList.add(levels[score-1]); });
    strengthLbl.textContent = labels[score-1] || '—';
    strengthLbl.style.color = ['#c0392b','#e67e22','#f1c40f','#27ae60'][score-1] || 'var(--muted)';
    checkConfirmMatch();
  });

  const confirmInput = document.getElementById('confirm_password');
  const matchHint = document.getElementById('match-hint');
  function checkConfirmMatch() {
    const pw = pwInput.value; const cfm = confirmInput.value;
    if (!cfm) { matchHint.textContent=''; matchHint.className='match-hint'; return; }
    if (pw === cfm) { matchHint.textContent='✓ Password cocok'; matchHint.className='match-hint ok'; }
    else { matchHint.textContent='✗ Password tidak cocok'; matchHint.className='match-hint err'; }
  }
  confirmInput.addEventListener('input', checkConfirmMatch);

  document.getElementById('register-form').addEventListener('submit', function(e) {
    if (!document.getElementById('agree').checked) { e.preventDefault(); showToast('Anda harus menyetujui syarat & ketentuan terlebih dahulu.'); return; }
    if (pwInput.value !== confirmInput.value) { e.preventDefault(); showToast('Konfirmasi password tidak cocok.'); return; }
  });

  let toastTimer;
  function showToast(msg) {
    const t = document.getElementById('toast'); t.textContent=msg; t.classList.add('show');
    clearTimeout(toastTimer); toastTimer = setTimeout(() => t.classList.remove('show'), 3200);
  }
</script>
</body>
</html>
<?php ob_end_flush(); ?>
