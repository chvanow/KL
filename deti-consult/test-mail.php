<?php
$result = mail('chvanov@outlook.com', 'Тест', 'Тестовое письмо');
echo $result ? 'Отправлено!' : 'Ошибка!';
?>