<?php
require_once 'config.php';
$token=trim(isset($_GET['token'])?$_GET['token']:'');
$pid=(int)(isset($_GET['project'])?$_GET['project']:0);
$readonly=false;
$isGuest=!isset($_SESSION['user_id'])&&!empty($_SESSION['guest']);
if(!isset($_SESSION['user_id'])&&!$isGuest&&!$token){header('Location: /');exit;}
if($token){
  $st=$pdo->prepare("SELECT * FROM projects WHERE share_token=? AND is_public=1");
  $st->execute([$token]);$project=$st->fetch();
  if(!$project){http_response_code(404);die('Проект не найден');}
  $readonly=true;$pid=(int)$project['id'];
}elseif($isGuest){
  $pid=(int)$_SESSION['guest_project'];
  $st=$pdo->prepare("SELECT * FROM projects WHERE id=?");
  $st->execute([$pid]);$project=$st->fetch();
  if(!$project){header('Location: /');exit;}
}else{
  $uid=(int)$_SESSION['user_id'];
  if(!$pid){
    $st=$pdo->prepare("SELECT id FROM projects WHERE user_id=? ORDER BY updated_at DESC LIMIT 1");
    $st->execute([$uid]);$row=$st->fetch();
    if(!$row){header('Location: /projects.php');exit;}
    $pid=(int)$row['id'];
  }
  $st=$pdo->prepare("SELECT * FROM projects WHERE id=? AND user_id=?");
  $st->execute([$pid,$uid]);$project=$st->fetch();
  if(!$project){http_response_code(404);die('Проект не найден');}
}
$st=$pdo->prepare("SELECT * FROM employees WHERE project_id=? ORDER BY list_order ASC, id ASC");
$st->execute([$pid]);$employees=$st->fetchAll();
$offices=array();
try{
  $st=$pdo->prepare("SELECT id,name,color FROM offices WHERE project_id=? ORDER BY id ASC");
  $st->execute([$pid]);
  foreach($st->fetchAll() as $o){$offices[(int)$o['id']]=$o;}
}catch(Exception $e){}
function parseLinks($str){if(!$str)return array();$out=array();foreach(explode('|',$str) as $item){if($item==='')continue;$parts=explode('@@',$item);$out[]=array('url'=>$parts[0],'label'=>isset($parts[1])?$parts[1]:'');}return $out;}
function buildTree($list,$parent){
  $out=array();
  foreach($list as $e){
    if((int)(isset($e['parent_id'])?$e['parent_id']:0)===$parent){$e['children']=buildTree($list,(int)$e['id']);$out[]=$e;}
  }
  usort($out,function($a,$b){
    $ao=(int)(isset($a['list_order'])?$a['list_order']:0);
    $bo=(int)(isset($b['list_order'])?$b['list_order']:0);
    if($ao!==$bo)return $ao-$bo;
    return ((int)$a['id'])-((int)$b['id']);
  });
  return $out;
}
$visibleCount=0;
foreach($employees as $e){if(isset($e['show_in_list']) && (int)$e['show_in_list']===0) continue;$visibleCount++;}
$tree=buildTree($employees,0);
function renderTree($nodes,$offices){
  if(!$nodes)return '';
  $html='<ul class="tree-ul">';
  foreach($nodes as $n){
    if(isset($n['show_in_list']) && (int)$n['show_in_list']===0) continue;
    $phones=$n['phone']?array_filter(explode('|',$n['phone'])):array();
    $emails=$n['email']?array_filter(explode('|',$n['email'])):array();
    $dept=!empty($n['is_department'])&&$n['department']?'<span class="tr-dept">'.htmlspecialchars($n['department']).'</span>':'';
    $raw=$n['full_name'].' '.$n['position'].' '.(isset($n['department'])?$n['department']:'').' '.(isset($n['phone'])?$n['phone']:'').' '.(isset($n['email'])?$n['email']:'').' '.(isset($n['ext'])?$n['ext']:'');
    $search=htmlspecialchars(mb_strtolower($raw,'UTF-8'),ENT_QUOTES);
    $hasChildren=!empty($n['children']);
    $toggleBtn=$hasChildren?'<button class="tree-toggle" onclick="event.stopPropagation();toggleNode(this)" title="Свернуть/Развернуть">▾</button>':'<span class="tree-toggle-placeholder"></span>';
    $html.='<li class="tree-li'.($hasChildren?' has-children':'').'" data-id="'.(int)$n['id'].'" data-search="'.$search.'">';
    $html.='<div class="tr-head">'.$toggleBtn.'<div class="tr-titles"><div class="tr-name">'.htmlspecialchars($n['full_name']).'</div><div class="tr-pos">'.htmlspecialchars($n['position']).'</div></div></div>';
    if($dept)$html.=$dept;
    $ext=isset($n['ext'])?trim($n['ext']):'';
    if($phones){foreach($phones as $p){$html.='<div class="tr-line tr-copy" onclick="copy(\''.htmlspecialchars($p,ENT_QUOTES).'\',\'phone\')" title="Скопировать"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.37 1.9.72 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.35 1.85.59 2.81.72A2 2 0 0 1 22 16.92z"/></svg>'.htmlspecialchars($p).'</div>';}}
    if($ext!==''){$html.='<div class="tr-line" style="padding-left:18px">вн. '.htmlspecialchars($ext).'</div>';}
    if($emails){foreach($emails as $em){$html.='<div class="tr-line tr-copy" onclick="copy(\''.htmlspecialchars($em,ENT_QUOTES).'\',\'email\')" title="Скопировать"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>'.htmlspecialchars($em).'</div>';}}
    $offHtml='';
    if(!empty($n['office_id'])&&isset($offices[(int)$n['office_id']])){
      $off=$offices[(int)$n['office_id']];
      $cab=!empty($n['cabinet'])?' (каб. '.htmlspecialchars($n['cabinet']).')':'';
      $offHtml='<span class="tr-office"><span class="tr-office-dot" style="background:'.htmlspecialchars($off['color']).'"></span>'.htmlspecialchars($off['name']).$cab.'</span>';
    }elseif(!empty($n['cabinet'])){
      $offHtml='<span class="tr-office"><span class="tr-office-dot" style="background:#888"></span>каб. '.htmlspecialchars($n['cabinet']).'</span>';
    }
    $html.='<div class="tr-bottom">'.$offHtml.'<button class="tr-show" onclick="event.stopPropagation();focusEmployee('.(int)$n['id'].')">Показать</button></div>';
    $html.='</li>';
    if($hasChildren){$html.='<li class="tree-branch">'.renderTree($n['children'],$offices).'</li>';}
  }
  return $html.'</ul>';
}
?><!DOCTYPE html><html lang="ru"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
<title><?=!empty($project['name'])?htmlspecialchars($project['name']).' — Организационная структура':''?>EZYX — Организационная структура</title>
<meta name="description" content="Организационная структура<?=!empty($project['name'])?': '.htmlspecialchars($project['name']):''?>">
<meta property="og:type" content="website">
<meta property="og:title" content="Организационная структура<?=!empty($project['name'])?': '.htmlspecialchars($project['name']):''?>">
<meta property="og:description" content="Организационная структура компании на EZYX">
<meta property="og:image" content="https://ezyx.ru/og-preview.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:url" content="https://ezyx.ru/dashboard?token=<?=htmlspecialchars(isset($token)?$token:'')?>">
<meta property="og:site_name" content="EZYX">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Организационная структура">
<meta name="twitter:description" content="Организационная структура компании на EZYX">
<meta name="twitter:image" content="https://ezyx.ru/og-preview.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600&family=Inter:wght@400;430;450;480;500&display=swap" rel="stylesheet">
<style>
:root{--ink:#17191c;--white:#fff;--mist:#f2f2f3;--fog:#fafafb;--slate:#777b86;--ash:#979799;--smoke:#a3a6af;--peach:#fbe1d1;--sienna:#5d2a1a;--border:#ececec;--gold:#d4a84b;--font:'Inter',ui-sans-serif,system-ui,-apple-system,sans-serif;--font-display:'Golos Text','Inter',ui-sans-serif,system-ui,sans-serif}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:var(--font);background:var(--white);height:100vh;overflow:hidden;display:flex;flex-direction:column;color:var(--ink);-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
.header{display:flex;justify-content:space-between;align-items:center;padding:16px 32px;background:var(--white);border-bottom:1px solid var(--mist);z-index:100;flex-shrink:0;gap:12px;flex-wrap:wrap}
.header h1{font-family:var(--font-display);color:var(--ink);font-size:20px;font-weight:500;letter-spacing:-.2px}
.buttons{display:flex;gap:10px;flex-wrap:wrap}
.btn{padding:10px 20px;border:none;border-radius:9999px;cursor:pointer;font-size:14px;font-weight:400;font-family:var(--font);transition:all .2s;text-decoration:none;display:inline-block;line-height:1.4}
.btn-primary{background:transparent;border:1px solid var(--ink);color:var(--ink)}
.btn-primary:hover{background:var(--ink);color:#fff;filter:none}
.btn-success{background:var(--ink);border:1px solid var(--ink);color:#fff}
.btn-success:hover{opacity:.85;filter:none}
.btn-warning{background:var(--ink);border:1px solid var(--ink);color:#fff}
.btn-warning:hover{opacity:.85;filter:none}
.btn[disabled]{opacity:.5;cursor:wait}
.canvas-wrap{position:relative;flex:1;overflow:hidden}
#chart{position:absolute;top:0;right:0;bottom:0;left:0;cursor:grab !important;overflow:hidden;background:var(--white);background-image:linear-gradient(rgba(100,180,220,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(100,180,220,.05) 1px,transparent 1px);background-size:20px 20px;touch-action:none;-ms-touch-action:none}
#chart{cursor:grab !important}
#chart .card,#chart .card *{cursor:pointer !important}
#chart .photo{cursor:pointer !important}
#chart.panning,#chart.panning *{cursor:grabbing !important}
#inner{position:absolute;top:0;left:0;transform-origin:0 0;cursor:grab}
#inner.smooth{transition:transform .7s cubic-bezier(.22,.61,.36,1)}
#svg-lines{position:absolute;top:0;left:0;width:20000px;height:20000px;pointer-events:none;z-index:1;overflow:visible}
.card{position:absolute;background:var(--white);border:1px solid var(--border);border-radius:16px;padding:14px;min-width:180px;cursor:pointer;z-index:10;overflow:visible;box-sizing:border-box;word-wrap:break-word;box-shadow:0 1px 3px rgba(0,0,0,.04),0 4px 16px rgba(0,0,0,.05);transition:box-shadow .25s ease,transform .2s ease}
.card:hover{box-shadow:0 4px 16px rgba(0,0,0,.1);transform:translateY(-1px)}
.card.flash{animation:cardFlash 3s cubic-bezier(.22,.61,.36,1) forwards}
@keyframes cardFlash{
  0%{box-shadow:0 1px 3px rgba(0,0,0,.04),0 4px 16px rgba(0,0,0,.05),0 0 0 0 rgba(212,168,75,.55),0 0 0 0 rgba(212,168,75,0)}
  45%{box-shadow:0 12px 32px rgba(0,0,0,.16),0 2px 8px rgba(0,0,0,.08),0 0 0 16px rgba(212,168,75,.18),0 0 48px 6px rgba(212,168,75,.55)}
  100%{box-shadow:0 1px 3px rgba(0,0,0,.04),0 4px 16px rgba(0,0,0,.05),0 0 0 0 rgba(212,168,75,0),0 0 0 0 rgba(212,168,75,0)}
}
.card.has-photo{padding-left:48px;min-height:92px}
.card.has-dept{padding-top:32px}
.dept-badge{position:absolute;top:-18px;left:-1px;right:-1px;background:linear-gradient(135deg,#7ed1d1,#5db5b5);color:#fff;font-size:11px;font-weight:600;padding:5px 10px;border-radius:14px 14px 0 0;white-space:pre-wrap;word-break:break-word;max-height:3.6em;overflow:hidden;line-height:1.3em;text-align:left;letter-spacing:.2px}
.card.has-photo .dept-badge{padding-left:42px}
.photo{position:absolute;top:-26px;left:-26px;width:52px;height:52px;border-radius:50%;overflow:hidden;border:3px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.18);z-index:11;background:#fff;cursor:pointer}
.photo img{width:100%;height:100%;object-fit:cover;display:block}
.name{font-size:14px;font-weight:600;margin-bottom:3px;width:100%;word-break:break-word;line-height:1.25;color:var(--ink)}
.position{font-size:12px;color:var(--slate);width:100%;word-break:break-word;line-height:1.3}
.contact-lines{margin-top:4px}
.contact-line{display:flex;align-items:center;gap:5px;font-size:11px;color:var(--slate);margin-top:2px;line-height:1.3;word-break:break-word}
.contact-line svg{opacity:.5;flex-shrink:0}
.office-badge{display:inline-block;color:#fff;font-size:10px;font-weight:400;padding:2px 8px;border-radius:9999px;margin-top:4px;line-height:1.3;white-space:nowrap;max-width:100%;overflow:hidden;text-overflow:ellipsis}
.zoom-float{position:absolute;bottom:24px;right:24px;display:flex;flex-direction:column;gap:8px;z-index:50}
.zoom-float button{width:44px;height:44px;border:1px solid var(--border);border-radius:50%;background:var(--white);box-shadow:0 2px 10px rgba(0,0,0,.08);cursor:pointer;font-size:16px;color:var(--ink);transition:all .2s;display:flex;align-items:center;justify-content:center;font-family:var(--font)}
.zoom-float button:hover{background:var(--ink);color:#fff;border-color:var(--ink)}
.zoom-float button svg{width:18px;height:18px}
.btn-list{position:absolute;top:20px;right:24px;z-index:50;padding:10px 20px;border:1px solid var(--ink);border-radius:9999px;background:var(--ink);color:#fff;cursor:pointer;font-size:14px;font-weight:400;font-family:var(--font);display:flex;align-items:center;gap:8px;transition:all .2s}
.btn-list:hover{opacity:.85}
.btn-birthdays{position:absolute;top:20px;left:24px;z-index:50;padding:10px 20px;border:1px solid var(--ink);border-radius:9999px;background:transparent;color:var(--ink);cursor:pointer;font-size:14px;font-weight:400;font-family:var(--font);display:flex;align-items:center;gap:8px;transition:all .2s}
.btn-birthdays:hover{background:var(--ink);color:#fff}
.modal{display:none;position:fixed;background:var(--white);border:1px solid var(--border);border-radius:24px;padding:28px;min-width:280px;box-shadow:0 0 0 1px rgba(0,0,0,.05),0 12px 48px rgba(0,0,0,.12);z-index:1000;opacity:0;transition:opacity .2s;overflow:visible}
.modal.show{opacity:1}
.modal.show-flex{display:block}
.modal-close{position:absolute;top:12px;right:16px;background:none;border:none;font-size:24px;color:var(--ash);cursor:pointer;line-height:1;padding:2px 6px;border-radius:50%;transition:color .2s}
.modal-close:hover{color:var(--ink)}
.modal-content{text-align:center;padding-top:14px}
.modal-content .photo{width:50px;height:50px;position:relative;margin:6px auto 46px;top:0;left:0;transform:scale(2.4);transform-origin:center center;cursor:pointer}
.modal-content .photo img{width:100%;height:100%;object-fit:cover;display:block}
.dept-info-badge{display:inline-block;background:linear-gradient(135deg,#7ed1d1,#5db5b5);color:#fff;padding:5px 12px;border-radius:9999px;font-size:12px;font-weight:600;margin:0 0 12px 0;white-space:normal;word-break:break-word;max-width:100%;text-align:center;line-height:1.3}
.office-info-badge{display:inline-block;color:#fff;padding:5px 12px;border-radius:9999px;font-size:12px;font-weight:400;margin:0;white-space:normal;word-break:break-word;max-width:100%;text-align:left;line-height:1.3}
.office-info-wrap{display:block;text-align:left;margin-top:4px}
.modal-content h3{font-family:var(--font-display);color:var(--ink);margin-bottom:5px;font-size:18px;font-weight:500;line-height:1.3}
.modal-content .pos{color:var(--slate);margin-bottom:15px;font-size:13px;line-height:1.3}
.contacts{margin-top:15px;padding-top:15px;border-top:1px solid var(--mist);text-align:left}
.contact{margin:2px 0;color:var(--slate);font-size:13px;display:flex;align-items:center;gap:8px;cursor:pointer;padding:6px 10px;border-radius:12px;transition:background .15s}
.contact:hover{background:var(--mist)}
.copy-icon{width:15px;height:15px;opacity:.5;flex-shrink:0}
.contact:hover .copy-icon{opacity:1}
.link-item{display:flex;align-items:center;gap:8px;margin:2px 0;padding:6px 10px;border-radius:12px;font-size:13px;color:var(--ink);text-decoration:none;transition:background .15s}
.link-item:hover{background:var(--mist);text-decoration:none}
.link-item svg{opacity:.6;flex-shrink:0}
.copy-notify{position:fixed;bottom:24px;right:24px;background:var(--ink);color:#fff;padding:12px 20px;border-radius:9999px;font-weight:400;font-size:14px;z-index:10000;animation:slideIn .3s;box-shadow:0 4px 16px rgba(0,0,0,.15)}
@keyframes slideIn{from{transform:translateX(400px);opacity:0}to{transform:translateX(0);opacity:1}}
.photo-modal{display:none;position:fixed;z-index:5000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.85);justify-content:center;align-items:center}
.photo-modal.show-flex{display:flex}
.photo-modal-content{position:relative;max-width:90%;max-height:90%;padding:0}
.photo-modal img{max-width:100%;max-height:90vh;border-radius:16px;display:block}
.close-photo{position:absolute;top:0;right:-50px;color:#fff;font-size:28px;cursor:pointer;background:rgba(0,0,0,.55);width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;z-index:2;transition:background .2s}
.close-photo:hover{background:rgba(0,0,0,.8)}
.side-panel,.bd-panel{position:fixed;top:0;height:100vh;max-height:100vh;background:var(--white);z-index:2000;transition:transform .3s;display:flex;flex-direction:column;overflow:hidden}
.side-panel{right:0;width:50%;max-width:640px;box-shadow:-6px 0 30px rgba(0,0,0,.08);transform:translateX(100%)}
.side-panel.open{transform:translateX(0)}
.bd-panel{left:0;width:420px;max-width:90vw;box-shadow:6px 0 30px rgba(0,0,0,.08);transform:translateX(-100%)}
.bd-panel.open{transform:translateX(0)}
.side-head,.bd-head{padding:24px 28px 16px;border-bottom:1px solid var(--mist);display:flex;justify-content:space-between;align-items:center;flex-shrink:0;gap:12px}
.side-head h2,.bd-head h2{font-family:var(--font-display);font-size:20px;font-weight:500;color:var(--ink);white-space:nowrap;letter-spacing:-.2px}
.side-head-actions,.bd-head-actions{display:flex;gap:8px;align-items:center}
.side-close,.bd-close{background:none;border:none;font-size:28px;color:var(--ash);cursor:pointer;line-height:1;padding:0 4px;transition:color .2s}
.side-close:hover,.bd-close:hover{color:var(--ink)}
.side-search,.bd-search{padding:14px 28px;border-bottom:1px solid var(--mist);flex-shrink:0;position:relative}
.side-search input,.bd-search input{width:100%;padding:12px 40px 12px 16px;border:1px solid var(--border);border-radius:9999px;font-size:14px;font-family:var(--font);outline:none;transition:border-color .2s;background:var(--white);color:var(--ink)}
.side-search input:focus,.bd-search input:focus{border-color:var(--ink)}
.side-search-clear,.bd-search-clear{position:absolute;right:40px;top:50%;transform:translateY(-50%);width:24px;height:24px;border:none;background:var(--mist);border-radius:50%;cursor:pointer;font-size:14px;color:var(--slate);display:none;align-items:center;justify-content:center;line-height:1;padding:0;transition:background .2s}
.side-search-clear:hover,.bd-search-clear:hover{background:var(--ink);color:#fff}
.side-search-clear.show,.bd-search-clear.show{display:flex}
.side-body,.bd-body{flex:1;min-height:0;overflow-y:auto;overflow-x:hidden;padding:16px 28px 24px;-webkit-overflow-scrolling:touch}
.side-body::-webkit-scrollbar,.bd-body::-webkit-scrollbar{width:8px}
.side-body::-webkit-scrollbar-thumb,.bd-body::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px}
.tree-ul{list-style:none;margin:0;padding-left:20px;border-left:2px solid var(--mist)}
.side-body>.tree-ul{padding-left:0;border-left:none}
.tr-head{display:flex;align-items:flex-start;gap:6px}
.tree-toggle{width:22px;height:22px;background:var(--white);border:1px solid var(--border);cursor:pointer;color:var(--slate);font-size:11px;line-height:1;padding:0;border-radius:6px;flex-shrink:0;display:flex;align-items:center;justify-content:center;transition:transform .2s,color .2s,border-color .2s,background .2s;font-family:var(--font)}
.tree-toggle:hover{color:var(--ink);border-color:var(--ink);background:var(--mist)}
.tree-toggle:hover{color:var(--ink);background:var(--mist)}
.tree-toggle-placeholder{width:22px;flex-shrink:0;display:inline-block}
.tr-titles{flex:1;min-width:0}
.tree-branch{list-style:none;overflow:hidden;transition:max-height .35s ease,opacity .25s ease;max-height:100000px;opacity:1}
.tree-branch.collapsed{max-height:0;opacity:0;margin:0;padding:0}
.tree-li.node-collapsed .tree-toggle{transform:rotate(-90deg)}
.tree-li.node-collapsed{background:var(--fog)}
.tree-li{position:relative;margin:8px 0;padding:12px 16px 12px 46px;background:var(--mist);border-radius:16px;transition:background .15s}
.tree-li>.tr-head{margin-left:-34px}
.tree-li:hover{background:var(--fog);box-shadow:0 0 0 1px var(--border)}
.tree-ul .tree-li:before{content:'';position:absolute;left:-20px;top:22px;width:16px;height:2px;background:var(--mist)}
.side-body>.tree-ul>.tree-li:before{display:none}
.tr-name{font-size:14px;font-weight:600;color:var(--ink)}
.tr-pos{font-size:12px;color:var(--slate);margin-top:2px}
.tr-dept{display:inline-block;font-size:11px;background:var(--peach);color:var(--sienna);padding:3px 10px;border-radius:9999px;margin-top:6px;font-weight:500}
.tr-line{display:flex;align-items:center;gap:6px;font-size:12px;color:var(--slate);margin-top:4px}
.tr-line svg{opacity:.45;flex-shrink:0}
.tr-copy{cursor:pointer;border-radius:8px;padding:2px 6px;margin-left:-6px;transition:background .15s}
.tr-copy:hover{background:var(--white)}
.tr-office{display:inline-flex;align-items:center;gap:6px;font-size:11px;color:var(--slate);margin-top:6px;padding:3px 10px;border-radius:9999px;background:var(--white)}
.tr-office-dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.tr-bottom{display:flex;justify-content:space-between;align-items:center;gap:8px;margin-top:6px}
.tr-show{opacity:0;pointer-events:none;background:var(--ink);color:#fff;border:none;border-radius:9999px;padding:6px 14px;font-size:12px;font-family:var(--font);cursor:pointer;transition:opacity .2s,transform .2s;white-space:nowrap}
.tree-li:hover .tr-show{opacity:1;pointer-events:auto}
.tree-li.hidden{display:none}
.tree-empty{text-align:center;color:var(--slate);padding:40px 20px;font-size:14px}
.side-overlay,.bd-overlay{position:fixed;top:0;right:0;bottom:0;left:0;background:rgba(0,0,0,.3);z-index:1999;display:none;opacity:0;transition:opacity .3s;backdrop-filter:blur(4px)}
.side-overlay.open,.bd-overlay.open{display:block;opacity:1}
.bd-month{margin-bottom:20px}
.bd-month-title{font-size:14px;font-weight:700;color:var(--ink);text-transform:uppercase;letter-spacing:.5px;padding:8px 0;border-bottom:2px solid var(--mist);margin-bottom:8px;position:sticky;top:0;background:var(--white);z-index:2}
.bd-item{display:flex;justify-content:space-between;align-items:baseline;padding:10px 14px;border-radius:12px;font-size:13px;color:var(--ink);transition:background .15s}
.bd-item:hover{background:var(--mist)}
.bd-item .bd-name{font-weight:500;flex:1;word-break:break-word;padding-right:10px}
.bd-item .bd-date{color:var(--slate);font-variant-numeric:tabular-nums;white-space:nowrap}
.bd-item.today{background:var(--peach)}
.bd-item.today .bd-date{color:var(--sienna);font-weight:600}
.bd-loading{text-align:center;color:var(--slate);padding:40px 20px}
.bd-empty{text-align:center;color:var(--slate);padding:40px 20px;font-size:14px}
@media (max-width:768px){
  .side-panel{width:100%;max-width:none}
  .bd-panel{width:100%;max-width:none}
  .header{padding:12px 16px;flex-wrap:wrap;gap:8px}
  .header h1{font-size:16px}
  .buttons{flex-wrap:wrap}
  .btn{padding:8px 14px;font-size:13px}
  .zoom-float{bottom:calc(20px + env(safe-area-inset-bottom,0px) + 90px);right:14px}
  .zoom-float button{width:40px;height:40px}
  .btn-list,.btn-birthdays{top:14px;padding:8px 14px;font-size:13px}
  .btn-list{right:14px}.btn-birthdays{left:14px}
  .modal{min-width:auto!important;max-width:calc(100vw - 20px);padding:20px}
  .modal-content{padding-top:10px}
  .modal-content .photo{width:44px;height:44px;margin:4px auto 36px}
  .contacts{margin-top:10px;padding-top:10px}
  .tr-show{opacity:1;pointer-events:auto}
  .side-head{padding:14px 16px 12px;gap:8px;flex-wrap:nowrap}
.side-head h2{font-size:16px;min-width:0;overflow:hidden;text-overflow:ellipsis;flex:1}
.side-head-actions{gap:6px;flex-shrink:0}
.side-head-actions .btn{padding:7px 12px !important;font-size:12px !important;border-radius:9999px}
.side-head-actions .side-close,.bd-head-actions .bd-close{font-size:22px;padding:0;width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:var(--white);border:1px solid var(--ink);border-radius:50%;color:var(--ink);line-height:1;transition:background .2s,color .2s}
.side-head-actions .side-close:hover,.bd-head-actions .bd-close:hover{background:var(--ink);color:#fff}
}
.manager-info{margin:4px 0 12px;font-size:13px;color:var(--slate);text-align:center;line-height:1.3}
.manager-link{color:#0a7a7a;text-decoration:none;font-weight:400;transition:color .15s;cursor:pointer}
.manager-link:hover{color:#0d9a9a;border-bottom-color:#0d9a9a}
</style></head><body>
<div class="header">
<h1>Организационная структура<?=!empty($project['name'])?': '.htmlspecialchars($project['name']):''?></h1>
<div class="buttons">
<?php if(!$readonly): ?>
<?php if(!$isGuest): ?>
<a href="/profile.php" class="btn btn-primary">Личный кабинет</a>
<a href="/projects" class="btn btn-primary">Мои проекты</a><?php endif; ?>
<button class="btn btn-primary" onclick="shareProject()">Поделиться</button>
<a href="/editor?project=<?=(int)$pid?>" class="btn btn-warning">Редактор</a>
<?php endif; ?>
<button id="btnExportPdf" onclick="exportPdf()" class="btn btn-success">Экспорт PDF</button>
</div>
</div>
<div class="canvas-wrap">
<div id="chart">
<div id="inner">
<svg id="svg-lines"></svg>
<?php foreach($employees as $e): $pc=$e['show_photo']?'has-photo':'';$dc=!empty($e['is_department'])?'has-dept':'';
$cx=(int)(isset($e['crop_x'])?$e['crop_x']:0);$cy=(int)(isset($e['crop_y'])?$e['crop_y']:0);$cz=(float)(isset($e['crop_zoom'])?$e['crop_zoom']:1);
$ph='';
if($e['show_photo']&&$e['photo']&&file_exists($e['photo'])){$ph='<div class="photo" onclick="event.stopPropagation();showFullPhoto(\''.$e['photo'].'\')"><img src="'.$e['photo'].'" style="transform:scale('.$cz.') translate('.$cx.'px,'.$cy.'px)"></div>';}
$dept=!empty($e['is_department'])&&$e['department']?'<div class="dept-badge">'.htmlspecialchars($e['department'],ENT_QUOTES).'</div>':'';
$phones=isset($e['phone'])?array_filter(explode('|',$e['phone'])):array();
$emails=isset($e['email'])?array_filter(explode('|',$e['email'])):array();
$ext=isset($e['ext'])?trim($e['ext']):'';
$showContacts=(!isset($e['show_contacts'])||$e['show_contacts']==1);
$contactsHtml='';
if($showContacts&&($phones||$emails||$ext!=='')){
  $contactsHtml='<div class="contact-lines">';
  foreach($phones as $p){$contactsHtml.='<div class="contact-line"><svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.37 1.9.72 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.35 1.85.59 2.81.72A2 2 0 0 1 22 16.92z"/></svg>'.htmlspecialchars($p).'</div>';}
  if($ext!==''){$contactsHtml.='<div class="contact-line">вн. '.htmlspecialchars($ext).'</div>';}
  foreach($emails as $m){$contactsHtml.='<div class="contact-line"><svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>'.htmlspecialchars($m).'</div>';}
  $contactsHtml.='</div>';
}
$showOffice=(isset($e['show_office'])&&$e['show_office']==1);
$officeHtml='';
if($showOffice){
  if(!empty($e['office_id'])&&isset($offices[(int)$e['office_id']])){
    $off=$offices[(int)$e['office_id']];
    $cab=!empty($e['cabinet'])?' (каб. '.htmlspecialchars($e['cabinet']).')':'';
    $officeHtml='<div class="office-badge" style="background:'.htmlspecialchars($off['color']).'">'.htmlspecialchars($off['name']).$cab.'</div>';
  }elseif(!empty($e['cabinet'])){
    $officeHtml='<div class="office-badge" style="background:#888">каб. '.htmlspecialchars($e['cabinet']).'</div>';
  }
}
$parentName='';
$parentId=0;
if(!empty($e['parent_id'])){
  foreach($employees as $_pe){
    if((int)$_pe['id']===(int)$e['parent_id']){$parentName=$_pe['full_name'];$parentId=(int)$_pe['id'];break;}
  }
}
echo '<div class="card '.$pc.' '.$dc.'" data-id="'.$e['id'].'"
data-name="'.htmlspecialchars($e['full_name'],ENT_QUOTES).'"
data-show-manager="'.((isset($e['show_manager'])&&$e['show_manager']==1&&$parentId)?'1':'0').'"
data-parent-id="'.$parentId.'"
data-parent-name="'.htmlspecialchars($parentName,ENT_QUOTES).'"
data-position="'.htmlspecialchars($e['position'],ENT_QUOTES).'"
data-phone="'.htmlspecialchars(isset($e['phone'])?$e['phone']:'',ENT_QUOTES).'"
data-ext="'.htmlspecialchars(isset($e['ext'])?$e['ext']:'',ENT_QUOTES).'"
data-email="'.htmlspecialchars(isset($e['email'])?$e['email']:'',ENT_QUOTES).'"
data-office-name="'.(!empty($e['office_id'])&&isset($offices[(int)$e['office_id']])?htmlspecialchars($offices[(int)$e['office_id']]['name'],ENT_QUOTES):'').'"
data-office-color="'.(!empty($e['office_id'])&&isset($offices[(int)$e['office_id']])?htmlspecialchars($offices[(int)$e['office_id']]['color'],ENT_QUOTES):'').'"
data-cabinet="'.htmlspecialchars(isset($e['cabinet'])?$e['cabinet']:'',ENT_QUOTES).'"
data-links="'.htmlspecialchars(isset($e['links'])?$e['links']:'',ENT_QUOTES).'"
data-photo="'.$e['photo'].'"
data-show="'.($e['show_photo']?'1':'0').'"
data-dept="'.htmlspecialchars(isset($e['department'])?$e['department']:'',ENT_QUOTES).'"
data-is-dept="'.($e['is_department']?'1':'0').'"
data-crop-x="'.$cx.'" data-crop-y="'.$cy.'" data-crop-zoom="'.$cz.'"
style="left:'.$e['position_x'].'px;top:'.$e['position_y'].'px;width:'.(int)(isset($e['width'])&&$e['width']?$e['width']:220).'px;height:'.(int)(isset($e['height'])&&$e['height']?$e['height']:100).'px;z-index:'.(int)(isset($e['z_index'])&&$e['z_index']?$e['z_index']:10).'">'.$dept.$ph.'<div class="info"><div class="name">'.htmlspecialchars($e['full_name']).'</div><div class="position">'.htmlspecialchars($e['position']).'</div>'.$contactsHtml.$officeHtml.'</div></div>';
endforeach; ?>
</div>
</div>
<button class="btn-list" onclick="toggleSide()">
<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
Список
</button>
<button class="btn-birthdays" onclick="toggleBirthdays()" title="Дни рождения">
  <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M20 21v-8a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8"/>
    <path d="M4 16s.5-1 2-1 2.5 2 4 2 2.5-2 4-2 2.5 2 4 2 2-1 2-1"/>
    <path d="M2 21h20"/>
    <path d="M7 8v3"/>
    <path d="M12 8v3"/>
    <path d="M17 8v3"/>
    <path d="M7 4h.01"/>
    <path d="M12 4h.01"/>
    <path d="M17 4h.01"/>
  </svg>
  ДР
</button>
<div class="zoom-float">
<button onclick="zoomIn()" title="Приблизить">+</button>
<button onclick="zoomOut()" title="Отдалить">−</button>
<button onclick="fitToContent()" title="Показать всё"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 3H5a2 2 0 0 0-2 2v3M21 8V5a2 2 0 0 0-2-2h-3M3 16v3a2 2 0 0 0 2 2h3M16 21h3a2 2 0 0 0 2-2v-3"/></svg></button>
<button onclick="resetView()" title="Сбросить"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 3-6.7"/><polyline points="3 4 3 10 9 10"/></svg></button>
</div>
</div>
<div id="modal" class="modal"></div>
<div id="shareModal" class="modal" style="min-width:420px">
<button class="modal-close" onclick="event.stopPropagation();document.getElementById('shareModal').classList.remove('show-flex','show')">×</button>
<div class="modal-content">
<h3 style="margin-bottom:10px;font-family:var(--font-display);font-size:22px;font-weight:500;color:var(--ink)">Ссылка для просмотра</h3>
<p style="color:var(--slate);font-size:14px;margin-bottom:16px">Любой, у кого есть эта ссылка, сможет посмотреть структуру (только чтение).</p>
<input id="shareUrl" readonly style="width:100%;padding:12px 16px;border:1px solid var(--border);border-radius:9999px;font-size:13px;text-align:center;margin-bottom:14px;font-family:var(--font);outline:none;color:var(--ink);background:var(--white)">
<div style="display:flex;gap:8px;justify-content:center">
<button class="btn btn-success" onclick="copyShareUrl()">Скопировать</button>
<button class="btn btn-primary" onclick="document.getElementById('shareModal').classList.remove('show-flex','show')">Закрыть</button>
</div>
</div>
</div>
<div id="photoModal" class="photo-modal"><div class="photo-modal-content"><span class="close-photo" onclick="event.stopPropagation();document.getElementById('photoModal').classList.remove('show-flex')">×</span><img id="fullPhoto" src=""></div></div>
<div class="side-overlay" id="sideOverlay" onclick="toggleSide()"></div>
<div class="side-panel" id="sidePanel">
<div class="side-head">
<h2>Сотрудники (<?=$visibleCount?>)</h2>
<div class="side-head-actions">
<button id="treeToggleAll" class="btn btn-success" onclick="toggleAllNodes()" style="padding:8px 16px;font-size:13px;border-radius:9999px;white-space:nowrap">Свернуть</button>
<button class="btn btn-success" onclick="window.location.href='/export_excel?project=<?=(int)$pid?>'" style="padding:8px 16px;font-size:13px;border-radius:9999px;white-space:nowrap">Экспорт в Excel</button>
<button class="side-close" onclick="toggleSide()">×</button>
</div>
</div>
<div class="side-search">
<input type="text" id="treeSearch" placeholder="Поиск по ФИО, должности, телефону..." oninput="filterTree(this.value)">
<button class="side-search-clear" id="searchClear" onclick="clearSearch()" title="Очистить">×</button>
</div>
<div class="side-body" id="sideBody">
<?=renderTree($tree,$offices)?>
<?php if(empty($tree)): ?><p class="tree-empty">Пока нет сотрудников</p><?php endif; ?>
</div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
let current=null;
const CSRF_TOKEN = '<?=htmlspecialchars(csrf_token(),ENT_QUOTES)?>';
function apiFetch(url, opts){
  opts = opts || {};
  opts.headers = Object.assign({'X-CSRF-Token': CSRF_TOKEN}, opts.headers || {});
  return fetch(url, opts);
}
document.addEventListener('click',function(e){
  if(window.innerWidth<=768){
    // На мобильном клики по карточкам обрабатывает touchend
    if(!e.target.closest('.modal')&&!e.target.closest('.photo-modal')&&!e.target.closest('.side-panel')&&!e.target.closest('.btn-list')&&!e.target.closest('.card')){hide();}
    return;
  }
  const c=e.target.closest('.card');
  if(c&&!e.target.closest('.photo')){e.stopPropagation();show(c);}
  else if(!e.target.closest('.modal')&&!e.target.closest('.photo-modal')&&!e.target.closest('.side-panel')&&!e.target.closest('.btn-list')){hide();}
});
function parseLinks(str){if(!str)return [];return String(str).split('|').filter(Boolean).map(function(item){const parts=item.split('@@');return {url:parts[0]||'',label:parts[1]||''};});}
function esc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function show(card){
  if(current&&current!==card)hide();
  if(current===card){hide();return;}
  const name=card.dataset.name,pos=card.dataset.position,phone=card.dataset.phone,email=card.dataset.email;
  const photo=card.dataset.photo,showPh=card.dataset.show==='1',isDept=card.dataset.isDept==='1',dept=card.dataset.dept;
  const offName=card.dataset.officeName||'',offColor=card.dataset.officeColor||'',cabinet=card.dataset.cabinet||'';
  const links=parseLinks(card.dataset.links||'');
  const showManager=card.dataset.showManager==='1';
const parentId=parseInt(card.dataset.parentId)||0;
const parentName=card.dataset.parentName||'';
  const cX=parseFloat(card.dataset.cropX)||0,cY=parseFloat(card.dataset.cropY)||0,cZ=parseFloat(card.dataset.cropZoom)||1;
  let phHtml=showPh&&photo?'<div class="photo" onclick="event.stopPropagation();showFullPhoto(\'/'+photo+'\')"><img src="'+photo+'" style="transform:scale('+cZ+') translate('+cX+'px,'+cY+'px)"></div>':'';
  let deptHtml=isDept&&dept?'<div class="dept-info-badge">'+dept+'</div>':'';
  let officeHtml='';
  if(offName||cabinet){
    const cab=cabinet?' (каб. '+cabinet+')':'';
    if(offName){officeHtml='<div class="office-info-badge" style="background:'+(offColor||'#888')+'">'+offName+cab+'</div>';}
    else{officeHtml='<div class="office-info-badge" style="background:#888">каб. '+cabinet+'</div>';}
  }
  let managerHtml='';
if(showManager&&parentName){
  managerHtml='<div class="manager-info">Руководитель: <a href="#" class="manager-link" onclick="event.preventDefault();event.stopPropagation();hide();showCardById('+parentId+')">'+esc(parentName)+'</a></div>';
}
  let contacts='';
  const extVal=card.dataset.ext||'';
  if(phone||email||extVal){
    const phones=phone.split('|').filter(function(p){return p;});
    const emails=email.split('|').filter(function(e){return e;});
    contacts='<div class="contacts">'
      +phones.map(function(p){return '<div class="contact" onclick="copy(\''+p+'\',\'phone\')"><svg class="copy-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><span>'+p+'</span></div>';}).join('')
      +(extVal?'<div class="contact" style="cursor:default;padding-left:28px">вн. '+extVal+'</div>':'')
      +emails.map(function(e){return '<div class="contact" onclick="copy(\''+e+'\',\'email\')"><svg class="copy-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><span>'+e+'</span></div>';}).join('')
      +'</div>';
  }
  let linksHtml='';
  if(links.length){
    linksHtml='<div class="contacts">';
    links.forEach(function(l){const text=l.label||l.url;linksHtml+='<a class="link-item" href="'+esc(l.url)+'" target="_blank" rel="noopener"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg><span>'+esc(text)+'</span></a>';});
    linksHtml+='</div>';
  }
  const m=document.getElementById('modal');
  clearTimeout(m._t);
  m.innerHTML='<button class="modal-close" onclick="event.stopPropagation();hide()">×</button><div class="modal-content">'+phHtml+deptHtml+'<h3>'+name+'</h3><div class="pos">'+pos+'</div>'+managerHtml+contacts+(officeHtml?'<div class="office-info-wrap">'+officeHtml+'</div>':'')+linksHtml+'</div>';
  m.classList.add('show');
  m.style.cssText='left:0;top:0;display:block;visibility:hidden';
  const r=card.getBoundingClientRect();
  const mw=m.offsetWidth||320,mh=m.offsetHeight||400,pad=10;
  let lx=r.right+12,ly=r.top;
  if(lx+mw>window.innerWidth-pad)lx=r.left-mw-12;
  if(lx<pad)lx=pad;
  if(lx+mw>window.innerWidth-pad)lx=window.innerWidth-mw-pad;
  if(ly+mh>window.innerHeight-pad)ly=window.innerHeight-mh-pad;
  if(ly<pad)ly=pad;
  m.style.cssText='left:'+lx+'px;top:'+ly+'px;display:block';
  m.style.visibility='visible';
  current=card;
}
function showCardById(id){
  const card=document.querySelector('.card[data-id="'+id+'"]');
  if(card)show(card);
}
function hide(){const m=document.getElementById('modal');m.classList.remove('show');current=null;clearTimeout(m._t);m._t=setTimeout(function(){if(!m.classList.contains('show')){m.style.display='none';m.innerHTML='';}},200);}
function copy(text,type){
  var val=String(text);
  if(type==='phone'){val=val.replace(/[^\d+]/g,'');}
  navigator.clipboard.writeText(val).then(function(){
    var n=document.createElement('div');n.className='copy-notify';n.textContent='Скопировано';document.body.appendChild(n);
    setTimeout(function(){n.remove();},2000);
  });
}
function showFullPhoto(src){const img=document.getElementById('fullPhoto');img.src=src;img.style.transform='none';document.getElementById('photoModal').classList.add('show-flex');}
async function shareProject(){
  const r=await apiFetch('api.php?action=project_share',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id:<?=(int)$pid?>})});
  const d=await r.json();
  if(!d.success){alert('Ошибка: '+(d.error||'неизвестная'));return}
  document.getElementById('shareUrl').value=d.url;
  const m=document.getElementById('shareModal');
  const r2=document.querySelector('.header .buttons').getBoundingClientRect();
  m.style.cssText='left:'+(window.innerWidth/2-210)+'px;top:'+Math.max(80,r2.bottom+12)+'px;display:block';
  m.classList.add('show-flex');m.classList.add('show');
}
function copyShareUrl(){const inp=document.getElementById('shareUrl');inp.select();inp.setSelectionRange(0,99999);navigator.clipboard.writeText(inp.value).then(function(){const n=document.createElement('div');n.className='copy-notify';n.textContent='Ссылка скопирована';document.body.appendChild(n);setTimeout(function(){n.remove();},2000);});}
function toggleSide(){document.getElementById('sidePanel').classList.toggle('open');document.getElementById('sideOverlay').classList.toggle('open');if(document.getElementById('sidePanel').classList.contains('open')){setTimeout(function(){document.getElementById('treeSearch').focus();},300);}}
function closeSide(){document.getElementById('sidePanel').classList.remove('open');document.getElementById('sideOverlay').classList.remove('open');}
function clearSearch(){document.getElementById('treeSearch').value='';document.getElementById('searchClear').classList.remove('show');filterTree('');}
function toggleNode(btn){
  const li=btn.closest('.tree-li');
  if(!li)return;
  const branch=li.nextElementSibling;
  if(!branch||!branch.classList.contains('tree-branch'))return;
  const collapsed=branch.classList.toggle('collapsed');
  li.classList.toggle('node-collapsed',collapsed);
  btn.textContent=collapsed?'▸':'▾';
}
function toggleAllNodes(){
  const anyOpen=document.querySelector('#sideBody .tree-branch:not(.collapsed)');
  const btn=document.getElementById('treeToggleAll');
  if(anyOpen){
    // свернуть все
    document.querySelectorAll('#sideBody .tree-branch').forEach(b=>b.classList.add('collapsed'));
    document.querySelectorAll('#sideBody .tree-li.has-children').forEach(li=>li.classList.add('node-collapsed'));
    document.querySelectorAll('#sideBody .tree-toggle').forEach(t=>t.textContent='▸');
    if(btn)btn.textContent='Развернуть';
  }else{
    // развернуть все
    document.querySelectorAll('#sideBody .tree-branch').forEach(b=>b.classList.remove('collapsed'));
    document.querySelectorAll('#sideBody .tree-li.has-children').forEach(li=>li.classList.remove('node-collapsed'));
    document.querySelectorAll('#sideBody .tree-toggle').forEach(t=>t.textContent='▾');
    if(btn)btn.textContent='Свернуть';
  }
}
function filterTree(q){
  q=(q||'').toLowerCase().trim();
  const clearBtn=document.getElementById('searchClear');
  if(q){clearBtn.classList.add('show');}else{clearBtn.classList.remove('show');}
  const items=document.querySelectorAll('#sideBody .tree-li');
  const branches=document.querySelectorAll('#sideBody .tree-branch');

  if(!q){
    items.forEach(function(i){i.classList.remove('hidden');});
    // восстановим свёрнутое состояние (если было) — просто ничего не трогаем
    return;
  }

  // 1. Скрыть всё, развернуть все ветки (чтобы совпадения были видны)
  items.forEach(function(i){i.classList.add('hidden');});
  branches.forEach(function(b){b.classList.remove('collapsed');});
  document.querySelectorAll('#sideBody .tree-li.has-children').forEach(function(li){li.classList.remove('node-collapsed');});
  document.querySelectorAll('#sideBody .tree-toggle').forEach(function(t){t.textContent='▾';});
  if(document.getElementById('treeToggleAll'))document.getElementById('treeToggleAll').textContent='Свернуть';

  // 2. Показать только совпадения и их родителей
  items.forEach(function(i){
    if((i.dataset.search||'').indexOf(q)!==-1){
      i.classList.remove('hidden');
      let p=i.parentElement;
      while(p&&p!==document.getElementById('sideBody')){
        if(p.classList.contains('tree-li'))p.classList.remove('hidden');
        p=p.parentElement;
      }
    }
  });
}
function drawLine(pid,cid,lineOffsets){
  const p=document.querySelector('[data-id="'+pid+'"]'),c=document.querySelector('[data-id="'+cid+'"]');
  if(!p||!c||!p.offsetWidth||!c.offsetWidth)return;
  const x1=parseInt(p.style.left)+(p.offsetWidth)/2,y1=parseInt(p.style.top)+p.offsetHeight;
  const x2=parseInt(c.style.left)+(c.offsetWidth)/2,y2=parseInt(c.style.top);
  const natural=(y1+y2)/2;
  const midY=natural+((lineOffsets&&typeof lineOffsets.midY==='number')?lineOffsets.midY:0);
  const upperX=(lineOffsets&&typeof lineOffsets.upperX==='number')?lineOffsets.upperX:0;
  const lowerX=(lineOffsets&&typeof lineOffsets.lowerX==='number')?lineOffsets.lowerX:0;
  const ux=x1+upperX, lx=x2+lowerX;
  const OFFSET=12;
  let d;
if(upperX===0&&lowerX===0){
  d='M '+x1+' '+y1+' L '+x1+' '+midY+' L '+x2+' '+midY+' L '+x2+' '+y2;
}else{
  let segs=[];
  segs.push('M '+x1+' '+y1);
  segs.push('L '+x1+' '+(y1+OFFSET));
  segs.push('L '+ux+' '+(y1+OFFSET));
  segs.push('L '+ux+' '+midY);
  segs.push('L '+lx+' '+midY);
  segs.push('L '+lx+' '+(y2-OFFSET));
  segs.push('L '+x2+' '+(y2-OFFSET));
  segs.push('L '+x2+' '+y2);
  d=segs.join(' ');
}
  const svg=document.getElementById('svg-lines'),path=document.createElementNS('http://www.w3.org/2000/svg','path');
  path.setAttribute('d',d);
  path.setAttribute('stroke','rgba(100,180,220,.7)');path.setAttribute('stroke-width','2');path.setAttribute('fill','none');
  svg.appendChild(path);
}
function redrawLines(){
  const svg=document.getElementById('svg-lines');
  svg.innerHTML='';
  <?php foreach($employees as $e):
    if(!$e['parent_id'])continue;
    $lo=isset($e['line_offsets'])?$e['line_offsets']:'';
    if($lo){$loArr=json_decode($lo,true);$loJson=is_array($loArr)?json_encode($loArr):'null';}else{$loJson='null';}
  ?>drawLine(<?php echo (int)$e['parent_id']; ?>,<?php echo (int)$e['id']; ?>,<?php echo $loJson; ?>);<?php endforeach; ?>
}
let _panX=0,_panY=0,_scale=1,_isPanning=false,_panStart={x:0,y:0};
try{var _sv=JSON.parse(localStorage.getItem('dash_'+<?=(int)$pid?>)||'{}');if(typeof _sv.x==='number'){_panX=_sv.x;_panY=_sv.y;_scale=_sv.s||1;}}catch(e){}
const _inner=document.getElementById('inner'),_chart=document.getElementById('chart');
let _rafPending=false;
function _applyView(){
  if(_rafPending)return;
  _rafPending=true;
  requestAnimationFrame(function(){
    _rafPending=false;
    _inner.style.transform='translate('+_panX+'px,'+_panY+'px) scale('+_scale+')';
    try{localStorage.setItem('dash_'+<?=(int)$pid?>,JSON.stringify({x:_panX,y:_panY,s:_scale}));}catch(e){}
    redrawLines();
  });
}
_applyView();
function fitToContent(){
  const cards=_inner.querySelectorAll('.card');
  if(!cards.length){_panX=40;_panY=40;_scale=1;_applyView();return;}
  let minX=1e9,minY=1e9,maxX=-1e9,maxY=-1e9;
  cards.forEach(function(c){
    const x=parseFloat(c.style.left)||0,y=parseFloat(c.style.top)||0,w=c.offsetWidth,h=c.offsetHeight;
    const extraLeft=30,extraTop=30,extraRight=10,extraBottom=10;
    const cx1=x-extraLeft,cy1=y-extraTop,cx2=x+w+extraRight,cy2=y+h+extraBottom;
    if(cx1<minX)minX=cx1;if(cy1<minY)minY=cy1;if(cx2>maxX)maxX=cx2;if(cy2>maxY)maxY=cy2;
  });
  const pad=70,cw=maxX-minX+pad*2,ch=maxY-minY+pad*2;
  const availW=_chart.clientWidth-40,availH=_chart.clientHeight-40;
  let s=Math.min(availW/cw,availH/ch,1);if(s<0.2)s=0.2;
  _scale=s;_panX=pad-minX*s;_panY=pad-minY*s;_applyView();
}
function resetView(){_panX=40;_panY=40;_scale=1;_applyView();}
function zoomIn(){_scale=Math.min(3,_scale*1.2);_applyView();}
function zoomOut(){_scale=Math.max(0.2,_scale/1.2);_applyView();}
function focusEmployee(id){
  closeSide();
  setTimeout(function(){
    const c=document.querySelector('.card[data-id="'+id+'"]');
    if(!c)return;
    const x=parseFloat(c.style.left)||0,y=parseFloat(c.style.top)||0,w=c.offsetWidth,h=c.offsetHeight;
    const cx=x+w/2,cy=y+h/2;
    _scale=(window.innerWidth<=768)?1:1.4;
    _panX=_chart.clientWidth/2-cx*_scale;
    _panY=_chart.clientHeight/2-cy*_scale;
    _inner.classList.add('smooth');
    _inner.style.transform='translate('+_panX+'px,'+_panY+'px) scale('+_scale+')';
    try{localStorage.setItem('dash_'+<?=(int)$pid?>,JSON.stringify({x:_panX,y:_panY,s:_scale}));}catch(e){}
    setTimeout(function(){redrawLines();},720);
    setTimeout(function(){_inner.classList.remove('smooth');},760);
    c.classList.remove('flash');void c.offsetWidth;c.classList.add('flash');
    setTimeout(function(){c.classList.remove('flash');},3200);
  },320);
}
_chart.addEventListener('wheel',function(e){
  e.preventDefault();
  const factor=e.deltaY<0?1.1:1/1.1,r=_chart.getBoundingClientRect();
  const mx=e.clientX-r.left,my=e.clientY-r.top;
  const ns=Math.max(0.2,Math.min(3,_scale*factor));
  _panX=mx-(mx-_panX)*(ns/_scale);_panY=my-(my-_panY)*(ns/_scale);_scale=ns;_applyView();
},{passive:false});
function _canStartPan(t){
  if(!t)return true;
  if(t.closest('.btn-list'))return false;
  if(t.closest('.zoom-float'))return false;
  if(t.closest('.btn-birthdays'))return false;
  if(t.closest('.modal'))return false;
  if(t.closest('.photo-modal'))return false;
  if(t.closest('.side-panel'))return false;
  if(t.closest('.bd-panel'))return false;
  if(t.closest('.card'))return false;
  return true;
}
function _startPan(cx,cy){_isPanning=true;_panStart={x:cx-_panX,y:cy-_panY};_chart.classList.add('panning');}
function _movePan(cx,cy){if(!_isPanning)return;_panX=cx-_panStart.x;_panY=cy-_panStart.y;_applyView();}
function _endPan(){if(_isPanning){_isPanning=false;_chart.classList.remove('panning');}}
_chart.addEventListener('mousedown',function(e){
  if(!_canStartPan(e.target))return;
  _startPan(e.clientX,e.clientY);e.preventDefault();
});
window.addEventListener('mousemove',function(e){_movePan(e.clientX,e.clientY);});
window.addEventListener('mouseup',_endPan);
let _touchMode=null,_touchStartDist=0,_touchStartScale=1,_touchStartMid={x:0,y:0};
let _touchOnCard=null;
_chart.addEventListener('touchstart',function(e){
  if(window.innerWidth>768)return;
  const m=document.getElementById('modal');
  if(!m||!m.classList.contains('show'))return;
  if(!e.target.closest('.modal') && !e.target.closest('.card') && !e.target.closest('.photo-modal')){
    hide();
  }
},{passive:true});
_chart.addEventListener('touchstart',function(e){
   if(e.touches.length===2){
    _touchMode='pinch';
    _touchOnCard=null;
    const t1=e.touches[0],t2=e.touches[1];
    _touchStartDist=Math.hypot(t2.clientX-t1.clientX,t2.clientY-t1.clientY);
    _touchStartScale=_scale;
    _touchStartMid={x:(t1.clientX+t2.clientX)/2,y:(t1.clientY+t2.clientY)/2};
    _isPanning=false;
    _chart.classList.remove('panning');
    e.preventDefault();
    return;
  }
  const isMobile=(window.innerWidth<=768);
  const onCard=e.target.closest && e.target.closest('.card');
    if(onCard && isMobile && e.touches.length===1){
    _touchOnCard={
      id: onCard.dataset.id,
      startX: e.touches[0].clientX,
      startY: e.touches[0].clientY,
      moved: false,
      card: onCard
    };
    _isPanning=true;
    _panStart={x:e.touches[0].clientX-_panX,y:e.touches[0].clientY-_panY};
    _chart.classList.add('panning');
    e.preventDefault();
    return;
  }
  if(!_canStartPan(e.target))return;
  if(e.touches.length===1){_touchMode='pan';_startPan(e.touches[0].clientX,e.touches[0].clientY);}
  else if(e.touches.length===2){_touchMode='pinch';const t1=e.touches[0],t2=e.touches[1];_touchStartDist=Math.hypot(t2.clientX-t1.clientX,t2.clientY-t1.clientY);_touchStartScale=_scale;_touchStartMid={x:(t1.clientX+t2.clientX)/2,y:(t1.clientY+t2.clientY)/2};}
  e.preventDefault();
},{passive:false});
_chart.addEventListener('touchmove',function(e){
  if(_touchOnCard && e.touches.length===1){
    const dx=Math.abs(e.touches[0].clientX-_touchOnCard.startX);
    const dy=Math.abs(e.touches[0].clientY-_touchOnCard.startY);
    if(dx>10||dy>10)_touchOnCard.moved=true;
    if(_touchOnCard.moved){
      _movePan(e.touches[0].clientX,e.touches[0].clientY);
    }
    e.preventDefault();
    return;
  }
  if(_touchMode==='pan'&&e.touches.length===1){
    _movePan(e.touches[0].clientX,e.touches[0].clientY);
  }else if(_touchMode==='pinch'&&e.touches.length===2){
    const t1=e.touches[0],t2=e.touches[1];
    const dist=Math.hypot(t2.clientX-t1.clientX,t2.clientY-t1.clientY);
    if(_touchStartDist>0){
      const ns=Math.max(0.2,Math.min(3,_touchStartScale*(dist/_touchStartDist)));
      const r=_chart.getBoundingClientRect();
      const mx=_touchStartMid.x-r.left,my=_touchStartMid.y-r.top;
      _panX=mx-(mx-_panX)*(ns/_scale);_panY=my-(my-_panY)*(ns/_scale);
      _scale=ns;_applyView();
    }
  }
  e.preventDefault();
},{passive:false});
_chart.addEventListener('touchend',function(e){
  if(_touchOnCard){
    if(!_touchOnCard.moved && _touchOnCard.card){
      show(_touchOnCard.card);
    }
    _touchOnCard=null;
    _isPanning=false;
    _chart.classList.remove('panning');
    e.preventDefault();
    return;
  }
  if(e.touches.length===0){_touchMode=null;_endPan();}
  else if(e.touches.length===1&&_touchMode==='pinch'){_touchMode='pan';_startPan(e.touches[0].clientX,e.touches[0].clientY);}
},{passive:false});
_chart.addEventListener('touchcancel',function(){_touchMode=null;_endPan();});
document.addEventListener('DOMContentLoaded',function(){_applyView();});
window.addEventListener('load',function(){if(_panX===0&&_panY===0&&_scale===1)fitToContent();else{_applyView();}});
if(document.fonts&&document.fonts.ready){document.fonts.ready.then(redrawLines);}
document.querySelectorAll('.photo img').forEach(function(img){if(!img.complete)img.addEventListener('load',redrawLines,{once:true});});
window.addEventListener('resize',function(){redrawLines();});
async function exportPdf(){
  const btn=document.getElementById('btnExportPdf');
  const oldText=btn.textContent;
  btn.textContent='Готовим PDF...';btn.disabled=true;
  try{
    const inner=document.getElementById('inner');
    const cards=inner.querySelectorAll('.card');
    if(!cards.length){alert('Нет карточек для экспорта');return;}
    let minX=1e9,minY=1e9,maxX=-1e9,maxY=-1e9;
    cards.forEach(function(c){
      const x=parseFloat(c.style.left)||0,y=parseFloat(c.style.top)||0,w=c.offsetWidth,h=c.offsetHeight;
      if(x<minX)minX=x;if(y<minY)minY=y;if(x+w>maxX)maxX=x+w;if(y+h>maxY)maxY=y+h;
    });
    const pad=100;
    const W=Math.ceil(maxX-minX+pad*2),H=Math.ceil(maxY-minY+pad*2);
    const oldTransform=inner.style.transform;
    const oldTransformOrigin=inner.style.transformOrigin;
    const oldTransition=inner.style.transition;
    inner.style.transition='none';
    inner.style.transformOrigin='0 0';
    inner.style.transform='translate('+(pad-minX)+'px,'+(pad-minY)+'px) scale(1)';
    await new Promise(function(r){setTimeout(r,80)});
    const canvas=await html2canvas(document.getElementById('chart'),{
      backgroundColor:'#f5f7fa',scale:2,useCORS:true,allowTaint:false,logging:false,
      width:W,height:H,windowWidth:W,windowHeight:H,scrollX:0,scrollY:0
    });
    inner.style.transition=oldTransition;
    inner.style.transform=oldTransform;
    inner.style.transformOrigin=oldTransformOrigin;
    const imgData=canvas.toDataURL('image/jpeg',0.92);
    const {jsPDF}=window.jspdf;
    const pdf=new jsPDF({orientation:'landscape',unit:'mm',format:'a4'});
    const pw=pdf.internal.pageSize.getWidth(),ph=pdf.internal.pageSize.getHeight();
    const margin=8,availW=pw-margin*2,availH=ph-margin*2;
    const ratio=Math.min(availW/canvas.width,availH/canvas.height);
    const imgW=canvas.width*ratio,imgH=canvas.height*ratio;
    const x=(pw-imgW)/2,y=(ph-imgH)/2;
    pdf.addImage(imgData,'JPEG',x,y,imgW,imgH);
    pdf.save('org-chart_<?=(int)$pid?>.pdf');
  }catch(err){alert('Ошибка PDF: '+err.message);console.error(err);}
  finally{btn.textContent=oldText;btn.disabled=false;}
}
const MONTHS_RU=['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'];
let birthdaysCache=null;
async function toggleBirthdays(){
  const panel=document.getElementById('bdPanel');
  const overlay=document.getElementById('bdOverlay');
  const isOpen=panel.classList.contains('open');
  if(isOpen){panel.classList.remove('open');overlay.classList.remove('open');return;}
  panel.classList.add('open');overlay.classList.add('open');
  if(!birthdaysCache){await loadBirthdays();}
  const searchInput=document.getElementById('bdSearch');
  if(searchInput && searchInput.value){searchInput.value='';document.getElementById('bdSearchClear').classList.remove('show');filterBirthdays('');}
  setTimeout(scrollToCurrentMonth,50);
}
async function loadBirthdays(){
  const body=document.getElementById('bdBody');
  body.innerHTML='<div class="bd-loading">Загрузка...</div>';
  try{
    const r=await apiFetch('api.php?action=birthdays&project=<?=(int)$pid?>&token=<?=htmlspecialchars(isset($token)?$token:'')?>');
    const d=await r.json();
    if(d.error){body.innerHTML='<div class="bd-empty">Ошибка: '+d.error+'</div>';return}
    birthdaysCache=Array.isArray(d)?d:[];
    renderBirthdays(birthdaysCache);
  }catch(e){body.innerHTML='<div class="bd-empty">Ошибка сети: '+e.message+'</div>';}
}
function renderBirthdays(list){
  const body=document.getElementById('bdBody');
  if(!list.length){body.innerHTML='<div class="bd-empty">Нет сотрудников с указанной датой рождения</div>';return;}
  const byMonth={};
  list.forEach(function(e){
    if(!e.birthday)return;
    const p=String(e.birthday).split('-');
    if(p.length!==3)return;
    const m=parseInt(p[1],10)-1;
    if(!byMonth[m])byMonth[m]=[];
    byMonth[m].push(e);
  });
  const today=new Date();
  const curM=today.getMonth();
  const curD=today.getDate();
  let html='';
  for(let m=0;m<12;m++){
    if(!byMonth[m]||!byMonth[m].length)continue;
    html+='<div class="bd-month" data-month="'+m+'">';
    html+='<div class="bd-month-title">'+MONTHS_RU[m]+'</div>';
    byMonth[m].sort(function(a,b){return parseInt(a.birthday.split('-')[2],10)-parseInt(b.birthday.split('-')[2],10);});
    byMonth[m].forEach(function(e){
      const p=e.birthday.split('-');
      const day=parseInt(p[2],10);
      const isToday=(m===curM&&day===curD);
      const dateStr=p[2]+'.'+p[1];
      const nameLower=String(e.full_name||'').toLowerCase();
      html+='<div class="bd-item'+(isToday?' today':'')+'" data-name="'+escBd(nameLower)+'"><span class="bd-name">'+escBd(e.full_name)+'</span><span class="bd-date">'+dateStr+'</span></div>';
    });
    html+='</div>';
  }
  body.innerHTML=html;
}
function filterBirthdays(q){
  q=(q||'').toLowerCase().trim();
  const clearBtn=document.getElementById('bdSearchClear');
  if(q){clearBtn.classList.add('show');}else{clearBtn.classList.remove('show');}
  const months=document.querySelectorAll('#bdBody .bd-month');
  if(!q){
    months.forEach(function(m){m.style.display='';m.classList.remove('bd-hidden');});
    document.querySelectorAll('#bdBody .bd-item').forEach(function(i){i.style.display='';});
    scrollToCurrentMonth();
    return;
  }
  let firstVisible=null;
  months.forEach(function(monthEl){
    let monthHasVisible=false;
    monthEl.querySelectorAll('.bd-item').forEach(function(item){
      const name=item.dataset.name||'';
      if(name.indexOf(q)!==-1){item.style.display='';monthHasVisible=true;}else{item.style.display='none';}
    });
    if(monthHasVisible){monthEl.style.display='';monthEl.classList.remove('bd-hidden');if(!firstVisible)firstVisible=monthEl;}
    else{monthEl.style.display='none';monthEl.classList.add('bd-hidden');}
  });
  if(firstVisible){
    const body=document.getElementById('bdBody');
    body.scrollTo({top:firstVisible.offsetTop-8,behavior:'smooth'});
  }
}
function clearBdSearch(){document.getElementById('bdSearch').value='';document.getElementById('bdSearchClear').classList.remove('show');filterBirthdays('');}
function escBd(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');}
function scrollToCurrentMonth(){
  const body=document.getElementById('bdBody');
  if(!body)return;
  const today=new Date();
  const curM=today.getMonth();
  let target=body.querySelector('.bd-month[data-month="'+curM+'"]');
  if(!target){for(let m=curM+1;m<12;m++){target=body.querySelector('.bd-month[data-month="'+m+'"]');if(target)break;}}
  if(!target)target=body.querySelector('.bd-month');
  if(!target)return;
  const top=Math.max(0,target.offsetTop-8);
  smoothScrollTo(body,top,700);
}
function smoothScrollTo(el,targetY,duration){
  duration=duration||600;
  const startY=el.scrollTop;
  const diff=targetY-startY;
  if(Math.abs(diff)<2){el.scrollTop=targetY;return;}
  let startTime=null;
  function easeInOutCubic(t){return t<0.5 ? 4*t*t*t : 1-Math.pow(-2*t+2,3)/2;}
  function step(ts){
    if(startTime===null)startTime=ts;
    const elapsed=ts-startTime;
    const t=Math.min(1,elapsed/duration);
    el.scrollTop=startY+diff*easeInOutCubic(t);
    if(t<1)requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}
function exportBirthdays(){window.location.href='export_birthdays.php?project=<?=(int)$pid?>';}
</script>
<div class="bd-overlay" id="bdOverlay" onclick="toggleBirthdays()"></div>
<div class="bd-panel" id="bdPanel">
  <div class="bd-head">
    <h2>Дни рождения</h2>
<div class="bd-head-actions">
  <button class="bd-close" onclick="toggleBirthdays()">×</button>
</div>
  </div>
  <div class="bd-search">
    <input type="text" id="bdSearch" placeholder="Поиск по ФИО..." oninput="filterBirthdays(this.value)">
    <button class="bd-search-clear" id="bdSearchClear" onclick="clearBdSearch()" title="Очистить">×</button>
  </div>
  <div class="bd-body" id="bdBody">
    <div class="bd-loading">Загрузка...</div>
  </div>
</div></body></html>