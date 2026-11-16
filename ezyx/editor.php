<?php
require_once 'config.php';
$isGuest=!isset($_SESSION['user_id'])&&!empty($_SESSION['guest']);
if(!isset($_SESSION['user_id'])&&!$isGuest){header('Location: /');exit;}
$uid=isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:0;
$pid=(int)(isset($_GET['project'])?$_GET['project']:0);
if($isGuest){
  $pid=(int)$_SESSION['guest_project'];
  $st=$pdo->prepare("SELECT id,name FROM projects WHERE id=?");$st->execute([$pid]);
}else{
  if(!$pid){
    $st=$pdo->prepare("SELECT id FROM projects WHERE user_id=? ORDER BY updated_at DESC LIMIT 1");
    $st->execute([$uid]);$row=$st->fetch();
    if(!$row){header('Location: /projects.php');exit;}
    $pid=(int)$row['id'];
  }
  $st=$pdo->prepare("SELECT id,name FROM projects WHERE id=? AND user_id=?");$st->execute([$pid,$uid]);
}
$project=$st->fetch();
if(!$project){header('Location: /');exit;}
?><!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=htmlspecialchars($project['name'])?> — Редактор</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600&family=Inter:wght@400;430;450;480;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/editor.css">
</head><body>
<div class="toolbar">
  <h1 id="projectTitle"><?=htmlspecialchars($project['name'])?></h1>
  <div class="toolbar-buttons">
    <?php if(!$isGuest): ?>
    <a href="/profile.php" class="btn btn-ghost">Личный кабинет</a>
    <a href="/projects" class="btn btn-ghost">Мои проекты</a>
    <?php endif; ?>
    <button class="btn btn-ghost" onclick="shareProject()">Поделиться</button>
    <button class="btn btn-filled" onclick="viewResult()">Дашборд</button>
    <?php if(!$isGuest): ?><a href="/?logout" class="btn btn-ghost">Выйти</a><?php else: ?><a href="/" class="btn btn-filled">Регистрация</a><?php endif; ?>
  </div>
</div>
<div id="canvas" class="canvas">
  <div id="container"><svg id="svg"></svg></div>
  <button class="btn-rename" onclick="renameProject()" title="Переименовать проект">Переименовать</button>
  <button class="btn-add" onclick="addEmployee()">+ Добавить</button>
  <button class="btn-list-editor" onclick="openListModal()">Список</button>
</div>
<div class="zoom-controls">
  <button onclick="zoom(1.15)" title="Приблизить">+</button>
  <button onclick="zoom(1/1.15)" title="Отдалить">−</button>
  <button onclick="resetZoom()" title="Сбросить вид"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 3-6.7"/><polyline points="3 4 3 10 9 10"/></svg></button>
</div>
<div id="contextMenu" class="context-menu"></div>
<div id="modal" class="modal"><div class="modal-content">
  <span class="close" onclick="closeModal()">×</span>
  <form id="form">
    <input type="hidden" name="id">
    <div class="form-row">
      <div class="form-group"><label>ФИО</label><input name="full_name" required></div>
      <div class="form-group"><label>Должность</label><input name="position" required></div>
    </div>
    <div class="form-group"><label>Руководитель</label><select name="parent_id"><option value="0">Нет</option></select></div>
    <div class="form-group"><label><input type="checkbox" name="show_manager" checked> Отображать руководителя</label></div>
    <div class="form-group"><label><input type="checkbox" name="is_department"> Отображать название отдела</label></div>
    <div class="form-group" style="display:none"><label>Название отдела</label><input name="department"></div>
    <div class="form-group"><label>Телефоны</label><div id="phone-container"></div><span class="add-contact" id="add-phone" onclick="addContact('phone')">+ Добавить</span></div>
    <div class="form-group"><label>Внутренний ном. тел.</label><input type="text" name="ext" maxlength="6" placeholder="Например: 8024"></div>
    <div class="form-group"><label>Email</label><div id="email-container"></div><span class="add-contact" id="add-email" onclick="addContact('email')">+ Добавить</span></div>
    <div class="form-group">
      <label>Офис</label>
      <div style="display:flex;gap:8px;align-items:center">
        <select name="office_id" id="officeSelect" style="flex:1"><option value="0">Не указан</option></select>
        <button type="button" class="btn btn-filled" onclick="openOfficeModal()" style="padding:8px 14px;font-size:13px;white-space:nowrap;border-radius:9999px">+ Добавить</button>
      </div>
    </div>
    <div class="form-group">
      <label style="display:flex;align-items:center;gap:8px">каб. <input type="text" name="cabinet" maxlength="6" placeholder="411" style="width:100px;display:inline-block;padding:8px 10px"></label>
    </div>
    <div class="form-group">
      <label>Ссылки</label>
      <div id="links-container"></div>
      <span class="add-contact" id="add-link" onclick="addLink()">+ Добавить</span>
    </div>
    <div class="form-row">
      <div class="form-group"><label><input type="checkbox" name="show_contacts"> Отображать контакты на схеме</label></div>
      <div class="form-group"><label><input type="checkbox" name="show_office"> Отображать офис на схеме</label></div>
    </div>
    <div class="form-group">
      <label><input type="checkbox" name="show_in_list" checked> Показывать в списке сотрудников</label>
    </div>
    <div class="form-group">
      <label>Фото</label>
      <input type="file" name="photo" accept="image/*">
      <div id="preview"></div>
      <label style="margin-top:6px"><input type="checkbox" name="show_photo"> Отображать фото</label>
    </div>
    <div class="form-group">
      <label>День рождения</label>
      <input type="text" name="birthday" maxlength="10" placeholder="ДД.ММ.ГГГГ" pattern="\d{2}\.\d{2}\.\d{4}">
    </div>
    <div class="form-actions">
      <button type="submit" class="btn btn-filled">Сохранить</button>
      <button type="button" class="btn btn-ghost" id="btnDelete">Удалить</button>
      <button type="button" class="btn btn-text" onclick="closeModal()">Отмена</button>
    </div>
  </form>
</div></div>
<div id="cropModal" class="modal"><div class="modal-content">
  <span class="close" onclick="closeCropModal()">×</span>
  <h3 style="margin-bottom:15px">Настройка фото</h3>
  <div class="crop-container"><img id="cropImg" src="" alt=""></div>
  <div style="margin-top:15px"><label>Масштаб</label><input type="range" id="cropZoom" min="0.5" max="3" step="0.1" value="1" style="width:100%"></div>
  <div class="form-actions">
    <button type="button" class="btn btn-filled" id="cropApply">Применить</button>
    <button type="button" class="btn btn-ghost" onclick="resetCrop()">Сбросить</button>
    <button type="button" class="btn btn-text" onclick="closeCropModal()">Отмена</button>
  </div>
</div></div>
<div id="officeModal" class="modal"><div class="modal-content">
  <span class="close" onclick="closeOfficeModal()">×</span>
  <h3 style="margin-bottom:15px" id="officeModalTitle">Новый офис</h3>
  <input type="hidden" id="officeEditId" value="">
  <div class="form-group"><label>Название офиса</label><input id="officeName" type="text" placeholder="Например: Миусская" maxlength="100"></div>
  <div class="form-group">
    <label>Цвет</label>
    <div id="officeColors" style="display:flex;gap:10px;margin-top:6px">
      <div class="color-swatch" data-color="#D2B773" style="background:#D2B773"></div>
      <div class="color-swatch" data-color="#D47479" style="background:#D47479"></div>
      <div class="color-swatch" data-color="#8773A1" style="background:#8773A1"></div>
      <div class="color-swatch" data-color="#86A47C" style="background:#86A47C"></div>
      <div class="color-swatch" data-color="#4D668E" style="background:#4D668E"></div>
    </div>
  </div>
  <div class="form-actions">
    <button type="button" class="btn btn-filled" onclick="saveOffice()">Сохранить</button>
    <button type="button" class="btn btn-text" onclick="closeOfficeModal()">Отмена</button>
  </div>
  <div id="officeListBlock" style="margin-top:20px;padding-top:15px;border-top:1px solid #eee">
    <label style="display:block;margin-bottom:8px;color:#666;font-size:13px">Существующие офисы:</label>
    <div id="officeList"></div>
  </div>
</div></div>
<div id="linkModal" class="modal"><div class="modal-content">
  <span class="close" onclick="closeLinkModal()">×</span>
  <h3 style="margin-bottom:15px">Название ссылки</h3>
  <div class="form-group"><label>Отображаемый текст (если пусто — показывается URL)</label><input id="linkLabel" type="text" placeholder="Например: Профиль" maxlength="100"></div>
  <div class="form-actions">
    <button type="button" class="btn btn-filled" onclick="applyLinkLabel()">Применить</button>
    <button type="button" class="btn btn-ghost" onclick="clearLinkLabel()">Очистить</button>
    <button type="button" class="btn btn-text" onclick="closeLinkModal()">Отмена</button>
  </div>
</div></div>
<div id="shareModal" class="modal"><div class="modal-content">
  <span class="close" onclick="document.getElementById('shareModal').classList.remove('show-flex')">×</span>
  <h3 style="margin-bottom:15px">Ссылка для просмотра</h3>
  <p style="color:#666;margin-bottom:10px">Любой, у кого есть эта ссылка, сможет посмотреть структуру (только чтение).</p>
  <input id="shareUrl" readonly style="width:100%;padding:10px;border:1px solid #ddd;border-radius:12px">
  <div class="form-actions">
    <button type="button" class="btn btn-filled" onclick="copyShareUrl()">Скопировать</button>
    <button type="button" class="btn btn-ghost" onclick="unshareProject()">Отключить</button>
    <button type="button" class="btn btn-text" onclick="document.getElementById('shareModal').classList.remove('show-flex')">Закрыть</button>
  </div>
</div></div>
<div id="listModal" class="modal"><div class="modal-content" style="max-width:520px">
  <span class="close" onclick="closeListModal()">×</span>
  <h3 style="margin-bottom:15px">Список сотрудников</h3>
  <div class="list-filters">
    <button class="btn btn-filled list-filter-btn active" data-sort="default" onclick="setListSort('default',this)">По умолчанию</button>
    <button class="btn btn-ghost list-filter-btn" data-sort="alpha" onclick="setListSort('alpha',this)">По алфавиту</button>
  </div>
  <p class="list-hint">Перетащите строку, чтобы изменить порядок</p>
  <div id="listBody" class="list-body"></div>
  <div class="form-actions">
    <button type="button" class="btn btn-filled" onclick="saveListOrder()">Сохранить порядок</button>
    <button type="button" class="btn btn-text" onclick="closeListModal()">Закрыть</button>
  </div>
</div></div>
<script>
window.PROJECT_ID = <?=(int)$pid?>;
window.PROJECT_NAME = <?=json_encode($project['name'],JSON_UNESCAPED_UNICODE)?>;
window.CSRF_TOKEN = '<?=htmlspecialchars(csrf_token(),ENT_QUOTES)?>';
</script>
<script src="assets/js/editor.js"></script>
</body></html>