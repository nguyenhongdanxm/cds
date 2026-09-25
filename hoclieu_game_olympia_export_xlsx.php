<?php
require_once __DIR__.'/includes/auth.php';
require_login();
require_once __DIR__.'/includes/olympia_store.php';

if(!olympia_admin()){
    http_response_code(403);
    exit('Chỉ quản trị được xuất thống kê Olympia.');
}
if(!class_exists('ZipArchive')){
    http_response_code(500);
    exit('Máy chủ chưa bật ZipArchive để tạo tệp Excel.');
}

olympia_ensure_schema();

function ox_xml(string $value): string {
    return htmlspecialchars($value,ENT_XML1|ENT_QUOTES,'UTF-8');
}
function ox_col(int $number): string {
    $name='';
    while($number>0){$number--;$name=chr(65+($number%26)).$name;$number=intdiv($number,26);}
    return $name;
}
function ox_cell($value,int $row,int $column,int $style): string {
    $ref=ox_col($column).$row;
    if(is_int($value)||is_float($value))return '<c r="'.$ref.'" s="'.$style.'"><v>'.$value.'</v></c>';
    return '<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.ox_xml((string)$value).'</t></is></c>';
}
function ox_sheet(array $headers,array $rows,array $widths,string $title,string $subtitle,int $resultColumn=0): string {
    $last=ox_col(count($headers));$lastRow=max(4,4+count($rows));$xml=[];
    $xml[]='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
    $xml[]='<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
    $xml[]='<dimension ref="A1:'.$last.$lastRow.'"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
    $xml[]='<cols>';foreach($widths as $index=>$width)$xml[]='<col min="'.($index+1).'" max="'.($index+1).'" width="'.$width.'" customWidth="1"/>';$xml[]='</cols><sheetData>';
    $xml[]='<row r="1" ht="28" customHeight="1">'.ox_cell($title,1,1,1).'</row>';
    $xml[]='<row r="2" ht="24" customHeight="1">'.ox_cell($subtitle,2,1,2).'</row><row r="3" ht="8" customHeight="1"/>';
    $head='';foreach($headers as $index=>$header)$head.=ox_cell($header,4,$index+1,3);$xml[]='<row r="4" ht="34" customHeight="1">'.$head.'</row>';
    foreach($rows as $index=>$values){$rowNumber=$index+5;$base=$index%2===0?4:5;$cells='';foreach($values as $column=>$value){$style=$base;if($resultColumn===$column+1){$style=$value==='Đúng'?6:7;}$cells.=ox_cell($value,$rowNumber,$column+1,$style);}$xml[]='<row r="'.$rowNumber.'" ht="30" customHeight="1">'.$cells.'</row>';}
    $xml[]='</sheetData><mergeCells count="2"><mergeCell ref="A1:'.$last.'1"/><mergeCell ref="A2:'.$last.'2"/></mergeCells><autoFilter ref="A4:'.$last.$lastRow.'"/><pageMargins left="0.3" right="0.3" top="0.45" bottom="0.45" header="0.2" footer="0.2"/><pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/></worksheet>';
    return implode('',$xml);
}
function ox_applies(string $scope,string $grade): bool {
    if($scope===''||$scope==='all')return true;
    return in_array($grade,array_map('trim',explode(',',$scope)),true);
}

$weeks=olympia_weeks();
if(!$weeks){http_response_code(404);exit('Chưa có tuần Olympia để xuất.');}
$positions=[];foreach($weeks as $index=>$week)$positions[(string)$week['id']]=$index;
$from=trim((string)($_GET['rank_from']??$weeks[0]['id']));
$to=trim((string)($_GET['rank_to']??$from));
if(!isset($positions[$from]))$from=(string)$weeks[0]['id'];
if(!isset($positions[$to]))$to=$from;
$start=min($positions[$from],$positions[$to]);$end=max($positions[$from],$positions[$to]);
$selected=array_slice($weeks,$start,$end-$start+1);$weekIds=array_map(fn($w)=>(string)$w['id'],$selected);
$weekMap=[];foreach($selected as $week)$weekMap[(string)$week['id']]=$week;
$marks=implode(',',array_fill(0,count($weekIds),'?'));$db=olympia_db();

$statement=$db->prepare("SELECT * FROM cds_olympia_questions WHERE week_id IN ($marks) ORDER BY week_id,sort_order,id");
$statement->execute($weekIds);$questionsByWeek=[];foreach($statement as $row)$questionsByWeek[(string)$row['week_id']][]=$row;
$statement=$db->prepare("SELECT * FROM cds_olympia_week_classes WHERE week_id IN ($marks) ORDER BY week_id,class_name");
$statement->execute($weekIds);$weekClasses=[];foreach($statement as $row)$weekClasses[(string)$row['week_id']][]=$row;
$statement=$db->prepare("SELECT * FROM cds_olympia_scores WHERE week_id IN ($marks) ORDER BY week_id,class_name,student_name,marked_at");
$statement->execute($weekIds);$scoreMap=[];$historicStudents=[];
foreach($statement as $row){$wid=(string)$row['week_id'];$cid=(string)$row['class_id'];$qid=(string)$row['question_id'];$sid=(string)$row['student_id'];$scoreMap[$wid][$cid][$qid][$sid]=$row;$historicStudents[$wid][$cid][$sid]=['id'=>$sid,'name'=>(string)$row['student_name']];}

$classMap=olympia_class_map();$rosters=[];
foreach($weekClasses as $wid=>$classRows)foreach($classRows as $classRow){$cid=(string)$classRow['class_id'];$students=[];foreach(olympia_students($cid) as $student)$students[(string)$student['id']]=['id'=>(string)$student['id'],'name'=>(string)$student['name']];foreach($historicStudents[$wid][$cid]??[] as $sid=>$student)$students[$sid]=$student;uasort($students,fn($a,$b)=>strnatcasecmp($a['name'],$b['name']));$rosters[$wid][$cid]=$students;}

$summary=[];$details=[];$summaryNo=0;$detailNo=0;
foreach($selected as $week){
    $wid=(string)$week['id'];$weekName=trim((string)($week['week_label']??''));if($weekName==='')$weekName=(string)$week['title'];
    $roundCounters=[];
    foreach($questionsByWeek[$wid]??[] as $question){$round=(string)$question['stage_name'];$roundCounters[$round]=($roundCounters[$round]??0)+1;$questionNo=$roundCounters[$round];
        foreach($weekClasses[$wid]??[] as $classRow){$cid=(string)$classRow['class_id'];$class=$classMap[$cid]??[];$className=(string)($classRow['class_name']??$class['name']??'');$grade=olympia_class_grade($class);if(!ox_applies((string)$question['grade_scope'],$grade))continue;
            $students=$rosters[$wid][$cid]??[];$correct=$scoreMap[$wid][$cid][(string)$question['id']]??[];$total=count($students);$correctCount=count($correct);$wrong=max(0,$total-$correctCount);$rightRate=$total?round($correctCount*100/$total,1):0;$wrongRate=$total?round($wrong*100/$total,1):0;
            $summary[]=[++$summaryNo,$weekName,$className,$grade,$round,$questionNo,(string)$question['question_text'],(string)$question['answer_text'],(int)$question['points'],$total,$correctCount,$wrong,number_format($rightRate,1,',','.').'%',number_format($wrongRate,1,',','.').'%'];
            foreach($students as $sid=>$student){$score=$correct[$sid]??null;$isCorrect=$score!==null;$details[]=[++$detailNo,$weekName,$className,$sid,(string)$student['name'],$round,$questionNo,(string)$question['question_text'],(string)$question['answer_text'],$isCorrect?'Đúng':'Sai hoặc không trả lời',$isCorrect?(int)$score['points']:0,$isCorrect?(string)$score['marked_by']:'',$isCorrect?date('d/m/Y H:i',strtotime((string)$score['marked_at'])):''];}
        }
    }
}

$studentRanks=[];$rank=0;foreach(olympia_ranking_students($weekIds) as $row)$studentRanks[]=[++$rank,(string)$row['student_name'],(string)$row['class_name'],(int)$row['weeks'],(int)$row['correct'],(int)$row['points']];
$classRanks=[];$rank=0;foreach(olympia_ranking_classes($weekIds) as $row)$classRanks[]=[++$rank,(string)$row['class_name'],(int)$row['participants'],(int)$row['class_size'],(int)$row['correct'],(int)$row['points'],number_format((float)$row['average'],2,',','.')];

$rangeLabel=trim((string)($selected[0]['week_label']??$selected[0]['title']??'')).' đến '.trim((string)($selected[count($selected)-1]['week_label']??$selected[count($selected)-1]['title']??''));
$school=defined('SCHOOL_NAME')?SCHOOL_NAME:'Trường PTDTNT THCS&THPT Xín Mần';
$sheets=[
 ['Tổng hợp câu hỏi',['STT','Tuần','Lớp','Khối','Vòng','Câu','Nội dung câu hỏi','Đáp án đúng','Điểm','Sĩ số','Đúng','Sai/không TL','Tỷ lệ đúng','Tỷ lệ sai'], $summary,[7,15,10,8,22,7,48,28,8,9,9,14,13,13],0],
 ['Chi tiết học sinh',['STT','Tuần','Lớp','Mã HS','Họ và tên','Vòng','Câu','Nội dung câu hỏi','Đáp án đúng','Kết quả','Điểm đạt','Người chấm','Thời gian'], $details,[7,15,10,14,24,22,7,48,28,23,10,20,18],10],
 ['Xếp hạng học sinh',['Hạng','Họ và tên','Lớp','Số tuần','Câu đúng','Tổng điểm'], $studentRanks,[9,28,12,12,12,14],0],
 ['Xếp hạng lớp',['Hạng','Lớp','HS tham gia','Sĩ số','Câu đúng','Tổng điểm','Điểm bình quân'], $classRanks,[9,14,16,12,13,14,18],0],
];

$styles='<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="4"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="16"/><name val="Calibri"/></font><font><i/><color rgb="FF365F7D"/><sz val="10"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="8"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF123E66"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFDCEEFF"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0B69A3"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF4F9FD"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFDFF3E4"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFFCE1E1"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="2"><border/><border><left style="thin"><color rgb="FFD4E1EA"/></left><right style="thin"><color rgb="FFD4E1EA"/></right><top style="thin"><color rgb="FFD4E1EA"/></top><bottom style="thin"><color rgb="FFD4E1EA"/></bottom></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="8"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"><alignment horizontal="center" vertical="center"/></xf><xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="5" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="6" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf><xf numFmtId="0" fontId="0" fillId="7" borderId="1" xfId="0" applyFill="1" applyBorder="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';

$tmp=tempnam(sys_get_temp_dir(),'olympia_');$zip=new ZipArchive();if($zip->open($tmp,ZipArchive::OVERWRITE)!==true)exit('Không thể tạo tệp Excel.');
$overrides='';$workbookSheets='';$relationships='';
foreach($sheets as $index=>$sheet){$number=$index+1;$overrides.='<Override PartName="/xl/worksheets/sheet'.$number.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';$workbookSheets.='<sheet name="'.ox_xml($sheet[0]).'" sheetId="'.$number.'" r:id="rId'.$number.'"/>';$relationships.='<Relationship Id="rId'.$number.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$number.'.xml"/>';$zip->addFromString('xl/worksheets/sheet'.$number.'.xml',ox_sheet($sheet[1],$sheet[2],$sheet[3],'THỐNG KÊ CHI TIẾT ĐƯỜNG LÊN ĐỈNH OLYMPIA',$school.' · '.$rangeLabel.' · Sai gồm học sinh không được ghi nhận trả lời đúng.',$sheet[4]));}
$zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'.$overrides.'</Types>');
$zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
$zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>'.$workbookSheets.'</sheets></workbook>');
$zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'<Relationship Id="rId'.(count($sheets)+1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
$zip->addFromString('xl/styles.xml',$styles);$zip->close();

$filename='thong-ke-olympia-'.date('Ymd-His').'.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$filename.'"');header('Content-Length: '.filesize($tmp));header('Cache-Control: no-store');readfile($tmp);unlink($tmp);exit;
