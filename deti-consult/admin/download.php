<?php
// admin/download.php
require_once '../config.php';

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    http_response_code(403);
    die('Доступ запрещен');
}

$action = $_GET['action'] ?? '';
$msgNumber = $_GET['msg'] ?? 0;

if (!$msgNumber) {
    die('Неверный номер сообщения');
}

$imap = connectIMAP();

if ($action === 'view') {
    // Просмотр письма
    $email = parseEmail($imap, $msgNumber);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'from' => $email['from'],
        'subject' => $email['subject'],
        'date' => $email['date'],
        'body' => $email['body'],
        'attachments' => $email['attachments']
    ]);
    
} elseif ($action === 'attachment') {
    // Скачивание вложения
    $filename = $_GET['file'] ?? '';
    
    if (!$filename) {
        die('Файл не указан');
    }
    
    $email = parseEmail($imap, $msgNumber);
    
    foreach ($email['attachments'] as $att) {
        if ($att['filename'] === $filename) {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $att['filename'] . '"');
            header('Content-Length: ' . $att['size']);
            echo $att['data'];
            exit;
        }
    }
    
    die('Файл не найден');
}

imap_close($imap);
?>