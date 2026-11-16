<?php
require_once 'config.php';
if(!isset($_SESSION['user_id'])){header('Location: /');exit;}
$uid=(int)$_SESSION['user_id'];
$st=$pdo->prepare("SELECT id,name,email,email_verified,created_at FROM users WHERE id=?");
$st->execute([$uid]);
$user=$st->fetch();
if(!$user){header('Location: /?logout');exit;}
?><!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Личный кабинет — EZYX</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600&family=Inter:wght@400;430;450;480;500&display=swap" rel="stylesheet">
<style>
:root{--ink:#17191c;--white:#fff;--mist:#f2f2f3;--fog:#fafafb;--slate:#777b86;--ash:#979799;--peach:#fbe1d1;--sienna:#5d2a1a;--border:#ececec;--font:'Inter',ui-sans-serif,system-ui,-apple-system,sans-serif;--font-display:'Golos Text','Inter',ui-sans-serif,system-ui,sans-serif}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:var(--font);background:var(--white);min-height:100vh;padding:60px 20px;color:var(--ink);-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.container{max-width:640px;margin:0 auto}
.top{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;flex-wrap:wrap;gap:12px}
.top h1{font-family:var(--font-display);font-size:32px;font-weight:400;color:var(--ink);letter-spacing:-.8px}
.btn{padding:10px 20px;border:none;border-radius:9999px;cursor:pointer;font-size:14px;font-weight:400;font-family:var(--font);text-decoration:none;display:inline-block;transition:all .2s;line-height:1.4}
.btn-ghost{background:transparent;border:1px solid var(--ink);color:var(--ink)}
.btn-ghost:hover{background:var(--ink);color:#fff}
.btn-filled{background:var(--ink);border:1px solid var(--ink);color:#fff}
.btn-filled:hover{opacity:.85}
.card{background:var(--mist);border-radius:24px;padding:32px;margin-bottom:20px}
.card h2{font-family:var(--font-display);font-size:20px;font-weight:500;color:var(--ink);margin-bottom:24px;letter-spacing:-.2px}
.card h2.no-border{margin-bottom:16px}
.row{display:flex;justify-content:space-between;align-items:center;padding:14px 0;gap:12px;flex-wrap:wrap;border-bottom:1px solid var(--border)}
.row:last-child{border-bottom:none}
.row .label{font-size:14px;color:var(--slate)}
.row .value{font-size:14px;color:var(--ink);font-weight:500;word-break:break-word}
.verified{display:inline-block;background:#d5efd5;color:#2a7a2a;font-size:11px;padding:3px 10px;border-radius:9999px;font-weight:500;margin-left:6px}
.not-verified{display:inline-block;background:var(--peach);color:var(--sienna);font-size:11px;padding:3px 10px;border-radius:9999px;font-weight:500;margin-left:6px}
.form-group{margin-bottom:16px}
label{display:block;margin-bottom:8px;color:var(--ink);font-weight:450;font-size:14px;font-family:var(--font)}
input{width:100%;padding:14px 16px;border:1px solid var(--border);border-radius:16px;font-size:15px;font-family:var(--font);background:var(--white);color:var(--ink);transition:border-color .2s}
input:focus{outline:none;border-color:var(--ink)}
.form-actions{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap}
.hint{font-size:12px;color:var(--slate);margin-top:6px}
.danger-zone{background:var(--peach)}
.danger-zone h2{color:var(--sienna)}
.danger-zone p{color:var(--sienna);font-size:14px;line-height:1.6;margin-bottom:20px}
.danger-zone .btn-filled{background:var(--sienna);border-color:var(--sienna);color:#fff}
@media (max-width:768px){body{padding:32px 16px}.top h1{font-size:24px}.card{padding:24px;border-radius:20px}}
</style></head><body>
<div class="container">
  <div class="top">
    <h1>Личный кабинет</h1>
    <a href="/projects.php" class="btn btn-ghost">← Мои проекты</a>
  </div>

  <div class="card">
    <h2>Профиль</h2>
    <div class="row">
      <span class="label">Имя</span>
      <span class="value" id="curName"><?=htmlspecialchars($user['name'])?></span>
    </div>
    <div class="row">
      <span class="label">Email</span>
      <span class="value"><?=htmlspecialchars($user['email'])?><?php if($user['email_verified']): ?><span class="verified">подтверждён</span><?php else: ?><span class="not-verified">не подтверждён</span><?php endif; ?></span>
    </div>
    <div class="row">
      <span class="label">Дата регистрации</span>
      <span class="value"><?=date('d.m.Y',strtotime($user['created_at']))?></span>
    </div>
  </div>

  <div class="card">
    <h2>Изменить имя</h2>
    <form id="nameForm">
  <?= csrf_field() ?>
      <div class="form-group">
        <label>Новое имя</label>
        <input type="text" name="name" value="<?=htmlspecialchars($user['name'])?>" required maxlength="100">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-filled">Сохранить</button>
      </div>
    </form>
  </div>

  <div class="card">
    <h2>Сменить пароль</h2>
    <form id="passForm">
  <?= csrf_field() ?>
      <div class="form-group">
        <label>Текущий пароль</label>
        <input type="password" name="old_password" required autocomplete="current-password">
      </div>
      <div class="form-group">
        <label>Новый пароль</label>
        <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
        <div class="hint">Минимум 8 символов, хотя бы одна буква и одна цифра</div>
      </div>
      <div class="form-group">
        <label>Повторите новый пароль</label>
        <input type="password" name="new_password2" required autocomplete="new-password">
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-filled">Сменить пароль</button>
      </div>
    </form>
  </div>

  <div class="card danger-zone">
    <h2 class="no-border">Удаление аккаунта</h2>
    <p>Удаление аккаунта <b>необратимо</b>. Все ваши проекты, сотрудники, офисы и фотографии будут <b>удалены навсегда</b>.</p>
    <button class="btn btn-filled" onclick="deleteAccount()">Удалить аккаунт навсегда</button>
  </div>
</div>

<script>
function toast(msg,ok){
  var n=document.createElement('div');
  n.style.cssText='position:fixed;bottom:24px;right:24px;background:'+(ok?'#17191c':'#5d2a1a')+';color:#fff;padding:12px 20px;border-radius:9999px;font-weight:400;font-size:14px;z-index:10000;box-shadow:0 4px 16px rgba(0,0,0,.15);font-family:var(--font)';
  n.textContent=msg;document.body.appendChild(n);
  setTimeout(function(){n.remove()},3000);
}
document.getElementById('nameForm').addEventListener('submit',async function(e){
  e.preventDefault();
  var fd=new FormData(this);fd.append('action','change_name');
  try{
    var r=await fetch('profile_action.php',{method:'POST',body:fd});
    var d=await r.json();
    if(d.success){toast('Имя изменено',true);document.getElementById('curName').textContent=fd.get('name');}
    else toast(d.error||'Ошибка',false);
  }catch(err){toast('Ошибка сети',false)}
});
document.getElementById('passForm').addEventListener('submit',async function(e){
  e.preventDefault();
  var fd=new FormData(this);fd.append('action','change_password');
  if(fd.get('new_password')!==fd.get('new_password2')){toast('Пароли не совпадают',false);return;}
  try{
    var r=await fetch('profile_action.php',{method:'POST',body:fd});
    var d=await r.json();
    if(d.success){toast('Пароль изменён',true);document.getElementById('passForm').reset();}
    else toast(d.error||'Ошибка',false);
  }catch(err){toast('Ошибка сети',false)}
});
async function deleteAccount(){
  var pass=prompt('Введите ваш пароль для подтверждения удаления:');
  if(!pass)return;
  if(!confirm('Точно удалить аккаунт навсегда? Все проекты и сотрудники будут стёрты.'))return;
  var fd=new FormData();
  fd.append('action','delete_account');
  fd.append('password',pass);
  fd.append('csrf_token','<?=htmlspecialchars(csrf_token(),ENT_QUOTES)?>');
  try{
    var r=await fetch('profile_action.php',{method:'POST',body:fd});
    var d=await r.json();
    if(d.success){alert('Аккаунт удалён');window.location.href='/';}
    else toast(d.error||'Ошибка',false);
  }catch(err){toast('Ошибка сети',false)}
}
</script>
</body></html>