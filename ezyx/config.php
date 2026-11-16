<?php
// ==== Секреты ====
$secretsFile = __DIR__.'/config.secrets.php';
if (!file_exists($secretsFile)) {
    http_response_code(500);
    die('config.secrets.php не найден. Скопируйте config.secrets.example.php в config.secrets.php и заполните.');
}
$secrets = require $secretsFile;

// ==== DB ====
define('DB_HOST', $secrets['db']['host']);
define('DB_NAME', $secrets['db']['name']);
define('DB_USER', $secrets['db']['user']);
define('DB_PASS', $secrets['db']['pass']);

// ==== SMTP ====
define('SMTP_HOST',      $secrets['smtp']['host']);
define('SMTP_PORT',      (int)$secrets['smtp']['port']);
define('SMTP_USER',      $secrets['smtp']['user']);
define('SMTP_PASS',      $secrets['smtp']['pass']);
define('SMTP_FROM',      $secrets['smtp']['from']);
define('SMTP_FROM_NAME', $secrets['smtp']['fromName']);
define('SMTP_SECURE',    $secrets['smtp']['secure']);

// ==== Cleanup token ====
define('CLEANUP_TOKEN', $secrets['cleanup']['token'] ?? '');

// ==== Sessions ====
ini_set('session.gc_maxlifetime', 86400);
session_set_cookie_params([
    'lifetime' => 86400,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() === PHP_SESSION_NONE) session_start();
// Security headers
if(!headers_sent()){
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
}

// ==== PDO ====
try {
    $pdo = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'DB Error: '.$e->getMessage()]));
}

// ==== Helpers ====
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function sendMail($to, $subject, $bodyHtml) {
    require_once __DIR__.'/vendor/autoload.php';
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_SECURE === 'tls'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($to);
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $bodyHtml;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mail error: '.$mail->ErrorInfo);
        return false;
    }
}

function siteUrl() {
    return 'https://ezyx.ru';
}

function checkAuth()       { return isset($_SESSION['user_id']); }
function checkEditorAuth() { if (!isset($_SESSION['editor_auth'])) jsonResponse(['error' => 'Unauthorized'], 401); }

/**
 * Удаляет файл фото, если на него больше нет ссылок в employees.
 * Безопасно: если файл используется где-то ещё — не удалит.
 */
function deletePhotoIfUnused(PDO $pdo, $photoPath) {
    if (!$photoPath) return;
    // Нормализуем: убираем ведущий слеш
    $rel = ltrim($photoPath, '/');
    // Защита от path traversal
    if (strpos($rel, '..') !== false) return;
    // Не трогаем внешние URL
    if (preg_match('#^https?://#i', $rel)) return;
    // Проверяем, что путь внутри uploads/
    if (strpos($rel, 'uploads/') !== 0) return;

    $st = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE photo = ?");
    $st->execute([$photoPath]);
    if ((int)$st->fetchColumn() > 0) return; // ещё используется

    $full = __DIR__.'/'.$rel;
    if (is_file($full)) {
        @unlink($full);
    }
}

// ==== CSRF ====
function csrf_token(){
    if(empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}
function csrf_field(){
    return '<input type="hidden" name="csrf_token" value="'.htmlspecialchars(csrf_token(),ENT_QUOTES).'">';
}
function csrf_check(){
    $t = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if(!$t || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'],$t)){
        http_response_code(403);
        if(strpos($_SERVER['REQUEST_URI'] ?? '', '/api.php') !== false || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'],'application/json')!==false)){
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error'=>'CSRF token invalid']);
        }else{
            die('CSRF token invalid');
        }
        exit;
    }
}

// ==== Logout ====
if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: /');
    exit;
}