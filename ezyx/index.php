<?php require_once 'config.php';
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
if(checkAuth()) { header('Location: /dashboard'); exit; } ?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>EZYX — создание организационной структуры компании онлайн</title>
<meta name="description" content="EZYX — бесплатный сервис для быстрого создания и визуализации организационной структуры компании. Редактор с drag-and-drop, экспорт в PDF и Excel, совместная работа.">
<meta name="keywords" content="оргструктура, организационная структура, редактор оргструктуры, оргсхема, штатное расписание, иерархия компании, EZYX">
<meta name="author" content="EZYX">
<meta name="robots" content="index, follow">
<link rel="canonical" href="https://ezyx.ru/">
<meta property="og:type" content="website">
<meta property="og:title" content="EZYX — создание организационной структуры компании">
<meta property="og:description" content="Простой и удобный инструмент для визуализации иерархии сотрудников. Экспорт в PDF и Excel.">
<meta property="og:url" content="https://ezyx.ru/">
<meta property="og:image" content="https://ezyx.ru/logo.png">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="EZYX — организационная структура компании">
<meta name="twitter:description" content="Визуальный редактор оргструктуры с экспортом в PDF и Excel.">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.ico" type="image/x-icon">
<link rel="apple-touch-icon" href="/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600&family=Inter:wght@400;430;450;480;500&display=swap" rel="stylesheet">
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"WebApplication","name":"EZYX","url":"https://ezyx.ru/","description":"Сервис для создания и визуализации организационной структуры компании","applicationCategory":"BusinessApplication","operatingSystem":"Web","offers":{"@type":"Offer","price":"0","priceCurrency":"RUB"}}
</script>
<style>
:root {
  --ink: #17191c;
  --white: #ffffff;
  --mist: #f2f2f3;
  --fog: #fafafb;
  --slate: #777b86;
  --ash: #979799;
  --smoke: #a3a6af;
  --peach: #fbe1d1;
  --sienna: #5d2a1a;
  --border: #ececec;
  --font: 'Inter', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  --font-display: 'Golos Text', 'Inter', ui-sans-serif, system-ui, sans-serif;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:var(--font);background:var(--white);min-height:100vh;display:flex;flex-direction:column;color:var(--ink);-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
main{flex:1}

/* Top Bar */
.top-bar{padding:20px 48px;display:flex;justify-content:space-between;align-items:center;max-width:1600px;margin:0 auto;width:100%}
.logo{display:flex;align-items:center;text-decoration:none;background:transparent}
.logo img{height:40px;width:auto;display:block;background:transparent}
.logo-placeholder{height:40px;min-width:120px;display:flex;align-items:center;color:var(--smoke);font-size:13px}
.top-buttons{display:flex;gap:10px;align-items:center;flex-wrap:wrap}

/* Mobile menu button */
.menu-toggle{display:none;background:transparent;border:none;cursor:pointer;padding:8px;color:var(--ink)}
.menu-toggle svg{width:24px;height:24px}

/* Buttons */
.btn{padding:10px 20px;border:none;border-radius:9999px;cursor:pointer;font-size:16px;font-weight:400;font-family:var(--font);transition:all 0.2s;text-decoration:none;display:inline-block;line-height:1.4}
.btn-ghost{background:transparent;border:1px solid var(--ink);color:var(--ink)}
.btn-ghost:hover{background:var(--ink);color:#fff}
.btn-filled{background:var(--ink);border:1px solid var(--ink);color:#fff}
.btn-filled:hover{opacity:0.85}
.btn-text{background:transparent;border:none;padding:10px 16px;color:var(--slate);font-size:16px;font-weight:400;font-family:var(--font);cursor:pointer;transition:color 0.2s;border-radius:9999px}
.btn-text:hover{color:var(--ink)}
.btn-large{padding:16px 40px;font-size:18px}

/* Hero */
.hero{max-width:1600px;margin:0 auto;padding:100px 48px 60px;text-align:center}
.hero h1{font-family:var(--font-display);font-size:clamp(40px,6vw,80px);font-weight:400;line-height:1.15;letter-spacing:-1.5px;color:var(--ink);margin-bottom:24px;max-width:1200px;margin-left:auto;margin-right:auto}
.hero p{font-size:clamp(17px,1.5vw,22px);font-weight:400;line-height:1.35;color:var(--slate);margin-bottom:40px;max-width:600px;margin-left:auto;margin-right:auto}

/* Features */
.features{max-width:1600px;margin:0 auto;padding:40px 48px 100px;display:grid;grid-template-columns:repeat(3,1fr);gap:24px}
.feature{background:var(--mist);border-radius:24px;padding:36px 28px;transition:all 0.25s}
.feature:hover{background:var(--fog);box-shadow:0 0 0 1px rgba(4,23,43,0.05),0 4px 24px 0 rgba(0,0,0,0.08)}
.feature-icon{margin-bottom:16px}
.feature-icon svg{width:40px;height:40px}
.feature-tag{font-size:14px;font-weight:400;color:var(--ash);display:block;margin-bottom:8px}
.feature h3{font-size:20px;font-weight:500;color:var(--ink);margin-bottom:10px}
.feature p{font-size:16px;font-weight:400;color:var(--ink);line-height:1.5}

/* Accent feature card */
.feature-accent{background:var(--peach)}
.feature-accent:hover{background:var(--peach)}
.feature-accent .feature-icon svg{stroke:var(--sienna)}
.feature-accent .feature-tag{color:var(--sienna);opacity:0.7}
.feature-accent h3{color:var(--sienna)}
.feature-accent p{color:var(--sienna)}

/* Modal */
.modal{display:none;position:fixed;z-index:10000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,0.3);backdrop-filter:blur(4px);align-items:flex-start;justify-content:center;padding-top:5%;overflow-y:auto}
.modal.active{display:flex}
.modal-content{background:var(--white);padding:32px 40px 40px;border-radius:24px;max-width:480px;width:calc(100% - 40px);position:relative;box-shadow:0 0 0 1px rgba(0,0,0,0.05),0 8px 40px 0 rgba(0,0,0,0.1);margin:40px auto}
.modal-logo{display:flex;justify-content:center;margin:8px 0 24px}
.modal-logo img{height:44px;width:auto;display:block}
.modal-logo-placeholder{height:44px;min-width:120px;display:flex;align-items:center;justify-content:center;color:var(--smoke);font-size:12px;border:1px dashed var(--border);border-radius:6px;padding:0 15px}
.close-modal{position:absolute;right:20px;top:16px;font-size:24px;cursor:pointer;color:var(--ash);line-height:1;transition:color 0.2s}
.close-modal:hover{color:var(--ink)}

/* Tabs */
.tabs{display:flex;gap:4px;margin-bottom:24px;background:var(--mist);border-radius:9999px;padding:4px}
.tab-btn{flex:1;padding:10px 16px;border:none;background:transparent;cursor:pointer;border-radius:9999px;font-weight:400;font-size:14px;font-family:var(--font);color:var(--slate);transition:all 0.2s}
.tab-btn.active{background:var(--white);color:var(--ink);font-weight:500;box-shadow:0 1px 3px rgba(0,0,0,0.08)}
.tab-content{display:none}
.tab-content.active{display:block}

/* Forms */
.form-group{margin-bottom:20px}
.form-group label{display:block;margin-bottom:8px;font-size:14px;font-weight:450;color:var(--ink);font-family:var(--font)}
.form-group input[type="email"],
.form-group input[type="password"],
.form-group input[type="text"]{width:100%;padding:14px 16px;border:1px solid var(--border);border-radius:16px;font-size:16px;font-family:var(--font);background:var(--white);transition:border-color 0.2s}
.form-group input:focus{outline:none;border-color:var(--ink)}
.form-group input[type="submit"]{width:100%;padding:14px 24px;border:none;border-radius:9999px;font-size:16px;font-family:var(--font);font-weight:400;cursor:pointer;transition:opacity 0.2s;color:#fff;background:var(--ink)}
.form-group input[type="submit"]:hover{opacity:0.85}

/* Info modal */
.info-modal h3{font-family:var(--font-display);font-size:26px;font-weight:500;color:var(--ink);margin-bottom:16px;letter-spacing:-0.23px}
.info-modal p{font-size:16px;font-weight:400;color:var(--ink);line-height:1.5;margin-bottom:24px}
.info-modal p a{color:var(--ink);font-weight:500;text-decoration:none;border-bottom:1px solid var(--ink)}
.info-modal p a:hover{opacity:0.7}
.modal-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:10px;flex-wrap:wrap}

/* Footer */
.footer{background:var(--fog);padding:48px;border-top:1px solid var(--mist)}
.footer-inner{max-width:1000px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:48px;text-align:left}
.footer-col h4{font-size:14px;font-weight:500;color:var(--ink);margin-bottom:12px;text-transform:uppercase;letter-spacing:0.5px}
.footer-col a,.footer-col p{display:block;font-size:14px;color:var(--slate);text-decoration:none;margin-bottom:8px;transition:color 0.2s;line-height:1.5}
.footer-col a:hover{color:var(--ink)}
.footer-col p{max-width:280px}
.footer-bottom{max-width:1000px;margin:24px auto 0;padding-top:16px;border-top:1px solid var(--mist);text-align:center;font-size:13px;color:var(--ash)}

/* Tablet */
@media (max-width:1024px){
  .features{grid-template-columns:repeat(2,1fr)}
  .hero h1{font-size:clamp(36px,5vw,60px)}
}

/* Mobile */
@media (max-width:768px){
  .top-bar{padding:14px 20px}
  .top-buttons{display:none}
  .top-buttons.open{display:flex;position:fixed;top:0;left:0;right:0;bottom:0;background:var(--white);flex-direction:column;align-items:center;justify-content:center;gap:16px;z-index:9999;padding:40px}
  .top-buttons.open .btn-text{font-size:20px;padding:14px 24px}
  .top-buttons.open .btn{font-size:18px;padding:14px 32px}
  .menu-toggle{display:block;z-index:10000;position:relative}
  .hero{padding:60px 20px 32px}
  .hero h1{font-size:clamp(28px,7vw,42px);letter-spacing:-0.5px;line-height:1.2}
  .hero p{font-size:16px;margin-bottom:32px}
  .btn-large{padding:14px 32px;font-size:16px}
  .features{padding:24px 20px 60px;grid-template-columns:1fr;gap:16px}
  .feature{padding:28px 20px}
  .footer{padding:32px 20px}
  .footer-inner{flex-direction:column;gap:24px}
  .modal-content{margin:16px;padding:24px 20px 28px;width:calc(100% - 32px)}
  .info-modal h3{font-size:22px}
}

@media (max-width:380px){
  .hero h1{font-size:26px}
  .btn-large{padding:12px 24px;font-size:15px}
}
</style>
</head>
<body>

<div class="top-bar">
  <a href="/" class="logo">
    <img src="logo.png" alt="EZYX" onerror="this.style.display='none';this.parentNode.innerHTML='<div class=\'logo-placeholder\'>Место для лого</div>'">
  </a>
  <button class="menu-toggle" onclick="toggleMenu()" aria-label="Меню">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
      <line x1="3" y1="6" x2="21" y2="6"/>
      <line x1="3" y1="12" x2="21" y2="12"/>
      <line x1="3" y1="18" x2="21" y2="18"/>
    </svg>
  </button>
  <div class="top-buttons" id="topButtons">
    <button class="btn btn-ghost" onclick="openModal('login');closeMenu()">Войти</button>
    <button class="btn btn-filled" onclick="openModal('register');closeMenu()">Регистрация</button>
    <button class="btn-text" onclick="openInfo('feedback');closeMenu()">Обратная связь</button>
    <button class="btn-text" onclick="openInfo('about');closeMenu()">О проекте</button>
  </div>
</div>

<main>
<div class="hero">
  <h1>Создайте организационную структуру вашей компании</h1>
  <p>Простой и удобный инструмент для визуализации иерархии сотрудников</p>
  <a href="/guest.php" class="btn btn-large btn-filled">Попробовать без регистрации</a>
</div>

<div class="features">
  <div class="feature">
    <div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--ink)" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/><path d="M9 21V9"/></svg></div>
    <span class="feature-tag">Редактор</span>
    <h3>Визуальный редактор</h3>
    <p>Drag-and-drop интерфейс для создания структуры</p>
  </div>
  <div class="feature">
    <div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--ink)" stroke-width="1.5"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg></div>
    <span class="feature-tag">Структура</span>
    <h3>Иерархия связей</h3>
    <p>Автоматическое отображение связей</p>
  </div>
  <div class="feature feature-accent">
    <div class="feature-icon"><svg viewBox="0 0 24 24" fill="none" stroke="var(--sienna)" stroke-width="1.5"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg></div>
    <span class="feature-tag">Экспорт</span>
    <h3>Экспорт в PDF и Excel</h3>
    <p>Быстрая выгрузка в PDF и Excel</p>
  </div>
</div>
</main>

<footer class="footer">
<div class="footer-inner">
  <div class="footer-col">
    <h4>EZYX</h4>
    <p>Сервис для визуализации организационной структуры компании</p>
  </div>
  <div class="footer-col">
    <h4>Навигация</h4>
    <a href="/">Главная</a>
    <a href="/guest.php">Демо-редактор</a>
    <a href="#" onclick="openInfo('about');return false;">О проекте</a>
  </div>
  <div class="footer-col">
    <h4>Контакты</h4>
    <a href="mailto:vuoo@mail.ru">vuoo@mail.ru</a>
    <a href="#" onclick="openInfo('feedback');return false;">Обратная связь</a>
  </div>
  <div class="footer-col">
    <h4>Документы</h4>
    <a href="#">Политика конфиденциальности</a>
    <a href="#">Пользовательское соглашение</a>
  </div>
</div>
<div class="footer-bottom">© <?=date('Y')?> EZYX. Все права защищены.</div>
</footer>

<!-- Auth Modal -->
<div id="authModal" class="modal">
<div class="modal-content">
  <span class="close-modal" onclick="closeModal('authModal')">×</span>
  <div class="modal-logo">
    <img src="logo.png" alt="EZYX" onerror="this.style.display='none';this.parentNode.innerHTML='<div class=\'modal-logo-placeholder\'>Место для лого</div>'">
  </div>
  <div class="tabs">
    <button class="tab-btn active" onclick="showTab('login',this)">Вход</button>
    <button class="tab-btn" onclick="showTab('register',this)">Регистрация</button>
  </div>
  <div id="login" class="tab-content active">
    <form method="POST" action="login.php">
  <?= csrf_field() ?>
      <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
      <div class="form-group"><label>Пароль</label><input type="password" name="password" required></div>
      <div class="form-group"><input type="submit" value="Войти"></div>
    </form>
  </div>
  <div id="register" class="tab-content">
    <form method="POST" action="register.php">
  <?= csrf_field() ?>
      <div class="form-group"><label>Имя</label><input type="text" name="name" required></div>
      <div class="form-group"><label>Email</label><input type="email" name="email" required></div>
      <div class="form-group"><label>Пароль</label><input type="password" name="password" required></div>
      <div class="form-group"><input type="submit" value="Зарегистрироваться"></div>
    </form>
  </div>
</div>
</div>

<!-- Feedback Modal -->
<div id="feedbackModal" class="modal">
<div class="modal-content info-modal">
  <span class="close-modal" onclick="closeModal('feedbackModal')">×</span>
  <h3>Обратная связь</h3>
  <p>Если у вас есть вопрос или вы хотите внести свои предложения — напишите нам: <a href="mailto:vuoo@mail.ru">vuoo@mail.ru</a></p>
  <div class="modal-actions">
    <button class="btn btn-filled" onclick="closeModal('feedbackModal')">Ок</button>
  </div>
</div>
</div>

<!-- About Modal -->
<div id="aboutModal" class="modal">
<div class="modal-content info-modal">
  <span class="close-modal" onclick="closeModal('aboutModal')">×</span>
  <h3>О проекте</h3>
  <p>EZYX — cервис для быстрого создания и визуализации организационной структуры компании с помощью редактора c возможностью экспорта.</p>
  <div class="modal-actions">
    <button class="btn btn-filled" onclick="closeModal('aboutModal')">Ок</button>
    <a class="btn btn-ghost" href="https://yookassa.ru/my/i/ar-D9qpxAQMj/l" target="_blank" rel="noopener">Поддержать проект</a>
  </div>
</div>
</div>

<script>
function openModal(type){
  document.getElementById('authModal').classList.add('active');
  document.querySelectorAll('#authModal .tab-content').forEach(function(t){t.classList.remove('active');});
  document.querySelectorAll('#authModal .tab-btn').forEach(function(b){b.classList.remove('active');});
  document.getElementById(type).classList.add('active');
  document.querySelectorAll('#authModal .tab-btn')[type==='login'?0:1].classList.add('active');
}
function openInfo(type){document.getElementById(type==='feedback'?'feedbackModal':'aboutModal').classList.add('active');}
function closeModal(id){document.getElementById(id).classList.remove('active');}
function showTab(tab,btn){
  document.querySelectorAll('#authModal .tab-content').forEach(function(t){t.classList.remove('active');});
  document.querySelectorAll('#authModal .tab-btn').forEach(function(b){b.classList.remove('active');});
  document.getElementById(tab).classList.add('active');
  btn.classList.add('active');
}
function toggleMenu(){document.getElementById('topButtons').classList.toggle('open');}
function closeMenu(){document.getElementById('topButtons').classList.remove('open');}
window.onclick=function(e){
  if(e.target==document.getElementById('authModal'))closeModal('authModal');
  if(e.target==document.getElementById('feedbackModal'))closeModal('feedbackModal');
  if(e.target==document.getElementById('aboutModal'))closeModal('aboutModal');
};
</script>
</body>
</html>
