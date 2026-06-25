<?php
// config.php
session_start();

// Настройки Яндекс.Почты
define('EMAIL_ADDRESS', 'consult@deti.gov.ru'); // Ваша почта
define('EMAIL_PASSWORD', 'towjvdihxqhvqulp'); // Пароль приложения (если нужен SMTP)
define('EMAIL_FROM_NAME', 'Портал deti.gov.ru - Консультации');

// Секреты для нумерации
define('SECRET_KEY', 'deti_gov_ru_2026_consultations');

// Время зоны
date_default_timezone_set('Europe/Moscow');

/**
 * Генерация номера консультации
 */
function generateConsultationNumber() {
    $date = date('Ymd');
    $random = strtoupper(substr(md5(uniqid() . SECRET_KEY), 0, 6));
    return "QC-{$date}-{$random}";
}

/**
 * Отправка email через стандартную mail()
 */
function sendEmail($to, $subject, $body, $attachments = []) {
    // Заголовки
    $headers = "From: " . EMAIL_FROM_NAME . " <" . EMAIL_ADDRESS . ">\r\n";
    $headers .= "Reply-To: " . EMAIL_ADDRESS . "\r\n";
    $headers .= "Return-Path: " . EMAIL_ADDRESS . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    
    // Если есть вложения - используем multipart
    if (!empty($attachments)) {
        $boundary = md5(uniqid(time()));
        $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n\r\n";
        
        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $message .= chunk_split(base64_encode($body)) . "\r\n";
        
        foreach ($attachments as $file) {
            if (file_exists($file)) {
                $filename = basename($file);
                $fileContent = chunk_split(base64_encode(file_get_contents($file)));
                $filetype = mime_content_type($file);
                
                $message .= "--{$boundary}\r\n";
                $message .= "Content-Type: {$filetype}; name=\"{$filename}\"\r\n";
                $message .= "Content-Transfer-Encoding: base64\r\n";
                $message .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
                $message .= $fileContent . "\r\n";
            }
        }
        
        $message .= "--{$boundary}--";
    } else {
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $message = $body;
    }
    
    // Отправляем
    return @mail($to, $subject, $message, $headers);
}

/**
 * Подключение к IMAP (для чтения писем)
 */
function connectIMAP() {
    $mailbox = '{imap.yandex.ru:993/imap/ssl/novalidate}INBOX';
    $imap = @imap_open($mailbox, EMAIL_ADDRESS, EMAIL_PASSWORD);
    
    if (!$imap) {
        die('Ошибка подключения к почте: ' . imap_last_error());
    }
    
    return $imap;
}

/**
 * Парсинг письма
 */
function parseEmail($imap, $msgNumber) {
    $overview = imap_fetch_overview($imap, $msgNumber, 0);
    $body = imap_fetchbody($imap, $msgNumber, 1);
    $bodyHtml = imap_fetchbody($imap, $msgNumber, 2);
    
    $body = imap_utf8($body);
    $bodyHtml = imap_utf8($bodyHtml);
    
    $messageBody = !empty($bodyHtml) ? $bodyHtml : $body;
    $attachments = getAttachments($imap, $msgNumber);
    
    $subject = $overview[0]->subject;
    $from = $overview[0]->from;
    $date = $overview[0]->date;
    $seen = $overview[0]->seen;
    
    $data = parseEmailContent($subject, $messageBody);
    
    return [
        'msgNumber' => $msgNumber,
        'consultationNumber' => $data['number'] ?? extractNumberFromSubject($subject),
        'sector' => $data['sector'] ?? 'Не определен',
        'category' => $data['category'] ?? 'Не указана',
        'fullName' => $data['fullName'] ?? 'Не указано',
        'phone' => $data['phone'] ?? 'Не указан',
        'email' => $data['email'] ?? extractEmailFromFrom($from),
        'region' => $data['region'] ?? 'Не указан',
        'description' => $data['description'] ?? $messageBody,
        'subject' => $subject,
        'from' => $from,
        'date' => date('d.m.Y H:i', strtotime($date)),
        'seen' => (bool)$seen,
        'attachments' => $attachments,
        'body' => $messageBody
    ];
}

function extractNumberFromSubject($subject) {
    if (preg_match('/(QC-\d+-[A-Z0-9]+)/i', $subject, $matches)) {
        return $matches[1];
    }
    return 'Не присвоен';
}

function extractEmailFromFrom($from) {
    if (preg_match('/<([^>]+)>/', $from, $matches)) {
        return $matches[1];
    }
    return $from;
}

function parseEmailContent($subject, $body) {
    $data = [];
    
    if (preg_match('/QC-[\d-]+-[A-Z0-9]+\s*\|\s*([^|]+)\s*\|\s*([^|]+)/i', $subject, $matches)) {
        $data['sector'] = trim($matches[1]);
        $data['category'] = trim($matches[2]);
    }
    
    if (preg_match('/(QC-\d+-[A-Z0-9]+)/i', $subject, $matches)) {
        $data['number'] = $matches[1];
    }
    
    $lines = explode("\n", $body);
    foreach ($lines as $line) {
        $line = strip_tags(trim($line));
        
        if (preg_match('/ФИО:\s*(.+)/i', $line, $m)) $data['fullName'] = trim($m[1]);
        if (preg_match('/Телефон:\s*(.+)/i', $line, $m)) $data['phone'] = trim($m[1]);
        if (preg_match('/Email:\s*(.+)/i', $line, $m)) $data['email'] = trim($m[1]);
        if (preg_match('/Регион:\s*(.+)/i', $line, $m)) $data['region'] = trim($m[1]);
        if (preg_match('/Описание:\s*(.+)/i', $line, $m)) $data['description'] = trim($m[1]);
    }
    
    return $data;
}

function getAttachments($imap, $msgNumber) {
    $attachments = [];
    $structure = imap_fetchstructure($imap, $msgNumber);
    
    if (isset($structure->parts)) {
        foreach ($structure->parts as $index => $part) {
            if ($part->ifdisposition && 
                (strcasecmp($part->disposition, 'attachment') == 0 || 
                 strcasecmp($part->disposition, 'inline') == 0)) {
                
                $params = [];
                if (isset($part->parameters)) {
                    foreach ($part->parameters as $param) {
                        $params[strtolower($param->attribute)] = $param->value;
                    }
                }
                
                if (isset($part->dparameters)) {
                    foreach ($part->dparameters as $param) {
                        $params[strtolower($param->attribute)] = $param->value;
                    }
                }
                
                if (isset($params['filename']) || isset($params['name'])) {
                    $filename = $params['filename'] ?? $params['name'];
                    $attachment = imap_fetchbody($imap, $msgNumber, $index + 1);
                    
                    if ($part->encoding == 3) {
                        $attachment = base64_decode($attachment);
                    } elseif ($part->encoding == 4) {
                        $attachment = imap_qprint($attachment);
                    }
                    
                    $attachments[] = [
                        'filename' => imap_utf8($filename),
                        'data' => $attachment,
                        'size' => strlen($attachment)
                    ];
                }
            }
        }
    }
    
    return $attachments;
}
?>