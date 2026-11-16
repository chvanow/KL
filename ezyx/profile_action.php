<?php
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');
csrf_check();

if(!isset($_SESSION['user_id'])){echo json_encode(['error'=>'Требуется вход']);exit;}
$uid=(int)$_SESSION['user_id'];
$action=$_POST['action']??'';

/* Загружаем пользователя */
$st=$pdo->prepare("SELECT * FROM users WHERE id=?");
$st->execute([$uid]);
$user=$st->fetch();
if(!$user){echo json_encode(['error'=>'Пользователь не найден']);exit;}

/* ==== Смена имени ==== */
if($action==='change_name'){
    $name=trim($_POST['name']??'');
    if($name===''){echo json_encode(['error'=>'Имя не может быть пустым']);exit;}
    if(mb_strlen($name)>100){echo json_encode(['error'=>'Имя слишком длинное']);exit;}
    try{
        $st=$pdo->prepare("UPDATE users SET name=? WHERE id=?");
        $st->execute([$name,$uid]);
        echo json_encode(['success'=>true]);
    }catch(Exception $e){echo json_encode(['error'=>$e->getMessage()]);}
    exit;
}

/* ==== Смена пароля ==== */
if($action==='change_password'){
    $old=$_POST['old_password']??'';
    $new=$_POST['new_password']??'';
    $new2=$_POST['new_password2']??'';
    if(!$old||!$new||!$new2){echo json_encode(['error'=>'Заполните все поля']);exit;}
    if($new!==$new2){echo json_encode(['error'=>'Пароли не совпадают']);exit;}
    if(mb_strlen($new)<8){echo json_encode(['error'=>'Пароль минимум 8 символов']);exit;}
if(!preg_match('/[A-Za-zА-Яа-я]/',$new)){echo json_encode(['error'=>'Пароль должен содержать хотя бы одну букву']);exit;}
if(!preg_match('/\d/',$new)){echo json_encode(['error'=>'Пароль должен содержать хотя бы одну цифру']);exit;}
    if(!password_verify($old,$user['password'])){echo json_encode(['error'=>'Текущий пароль неверный']);exit;}
    try{
        $hash=password_hash($new,PASSWORD_DEFAULT);
        $st=$pdo->prepare("UPDATE users SET password=? WHERE id=?");
        $st->execute([$hash,$uid]);
        echo json_encode(['success'=>true]);
    }catch(Exception $e){echo json_encode(['error'=>$e->getMessage()]);}
    exit;
}

/* ==== Удаление аккаунта ==== */
if($action==='delete_account'){
    $pass=$_POST['password']??'';
    if(!$pass){echo json_encode(['error'=>'Введите пароль']);exit;}
    if(!password_verify($pass,$user['password'])){echo json_encode(['error'=>'Неверный пароль']);exit;}

    try{
        $pdo->beginTransaction();

        /* 1. Удаляем офисы пользователя */
/* 1. Собираем фото всех сотрудников пользователя */
$st=$pdo->prepare("SELECT e.photo FROM employees e JOIN projects p ON p.id=e.project_id WHERE p.user_id=? AND e.photo IS NOT NULL AND e.photo<>''");
$st->execute([$uid]);
$photos=$st->fetchAll(PDO::FETCH_COLUMN);

/* 2. Удаляем офисы */
$st=$pdo->prepare("DELETE o FROM offices o JOIN projects p ON p.id=o.project_id WHERE p.user_id=?");
$st->execute([$uid]);

/* 3. Удаляем сотрудников */
$st=$pdo->prepare("DELETE e FROM employees e JOIN projects p ON p.id=e.project_id WHERE p.user_id=?");
$st->execute([$uid]);

/* 4. Удаляем проекты */
$st=$pdo->prepare("DELETE FROM projects WHERE user_id=?");
$st->execute([$uid]);

/* 5. Удаляем пользователя */
$st=$pdo->prepare("DELETE FROM users WHERE id=?");
$st->execute([$uid]);

/* 6. Удаляем фото, если на них больше нет ссылок (после коммита) */
// (сначала commit, потом удаление файлов — чтобы транзакция не откатилась из-за ошибки unlink)

        /* 5. Опционально — удалить фото с диска (только свои) */
        /* пока пропускаем — можно оставить в uploads */

        $pdo->commit();
        // Удаляем фото после успешного коммита
foreach($photos as $p){ deletePhotoIfUnused($pdo,$p); }
        $_SESSION=[];
        session_destroy();
        echo json_encode(['success'=>true]);
    }catch(Exception $e){
        if($pdo->inTransaction())$pdo->rollBack();
        echo json_encode(['error'=>$e->getMessage()]);
    }
    exit;
}

echo json_encode(['error'=>'Неизвестное действие']);