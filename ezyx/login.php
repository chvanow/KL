<?php
require_once 'config.php';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_check();
    
    // Rate limiting
    if(!isset($_SESSION['login_attempts'])) $_SESSION['login_attempts'] = 0;
    if(!isset($_SESSION['login_lock_until'])) $_SESSION['login_lock_until'] = 0;
    if($_SESSION['login_attempts'] >= 5 && time() < $_SESSION['login_lock_until']){
        $wait = $_SESSION['login_lock_until'] - time();
        echo'<script>alert("Слишком много попыток. Попробуйте через '.$wait.' сек.");window.location.href="/";</script>';
        exit;
    }
    if(time() >= $_SESSION['login_lock_until'] && $_SESSION['login_attempts'] >= 5){
        $_SESSION['login_attempts'] = 0;
    }
    
    $stmt=$pdo->prepare("SELECT * FROM users WHERE email=?");
    $stmt->execute([$_POST['email']??'']);
    $user=$stmt->fetch();

    if($user&&password_verify($_POST['password']??'',$user['password'])){
        if((int)$user['email_verified']!==1){
            echo'<script>alert("Email не подтверждён. Проверьте почту.");window.location.href="/";</script>';
            exit;
        }
        session_regenerate_id(true);
        $_SESSION['user_id']=(int)$user['id'];
        $_SESSION['login_attempts'] = 0;
        unset($_SESSION['login_lock_until']);
        header('Location: /projects.php');
        exit;
    }
    
    // Неудача
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    if($_SESSION['login_attempts'] >= 5){
        $_SESSION['login_lock_until'] = time() + 60; // блок на 60 сек
    }
    sleep(1); // задержка 1 сек
    echo'<script>alert("Неверный email или пароль");window.location.href="/";</script>';
    exit;
}
header('Location: /');
exit;