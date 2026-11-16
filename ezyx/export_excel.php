<?php
require_once 'config.php';
if(!file_exists(__DIR__.'/vendor/autoload.php')){http_response_code(500);die('Установите PhpSpreadsheet: composer install');}
require_once __DIR__.'/vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

$pid=(int)(isset($_GET['project'])?$_GET['project']:0);
$token=trim(isset($_GET['token'])?$_GET['token']:'');
if($token){
  $st=$pdo->prepare("SELECT id,name FROM projects WHERE share_token=? AND is_public=1");
  $st->execute([$token]);$project=$st->fetch();
}else{
  if(!isset($_SESSION['user_id'])){http_response_code(401);die('Unauthorized');}
  $uid=(int)$_SESSION['user_id'];
  if(!$pid){$st=$pdo->prepare("SELECT id,name FROM projects WHERE user_id=? ORDER BY updated_at DESC LIMIT 1");$st->execute([$uid]);$project=$st->fetch();}
  else{$st=$pdo->prepare("SELECT id,name FROM projects WHERE id=? AND user_id=?");$st->execute([$pid,$uid]);$project=$st->fetch();}
}
if(!$project){http_response_code(404);die('Проект не найден');}
$pid=(int)$project['id'];
$st=$pdo->prepare("SELECT * FROM employees WHERE project_id=? AND (show_in_list IS NULL OR show_in_list=1) ORDER BY position_y,position_x");
$st->execute([$pid]);$employees=$st->fetchAll();

$offices=array();
try{
  $st=$pdo->prepare("SELECT id,name FROM offices WHERE project_id=?");
  $st->execute([$pid]);
  foreach($st->fetchAll() as $o){$offices[(int)$o['id']]=$o['name'];}
}catch(Exception $e){}

$spreadsheet=new Spreadsheet();
$sheet=$spreadsheet->getActiveSheet();
$sheet->setTitle('Сотрудники');
$headers=['№','ФИО','Должность','Телефон','Email','Вн.','Офис / кабинет'];
foreach($headers as $i=>$h){$sheet->setCellValue(chr(65+$i).'1',$h);}
$sheet->getStyle('A1:G1')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A1:G1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
$sheet->getRowDimension(1)->setRowHeight(28);
$sheet->freezePane('A2');
$sheet->setAutoFilter('A1:G1');
$row=2;
foreach($employees as $e){
  $position=$e['position'];
  if(!empty($e['is_department'])&&$e['department'])$position.=' ('.$e['department'].')';
  $phone=str_replace('|',"\n",isset($e['phone'])?$e['phone']:'');
  $email=str_replace('|',"\n",isset($e['email'])?$e['email']:'');
  $officeName='';
  if(!empty($e['office_id'])&&isset($offices[(int)$e['office_id']])){$officeName=$offices[(int)$e['office_id']];}
  $cab=isset($e['cabinet'])?$e['cabinet']:'';
  $officeFull='';
  if($officeName&&$cab){$officeFull=$officeName.' (каб. '.$cab.')';}
  elseif($officeName){$officeFull=$officeName;}
  elseif($cab){$officeFull='каб. '.$cab;}
$sheet->setCellValue('A'.$row,$row-1);
$sheet->setCellValue('B'.$row,$e['full_name']);
$sheet->setCellValue('C'.$row,$position);
$sheet->setCellValue('D'.$row,$phone);
$sheet->setCellValue('E'.$row,$email);
$sheet->setCellValue('F'.$row,isset($e['ext'])?$e['ext']:'');
$sheet->setCellValue('G'.$row,$officeFull);
  $sheet->getStyle('B'.$row)->getFont()->setBold(true);
  $sheet->getRowDimension($row)->setRowHeight(45);
  $row++;
}
$lastRow=$row-1;
if($lastRow>=2){
  $sheet->getStyle('A2:G'.$lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
  $sheet->getStyle('A1:G'.$lastRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF999999');
}
$sheet->getColumnDimension('A')->setWidth(6);
$sheet->getColumnDimension('B')->setWidth(30);
$sheet->getColumnDimension('C')->setWidth(40);
$sheet->getColumnDimension('D')->setWidth(20);
$sheet->getColumnDimension('E')->setWidth(30);
$sheet->getColumnDimension('F')->setWidth(10);   // Доб.
$sheet->getColumnDimension('G')->setWidth(30);   // Офис
$filename='employees_'.$pid.'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');
$writer=new Xlsx($spreadsheet);
$writer->save('php://output');
exit;