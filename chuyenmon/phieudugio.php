<?php
$page_title='Phiếu đánh giá bài dạy';
require_once 'includes/functions.php';
require_once 'includes/observation_form.php';
require_login();

function obs_form_norm($value): string { $value=preg_replace('/\s+/u',' ',trim((string)$value));return function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value); }
function obs_form_names(array $record): array { $rows=$record['observers']??$record['assignees']??[];if(!is_array($rows))$rows=[$rows];return array_values(array_unique(array_filter(array_map(fn($v)=>trim((string)$v),$rows)))); }

$user=cds_user()??[];$isAdmin=($user['role']??'')==='admin';$isLeader=($user['role']??'')==='totruong'||in_array('totruong',(array)($user['groups']??[]),true);
$teacherName=trim((string)($user['teacher_name']??$user['name']??''));
$dataFile=DATA_PATH.'/observations.json';$records=load_json($dataFile,[]);if(!is_array($records))$records=[];
$id=trim((string)($_GET['id']??$_POST['id']??''));$recordIndex=null;
foreach($records as $index=>$row)if((string)($row['id']??'')===$id){$recordIndex=$index;break;}
if($recordIndex===null){http_response_code(404);exit('Không tìm thấy tiết dự giờ.');}
$record=$records[$recordIndex];$observers=obs_form_names($record);
$isObserved=obs_form_norm($record['teacher']??'')===obs_form_norm($teacherName);$canManage=$isAdmin||$isLeader;
$requestedObserver=trim((string)($_GET['observer']??$_POST['observer']??''));
$observer=($requestedObserver!==''&&in_array($requestedObserver,$observers,true)&&($canManage||$isObserved))?$requestedObserver:(in_array($teacherName,$observers,true)?$teacherName:($observers[0]??''));
$canEdit=$observer!==''&&obs_form_norm($observer)===obs_form_norm($teacherName)&&in_array($observer,$observers,true);
$canView=$canEdit||$canManage||$isObserved;
if(!$canView){http_response_code(403);exit('Bạn không có quyền xem phiếu dự giờ này.');}
$criteria=cm_observation_form_criteria();$evaluationKey=cm_observation_evaluation_key($observer);$evaluation=$record['evaluations'][$evaluationKey]??[];

if(empty($_SESSION['cm_observation_form_csrf']))$_SESSION['cm_observation_form_csrf']=bin2hex(random_bytes(20));$csrf=$_SESSION['cm_observation_form_csrf'];
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!hash_equals($csrf,(string)($_POST['csrf']??''))){http_response_code(403);exit('Phiên làm việc không hợp lệ.');}
    if(!$canEdit){http_response_code(403);exit('Chỉ người được phân công dự giờ mới được nhập phiếu của mình.');}
    $scores=(array)($_POST['scores']??[]);$clean=[];$total=0.0;
    foreach($criteria as $i=>$criterion){
        $raw=str_replace(',','.',trim((string)($scores[$i]??'')));
        if($raw===''||!is_numeric($raw)){flash('Vui lòng nhập đủ điểm của 12 tiêu chí.','danger');header('Location: '.BASE_URL.'phieudugio.php?id='.urlencode($id));exit;}
        $value=round((float)$raw,2);$max=(float)$criterion['max'];
        if($value<0||$value>$max){flash('Điểm tiêu chí '.($i+1).' phải từ 0 đến '.$max.'.','danger');header('Location: '.BASE_URL.'phieudugio.php?id='.urlencode($id));exit;}
        $clean[]=$value;$total+=$value;
    }
    $evaluation=['observer'=>$observer,'scores'=>$clean,'total'=>round($total,2),'rating'=>cm_observation_form_rating($total),'completed'=>true,'updated_at'=>date('c')];
    if(!isset($record['evaluations'])||!is_array($record['evaluations']))$record['evaluations']=[];
    $record['evaluations'][$evaluationKey]=$evaluation;cm_observation_form_recalculate($record);$record['reviewed_at']=date('c');$record['updated_at']=date('c');
    $records[$recordIndex]=$record;save_json($dataFile,array_values($records));flash('Đã lưu phiếu. Điểm trung bình của tiết dạy đã được cập nhật.');header('Location: '.BASE_URL.'phieudugio.php?id='.urlencode($id));exit;
}

$print=($_GET['print']??'')==='1';$scores=(array)($evaluation['scores']??[]);$total=$evaluation['total']??'';
require_once 'includes/header.php';
?>
<style>
.lesson-sheet{max-width:920px;margin:auto;background:#fff;padding:22px 30px;border-radius:12px;box-shadow:0 3px 16px rgba(15,23,42,.09);font-size:13px}.sheet-school{font-family:"Times New Roman",serif;font-size:13px;line-height:1.2}.sheet-title{text-align:center;font:bold 20px "Times New Roman",serif;margin:12px 0 7px}.sheet-meta{display:grid;grid-template-columns:1fr;gap:1px;margin-bottom:7px;font-family:"Times New Roman",serif;font-size:13px;line-height:1.15}.sheet-meta>div{border-bottom:1px dotted #555;padding:1px 0}.meta-triplet{display:grid!important;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px}.score-table{width:100%;border-collapse:collapse;font-family:"Times New Roman",serif;font-size:13px;line-height:1.15}.score-table th,.score-table td{border:1px solid #333;padding:3px 5px;vertical-align:middle}.score-table th{text-align:center}.score-table .group{width:104px;font-weight:bold;text-align:center}.score-table .criterion{text-align:left}.score-table .max,.score-table .score{width:74px;text-align:center}.score-table input{width:58px;text-align:center;padding:2px;border:1px solid #aab4c3;border-radius:4px;font-size:13px}.sheet-signatures{display:grid;grid-template-columns:1fr 1fr;text-align:center;margin-top:5px;font-family:"Times New Roman",serif;font-size:13px;line-height:1.15}.signature-date{min-height:16px;font-style:italic}.signature-space{height:28px}.signature-name{font-weight:bold}.sheet-actions{display:flex;flex-wrap:wrap;gap:8px;margin:0 auto 12px;max-width:920px}.sheet-observer-tabs{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:7px}.sheet-result{text-align:center;margin-top:4px;font:700 13px "Times New Roman",serif}.sheet-save{text-align:center;margin-top:7px}.pdf-mode{width:170mm!important;max-width:170mm!important;padding:0!important;border-radius:0!important;box-shadow:none!important;font-size:13px!important}.pdf-mode .no-print{display:none!important}.pdf-mode .sheet-school,.pdf-mode .sheet-meta,.pdf-mode .score-table,.pdf-mode .sheet-signatures,.pdf-mode .sheet-result{font-size:13px!important}.pdf-mode .score-table input{border:0;padding:0;font:inherit}
@media(max-width:767px){.lesson-sheet{padding:14px}.score-table{min-width:720px}.sheet-table-wrap{overflow-x:auto}.sheet-title{font-size:19px}.meta-triplet{gap:8px}}
@media print{@page{size:A4 portrait;margin:20mm}html,body{width:auto!important;height:auto!important;background:#fff!important}.navbar,.sidebar,.topbar,.app-header,.sheet-actions,footer,.no-print{display:none!important}body>.container,.container,.main-content,.content-wrapper,main{margin:0!important;padding:0!important;width:100%!important;max-width:none!important}.lesson-sheet{width:170mm!important;max-width:170mm!important;box-sizing:border-box;box-shadow:none;border-radius:0;padding:0;margin:0 auto;font-size:13px}.sheet-school{font-size:13px}.sheet-title{font-size:20px;margin:6px 0 4px}.sheet-meta{font-size:13px;gap:0;margin-bottom:4px;line-height:1.1}.sheet-meta>div{padding:1px 0}.score-table{font-size:13px;line-height:1.08}.score-table th,.score-table td{padding:2px 3px}.score-table input{border:0;padding:0;font:inherit}.sheet-result{font-size:13px;margin-top:2px}.sheet-signatures{font-size:13px;margin-top:3px}.signature-space{height:22px}.signature-date{min-height:15px}}
</style>
<div class="sheet-actions no-print"><a class="btn btn-outline-secondary" href="<?=BASE_URL?>dugio.php"><i class="bi bi-arrow-left"></i> Quay lại dự giờ</a><?php if(!empty($evaluation['completed'])):?><button class="btn btn-primary" type="button" onclick="window.print()"><i class="bi bi-printer"></i> In phiếu</button><button class="btn btn-success" type="button" id="downloadPdf"><i class="bi bi-file-earmark-arrow-down"></i> Tải PDF</button><?php endif;?></div>
<div class="lesson-sheet" id="lessonSheet">
<?php if(count($observers)>1):?><div class="sheet-observer-tabs no-print"><?php foreach($observers as $name):?><a class="btn btn-sm <?=obs_form_norm($name)===obs_form_norm($observer)?'btn-primary':'btn-outline-primary'?>" href="?id=<?=urlencode($id)?>&observer=<?=urlencode($name)?>"><?=e($name)?></a><?php endforeach;?></div><?php endif;?>
<div class="sheet-school">SỞ GIÁO DỤC VÀ ĐÀO TẠO TUYÊN QUANG<br><strong>TRƯỜNG PTDTNT THCS&amp;THPT XÍN MẦN</strong></div>
<div class="sheet-title">PHIẾU ĐÁNH GIÁ BÀI DẠY</div>
<div class="sheet-meta"><div><strong>Tên bài dạy:</strong> <?=e($record['lesson_title']??'')?></div><div><strong>Môn học/Hoạt động giáo dục:</strong> <?=e($record['subject']??'')?></div><div class="meta-triplet"><span><strong>Lớp:</strong> <?=e($record['class']??'')?></span><span><strong>Tiết:</strong> <?=(int)($record['timetable_period']??0)?></span><span><strong>Ngày:</strong> <?=!empty($record['date'])?date('d/m/Y',strtotime($record['date'])):''?></span></div><div><strong>Họ và tên giáo viên thực hiện:</strong> <?=e($record['teacher']??'')?></div></div>
<form method="post"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="id" value="<?=e($id)?>"><input type="hidden" name="observer" value="<?=e($observer)?>">
<div class="sheet-table-wrap"><table class="score-table"><thead><tr><th>Nội dung</th><th>Tiêu chí</th><th>Điểm tối đa</th><th>Điểm đánh giá</th></tr></thead><tbody>
<?php $lastGroup='';foreach($criteria as $i=>$criterion):$group=$criterion['group'];$rowspan=count(array_filter($criteria,fn($row)=>$row['group']===$group));?><tr><?php if($group!==$lastGroup):?><td class="group" rowspan="<?=$rowspan?>"><?=e($group)?></td><?php $lastGroup=$group;endif;?><td class="criterion"><?=e($criterion['text'])?></td><td class="max"><?=number_format($criterion['max'],2,',','.')?></td><td class="score"><?php if($canEdit):?><input type="number" name="scores[<?=$i?>]" min="0" max="<?=$criterion['max']?>" step="0.25" value="<?=isset($scores[$i])?e((string)$scores[$i]):''?>" required><?php else:?><?=isset($scores[$i])?number_format((float)$scores[$i],2,',','.'):'—'?><?php endif;?></td></tr><?php endforeach;?><tr><td colspan="2" class="text-end"><strong>Tổng điểm</strong></td><td class="max">20,00</td><td class="score"><strong><?=($total!=='')?number_format((float)$total,2,',','.'):'—'?></strong></td></tr></tbody></table></div>
<div class="sheet-result">Xếp loại: <?=e($evaluation['rating']??'Chưa hoàn thành')?></div><?php if($canEdit):?><div class="sheet-save no-print"><button class="btn btn-success"><i class="bi bi-floppy"></i> Lưu phiếu đánh giá</button></div><?php endif;?>
</form>
<div class="sheet-signatures"><div><div class="signature-date">&nbsp;</div><strong>NGƯỜI DẠY</strong><br><em>(Ký và ghi rõ họ tên)</em><div class="signature-space"></div><div class="signature-name"><?=e($record['teacher']??'')?></div></div><div><div class="signature-date">Pà Vầy Sủ, ngày <?=!empty($record['date'])?date('d',strtotime($record['date'])):'...'?> tháng <?=!empty($record['date'])?date('m',strtotime($record['date'])):'...'?> năm <?=!empty($record['date'])?date('Y',strtotime($record['date'])):'...'?></div><strong>NGƯỜI DỰ</strong><br><em>(Ký và ghi rõ họ tên)</em><div class="signature-space"></div><div class="signature-name"><?=e($observer)?></div></div></div>
</div>
<?php if(!empty($evaluation['completed'])):?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
document.getElementById('downloadPdf')?.addEventListener('click',async function(){
  const button=this,sheet=document.getElementById('lessonSheet'),oldText=button.innerHTML;
  if(!window.html2canvas||!window.jspdf){alert('Chưa tải được công cụ tạo PDF. Vui lòng kiểm tra kết nối mạng và thử lại.');return}
  button.disabled=true;button.innerHTML='<span class="spinner-border spinner-border-sm"></span> Đang tạo PDF';sheet.classList.add('pdf-mode');
  try{
    const canvas=await html2canvas(sheet,{scale:2,useCORS:true,backgroundColor:'#ffffff',logging:false});
    const pdf=new window.jspdf.jsPDF({orientation:'portrait',unit:'mm',format:'a4',compress:true});
    const pageWidth=210,pageHeight=297,margin=20,maxWidth=pageWidth-margin*2,maxHeight=pageHeight-margin*2;
    let width=maxWidth,height=canvas.height*width/canvas.width;if(height>maxHeight){height=maxHeight;width=canvas.width*height/canvas.height}
    pdf.addImage(canvas.toDataURL('image/jpeg',0.96),'JPEG',(pageWidth-width)/2,margin,width,height,undefined,'FAST');
    pdf.save('phieu-danh-gia-bai-day-<?=preg_replace('/[^a-zA-Z0-9_-]+/','-',trim((string)($record['id']??'du-gio')))?>.pdf');
  }catch(error){console.error(error);alert('Không thể tạo PDF. Vui lòng thử lại hoặc dùng nút In phiếu.')}finally{sheet.classList.remove('pdf-mode');button.disabled=false;button.innerHTML=oldText}
});
</script>
<?php endif;?>
<?php require_once 'includes/footer.php'; ?>
