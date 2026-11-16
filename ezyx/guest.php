<?php
require_once 'config.php';
if(isset($_SESSION['user_id'])){header('Location: /dashboard');exit;}
if(!empty($_SESSION['guest_project'])){
  header('Location: /editor?project='.(int)$_SESSION['guest_project']);exit;
}
try{
  $st=$pdo->prepare("INSERT INTO projects (user_id,name) VALUES (0,?)");
  $st->execute(['Демо-проект']);
  $pid=(int)$pdo->lastInsertId();
  session_regenerate_id(true);
  $_SESSION['guest']=1;
  $_SESSION['guest_project']=$pid;
  header('Location: /editor?project='.$pid);exit;
}catch(Exception $e){http_response_code(500);die('Ошибка: '.$e->getMessage());}