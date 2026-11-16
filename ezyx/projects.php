<?php
require_once 'config.php';
if(!isset($_SESSION['user_id'])){header('Location: /');exit;}
$uid=(int)$_SESSION['user_id'];
$st=$pdo->prepare("SELECT p.id,p.name,p.share_token,p.is_public,p.created_at,p.updated_at,(SELECT COUNT(*) FROM employees e WHERE e.project_id=p.id) AS emp_count FROM projects p WHERE p.user_id=? ORDER BY p.updated_at DESC");
$st->execute([$uid]);$projects=$st->fetchAll();
?><!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Мои проекты — EZYX</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.ico" type="image/x-icon">
<link rel="apple-touch-icon" href="/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600&family=Inter:wght@400;430;450;480;500&display=swap" rel="stylesheet">
<style>
:root{--ink:#17191c;--white:#ffffff;--mist:#f2f2f3;--fog:#fafafb;--slate:#777b86;--ash:#979799;--smoke:#a3a6af;--peach:#fbe1d1;--sienna:#5d2a1a;--border:#ececec;--font:'Inter',ui-sans-serif,system-ui,-apple-system,sans-serif;--font-display:'Golos Text','Inter',ui-sans-serif,system-ui,sans-serif}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:var(--font);background:var(--white);min-height:100vh;display:flex;flex-direction:column;color:var(--ink);-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
main{flex:1}
.top-bar{padding:20px 48px;display:flex;justify-content:space-between;align-items:center;max-width:1600px;margin:0 auto;width:100%;flex-wrap:wrap;gap:12px}
.logo{display:flex;align-items:center;text-decoration:none;background:transparent}
.logo img{height:40px;width:auto;display:block;background:transparent}
.logo-placeholder{height:40px;min-width:120px;display:flex;align-items:center;color:var(--smoke);font-size:13px}
.top-buttons{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.btn{padding:10px 20px;border:none;border-radius:9999px;cursor:pointer;font-size:16px;font-weight:400;font-family:var(--font);transition:all 0.2s;text-decoration:none;display:inline-block;line-height:1.4}
.btn-ghost{background:transparent;border:1px solid var(--ink);color:var(--ink)}
.btn-ghost:hover{background:var(--ink);color:#fff}
.btn-filled{background:var(--ink);border:1px solid var(--ink);color:#fff}
.btn-filled:hover{opacity:0.85}
.btn-text{background:transparent;border:none;padding:10px 16px;color:var(--slate);font-size:16px;font-weight:400;font-family:var(--font);cursor:pointer;transition:color 0.2s;border-radius:9999px}
.btn-text:hover{color:var(--ink)}
.btn[disabled]{opacity:.4;cursor:not-allowed;pointer-events:none}
.btn-icon{padding:10px 14px}
.btn-icon svg{width:16px;height:16px;vertical-align:middle;display:block}
.page{max-width:1600px;margin:0 auto;padding:60px 48px 60px;width:100%}
.page h1{font-family:var(--font-display);font-size:clamp(36px,5vw,56px);font-weight:400;line-height:1.15;letter-spacing:-1px;color:var(--ink);margin-bottom:40px}
.projects{display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:24px}
.card{background:var(--mist);border-radius:24px;padding:28px;display:flex;flex-direction:column;transition:all .25s}
.card:hover{background:var(--fog);box-shadow:0 0 0 1px rgba(4,23,43,.05),0 4px 24px 0 rgba(0,0,0,.08)}
.card h3{font-family:var(--font-display);margin-bottom:10px;color:var(--ink);word-break:break-word;font-size:22px;font-weight:500;letter-spacing:-.2px}
.card .meta{color:var(--slate);font-size:14px;line-height:1.6;margin-bottom:24px}
.card .actions{display:flex;gap:6px;align-items:center;margin-top:auto;flex-wrap:nowrap}
.card .actions .btn{padding:10px 16px;font-size:14px;flex:1 1 auto;min-width:auto;text-align:center;white-space:nowrap}
.card .actions .btn-icon{flex:0 0 38px;width:38px;height:38px;padding:0;border-radius:50%;display:flex;align-items:center;justify-content:center}
.card .actions .btn-icon svg{width:16px;height:16px;display:block}
.card .actions .btn-icon:first-of-type{margin-left:auto}
.share-badge{display:inline-block;background:var(--peach);color:var(--sienna);font-size:11px;font-weight:500;padding:3px 10px;border-radius:9999px;margin-left:8px;vertical-align:middle;text-transform:uppercase;letter-spacing:.5px}
.empty{color:var(--slate);text-align:center;padding:80px 20px;font-size:16px}
.footer{background:var(--fog);padding:48px;border-top:1px solid var(--mist);margin-top:40px}
.footer-inner{max-width:1000px;margin:0 auto;display:flex;justify-content:center;flex-wrap:wrap;gap:64px;text-align:left}
.footer-col h4{font-size:14px;font-weight:500;color:var(--ink);margin-bottom:12px;text-transform:uppercase;letter-spacing:.5px}
.footer-col a,.footer-col p{display:block;font-size:14px;color:var(--slate);text-decoration:none;margin-bottom:8px;transition:color .2s;line-height:1.5}
.footer-col a:hover{color:var(--ink)}
.footer-col p{max-width:280px}
.footer-bottom{max-width:1600px;margin:24px auto 0;padding-top:16px;border-top:1px solid var(--mist);text-align:center;font-size:13px;color:var(--ash)}
@media (max-width:768px){.top-bar{padding:14px 20px}.page{padding:32px 20px 40px}.page h1{font-size:32px}.footer{padding:32px 20px}.footer-inner{flex-direction:column;gap:24px}}
</style></head><body>
<div class="top-bar">
  <a href="/" class="logo"><img src="logo.png" alt="EZYX" onerror="this.style.display='none';this.parentNode.innerHTML='<div class=\'logo-placeholder\'>Место для лого</div>'"></a>
  <div class="top-buttons">
    <a href="/profile.php" class="btn btn-ghost">Личный кабинет</a>
    <?php $limitReached = count($projects) >= 10; ?>
    <button class="btn btn-filled" id="btnNewProject" onclick="createProject()" <?= $limitReached ? 'disabled title="Достигнут лимит: 10 проектов."' : '' ?>>+ Новый проект</button>
    <a href="/?logout" class="btn btn-ghost">Выйти</a>
  </div>
</div>
<main class="page">
<h1>Мои проекты</h1>
<div class="projects" id="list">
<?php foreach($projects as $p): ?>
<div class="card">
<h3><?=htmlspecialchars($p['name'])?><?php if($p['is_public']): ?><span class="share-badge">публичный</span><?php endif; ?></h3>
<div class="meta">Сотрудников: <?=(int)$p['emp_count']?><br>Обновлён: <?=date('d.m.Y H:i',strtotime($p['updated_at']))?></div>
<div class="actions">
<a class="btn btn-filled" href="/editor?project=<?=(int)$p['id']?>">Редактор</a>
<a class="btn btn-filled" href="/dashboard?project=<?=(int)$p['id']?>">Просмотр</a>
<button class="btn btn-ghost btn-icon" onclick="duplicateProject(<?=(int)$p['id']?>)" title="Дублировать"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg></button>
<button class="btn btn-ghost btn-icon" onclick="deleteProject(<?=(int)$p['id']?>)" title="Удалить"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg></button>
</div>
</div>
<?php endforeach; ?>
<?php if(empty($projects)): ?>
<p class="empty">Пока нет проектов. Нажмите «+ Новый проект».</p>
<?php endif; ?>
</div>
</main>
<footer class="footer">
<div class="footer-inner">
<div class="footer-col"><h4>EZYX</h4><p>Сервис для визуализации организационной структуры компании</p></div>
<div class="footer-col"><h4>Навигация</h4><a href="/">Главная</a><a href="/guest.php">Демо-редактор</a><a href="/projects_all">Все проекты</a></div>
<div class="footer-col"><h4>Контакты</h4><a href="mailto:vuoo@mail.ru">vuoo@mail.ru</a></div>
<div class="footer-col"><h4>Документы</h4><a href="#">Политика конфиденциальности</a><a href="#">Пользовательское соглашение</a></div>
</div>
<div class="footer-bottom">© <?=date('Y')?> EZYX. Все права защищены.</div>
</footer>
<script>window.CSRF_TOKEN = '<?=htmlspecialchars(csrf_token(),ENT_QUOTES)?>';</script>
<script src="assets/js/projects.js"></script>
</body></html>