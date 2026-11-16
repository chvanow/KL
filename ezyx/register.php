<?php
require_once 'config.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    $email=trim($_POST['email']??'');
    $name=trim($_POST['name']??'');
    $pass=$_POST['password']??'';

    if(!$email||!$name||!$pass){
        echo'<script>alert("Заполните все поля");window.location.href="/";</script>';
        exit;
    }
    if(!filter_var($email,FILTER_VALIDATE_EMAIL)){
        echo'<script>alert("Некорректный email");window.location.href="/";</script>';
        exit;
    }

        if(strlen($pass) < 8){
        echo'<script>alert("Пароль должен содержать минимум 8 символов");window.location.href="/";</script>';
        exit;
    }
    if(!preg_match('/[A-Za-zА-Яа-я]/', $pass)){
        echo'<script>alert("Пароль должен содержать хотя бы одну букву");window.location.href="/";</script>';
        exit;
    }
    if(!preg_match('/\d/', $pass)){
        echo'<script>alert("Пароль должен содержать хотя бы одну цифру");window.location.href="/";</script>';
        exit;
    }

    $stmt=$pdo->prepare("SELECT id FROM users WHERE email=?");
    $stmt->execute([$email]);
    if($stmt->fetch()){
        echo'<script>alert("Email уже существует");window.location.href="/";</script>';
        exit;
    }

    $hash=password_hash($pass,PASSWORD_DEFAULT);
    $token=bin2hex(random_bytes(32));
    $expires=date('Y-m-d H:i:s',time()+24*3600); // 24 часа

    $stmt=$pdo->prepare("INSERT INTO users (name,email,password,email_verified,verify_token,verify_expires) VALUES (?,?,?,0,?,?)");
    $stmt->execute([$name,$email,$hash,$token,$expires]);
    $uid=(int)$pdo->lastInsertId();

    /* Ссылка подтверждения */
    $link=siteUrl().'/verify.php?token='.urlencode($token);
    $subject='Подтвердите регистрацию на EZYX';
    $body='<div style="font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:20px">'
        .'<h2 style="color:#333;font-weight:500">Добро пожаловать на EZYX!</h2>'
        .'<p>Здравствуйте, '.htmlspecialchars($name).'!</p>'
        .'<p>Для завершения регистрации подтвердите ваш email, нажав на кнопку ниже:</p>'
        .'<p style="text-align:center;margin:30px 0">'
        .'<a href="'.$link.'" style="display:inline-block;background:rgba(150,210,150,0.9);color:#222;padding:14px 28px;text-decoration:none;border-radius:8px;font-weight:600">Подтвердить email</a>'
        .'</p>'
        .'<p style="color:#888;font-size:13px">Если кнопка не работает, скопируйте ссылку:<br>'.$link.'</p>'
        .'<p style="color:#888;font-size:12px;margin-top:30px">Ссылка действует 24 часа. Если вы не регистрировались — просто проигнорируйте это письмо.</p>'
        .'</div>';

    sendMail($email,$subject,$body);

    echo'<script>alert("Регистрация успешна! Проверьте почту для подтверждения email.");window.location.href="/";</script>';
    exit;
}
header('Location: /');
exit;