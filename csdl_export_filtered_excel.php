<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csdl_io.php';
require_login();
require_perm('csdl.export');

$entity = trim((string)($_GET['entity'] ?? ''));
if (!in_array($entity, ['teachers','classes','students'], true)) {
    http_response_code(400); exit('Đối tượng không hợp lệ.');
}
$entityPermission = ['teachers'=>'csdl.teachers','classes'=>'csdl.classes','students'=>'csdl.students'][$entity];
require_perm($entityPermission);

$q = trim((string)($_GET['q'] ?? ''));
$grade = trim((string)($_GET['grade'] ?? ''));
$classId = trim((string)($_GET['class'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$split = !empty($_GET['split']);

$teachers = csdl_teachers_all();
$classes = csdl_classes_all();
$students = csdl_students_all();

// Tôn trọng phạm vi lớp của tài khoản đang đăng nhập.
$allowedClassNames = allowed_classes();
if ($allowedClassNames !== null) {
    $allowedIds = [];
    foreach ($classes as $c) if (in_array((string)($c['name'] ?? ''), $allowedClassNames, true)) $allowedIds[] = (string)($c['id'] ?? '');
    $classes = array_values(array_filter($classes, fn($c)=>in_array((string)($c['id']??''), $allowedIds, true)));
    $students = array_values(array_filter($students, fn($s)=>in_array((string)($s['class_id']??''), $allowedIds, true)));
}

function csdl_xls_key($value) {
    $value = trim((string)$value);
    if ($value === '') return '';
    if (function_exists('csdl_text_sort_key')) return csdl_text_sort_key($value);
    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    return preg_replace('/\s+/u', ' ', $value) ?? $value;
}
function csdl_xls_xml($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}
function csdl_xls_sheet_name($name, $used = []) {
    $name = preg_replace('/[\\\/?*\[\]:]+/u', ' ', trim((string)$name)) ?: 'Sheet';
    $name = trim($name);
    if (function_exists('mb_substr')) $name = mb_substr($name, 0, 31, 'UTF-8'); else $name = substr($name, 0, 31);
    if ($name === '') $name = 'Sheet';
    $base = $name; $n = 2;
    while (isset($used[$name])) {
        $suffix = ' '.$n++;
        $limit = 31 - strlen($suffix);
        $name = (function_exists('mb_substr') ? mb_substr($base,0,$limit,'UTF-8') : substr($base,0,$limit)) . $suffix;
    }
    return $name;
}
function csdl_xls_cell($value, $style = '') {
    $styleAttr = $style !== '' ? ' ss:StyleID="'.csdl_xls_xml($style).'"' : '';
    return '<Cell'.$styleAttr.'><Data ss:Type="String">'.csdl_xls_xml($value).'</Data></Cell>';
}
function csdl_xls_worksheet($name, array $headers, array $rows) {
    $xml = '<Worksheet ss:Name="'.csdl_xls_xml($name).'"><Table>';
    foreach ($headers as $h) $xml .= '<Column ss:AutoFitWidth="1" ss:Width="110"/>';
    $xml .= '<Row>';
    foreach ($headers as $h) $xml .= csdl_xls_cell($h, 'Header');
    $xml .= '</Row>';
    foreach ($rows as $row) {
        $xml .= '<Row>';
        foreach ($row as $cell) $xml .= csdl_xls_cell($cell);
        $xml .= '</Row>';
    }
    $xml .= '</Table><WorksheetOptions xmlns="urn:schemas-microsoft-com:office:excel"><FreezePanes/><FrozenNoSplit/><SplitHorizontal>1</SplitHorizontal><TopRowBottomPane>1</TopRowBottomPane><ProtectObjects>False</ProtectObjects><ProtectScenarios>False</ProtectScenarios></WorksheetOptions></Worksheet>';
    return $xml;
}

$schema = csdl_schema_entity($entity);
$flatRows = [];
$title = '';

if ($entity === 'teachers') {
    $list = $teachers;
    if ($q !== '') {
        $needle = csdl_xls_key($q);
        $list = array_values(array_filter($list, function($r) use($needle){
            return strpos(csdl_xls_key(implode(' ',[$r['name']??'',$r['code']??'',$r['cccd']??'',$r['phone']??'',$r['email']??'',$r['specialty']??'',$r['to_chuyen_mon']??$r['pccm_group']??''])), $needle)!==false;
        }));
    }
    if ($status === 'active') $list = array_values(array_filter($list, fn($r)=>!empty($r['active'])));
    elseif ($status === 'inactive') $list = array_values(array_filter($list, fn($r)=>empty($r['active'])));
    foreach ($list as $r) $flatRows[] = csdl_io_teacher_flat($r);
    $title = 'Giáo viên';
} elseif ($entity === 'classes') {
    $list = $classes;
    if ($q !== '') {
        $needle=csdl_xls_key($q);
        $list=array_values(array_filter($list,fn($r)=>strpos(csdl_xls_key(implode(' ',[$r['name']??'',$r['grade']??'',$r['level']??'',$r['room']??''])),$needle)!==false));
    }
    if ($grade !== '') $list=array_values(array_filter($list,fn($r)=>(string)($r['grade']??'')===$grade));
    if (function_exists('csdl_sort_classes')) csdl_sort_classes($list);
    foreach ($list as $r) $flatRows[] = csdl_io_class_flat($r, $teachers);
    $title = 'Lớp';
} else {
    $list = $students;
    $classMap=[]; foreach($classes as $c)$classMap[(string)($c['id']??'')]=(string)($c['name']??'');
    if ($q !== '') {
        $needle=csdl_xls_key($q);
        $list=array_values(array_filter($list,function($r)use($needle,$classMap){$text=implode(' ',[$r['name']??'',$r['code']??'',$r['cccd']??'',$r['phone']??'',$r['parent_name']??'',$classMap[(string)($r['class_id']??'')]??'']);return strpos(csdl_xls_key($text),$needle)!==false;}));
    }
    if ($classId !== '') $list=array_values(array_filter($list,fn($r)=>(string)($r['class_id']??'')===$classId));
    if ($status === 'active') $list=array_values(array_filter($list,fn($r)=>!empty($r['active'])));
    elseif ($status === 'inactive') $list=array_values(array_filter($list,fn($r)=>empty($r['active'])));
    elseif ($status === 'boarder') $list=array_values(array_filter($list,fn($r)=>!empty($r['boarder'])));
    if (function_exists('csdl_sort_students')) csdl_sort_students($list,$classMap);
    foreach ($list as $r) $flatRows[] = csdl_io_student_flat($r, $classes);
    $title = 'Học sinh';
}

$filename = 'csdl-ket-qua-loc-'.($entity==='teachers'?'giao-vien':($entity==='classes'?'lop':'hoc-sinh')).'-'.date('Ymd-His').'.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="'.$filename.'"');
header('Cache-Control: no-store, no-cache, must-revalidate');

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<?mso-application progid="Excel.Sheet"?>';
echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
echo '<Styles><Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Center"/><Font ss:FontName="Arial" ss:Size="10"/></Style><Style ss:ID="Header"><Font ss:FontName="Arial" ss:Size="10" ss:Bold="1"/><Interior ss:Color="#D9EAF7" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center" ss:WrapText="1"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style></Styles>';

$keys = array_keys($schema);
$headers = array_merge(['STT'], array_map(fn($k)=>$schema[$k]['label']??$k, $keys));
$rows = [];
foreach ($flatRows as $i=>$flat) { $row=[$i+1]; foreach($keys as $k)$row[]=$flat[$k]??''; $rows[]=$row; }
echo csdl_xls_worksheet('Tổng hợp', $headers, $rows);

if ($split) {
    $used=['Tổng hợp'=>true];
    foreach ($keys as $k) {
        $sheetName=csdl_xls_sheet_name($schema[$k]['label']??$k,$used);$used[$sheetName]=true;
        $fieldRows=[];
        foreach($flatRows as $i=>$flat)$fieldRows[]=[$i+1,$flat['name']??($flat['code']??($flat['class_name']??'')),$flat[$k]??''];
        echo csdl_xls_worksheet($sheetName,['STT','Đối tượng',$schema[$k]['label']??$k],$fieldRows);
    }
}

echo '</Workbook>';
exit;
