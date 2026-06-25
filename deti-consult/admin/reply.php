<?php
// admin/reply.php
require_once '../config.php';

if (!isset($_SESSION['admin_logged']) || $_SESSION['admin_logged'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Доступ запрещен']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Метод не разрешен']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$to = $data['to'] ?? '';
$subject = $data['subject'] ?? '';
$text = $data['text'] ?? '';
$method = $data['method'] ?? 'email';
$consultationNumber = $data['consultationNumber'] ?? '';

if (empty($to) || empty($text)) {
    echo json_encode(['success' => false, 'message' => 'Заполните все обязательные поля']);
    exit;
}

$htmlBody = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #0056b3 0%, #0066cc 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
            <h2 style='margin: 0;'>Ответ на консультацию</h2>
            <p style='margin: 5px 0 0 0; opacity: 0.9;'>{$consultationNumber}</p>
        </div>
        
        <div style='background: #f5f7fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
            <p style='margin: 0; white-space: pre-line;'>{$text}</p>
        </div>
        
        <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 13px;'>
            <p style='margin: 0;'>С уважением,<br>Юридическая служба портала deti.gov.ru</p>
            <p style='margin: 10px 0 0 0;'>Это автоматическое сообщение, пожалуйста, не отвечайте на него.</p>
        </div>
    </div>
</body>
</html>
";

$success = sendEmail($to, $subject, $htmlBody);

if ($success) {
    $imap = connectIMAP();
    $emails = imap_search($imap, "SUBJECT \"{$consultationNumber}\"");
    
    if ($emails) {
        foreach ($emails as $msgNumber) {
            imap_setflag_full($imap, $msgNumber, "\\Answered");
        }
    }
    
    imap_close($imap);
    
    echo json_encode([
        'success' => true,
        'message' => 'Ответ отправлен'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка при отправке ответа'
    ]);
}
?>