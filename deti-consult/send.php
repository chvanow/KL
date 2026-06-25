<?php
// send.php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод']);
    exit;
}

$sector = $_POST['sector'] ?? '';
$category = $_POST['category'] ?? '';
$categoryOther = $_POST['category_other'] ?? '';
$fullName = $_POST['full_name'] ?? '';
$phone = $_POST['phone'] ?? '';
$email = $_POST['email'] ?? '';
$region = $_POST['region'] ?? '';
$description = $_POST['description'] ?? '';

if (empty($sector) || empty($fullName) || empty($phone) || empty($email) || empty($description)) {
    echo json_encode(['success' => false, 'message' => 'Заполните все обязательные поля']);
    exit;
}

$finalCategory = $category ?: $categoryOther;
if (empty($finalCategory)) {
    echo json_encode(['success' => false, 'message' => 'Укажите категорию вопроса']);
    exit;
}

$consultationNumber = generateConsultationNumber();

$sectorNames = [
    'international' => 'Международный сектор',
    'family' => 'Семейно-правовой сектор',
    'social' => 'Социально-правовой сектор',
    'judicial' => 'Судебный блок'
];

$subject = "{$consultationNumber} | {$sectorNames[$sector]} | {$finalCategory}";

$body = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
    <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
        <div style='background: linear-gradient(135deg, #0056b3 0%, #0066cc 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;'>
            <h2 style='margin: 0;'>Новая консультация</h2>
            <p style='margin: 5px 0 0 0; opacity: 0.9;'>{$consultationNumber}</p>
        </div>
        
        <table style='width: 100%; border-collapse: collapse;'>
            <tr><td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold; width: 150px;'>Направление:</td><td style='padding: 10px; border-bottom: 1px solid #eee;'>{$sectorNames[$sector]}</td></tr>
            <tr><td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;'>Категория:</td><td style='padding: 10px; border-bottom: 1px solid #eee;'>{$finalCategory}</td></tr>
            <tr><td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;'>ФИО:</td><td style='padding: 10px; border-bottom: 1px solid #eee;'>{$fullName}</td></tr>
            <tr><td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;'>Телефон:</td><td style='padding: 10px; border-bottom: 1px solid #eee;'>{$phone}</td></tr>
            <tr><td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;'>Email:</td><td style='padding: 10px; border-bottom: 1px solid #eee;'>{$email}</td></tr>
            <tr><td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold;'>Регион:</td><td style='padding: 10px; border-bottom: 1px solid #eee;'>{$region}</td></tr>
            <tr><td style='padding: 10px; border-bottom: 1px solid #eee; font-weight: bold; vertical-align: top;'>Описание:</td><td style='padding: 10px; border-bottom: 1px solid #eee;'>{$description}</td></tr>
        </table>
        
        <div style='margin-top: 20px; padding: 15px; background: #f5f7fa; border-radius: 8px; font-size: 13px; color: #666;'>
            <p style='margin: 0;'>Это письмо отправлено через виджет быстрых консультаций портала deti.gov.ru</p>
            <p style='margin: 10px 0 0 0;'>Срок ответа: до 10 рабочих дней</p>
        </div>
    </div>
</body>
</html>
";

// Обработка файлов
$attachments = [];
$uploadDir = __DIR__ . '/uploads/temp/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
    foreach ($_FILES['files']['tmp_name'] as $key => $tmpName) {
        if ($_FILES['files']['error'][$key] === UPLOAD_ERR_OK) {
            $filename = $_FILES['files']['name'][$key];
            $filepath = $uploadDir . uniqid() . '_' . basename($filename);
            
            if (move_uploaded_file($tmpName, $filepath)) {
                $attachments[] = $filepath;
            }
        }
    }
}

// Отправляем письмо СЕБЕ
$success = sendEmail(EMAIL_ADDRESS, $subject, $body, $attachments);

// Очищаем временные файлы
foreach ($attachments as $file) {
    @unlink($file);
}

if ($success) {
    // Подтверждение заявителю
    $confirmSubject = "Ваша консультация принята ({$consultationNumber})";
    $confirmBody = "
    <!DOCTYPE html>
    <html>
    <head><meta charset='UTF-8'></head>
    <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
        <div style='max-width: 600px; margin: 0 auto; padding: 20px;'>
            <h2 style='color: #0056b3;'>Здравствуйте, {$fullName}!</h2>
            <p>Ваш запрос на юридическую консультацию принят.</p>
            <p><strong>Номер обращения:</strong> {$consultationNumber}</p>
            <p><strong>Тема:</strong> {$finalCategory}</p>
            <p>Наш юрист рассмотрит ваш вопрос и свяжется с вами в течение <strong>10 рабочих дней</strong> по указанному телефону или email.</p>
            <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 13px;'>
                С уважением,<br>Команда портала deti.gov.ru
            </p>
        </div>
    </body>
    </html>
    ";
    
    sendEmail($email, $confirmSubject, $confirmBody);
    
    echo json_encode([
        'success' => true,
        'consultationNumber' => $consultationNumber,
        'message' => 'Запрос успешно отправлен'
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Ошибка при отправке. Попробуйте позже.'
    ]);
}
?>