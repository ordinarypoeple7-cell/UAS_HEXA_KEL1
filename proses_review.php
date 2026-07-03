<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once 'koneksi.php';

$user = $_SESSION['user'] ?? null;
$role = $user['role'] ?? 'individu';
// Mengamankan pembacaan ID (mencari 'id_user' atau 'id')
$user_id = $user['id_user'] ?? $user['id'] ?? 0;

$UPLOAD_DIR = __DIR__ . '/img/reviews/';
$ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
$MAX_SIZE    = 3 * 1024 * 1024; // 3MB

/**
 * Menangani upload gambar review.
 * Mengembalikan array [namaFileBaru|null, pesanError|null]
 */
function handle_review_image_upload($file, $uploadDir, $allowedExt, $maxSize) {
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, null];
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [null, 'Gagal mengupload gambar (kode error: ' . $file['error'] . ').'];
    }
    if ($file['size'] > $maxSize) {
        return [null, 'Ukuran gambar maksimal 3MB.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt)) {
        return [null, 'Format gambar tidak didukung. Gunakan JPG, PNG, WEBP, atau GIF.'];
    }

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $newName = 'rev_' . uniqid() . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $newName)) {
        return $newName;
    }
    return [null, 'Gagal menyimpan file ke folder tujuan.'];
}

// 1. TAMBAH REVIEW
if (isset($_POST['action']) && $_POST['action'] == 'submit_review') {
    if ($user_id <= 0) {
        header("Location: index.php?page=review&err=forbidden");
        exit;
    }

    $text  = mysqli_real_escape_string($conn, trim($_POST['review_text']));
    $stars = intval($_POST['rating']);
    $name  = mysqli_real_escape_string($conn, $user['nama'] ?? 'Pengguna');
    
    // Default values sesuai struktur template HEXA
    $avatar  = '👤';
    $product = 'Cable Knit Cardigan'; // Default product placeholder
    $date    = date('Y-m-d H:i:s');

    // Proses upload gambar
    $uploadRes = handle_review_image_upload($_FILES['review_image'], $UPLOAD_DIR, $ALLOWED_EXT, $MAX_SIZE);
    
    // Jika handle_review_image_upload mengembalikan string nama file, berarti berhasil. Jika array, periksa error-nya.
    $imageName = null;
    if (is_array($uploadRes)) {
        if ($uploadRes[1] !== null) {
            header("Location: index.php?page=review&err=image&msg=" . urlencode($uploadRes[1]));
            exit;
        }
        $imageName = $uploadRes[0];
    } else {
        $imageName = $uploadRes;
    }

    if (empty($text) || $stars <= 0) {
        $msg = empty($text) ? 'Teks ulasan tidak boleh kosong.' : 'Silakan pilih rating bintang terlebih dahulu.';
        header("Location: index.php?page=review&err=invalid&msg=" . urlencode($msg));
        exit;
    }

    $imgSqlVal = $imageName ? "'$imageName'" : "NULL";
    $query = "INSERT INTO reviews (id_user, avatar, name, stars, text, image, product, date) 
              VALUES ($user_id, '$avatar', '$name', $stars, '$text', $imgSqlVal, '$product', '$date')";
    mysqli_query($conn, $query);

    header("Location: index.php?page=review");
    exit;
}

// 2. UPDATE REVIEW
if (isset($_POST['action']) && $_POST['action'] == 'update_review') {
    $id = intval($_POST['id_user'] ?? 0);
    
    // Cek kepemilikan jika bukan admin
    $chk_query = "SELECT image, id_user FROM reviews WHERE id_user=$id";
    $chk_res = mysqli_query($conn, $chk_query);
    if (!$chk_res || mysqli_num_rows($chk_res) == 0) {
        header("Location: index.php?page=review");
        exit;
    }
    
    $oldReview = mysqli_fetch_assoc($chk_res);
    $oldImage  = $oldReview['image'];

    if ($role !== 'admin' && intval($oldReview['id_user']) !== $user_id) {
        $redirect = ($role === 'admin') ? "admin.php?page=reviews" : "index.php?page=review";
        header("Location: $redirect&err=forbidden");
        exit;
    }

    $text  = mysqli_real_escape_string($conn, trim($_POST['review_text']));
    $stars = intval($_POST['rating']);
    $redirect = ($role === 'admin') ? "admin.php?page=reviews" : "index.php?page=review";

    $uploadRes = handle_review_image_upload($_FILES['review_image'], $UPLOAD_DIR, $ALLOWED_EXT, $MAX_SIZE);
    
    $imageName = null;
    if (is_array($uploadRes)) {
        if ($uploadRes[1] !== null) {
            header("Location: $redirect&err=image&msg=" . urlencode($uploadRes[1]));
            exit;
        }
        $imageName = $uploadRes[0];
    } else {
        $imageName = $uploadRes;
    }

    $removeOld = isset($_POST['remove_image']) && $_POST['remove_image'] == '1';

    $imageSetSql = '';
    if ($imageName) {
        $imageSetSql = ", image='" . mysqli_real_escape_string($conn, $imageName) . "'";
        if ($oldImage && file_exists($UPLOAD_DIR . $oldImage)) @unlink($UPLOAD_DIR . $oldImage);
    } elseif ($removeOld) {
        $imageSetSql = ", image=NULL";
        if ($oldImage && file_exists($UPLOAD_DIR . $oldImage)) @unlink($UPLOAD_DIR . $oldImage);
    }

    if (empty($text) || $stars <= 0) {
        $msg = empty($text) ? 'Teks ulasan tidak boleh kosong.' : 'Silakan pilih rating bintang terlebih dahulu.';
        header("Location: $redirect&err=invalid&msg=" . urlencode($msg));
        exit;
    }

    mysqli_query($conn, "UPDATE reviews SET text='$text', stars=$stars$imageSetSql WHERE id_user=$id");
    header("Location: $redirect");
    exit;
}

// 3. HAPUS REVIEW
if (isset($_GET['action']) && $_GET['action'] == 'delete_review') {
    $id = intval($_GET['id_user'] ?? 0);
    $redirect = ($role === 'admin') ? "admin.php?page=reviews" : "index.php?page=review";

    if ($role !== 'admin') {
        $chk = mysqli_query($conn, "SELECT id FROM reviews WHERE id_user=$id AND id_user=$id_user");
        if (!$chk || mysqli_num_rows($chk) == 0) {
            header("Location: index.php?page=review&err=forbidden");
            exit;
        }
    }

    $old = mysqli_fetch_assoc(mysqli_query($conn, "SELECT image FROM reviews WHERE id_user=$id"));
    if ($old && !empty($old['image']) && file_exists($UPLOAD_DIR . $old['image'])) {
        @unlink($UPLOAD_DIR . $old['image']);
    }

    mysqli_query($conn, "DELETE FROM reviews WHERE id_user=$id");
    header("Location: $redirect");
    exit;
}