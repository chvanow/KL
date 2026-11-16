<?php
ini_set('display_errors',0);error_reporting(E_ALL);
require_once __DIR__.'/config.php';
header('Content-Type: application/json; charset=utf-8');
$action=isset($_GET['action'])?$_GET['action']:'';
$raw=file_get_contents('php://input');
$input=json_decode($raw,true);
if(!is_array($input)){$input=array();}
$userId=isset($_SESSION['user_id'])?(int)$_SESSION['user_id']:0;
$projectId=(int)(isset($_GET['project'])?$_GET['project']:(isset($input['project'])?$input['project']:0));
$token=isset($_GET['token'])?trim($_GET['token']):'';
function ok($d){jsonResponse($d);}
function err($m,$c=400){jsonResponse(array('error'=>$m),$c);}
function tableColumns(PDO $pdo,$table){
    static $cache=array();
    if(isset($cache[$table])){return $cache[$table];}
    $cols=array();$stmt=$pdo->query("SHOW COLUMNS FROM `".$table."`");
    while($row=$stmt->fetch()){$cols[]=$row['Field'];}
    $cache[$table]=$cols;return $cols;
}
function filterCols(PDO $pdo,$data){
    $existing=tableColumns($pdo,'employees');
    $out=array();
    foreach($data as $k=>$v){if(in_array($k,$existing,true)){$out[$k]=$v;}}
    return $out;
}
function createsCycle(PDO $pdo,$child,$parent){
    $cur=$parent;$seen=array();
    while($cur&&!in_array($cur,$seen,true)){
        if($cur===$child){return true;}
        $seen[]=$cur;
        $r=$pdo->prepare("SELECT parent_id FROM employees WHERE id=?");
        $r->execute(array($cur));$row=$r->fetch();
        $cur=$row?(int)$row['parent_id']:0;
    }
    return false;
}
function needAuth(){
    global $userId;
    if($userId)return;
    if(!empty($_SESSION['guest'])&&!empty($_SESSION['guest_project']))return;
    err('Требуется вход',401);
}
function needProject(PDO $pdo,$id,$uid){
    if(!$uid&&!empty($_SESSION['guest'])&&!empty($_SESSION['guest_project'])){
        if((int)$id!==(int)$_SESSION['guest_project']){err('Нет доступа',403);}
        return array('id'=>(int)$id,'user_id'=>null,'name'=>'Демо');
    }
    $st=$pdo->prepare("SELECT * FROM projects WHERE id=? AND user_id=?");
    $st->execute(array($id,$uid));$p=$st->fetch();
    if(!$p){err('Проект не найден',404);}
    return $p;
}
function resolveReadProject(PDO $pdo){
    global $userId,$projectId,$token;
    if($token){
        $st=$pdo->prepare("SELECT * FROM projects WHERE share_token=? AND is_public=1");
        $st->execute(array($token));$p=$st->fetch();
        if(!$p){err('Проект не найден',404);}
        return (int)$p['id'];
    }
    if(!$userId){
        if(!empty($_SESSION['guest'])&&!empty($_SESSION['guest_project'])){return (int)$_SESSION['guest_project'];}
        err('Требуется вход',401);
    }
    if(!$projectId){err('project обязателен');}
    needProject($pdo,$projectId,$userId);
    return $projectId;
}

// CSRF для мутирующих действий
$mutatingActions = ['create','update','delete','save','upload','project_create','project_rename','project_delete','project_duplicate','project_share','project_unshare','office_create','office_update','office_delete','list_reorder'];
if(in_array($action,$mutatingActions,true) && $_SERVER['REQUEST_METHOD']==='POST'){
    $t = $input['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? ''));
    if(!$t || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'],$t)){
        err('CSRF token invalid',403);
    }
}

if($action==='projects_list'){
    needAuth();
    try{
        $st=$pdo->prepare("SELECT p.id,p.name,p.share_token,p.is_public,p.created_at,p.updated_at,(SELECT COUNT(*) FROM employees e WHERE e.project_id=p.id) AS emp_count FROM projects p WHERE p.user_id=? ORDER BY p.updated_at DESC");
        $st->execute(array($userId));$rows=$st->fetchAll();
        foreach($rows as $k=>$r){$rows[$k]['id']=(int)$r['id'];$rows[$k]['is_public']=(int)$r['is_public'];$rows[$k]['emp_count']=(int)$r['emp_count'];}
        ok($rows);
    }catch(Exception $e){err($e->getMessage(),500);}
}
if($action==='project_create'){
    needAuth();
    try{
        $cnt=$pdo->prepare("SELECT COUNT(*) FROM projects WHERE user_id=?");
        $cnt->execute(array($userId));
        if((int)$cnt->fetchColumn()>=10){err('Достигнут лимит проектов (10). Удалите один из существующих, чтобы создать новый.',403);}
        $name=trim(isset($input['name'])?$input['name']:'');
        if($name===''){$name='Новый проект';}
        $st=$pdo->prepare("INSERT INTO projects (user_id,name) VALUES (?,?)");
        $st->execute(array($userId,$name));
        ok(array('success'=>true,'id'=>(int)$pdo->lastInsertId(),'name'=>$name));
    }catch(Exception $e){err($e->getMessage(),500);}
}
if($action==='project_rename'){
    needAuth();
    try{
        $id=(int)(isset($input['id'])?$input['id']:0);
        $name=trim(isset($input['name'])?$input['name']:'');
        if(!$id||$name===''){err('id и name обязательны');}
        needProject($pdo,$id,$userId);
        $pdo->prepare("UPDATE projects SET name=? WHERE id=?")->execute(array($name,$id));
        ok(array('success'=>true));
    }catch(Exception $e){err($e->getMessage(),500);}
}
if($action==='project_delete'){
    needAuth();
    try{
        $id=(int)(isset($input['id'])?$input['id']:0);
        if(!$id){err('id обязателен');}
        needProject($pdo,$id,$userId);
        $st=$pdo->prepare("SELECT photo FROM employees WHERE project_id=? AND photo IS NOT NULL AND photo<>''");
        $st->execute(array($id));
        $photos=$st->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare("DELETE FROM projects WHERE id=?")->execute(array($id));
        foreach($photos as $p){ deletePhotoIfUnused($pdo,$p); }
        ok(array('success'=>true));
    }catch(Exception $e){err($e->getMessage(),500);}
}
if($action==='project_duplicate'){
    needAuth();
    try{
        $cnt=$pdo->prepare("SELECT COUNT(*) FROM projects WHERE user_id=?");
        $cnt->execute(array($userId));
        if((int)$cnt->fetchColumn()>=10){err('Достигнут лимит проектов (10). Дублирование недоступно.',403);}
        $id=(int)(isset($input['id'])?$input['id']:0);
        if(!$id){err('id обязателен');}
        $src=needProject($pdo,$id,$userId);
        $pdo->beginTransaction();
        $newName=$src['name'].' (копия)';
        $pdo->prepare("INSERT INTO projects (user_id,name) VALUES (?,?)")->execute(array($userId,$newName));
        $newPid=(int)$pdo->lastInsertId();
        $officeMap=array();
        try{
            $st=$pdo->prepare("SELECT id,name,color FROM offices WHERE project_id=?");
            $st->execute(array($id));
            $insOff=$pdo->prepare("INSERT INTO offices (project_id,name,color) VALUES (?,?,?)");
            foreach($st->fetchAll() as $o){
                $insOff->execute(array($newPid,$o['name'],$o['color']));
                $officeMap[(int)$o['id']]=(int)$pdo->lastInsertId();
            }
        }catch(Exception $ex){}
        $st=$pdo->prepare("SELECT * FROM employees WHERE project_id=?");
        $st->execute(array($id));
        $rows=$st->fetchAll();
        $hasOffice=in_array('office_id',tableColumns($pdo,'employees'),true);
        $hasCabinet=in_array('cabinet',tableColumns($pdo,'employees'),true);
        $hasLineOffsets=in_array('line_offsets',tableColumns($pdo,'employees'),true);
        $baseCols='project_id,full_name,position,parent_id,show_manager,phone,email,ext,birthday,photo,show_photo,crop_x,crop_y,crop_zoom,is_department,department,show_contacts,show_office,show_in_list,links,position_x,position_y,width,height';
        if($hasLineOffsets)$baseCols.=',line_offsets';
        $cols=$baseCols;
        if($hasOffice)$cols.=',office_id';
        if($hasCabinet)$cols.=',cabinet';
        $n=substr_count($cols,',')+1;
        $ph=implode(',',array_fill(0,$n,'?'));
        $insEmp=$pdo->prepare("INSERT INTO employees ($cols) VALUES ($ph)");
        $empMap=array();
        foreach($rows as $e){
            $params=array(
                $newPid,$e['full_name'],$e['position'],null,1,
                $e['phone'],$e['email'],$e['ext'],$e['birthday'],$e['photo'],(int)$e['show_photo'],
                (int)$e['crop_x'],(int)$e['crop_y'],(float)$e['crop_zoom'],
                (int)$e['is_department'],$e['department'],
                (int)$e['show_contacts'],(int)$e['show_office'],(int)$e['show_in_list'],$e['links'],
                (int)$e['position_x'],(int)$e['position_y'],(int)$e['width'],(int)$e['height']
            );
            if($hasLineOffsets)$params[]=isset($e['line_offsets'])?$e['line_offsets']:null;
            if($hasOffice){$params[]=(!empty($e['office_id'])&&isset($officeMap[(int)$e['office_id']]))?$officeMap[(int)$e['office_id']]:null;}
            if($hasCabinet){$params[]=isset($e['cabinet'])?$e['cabinet']:null;}
            $insEmp->execute($params);
            $empMap[(int)$e['id']]=(int)$pdo->lastInsertId();
        }
        $upd=$pdo->prepare("UPDATE employees SET parent_id=? WHERE id=?");
        foreach($rows as $e){
            $oldParent=(int)(isset($e['parent_id'])?$e['parent_id']:0);
            if($oldParent&&isset($empMap[$oldParent])){$upd->execute(array($empMap[$oldParent],$empMap[(int)$e['id']]));}
        }
        $pdo->commit();
        ok(array('success'=>true,'id'=>$newPid,'name'=>$newName));
    }catch(Exception $e){if($pdo->inTransaction()){$pdo->rollBack();}err($e->getMessage(),500);}
}
if($action==='project_share'){
    needAuth();
    try{
        $id=(int)(isset($input['id'])?$input['id']:0);
        if(!$id){err('id обязателен');}
        $p=needProject($pdo,$id,$userId);
        $t=!empty($p['share_token'])?$p['share_token']:bin2hex(random_bytes(16));
        $pdo->prepare("UPDATE projects SET share_token=?, is_public=1 WHERE id=?")->execute(array($t,$id));
        $scheme=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
        $host=isset($_SERVER['HTTP_HOST'])?$_SERVER['HTTP_HOST']:'';
        ok(array('success'=>true,'token'=>$t,'url'=>$scheme.'://'.$host.'/dashboard?token='.$t));
    }catch(Exception $e){err($e->getMessage(),500);}
}
if($action==='project_unshare'){
    needAuth();
    try{
        $id=(int)(isset($input['id'])?$input['id']:0);
        if(!$id){err('id обязателен');}
        needProject($pdo,$id,$userId);
        $pdo->prepare("UPDATE projects SET is_public=0 WHERE id=?")->execute(array($id));
        ok(array('success'=>true));
    }catch(Exception $e){err($e->getMessage(),500);}
}
if($action==='project_get'){
    try{
        if($token){
            $st=$pdo->prepare("SELECT * FROM projects WHERE share_token=? AND is_public=1");
            $st->execute(array($token));$p=$st->fetch();
            if(!$p){err('Проект не найден',404);}
            ok(array('id'=>(int)$p['id'],'name'=>$p['name'],'public'=>true));
        }
        needAuth();
        if(!$projectId){err('project обязателен');}
        $p=needProject($pdo,$projectId,$userId);
        ok(array('id'=>(int)$p['id'],'name'=>$p['name'],'public'=>(bool)$p['is_public'],'share_token'=>$p['share_token']));
    }catch(Exception $e){err($e->getMessage(),500);}
}
if($action==='office_list'){
    if(!$projectId){err('project обязателен');}
    try{
        if(!$userId&&!empty($_SESSION['guest'])&&!empty($_SESSION['guest_project'])){
            if((int)$projectId!==(int)$_SESSION['guest_project']){err('Нет доступа',403);}
        }else{ needProject($pdo,$projectId,$userId); }
        $st=$pdo->prepare("SELECT id,name,color FROM offices WHERE project_id=? ORDER BY id ASC");
        $st->execute(array($projectId));$rows=$st->fetchAll();
        foreach($rows as $k=>$r){$rows[$k]['id']=(int)$r['id'];}
        ok($rows);
    }catch(Exception $e){err($e->getMessage(),500);}
}
if($action==='office_create'){
    if(!$projectId){err('project обязателен');}
    try{
        if(!$userId&&!empty($_SESSION['guest'])&&!empty($_SESSION['guest_project'])){
            if((int)$projectId!==(int)$_SESSION['guest_project']){err('Нет доступа',403);}
        }else{ needProject($pdo,$projectId,$userId); }
        $name=trim(isset($input['name'])?$input['name']:'');
        $color=trim(isset($input['color'])?$input['color']:'#4D668E');
        if($name===''){err('Название офиса обязательно');}
        if(!preg_match('/^#[0-9A-Fa-f]{6}$/',$color)){$color='#4D668E';}
        $st=$pdo->prepare("INSERT INTO offices (project_id,name,color) VALUES (?,?,?)");
        $st->execute(array($projectId,$name,$color));
        ok(array('success'=>true,'id'=>(int)$pdo->lastInsertId(),'name'=>$name,'color'=>$color));
    }catch(Exception $e){err($e->getMessage(),500);}
}
if($action==='office_update'){
    if(!$projectId){err('project обязателен');}
    try{
        if(!$userId&&!empty($_SESSION['guest'])&&!empty($_SESSION['guest_project'])){
            if((int)$projectId!==(int)$_SESSION['guest_project']){err('Нет доступа',403);}
        }else{ needProject($pdo,$projectId,$userId); }
        $id=(int)(isset($input['id'])?$input['id']:0);
        $name=trim(isset($input['name'])?$input['name']:'');
        $color=trim(isset($input['color'])?$input['color']:'#4D668E');
        if(!$id||$name===''){err('id и name обязательны');}
        if(!preg_match('/^#[0-9A-Fa-f]{6}$/',$color)){$color='#4D668E';}
        $chk=$pdo->prepare("SELECT id FROM offices WHERE id=? AND project_id=?");
        $chk->execute(array($id,$projectId));
        if(!$chk->fetch()){err('Офис не найден',404);}
        $pdo->prepare("UPDATE offices SET name=?, color=? WHERE id=?")->execute(array($name,$color,$id));
        ok(array('success'=>true,'id'=>$id,'name'=>$name,'color'=>$color));
    }catch(Exception $e){err($e->getMessage(),500);}
}
if($action==='office_delete'){
    if(!$projectId){err('project обязателен');}
    try{
        if(!$userId&&!empty($_SESSION['guest'])&&!empty($_SESSION['guest_project'])){
            if((int)$projectId!==(int)$_SESSION['guest_project']){err('Нет доступа',403);}
        }else{ needProject($pdo,$projectId,$userId); }
        $id=(int)(isset($input['id'])?$input['id']:0);
        if(!$id){err('id обязателен');}
        $pdo->prepare("DELETE FROM offices WHERE id=? AND project_id=?")->execute(array($id,$projectId));
        $pdo->prepare("UPDATE employees SET office_id=NULL WHERE office_id=?")->execute(array($id));
        ok(array('success'=>true));
    }catch(Exception $e){err($e->getMessage(),500);}
}

if($action==='get'){
    try{
        $pid=resolveReadProject($pdo);
        $isPublicView = !empty($token); // публичная ссылка
        $sql = "SELECT * FROM employees WHERE project_id=?";
        if($isPublicView){
            $sql .= " AND (show_in_list IS NULL OR show_in_list=1)";
        }
        $sql .= " ORDER BY list_order ASC, id ASC";
        $st=$pdo->prepare($sql);
        $st->execute(array($pid));$rows=$st->fetchAll();
        $out=array();
        foreach($rows as $r){
            foreach(array('id','parent_id','show_manager','position_x','position_y','width','height','show_photo','is_department','show_contacts','show_office','show_in_list','crop_x','crop_y','office_id','z_index') as $k){
                if(isset($r[$k])){$r[$k]=(int)$r[$k];}else{$r[$k]=0;}
            }
            $r['crop_zoom']=isset($r['crop_zoom'])?(float)$r['crop_zoom']:1;
            $r['project_id']=(int)$r['project_id'];
            if(isset($r['birthday']) && ($r['birthday']==='0000-00-00' || $r['birthday']==='')){$r['birthday']=null;}
            $out[]=$r;
        }
        ok($out);
    }catch(Exception $e){err($e->getMessage(),500);}
}
if($action==='create'){
    needAuth();
    if(!$projectId){err('project обязателен');}
    try{
        needProject($pdo,$projectId,$userId);
        if(empty($input['full_name'])||empty($input['position'])){err('full_name и position обязательны');}
        $birthday=null;
        if(!empty($input['birthday'])){
            $bd=trim($input['birthday']);
            if(preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/',$bd,$m) && checkdate((int)$m[2],(int)$m[1],(int)$m[3])){
                $day=str_pad($m[1],2,'0',STR_PAD_LEFT);$mon=str_pad($m[2],2,'0',STR_PAD_LEFT);
                $birthday=$m[3].'-'.$mon.'-'.$day;
            }
        }
        $data=array(
            'project_id'=>$projectId,'full_name'=>$input['full_name'],'position'=>$input['position'],
            'parent_id'=>!empty($input['parent_id'])?(int)$input['parent_id']:null,
            'show_manager'=>isset($input['show_manager'])?(!empty($input['show_manager'])?1:0):1,
            'phone'=>isset($input['phone'])?$input['phone']:null,
            'email'=>isset($input['email'])?$input['email']:null,
            'ext'=>isset($input['ext'])&&$input['ext']!==''?substr(trim($input['ext']),0,6):null,
            'birthday'=>$birthday,
            'office_id'=>!empty($input['office_id'])?(int)$input['office_id']:null,
            'cabinet'=>isset($input['cabinet'])&&$input['cabinet']!==''?substr(trim($input['cabinet']),0,6):null,
            'photo'=>isset($input['photo'])?$input['photo']:null,
            'show_photo'=>!empty($input['show_photo'])?1:0,
            'is_department'=>!empty($input['is_department'])?1:0,
            'department'=>isset($input['department'])?$input['department']:null,
            'crop_x'=>(int)(isset($input['crop_x'])?$input['crop_x']:0),
            'crop_y'=>(int)(isset($input['crop_y'])?$input['crop_y']:0),
            'crop_zoom'=>(float)(isset($input['crop_zoom'])?$input['crop_zoom']:1),
            'position_x'=>(int)(isset($input['position_x'])?$input['position_x']:100),
            'position_y'=>(int)(isset($input['position_y'])?$input['position_y']:100),
            'width'=>(int)(isset($input['width'])?$input['width']:220),
            'height'=>(int)(isset($input['height'])?$input['height']:100),
            'z_index'=>(int)(isset($input['z_index'])?$input['z_index']:10),
            'show_contacts'=>!empty($input['show_contacts'])?1:0,
            'show_office'=>!empty($input['show_office'])?1:0,
            'show_in_list'=>isset($input['show_in_list'])?(!empty($input['show_in_list'])?1:0):1,
            'links'=>isset($input['links'])?$input['links']:null,
        );
        $data=filterCols($pdo,$data);
        $cols=array_keys($data);$ph=array();
        foreach($cols as $c){$ph[]=':'.$c;}
        $sql="INSERT INTO employees (`".implode('`,`',$cols)."`) VALUES (".implode(',',$ph).")";
        $pdo->prepare($sql)->execute($data);
        ok(array('success'=>true,'id'=>(int)$pdo->lastInsertId()));
    }catch(Exception $e){err($e->getMessage(),500);}
}
if($action==='update'){
    needAuth();
    try{
        $id=(int)(isset($input['id'])?$input['id']:0);
        if(!$id){err('id обязателен');}
        $chk=$pdo->prepare("SELECT e.id,e.parent_id FROM employees e JOIN projects p ON p.id=e.project_id WHERE e.id=? AND p.user_id=?");
        $chk->execute(array($id,$userId));
        $row=$chk->fetch();
        if(!$row){err('Запись не найдена',404);}
        $oldParentId=(int)$row['parent_id'];
        if(!empty($input['parent_id'])&&createsCycle($pdo,$id,(int)$input['parent_id'])){err('Нельзя создать цикл');}
        $fields=array('full_name','position','parent_id','show_manager','phone','email','ext','birthday','office_id','cabinet','links','photo','show_photo','crop_x','crop_y','crop_zoom','is_department','department','show_contacts','show_office','show_in_list','line_offsets','position_x','position_y','width','height','z_index');
        $data=array();
        foreach($fields as $f){if(array_key_exists($f,$input)){$data[$f]=$input[$f];}}
        if(!$data){err('Нет полей для обновления');}
        if(array_key_exists('parent_id',$data)){
            $data['parent_id']=!empty($data['parent_id'])?(int)$data['parent_id']:null;
            // Сброс line_offsets при смене родителя
            if((int)$data['parent_id']!==$oldParentId){$data['line_offsets']=null;}
        }
        if(array_key_exists('office_id',$data)){$data['office_id']=!empty($data['office_id'])?(int)$data['office_id']:null;}
        if(array_key_exists('cabinet',$data)){$data['cabinet']=$data['cabinet']!==''&&$data['cabinet']!==null?substr(trim($data['cabinet']),0,6):null;}
        if(array_key_exists('ext',$data)){$data['ext']=$data['ext']!==''&&$data['ext']!==null?substr(trim($data['ext']),0,6):null;}
        if(array_key_exists('birthday',$data)){
            $bd=trim((string)$data['birthday']);
            if($bd===''){unset($data['birthday']);}
            elseif(preg_match('/^(\d{1,2})\.(\d{1,2})\.(\d{4})$/',$bd,$m) && checkdate((int)$m[2],(int)$m[1],(int)$m[3])){
                $day=str_pad($m[1],2,'0',STR_PAD_LEFT);$mon=str_pad($m[2],2,'0',STR_PAD_LEFT);
                $data['birthday']=$m[3].'-'.$mon.'-'.$day;
            }else{unset($data['birthday']);}
        }
        if(array_key_exists('show_photo',$data)){$data['show_photo']=$data['show_photo']?1:0;}
        if(array_key_exists('show_manager',$data)){$data['show_manager']=$data['show_manager']?1:0;}
        if(array_key_exists('is_department',$data)){$data['is_department']=$data['is_department']?1:0;}
        if(array_key_exists('show_contacts',$data)){$data['show_contacts']=$data['show_contacts']?1:0;}
        if(array_key_exists('show_office',$data)){$data['show_office']=$data['show_office']?1:0;}
        if(array_key_exists('show_in_list',$data)){$data['show_in_list']=$data['show_in_list']?1:0;}
        if(array_key_exists('line_offsets',$data)){
            if($data['line_offsets']===null||$data['line_offsets']===''){$data['line_offsets']=null;}
            else{
                $lo=is_array($data['line_offsets'])?$data['line_offsets']:json_decode($data['line_offsets'],true);
                if(!is_array($lo)){$data['line_offsets']=null;}
                else{
                    $clean=array();
                    if(isset($lo['midY'])&&is_numeric($lo['midY']))$clean['midY']=(int)$lo['midY'];
                    if(isset($lo['upperX'])&&is_numeric($lo['upperX'])&&$lo['upperX']!=0)$clean['upperX']=(int)$lo['upperX'];
                    if(isset($lo['lowerX'])&&is_numeric($lo['lowerX'])&&$lo['lowerX']!=0)$clean['lowerX']=(int)$lo['lowerX'];
                    $data['line_offsets']=empty($clean)?null:json_encode($clean);
                }
            }
        }
        foreach(array('position_x','position_y','width','height','crop_x','crop_y','z_index') as $k){if(array_key_exists($k,$data)){$data[$k]=(int)$data[$k];}}
        if(array_key_exists('crop_zoom',$data)){$data['crop_zoom']=(float)$data['crop_zoom'];}
        $data=filterCols($pdo,$data);
        if(!$data){err('Нет полей для обновления');}
        $oldPhoto=null;
        if(array_key_exists('photo',$data)){
            $st=$pdo->prepare("SELECT photo FROM employees WHERE id=?");
            $st->execute(array($id));
            $oldPhoto=$st->fetchColumn();
        }
        $sets=array();
        foreach(array_keys($data) as $c){$sets[]="`".$c."` = :".$c;}
        $sql="UPDATE employees SET ".implode(', ',$sets)." WHERE id = :id";
        $stmt=$pdo->prepare($sql);
        foreach($data as $k=>$v){$stmt->bindValue(':'.$k,$v);}
        $stmt->bindValue(':id',$id,PDO::PARAM_INT);
        $stmt->execute();
        if($oldPhoto && $oldPhoto !== ($data['photo'] ?? null)){deletePhotoIfUnused($pdo,$oldPhoto);}
        ok(array('success'=>true,'id'=>$id));
    }catch(Exception $e){err($e->getMessage(),500);}
}
if($action==='delete'){
    needAuth();
    try{
        $id=(int)(isset($input['id'])?$input['id']:0);
        if(!$id){err('id обязателен');}
        $st=$pdo->prepare("SELECT e.photo FROM employees e JOIN projects p ON p.id=e.project_id WHERE e.id=? AND p.user_id=?");
        $st->execute(array($id,$userId));
        $row=$st->fetch();
        if(!$row){err('Запись не найдена',404);}
        $photo=$row['photo'];
        $del=$pdo->prepare("DELETE FROM employees WHERE id=?");
        $del->execute(array($id));
        if($photo){ deletePhotoIfUnused($pdo,$photo); }
        ok(array('success'=>true));
    }catch(Exception $e){err($e->getMessage(),500);}
}
if($action==='save'){
    needAuth();
    if(!$projectId){err('project обязателен');}
    try{
        needProject($pdo,$projectId,$userId);
        $list=isset($input['employees'])?$input['employees']:array();
        if(!is_array($list)){err('employees[] обязателен');}
        $pdo->beginTransaction();
        $stmt=$pdo->prepare("UPDATE employees SET position_x=?,position_y=?,width=?,height=?,parent_id=? WHERE id=? AND project_id=?");
        foreach($list as $e){
            $stmt->execute(array(
                (int)(isset($e['position_x'])?$e['position_x']:0),
                (int)(isset($e['position_y'])?$e['position_y']:0),
                (int)(isset($e['width'])?$e['width']:220),
                (int)(isset($e['height'])?$e['height']:100),
                !empty($e['parent_id'])?(int)$e['parent_id']:null,
                (int)(isset($e['id'])?$e['id']:0),
                $projectId
            ));
        }
        $pdo->commit();
        ok(array('success'=>true));
    }catch(Exception $e){if($pdo->inTransaction()){$pdo->rollBack();}err($e->getMessage(),500);}
}
if($action==='upload'){
    needAuth();
    try{
        if(empty($_FILES['photo'])){err('Файл не загружен');}
        $f=$_FILES['photo'];
        if($f['error']!==UPLOAD_ERR_OK){err('Ошибка загрузки');}
        $allowed=array('image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp');
        $finfo=finfo_open(FILEINFO_MIME_TYPE);$mime=finfo_file($finfo,$f['tmp_name']);finfo_close($finfo);
        if(!isset($allowed[$mime])){err('Недопустимый тип');}
        if($f['size']>5*1024*1024){err('Файл >5MB');}
        if(!@getimagesize($f['tmp_name'])){err('Файл не является изображением');}
        $dir=__DIR__.'/uploads';if(!is_dir($dir)){mkdir($dir,0755,true);}
        $name=uniqid('photo_',true).'.'.$allowed[$mime];
        if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$name)){err('Не сохранилось',500);}
        ok(array('success'=>true,'filepath'=>'uploads/'.$name));
    }catch(Exception $e){err($e->getMessage(),500);}
}
if($action==='list_reorder'){
    needAuth();
    try{
        if(!$projectId){err('project обязателен');}
        needProject($pdo,$projectId,$userId);
        $order=isset($input['order'])?$input['order']:array();
        if(!is_array($order)){err('order[] обязателен');}
        $pdo->beginTransaction();
        $stmt=$pdo->prepare("UPDATE employees SET list_order=? WHERE id=? AND project_id=?");
        foreach($order as $i=>$empId){
            $stmt->execute(array((int)$i,(int)$empId,$projectId));
        }
        $pdo->commit();
        ok(array('success'=>true,'count'=>count($order)));
    }catch(Exception $e){if($pdo->inTransaction()){$pdo->rollBack();}err($e->getMessage(),500);}
}
if($action==='birthdays'){
    try{
        $pid=resolveReadProject($pdo);
        $st=$pdo->prepare("SELECT id,full_name,birthday FROM employees WHERE project_id=? AND (show_in_list IS NULL OR show_in_list=1) AND birthday IS NOT NULL AND birthday<>'0000-00-00' ORDER BY birthday ASC");
        $st->execute(array($pid));
        $rows=$st->fetchAll();
        foreach($rows as $k=>$r){$rows[$k]['id']=(int)$r['id'];}
        ok($rows);
    }catch(Exception $e){err($e->getMessage(),500);}
}
err('Unknown action',404);