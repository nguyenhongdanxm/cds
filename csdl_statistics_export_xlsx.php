<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_store.php';
require_once __DIR__ . '/includes/csdl_io.php';
require_login();
require_perm('csdl.statistics');
require_perm('csdl.export');

if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('Hosting chưa bật PHP ZIP (ZipArchive), chưa thể tạo file XLSX.');
}

$scope = (string)($_GET['stat_scope'] ?? 'all');
$status = (string)($_GET['stat_status'] ?? 'active');
$team = trim((string)($_GET['stat_team'] ?? ''));
$grade = trim((string)($_GET['stat_grade'] ?? ''));
$classId = trim((string)($_GET['stat_class'] ?? ''));
if (!in_array($scope, ['all','teachers','students'], true)) $scope = 'all';
if (!in_array($status, ['active','inactive','all'], true)) $status = 'active';

$teachers = csdl_teachers_all();
$classes = csdl_classes_all();
$students = csdl_students_all();

// Tôn trọng phạm vi lớp của tài khoản đang đăng nhập.
$allowedClassNames = allowed_classes();
if ($allowedClassNames !== null) {
    $allowedClassIds = [];
    foreach ($classes as $c) {
        if (in_array((string)($c['name'] ?? ''), $allowedClassNames, true)) $allowedClassIds[] = (string)($c['id'] ?? '');
    }
    $classes = array_values(array_filter($classes, fn($c) => in_array((string)($c['id'] ?? ''), $allowedClassIds, true)));
    $students = array_values(array_filter($students, fn($s) => in_array((string)($s['class_id'] ?? ''), $allowedClassIds, true)));
}
$classById = [];
foreach ($classes as $c) $classById[(string)($c['id'] ?? '')] = $c;

$filterStatus = static function(array $rows) use ($status): array {
    if ($status === 'all') return array_values($rows);
    $want = $status === 'active';
    return array_values(array_filter($rows, fn($r) => (!empty($r['active'])) === $want));
};
$ft = $filterStatus($teachers);
$fs = $filterStatus($students);
if ($team !== '') {
    $ft = array_values(array_filter($ft, fn($r) => trim((string)($r['to_chuyen_mon'] ?? ($r['pccm_group'] ?? ''))) === $team));
}
if ($grade !== '') {
    $fs = array_values(array_filter($fs, function($r) use ($classById, $grade) {
        $c = $classById[(string)($r['class_id'] ?? '')] ?? [];
        return (string)($c['grade'] ?? '') === $grade;
    }));
}
if ($classId !== '') $fs = array_values(array_filter($fs, fn($r) => (string)($r['class_id'] ?? '') === $classId));

function sx_xml($v): string { return htmlspecialchars((string)$v, ENT_QUOTES | ENT_XML1, 'UTF-8'); }
function sx_col_name(int $n): string { $s=''; while($n>0){$n--; $s=chr(65+($n%26)).$s; $n=intdiv($n,26);} return $s; }
function sx_cell($value, int $row, int $col, int $style=0): string {
    $ref=sx_col_name($col).$row; $styleAttr=$style ? ' s="'.$style.'"' : '';
    if (is_int($value) || is_float($value)) return '<c r="'.$ref.'"'.$styleAttr.' t="n"><v>'.$value.'</v></c>';
    return '<c r="'.$ref.'"'.$styleAttr.' t="inlineStr"><is><t xml:space="preserve">'.sx_xml($value).'</t></is></c>';
}
function sx_sheet(array $headers, array $rows, array $widths=[]): string {
    $cols='';
    foreach($headers as $i=>$h){$w=$widths[$i]??18;$cols.='<col min="'.($i+1).'" max="'.($i+1).'" width="'.$w.'" customWidth="1"/>';}
    $xml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols>'.$cols.'</cols><sheetData>';
    $r=1; $xml.='<row r="1" ht="26" customHeight="1">';
    foreach($headers as $i=>$h)$xml.=sx_cell($h,$r,$i+1,1); $xml.='</row>';
    foreach($rows as $row){$r++;$xml.='<row r="'.$r.'">';foreach(array_values($row) as $i=>$v)$xml.=sx_cell($v,$r,$i+1,0);$xml.='</row>';}
    $lastCol=sx_col_name(max(1,count($headers)));$xml.='</sheetData><autoFilter ref="A1:'.$lastCol.'1"/><freezePanes/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews></worksheet>';
    return $xml;
}
function sx_sheet_name(string $name, array &$used): string {
    $name=preg_replace('/[\\\/\?\*\[\]:]+/u',' ',trim($name)) ?: 'Sheet';
    $name=function_exists('mb_substr')?mb_substr($name,0,31,'UTF-8'):substr($name,0,31);
    $base=$name;$n=2;while(isset($used[$name])){$suffix=' '.$n++;$limit=31-strlen($suffix);$name=(function_exists('mb_substr')?mb_substr($base,0,$limit,'UTF-8'):substr($base,0,$limit)).$suffix;}$used[$name]=true;return $name;
}

$sheets=[]; $used=[];
$statusLabel=['active'=>'Đang hoạt động','inactive'=>'Đã nghỉ / ngừng','all'=>'Tất cả hồ sơ'][$status] ?? $status;
$scopeLabel=['all'=>'GV & HS','teachers'=>'Giáo viên','students'=>'Học sinh'][$scope] ?? $scope;
$filterRows=[
    ['Đối tượng',$scopeLabel],['Trạng thái',$statusLabel],['Tổ giáo viên',$team!==''?$team:'Tất cả'],
    ['Khối học sinh',$grade!==''?'Khối '.$grade:'Tất cả'],['Lớp học sinh',$classId!==''?($classById[$classId]['name']??$classId):'Tất cả'],
    ['Số GV/CBGVNV',count($ft)],['Số học sinh',count($fs)],['Xuất lúc',date('d/m/Y H:i')]
];
$sheets[]=['name'=>sx_sheet_name('Tổng quan bộ lọc',$used),'headers'=>['Nội dung','Giá trị'],'rows'=>$filterRows,'widths'=>[28,42]];

if ($scope !== 'students') {
    $rows=[];
    foreach($ft as $i=>$t){
        $flat=csdl_io_teacher_flat($t);
        $rows[]=[
            $i+1,$flat['code']??'',$flat['name']??'',$flat['cccd']??'',$flat['gender']??'',$flat['dob']??'',
            $flat['phone']??'',$flat['email']??'',$flat['ethnicity']??'',$flat['hometown']??'',$flat['address']??'',
            $flat['teaching_level']??'',$flat['specialty']??'',$flat['to_chuyen_mon']??'',$flat['chuc_vu']??'',
            $flat['kiem_nhiem_text']??'',$flat['join_date']??'',$flat['hang']??'',$flat['bac']??'',$flat['he_so']??'',
            $flat['active']??'',$flat['note']??''
        ];
    }
    $sheets[]=['name'=>sx_sheet_name('Chi tiết giáo viên',$used),'headers'=>['STT','Mã GV','Họ và tên','CCCD','Giới tính','Ngày sinh','SĐT','Email','Dân tộc','Quê quán','Địa chỉ','Cấp dạy','Chuyên môn','Tổ chuyên môn','Chức vụ','Kiêm nhiệm','Ngày vào ngành','Hạng','Bậc','Hệ số','Đang công tác','Ghi chú'],'rows'=>$rows,'widths'=>[7,12,26,18,10,13,15,24,12,24,28,12,20,20,20,28,14,10,10,10,14,28]];

    $teamCount=[];$specialtyCount=[];$genderCount=[];
    foreach($ft as $t){$tm=trim((string)($t['to_chuyen_mon']??($t['pccm_group']??'')))?:'Chưa xếp tổ';$sp=trim((string)($t['specialty']??''))?:'Chưa cập nhật';$g=trim((string)($t['gender']??''))?:'Chưa cập nhật';$teamCount[$tm]=($teamCount[$tm]??0)+1;$specialtyCount[$sp]=($specialtyCount[$sp]??0)+1;$genderCount[$g]=($genderCount[$g]??0)+1;}
    ksort($teamCount,SORT_NATURAL);ksort($specialtyCount,SORT_NATURAL);ksort($genderCount,SORT_NATURAL);
    $summary=[];foreach($teamCount as $k=>$v)$summary[]=['Tổ chuyên môn',$k,$v];foreach($specialtyCount as $k=>$v)$summary[]=['Chuyên môn',$k,$v];foreach($genderCount as $k=>$v)$summary[]=['Giới tính',$k,$v];
    $sheets[]=['name'=>sx_sheet_name('Thống kê giáo viên',$used),'headers'=>['Nhóm','Giá trị','Số lượng'],'rows'=>$summary,'widths'=>[22,32,12]];
}

if ($scope !== 'teachers') {
    $rows=[];$classStats=[];
    foreach($fs as $i=>$s){
        $flat=csdl_io_student_flat($s,$classes);$cn=$flat['class_name']??'';$cid=(string)($s['class_id']??'');$c=$classById[$cid]??[];$gr=$c['grade']??'';
        $rows[]=[$i+1,$flat['code']??'',$flat['name']??'',$flat['cccd']??'',$cn,$gr,$flat['gender']??'',$flat['dob']??'',$flat['ethnicity']??'',$flat['hometown']??'',$flat['address']??'',$flat['phone']??'',$flat['parent_name']??'',$flat['parent_phone']??'',$flat['boarder']??'',$flat['room_ktx']??'',$flat['meal_group']??'',$flat['active']??'',$flat['note']??''];
        $key=$cn!==''?$cn:'Chưa xếp lớp';if(!isset($classStats[$key]))$classStats[$key]=['grade'=>$gr,'total'=>0,'male'=>0,'female'=>0,'boarder'=>0];$classStats[$key]['total']++;$g=trim((string)($s['gender']??''));if($g==='Nam')$classStats[$key]['male']++;elseif(in_array($g,['Nữ','Nu','nu'],true))$classStats[$key]['female']++;if(!empty($s['boarder']))$classStats[$key]['boarder']++;
    }
    $sheets[]=['name'=>sx_sheet_name('Chi tiết học sinh',$used),'headers'=>['STT','Mã HS','Họ và tên','CCCD','Lớp','Khối','Giới tính','Ngày sinh','Dân tộc','Quê quán','Địa chỉ','SĐT HS','Họ tên PH','SĐT PH','Nội trú','Phòng KTX','Nhóm ăn','Đang học','Ghi chú'],'rows'=>$rows,'widths'=>[7,12,26,18,10,8,10,13,12,24,28,15,24,15,10,14,12,12,28]];
    uksort($classStats,'strnatcasecmp');$classRows=[];foreach($classStats as $cn=>$x)$classRows[]=[$x['grade'],$cn,$x['total'],$x['male'],$x['female'],$x['boarder']];
    $sheets[]=['name'=>sx_sheet_name('Theo lớp',$used),'headers'=>['Khối','Lớp','Sĩ số','Nam','Nữ','Nội trú'],'rows'=>$classRows,'widths'=>[10,12,12,10,10,12]];
}

$tmp=tempnam(sys_get_temp_dir(),'csdl_xlsx_');
$zip=new ZipArchive();
if($zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)exit('Không tạo được file XLSX tạm.');
$sheetEntries='';$rels='';$contentOverrides='';
foreach($sheets as $i=>$sheet){$n=$i+1;$zip->addFromString('xl/worksheets/sheet'.$n.'.xml',sx_sheet($sheet['headers'],$sheet['rows'],$sheet['widths']??[]));$sheetEntries.='<sheet name="'.sx_xml($sheet['name']).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';$rels.='<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';$contentOverrides.='<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';}
$styleRid=count($sheets)+1;$rels.='<Relationship Id="rId'.$styleRid.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
$zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$contentOverrides.'</Types>');
$zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
$zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$sheetEntries.'</sheets></workbook>');
$zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>');
$zip->addFromString('xl/styles.xml','<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="10"/><name val="Arial"/></font><font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Arial"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF1F4E79"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf></cellXfs></styleSheet>');
$zip->close();
$filename='thong-ke-gv-hs-'.date('Ymd-His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Content-Length: '.filesize($tmp));
header('Cache-Control: no-store');
readfile($tmp);@unlink($tmp);exit;
