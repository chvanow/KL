<?php
require_once 'config.php';
$token=trim($_GET['token']??'');
$msg='';
$ok=false;

if($token===''){
    $msg='Неверная ссылка';
}else{
    $st=$pdo->prepare("SELECT id,name,verify_expires FROM users WHERE verify_token=? AND email_verified=0");
    $st->execute([$token]);
    $u=$st->fetch();
    if(!$u){
        $msg='Ссылка недействительна или уже использована';
    }elseif(strtotime($u['verify_expires'])<time()){
        $msg='Срок действия ссылки истёк. Зарегистрируйтесь заново или запросите новое письмо.';
    }else{
        $st=$pdo->prepare("UPDATE users SET email_verified=1, verify_token=NULL, verify_expires=NULL WHERE id=?");
        $st->execute([$u['id']]);
        session_regenerate_id(true);
        $_SESSION['user_id']=(int)$u['id'];
        $ok=true;
        $msg='Email подтверждён! Добро пожаловать, '.htmlspecialchars($u['name']).'!';
    }
}
?>
<!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Подтверждение email — EZYX</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Golos Text',system-ui;background:linear-gradient(135deg,#E8F4F8,#F5E8F0);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{background:#fff;border-radius:14px;padding:40px 30px;max-width:460px;width:100%;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,.08)}
.icon{font-size:48px;margin-bottom:14px}
h2{color:#333;font-weight:500;margin-bottom:14px}
p{color:#666;line-height:1.6;margin-bottom:24px}
.btn{display:inline-block;padding:12px 26px;background:rgba(150,210,150,.7);color:#222;text-decoration:none;border-radius:8px;font-weight:600}
.btn:hover{filter:brightness(.9)}
.btn-blue{background:rgba(100,180,220,.6)}
</style></head><body>
<div class="box">
  <div class="icon"><?=$ok?'🗹':'⚠'?></div>
  <h2><?=$ok?'Успешно!':'Ошибка'?></h2>
  <p><?=$msg?></p>
  <?php if($ok): ?>
    <a href="/projects.php" class="btn">Перейти в личный кабинет</a>
  <?php else: ?>
    <a href="/" class="btn btn-blue">На главную</a>
  <?php endif; ?>
</div>
</body></html>