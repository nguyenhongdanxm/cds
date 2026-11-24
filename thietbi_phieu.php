<?php
require_once __DIR__.'/includes/form_profile.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_store.php';
require_login();
require_once __DIR__.'/includes/library_equipment_ledgers.php';
if(is_file(__DIR__.'/includes/teacher_signatures.php'))require_once __DIR__.'/includes/teacher_signatures.php';
$user=current_user()??[];$isAdmin=($user['role']??'')==='admin';
if(!$isAdmin&&!can_perm_level('tb.muontra','view')){http_response_code(403);exit('Không có quyền xem phiếu mượn thiết bị.');}
$batch=is_string($_GET['batch']??null)?trim($_GET['batch']):'';$ledger=is_string($_GET['ledger']??null)?trim($_GET['ledger']):'';
$data=load_json(DATA_PATH.'/library_equipment.json',['equipment_loans'=>[]]);$all=(array)($data['equipment_loans']??[]);$books=lib_eq_ledgers($all);if($ledger==='')$ledger=lib_eq_batch_ledger($all,$batch);$book=$books[$ledger]??null;$rows=$book['rows']??[];
if(!$rows){http_response_code(404);exit('Không tìm thấy phiếu mượn thiết bị.');}
$user=current_user()??[];$name=trim((string)($user['teacher_name']??$user['name']??''));$first=$rows[0];$isAdmin=($user['role']??'')==='admin';if(!$isAdmin&&!can_perm_level('tb.muontra','view')&&($first['created_by']??'')!==$name){http_response_code(403);exit('Không có quyền xem phiếu.');}
$currentYear=function_exists('csdl_year_current')?csdl_year_current():null;$year=(string)$book['school_year'];$borrower=(string)($first['borrower']??'');$subject=(string)($first['subject']??'');$group=(string)($first['teacher_group']??'');
function eqp_date($value): string {
    $value=trim((string)$value);if($value==='')return '';
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',substr($value,0,10));
    return $date&&$date->format('Y-m-d')===substr($value,0,10)?$date->format('d/m/Y'):'';
}
$classes=array_values(array_unique(array_filter(array_map(fn($r)=>trim((string)($r['teaching_grade']??'')),$rows),fn($v)=>$v!=='')));
$pages=array_chunk($rows,16);
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><title>Quản lý sử dụng thiết bị dạy học – <?=htmlspecialchars($borrower)?></title><style>
@page{size:A4 landscape;margin:12mm}*{box-sizing:border-box}body{font-family:"Times New Roman",serif;font-size:11pt;color:#000;margin:0}.toolbar{padding:12px;text-align:right;background:#eee}.toolbar button,.toolbar a{font:14px Arial;padding:8px 14px;margin-left:6px;text-decoration:none;border:1px solid #555;background:#fff;color:#111}.sheet{max-width:273mm;margin:18px auto;break-after:page;page-break-after:always}.sheet:last-child{break-after:auto;page-break-after:auto}.school{text-align:center;font-size:10pt}.title{text-align:center;font-size:16pt;margin:4mm 0}.info{display:flex;gap:7mm;align-items:baseline;margin:3mm 0;font-size:11pt}.teacher{flex:1.4}.subject{flex:1}.class{flex:.7}.dots{border-bottom:1px dotted #333;overflow-wrap:anywhere}table{width:100%;border-collapse:collapse;table-layout:fixed;font-size:10pt}thead{display:table-header-group}tr{break-inside:avoid;page-break-inside:avoid}th,td{border:1px solid #000;padding:1mm;text-align:center;overflow-wrap:anywhere}th{height:7mm}td .equipment-signature{width:100%;height:5mm;object-fit:contain}tbody td{height:6mm;border-top-style:dotted;border-bottom-style:dotted}.equipment{text-align:left}.review{margin-top:4mm;font-weight:bold;min-height:12mm}.page-number{text-align:right;font-size:9pt}.review-line{height:6mm;border-bottom:1px dotted #777}@media print{.toolbar{display:none}.sheet{margin:0;max-width:none}}@media screen and (max-width:900px){.sheet{min-width:950px;margin:12px}}
</style></head><body>
<div class="toolbar"><a href="<?=BASE_URL?>thuvien.php?tab=equipment_loans">Quay lại</a><button onclick="window.print()">In / Lưu PDF</button></div>
<?php $ordinal=0;foreach($pages as$pageIndex=>$pageRows):?>
<section class="sheet"><div class="school"><?=htmlspecialchars(cds_form_school_name())?> · Năm học <?=htmlspecialchars($year)?></div><h1 class="title">QUẢN LÝ SỬ DỤNG THIẾT BỊ DẠY HỌC</h1>
<div class="info"><div class="teacher">Họ và tên giáo viên: <span class="dots"><?=htmlspecialchars($borrower)?></span></div><div class="subject">Dạy môn: <span class="dots"><?=htmlspecialchars($subject)?></span></div><div class="class">Lớp: <span class="dots"><?=htmlspecialchars(implode(', ',$classes))?></span></div></div>
<table><colgroup><?php foreach([4,5,6,25,5,7,8,5,5,7,8,5,10]as$width):?><col style="width:<?=$width?>%"><?php endforeach;?></colgroup><thead><tr><th rowspan="2">Số<br>TT</th><th rowspan="2">Lớp</th><th rowspan="2">Tiết<br>theo<br>PPCT</th><th rowspan="2">TÊN THIẾT BỊ</th><th colspan="4">Mượn</th><th colspan="5">Trả</th></tr><tr><th>Số<br>lượng</th><th>Chất<br>lượng</th><th>Ngày<br>mượn</th><th>Ký</th><th>Số<br>lượng</th><th>Chất<br>lượng</th><th>Ngày<br>trả</th><th>Ký</th><th>Ghi chú</th></tr></thead><tbody>
<?php foreach($pageRows as$row):$ordinal++;$returned=trim((string)($row['returned_at']??''))!=='';?><tr><td><?=$ordinal?></td><td><?=htmlspecialchars((string)($row['teaching_grade']??''))?></td><td><?=htmlspecialchars((string)($row['lesson_period']??''))?></td><td class="equipment"><?=htmlspecialchars((string)($row['equipment_name']??''))?></td><td><?=(int)($row['quantity']??0)?></td><td><?=htmlspecialchars((string)($row['condition_out']??''))?></td><td><?=htmlspecialchars(eqp_date($row['borrowed_at']??''))?></td><td><?php if(function_exists('cds_ts_data_uri')&&($signatureSrc=cds_ts_data_uri($row['borrow_signature']??null))):?><img class="equipment-signature" src="<?=htmlspecialchars($signatureSrc)?>" alt="Chữ ký mượn"><?php endif;?></td><td><?=$returned?(int)($row['quantity']??0):''?></td><td><?=htmlspecialchars($returned?(string)($row['return_condition']??''):'')?></td><td><?=htmlspecialchars($returned?eqp_date($row['returned_at']):'')?></td><td><?php if($returned&&function_exists('cds_ts_data_uri')&&($signatureSrc=cds_ts_data_uri($row['return_signature']??null))):?><img class="equipment-signature" src="<?=htmlspecialchars($signatureSrc)?>" alt="Chữ ký trả"><?php endif;?></td><td><?=htmlspecialchars((string)($row['note']??''))?></td></tr>
<?php endforeach;for($i=count($pageRows);$i<16;$i++):?><tr><?php for($j=0;$j<13;$j++):?><td></td><?php endfor;?></tr><?php endfor;?></tbody></table>
<div class="review">Kiểm tra, nhận xét, đánh giá của Ban giám hiệu<div class="review-line"></div></div><?php if(count($pages)>1):?><div class="page-number">Trang <?=$pageIndex+1?> / <?=count($pages)?></div><?php endif;?></section>
<?php endforeach;?></body></html>
