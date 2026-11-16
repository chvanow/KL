<?php
require_once 'config.php';
if(!file_exists(__DIR__.'/vendor/autoload.php')){http_response_code(500);die('composer install');}
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
  if(!isset($_SESSION['user_id'])){
    if(!empty($_SESSION['guest'])&&!empty($_SESSION['guest_project'])){
      $pid=(int)$_SESSION['guest_project'];
      $st=$pdo->prepare("SELECT id,name FROM projects WHERE id=?");
      $st->execute([$pid]);$project=$st->fetch();
    }else{
      http_response_code(401);die('Unauthorized');
    }
  }else{
    $uid=(int)$_SESSION['user_id'];
    if(!$pid){$st=$pdo->prepare("SELECT id,name FROM projects WHERE user_id=? ORDER BY updated_at DESC LIMIT 1");$st->execute([$uid]);$project=$st->fetch();}
    else{$st=$pdo->prepare("SELECT id,name FROM projects WHERE id=? AND user_id=?");$st->execute([$pid,$uid]);$project=$st->fetch();}
  }
}
if(!$project){http_response_code(404);die('Проект не найден');}
$pid=(int)$project['id'];

$st=$pdo->prepare("SELECT full_name,birthday FROM employees WHERE project_id=? AND (show_in_list IS NULL OR show_in_list=1) AND birthday IS NOT NULL AND birthday<>'0000-00-00'");
$st->execute([$pid]);$employees=$st->fetchAll();

$MONTHS_RU=['Январь','Февраль','Март','Апрель','Май','Июнь','Июль','Август','Сентябрь','Октябрь','Ноябрь','Декабрь'];

// Группируем по месяцам
$byMonth=array_fill(0,12,[]);
foreach($employees as $e){
    $p=explode('-',$e['birthday']);
    if(count($p)!==3)continue;
    $m=(int)$p[1]-1;
    if($m<0||$m>11)continue;
    $byMonth[$m][]=['name'=>$e['full_name'],'day'=>(int)$p[2],'iso'=>$e['birthday']];
}
foreach($byMonth as $m=>&$arr){
    usort($arr,function($a,$b){return $a['day']-$b['day'];});
}
unset($arr);

$spreadsheet=new Spreadsheet();
$sheet=$spreadsheet->getActiveSheet();
$sheet->setTitle('Дни рождения');

$row=1;
$sheet->setCellValue('A'.$row,'№');
$sheet->setCellValue('B'.$row,'ФИО');
$sheet->setCellValue('C'.$row,'Дата рождения');
$sheet->getStyle('A1:C1')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A1:C1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
$sheet->getStyle('A1:C1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE0E0E0');
$sheet->getRowDimension(1)->setRowHeight(28);
$sheet->freezePane('A2');
$sheet->setAutoFilter('A1:C1');
$row++;

$counter=0;
foreach($byMonth as $m=>$arr){
    if(!$arr)continue;
    // Заголовок месяца
    $sheet->setCellValue('A'.$row,$MONTHS_RU[$m]);
    $sheet->mergeCells('A'.$row.':C'.$row);
    $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle('A'.$row)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFD9F0F0');
    $sheet->getStyle('A'.$row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setVertical(Alignment::VERTICAL_CENTER);
    $sheet->getRowDimension($row)->setRowHeight(22);
    $row++;

    foreach($arr as $e){
        $counter++;
        $p=explode('-',$e['iso']);
        $dateStr=$p[2].'.'.$p[1].'.'.$p[0];
        $sheet->setCellValue('A'.$row,$counter);
        $sheet->setCellValue('B'.$row,$e['name']);
        $sheet->setCellValue('C'.$row,$dateStr);
        $sheet->getRowDimension($row)->setRowHeight(22);
        $row++;
    }
}
$lastRow=$row-1;
if($lastRow>=2){
    $sheet->getStyle('A2:C'.$lastRow)->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER)
        ->setVertical(Alignment::VERTICAL_CENTER)
        ->setWrapText(true);

    // ФИО — по левому краю, с переносом
$sheet->getStyle('B2:B'.$lastRow)->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
    ->setVertical(Alignment::VERTICAL_CENTER)
    ->setWrapText(true);

    $sheet->getStyle('A1:C'.$lastRow)->getBorders()->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FF999999');

    // Ручная высота строк под перенос ФИО
    // Ширина B = 55 символов (примерно). Считаем строки по длине.
    for($r=2; $r<=$lastRow; $r++){
        $val=(string)$sheet->getCell('B'.$r)->getValue();
        $len=mb_strlen($val,'UTF-8');
        $charsPerLine=52; // при ширине 55 влезает ~52 русских символа
        $lines=max(1,(int)ceil($len/$charsPerLine));
        $sheet->getRowDimension($r)->setRowHeight($lines*15);
    }
}

$sheet->getColumnDimension('A')->setWidth(6);
$sheet->getColumnDimension('B')->setWidth(55);
$sheet->getColumnDimension('C')->setWidth(20);

$filename='birthdays_'.$pid.'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: max-age=0');
$writer=new Xlsx($spreadsheet);
$writer->save('php://output');
exit;