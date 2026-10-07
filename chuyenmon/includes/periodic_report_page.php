<?php
require_once __DIR__.'/periodic_report.php';
require_once __DIR__.'/periodic_report_docx.php';
require_once __DIR__.'/observation_form.php';
$user=cds_user()??[];$scope=pr_scope($user,get_teachers_sorted());$csrf=cds_drive_csrf_token();
$allReports=array_merge(cm_docs_by_section('bc_dinhky'),cm_docs_by_section('bc_thang'));
$visibleReports=array_values(array_filter($allReports,fn($r)=>pr_visible($r,$scope)));
$id=trim((string)($_POST['id']??$_GET['id']??''));$existing=null;
foreach($allReports as$row)if(($row['id']??'')===$id){$existing=$row;break;}
if($id!==''&&(!$existing||!pr_visible($existing,$scope))){http_response_code(404);exit('Không tìm thấy báo cáo trong phạm vi tổ của tài khoản.');}
$group=$scope['wide']?trim((string)($_POST['report_group']??$_GET['group']??$existing['report_group']??$scope['own'])):$scope['own'];
if(!in_array($group,$scope['groups'],true))$group=$scope['groups'][0]??'';
$month=trim((string)($_GET['month']??$existing['month']??date('Y-m')));if(!pr_month($month))$month=date('Y-m');
$error='';$submitted=null;$view=(string)($_GET['view']??'');
if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
    if(!hash_equals($csrf,(string)($_POST['csrf']??''))){http_response_code(403);exit('Phiên làm việc hết hiệu lực. Vui lòng tải lại trang.');}
    if(!$scope['write']){http_response_code(403);exit('Chỉ TTCM của tổ, BGH và quản trị có quyền soạn báo cáo.');}
    $action=(string)($_POST['action']??'');
    try {
        if($action==='periodic_save'){
            if($scope['wide']&&!in_array(trim((string)($_POST['report_group']??'')),$scope['groups'],true))throw new RuntimeException('Tổ chuyên môn không hợp lệ.');
            if($group==='')throw new RuntimeException('Chưa gán tổ chuyên môn. Vui lòng cập nhật tổ của giáo viên trong Phân công – Danh mục.');
            if($existing&&(string)($_POST['revision']??'')!==(string)($existing['report_revision']??$existing['updated_at']??$existing['created_at']??''))throw new RuntimeException('Báo cáo đã được cập nhật ở phiên khác. Nội dung vừa nhập vẫn ở dưới; hãy mở bản mới trong tab khác để đối chiếu trước khi lưu lại.');
            $month=trim((string)($_POST['month']??''));$next=trim((string)($_POST['next_month']??''));$date=trim((string)($_POST['date']??''));
            if(!pr_month($month)||!pr_month($next)||!pr_date($date))throw new RuntimeException('Tháng báo cáo, tháng kế hoạch hoặc ngày lập không hợp lệ.');
            $report=['id'=>$id,'section'=>'bc_dinhky','kind'=>'report','report_version'=>1,'report_group'=>$group,'month'=>$month,'next_month'=>$next,'date'=>$date,'report_sections'=>[],'report_revision'=>bin2hex(random_bytes(12)),'by'=>$scope['name']];
            foreach(['school','place','number','recipient','signer']as$key){$report[$key]=trim((string)($_POST[$key]??''));if(strlen($report[$key])>1000)throw new RuntimeException('Thông tin đầu báo cáo quá dài.');}
            if($report['school']===''||$report['place']===''||$report['recipient']===''||$report['signer']==='')throw new RuntimeException('Vui lòng nhập trường, địa danh, nơi nhận và người ký.');
            foreach(pr_sections()as$key=>$label)$report['report_sections'][$key]=pr_clean((string)($_POST['report_sections'][$key]??''));
            $report['title']='Báo cáo chuyên môn tháng '.substr($month,5,2).'/'.substr($month,0,4).' — '.$group;
            $report['content']=trim(strip_tags(implode("\n",$report['report_sections'])));
            $sameSnapshot=$existing&&($existing['month']??'')===$month&&($existing['report_group']??'')===$group&&!empty($existing['snapshot']);
            $report['snapshot']=$sameSnapshot&&empty($_POST['refresh_snapshot'])?$existing['snapshot']:pr_snapshot($group,$month,get_teachers_sorted());
            // Mỗi bản Drive gắn đúng nội dung đã lưu; bản cũ không bị ghi đè hay xóa.
            $report['drive_path']='';$report['drive_saved_at']='';
            $savedId=pr_store($report,$existing?(string)($_POST['revision']??''):null);flash('Đã lưu báo cáo và phụ lục của '.$group.'.');
            header('Location: '.BASE_URL.'baocao.php?tab=dinhky&id='.rawurlencode($savedId).'&view=preview');exit;
        }
        if($action==='periodic_drive'){
            if(!$existing||empty($existing['report_version']))throw new RuntimeException('Hãy lưu bản báo cáo hoàn chỉnh trước.');
            if((string)($_POST['revision']??'')!==(string)($existing['report_revision']??''))throw new RuntimeException('Báo cáo đã thay đổi. Hãy tải lại trang trước khi lưu lên Drive.');
            $bytes=pr_docx($existing);$type=cds_drive_type_for_action(cds_drive_page_action(),'plans');
            $result=cds_drive_upload_bytes($bytes,$existing['title'].'.docx','application/vnd.openxmlformats-officedocument.wordprocessingml.document',$type);
            if(empty($result['ok']))throw new RuntimeException((string)($result['message']??'Không lưu được lên Drive.'));
            pr_store(['id'=>$id,'drive_path'=>(string)$result['path'],'drive_saved_at'=>date('c')],(string)$existing['report_revision']);flash('Đã lưu bản Word hoàn chỉnh vào Google Drive.');header('Location: '.BASE_URL.'baocao.php?tab=dinhky&id='.rawurlencode($id).'&view=preview');exit;
        }
        if($action==='periodic_delete'){
            if(!$scope['wide']||!cds_can_feature('cm.baocao.dinhky','delete')){http_response_code(403);exit('Chỉ BGH hoặc quản trị có quyền xóa báo cáo.');}
            if(!$existing)throw new RuntimeException('Báo cáo không tồn tại.');cm_doc_delete($id);flash('Đã xóa báo cáo khỏi CDS. Tệp trên Drive được giữ nguyên.','warning');header('Location: '.BASE_URL.'baocao.php?tab=dinhky');exit;
        }
        throw new RuntimeException('Thao tác không hợp lệ.');
    }catch(Throwable $e){$error=$e->getMessage();$view='';if($action==='periodic_save'){
            if($scope['wide']&&!in_array(trim((string)($_POST['report_group']??'')),$scope['groups'],true))throw new RuntimeException('Tổ chuyên môn không hợp lệ.');$submitted=$_POST;$submitted['report_sections']=[];foreach(pr_sections()as$key=>$label){try{$submitted['report_sections'][$key]=pr_clean((string)($_POST['report_sections'][$key]??''));}catch(Throwable $ignored){$submitted['report_sections'][$key]='';}}}else $view='preview';}
}
if(in_array($view,['print','embed','download'],true)){
    if(!$existing||empty($existing['report_version'])){http_response_code(400);exit('Hãy chuyển báo cáo cũ sang mẫu mới và lưu trước khi xuất.');}
    if($view==='print'||$view==='embed'){echo pr_html($existing,$view==='print');exit;}
    try{$bytes=pr_docx($existing);header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');header('Content-Disposition: attachment; filename="bao-cao-'.$existing['month'].'.docx"; filename*=UTF-8\'\''.rawurlencode($existing['title'].'.docx'));header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');echo $bytes;exit;}catch(Throwable $e){$error=$e->getMessage();$view='preview';}
}
$report=$submitted??$existing??['id'=>'','report_group'=>$group,'month'=>$month,'next_month'=>date('Y-m',strtotime($month.'-01 +1 month')),'date'=>date('Y-m-d'),'school'=>SCHOOL_NAME,'place'=>function_exists('school_place')?school_place():'Xín Mần','number'=>'','recipient'=>'Ban Giám hiệu nhà trường','signer'=>$scope['name'],'report_sections'=>[]];
if(empty($report['report_version'])&&$existing){$report=array_merge(['next_month'=>date('Y-m',strtotime($month.'-01 +1 month')),'school'=>SCHOOL_NAME,'place'=>'Xín Mần','number'=>'','recipient'=>'Ban Giám hiệu nhà trường','signer'=>$scope['name']],$report);$report['month']=$month;$report['report_sections']=$submitted['report_sections']??['results'=>'<p>'.nl2br(pr_escape($existing['content']??'')).'</p>'];}
$report['report_group']=$group;
require __DIR__.'/header.php';
?>
<link rel="stylesheet" href="<?=BASE_URL?>assets/periodic-report.css?v=20261007-1">
<div class="pr-heading"><div><h3><i class="bi bi-journal-richtext"></i> Báo cáo định kỳ</h3><p><?=pr_escape($group?:'Chưa gán tổ chuyên môn')?> · Soạn nội dung, chốt số liệu, xuất báo cáo</p></div><a class="btn btn-outline-primary" href="<?=BASE_URL?>baocao.php?tab=dinhky&group=<?=rawurlencode($group)?>"><i class="bi bi-plus-lg"></i> Báo cáo mới</a></div>
<?php if($error):?><div class="alert alert-danger" role="alert"><?=pr_escape($error)?></div><?php endif;?>
<?php if(!$group):?><div class="alert alert-warning">Tài khoản chưa được gán tổ chuyên môn. Vui lòng cập nhật tổ trong Phân công – Danh mục.</div><?php endif;?>
<div class="pr-workspace"><main>
<?php if($view==='preview'&&$existing):?>
<section class="card"><div class="card-header pr-actions"><strong><i class="bi bi-file-earmark-check"></i> Bản báo cáo đã lưu</strong><div><a class="btn btn-sm btn-light" href="<?=BASE_URL?>baocao.php?tab=dinhky&id=<?=rawurlencode($id)?>">Chỉnh sửa</a><?php if(!empty($existing['report_version'])):?><a class="btn btn-sm btn-light" href="<?=BASE_URL?>baocao.php?tab=dinhky&id=<?=rawurlencode($id)?>&view=download">Tải Word</a><a class="btn btn-sm btn-light" target="_blank" rel="noopener" href="<?=BASE_URL?>baocao.php?tab=dinhky&id=<?=rawurlencode($id)?>&view=print">In / PDF</a><?php endif;?></div></div><div class="card-body">
<?php if(!empty($existing['report_version'])):?>
<div class="pr-drive-row"><?php if($scope['write']):?><form method="post"><input type="hidden" name="csrf" value="<?=pr_escape($csrf)?>"><input type="hidden" name="id" value="<?=pr_escape($id)?>"><input type="hidden" name="revision" value="<?=pr_escape($existing['report_revision']??'')?>"><button class="btn btn-success btn-sm" name="action" value="periodic_drive"><i class="bi bi-google"></i> Lưu vào Google Drive</button></form><?php endif;?><?php if(!empty($existing['drive_path'])):?><a class="btn btn-outline-success btn-sm" href="<?=pr_escape(cds_storage_file_url($existing['drive_path']))?>" target="_blank" rel="noopener">Mở bản đã lưu trên Drive</a><?php endif;?><small>Tệp Word dùng kho “Kế hoạch và báo cáo” trong cấu hình Drive.</small></div>
<iframe class="pr-preview" title="Bản báo cáo hoàn chỉnh" sandbox="allow-same-origin" src="<?=BASE_URL?>baocao.php?tab=dinhky&id=<?=rawurlencode($id)?>&view=embed"></iframe>
<?php else:?><p class="text-muted">Báo cáo cũ được giữ nguyên. Chọn Chỉnh sửa để chuyển nội dung sang mẫu mới.</p><h4><?=pr_escape($existing['title']??'')?></h4><div style="white-space:pre-wrap"><?=pr_escape($existing['content']??'')?></div><?php if(!empty($existing['file_path'])):?><a href="<?=pr_escape(cds_storage_file_url($existing['file_path']))?>" target="_blank" rel="noopener">Mở tệp đính kèm cũ</a><?php endif;?><?php endif;?>
</div></section>
<?php else:?>
<form method="post" id="pr-form" class="card"><div class="card-header"><i class="bi bi-pencil-square"></i> Soạn báo cáo · <?=pr_escape($group)?></div><div class="card-body">
<input type="hidden" name="action" value="periodic_save"><input type="hidden" name="csrf" value="<?=pr_escape($csrf)?>"><input type="hidden" name="id" value="<?=pr_escape($id)?>"><input type="hidden" name="revision" value="<?=pr_escape($submitted['revision']??$existing['report_revision']??$existing['updated_at']??$existing['created_at']??'')?>">
<fieldset <?=$scope['write']?'':'disabled'?>>
<div class="pr-fields">
<div><label for="pr-group">Tổ chuyên môn</label><?php if($scope['wide']):?><select id="pr-group" class="form-select" name="report_group"><?php foreach($scope['groups']as$g):?><option <?= $g===$group?'selected':''?> value="<?=pr_escape($g)?>"><?=pr_escape($g)?></option><?php endforeach;?></select><?php else:?><input id="pr-group" class="form-control" value="<?=pr_escape($group)?>" readonly><input type="hidden" name="report_group" value="<?=pr_escape($group)?>"><?php endif;?><small>Tự nhận theo tổ của TTCM.</small></div>
<?php foreach(['month'=>['Tháng báo cáo','month'],'next_month'=>['Tháng kế hoạch','month'],'school'=>['Tên trường','text'],'place'=>['Địa danh','text'],'date'=>['Ngày lập','date'],'number'=>['Số, ký hiệu','text'],'signer'=>['Tổ trưởng ký báo cáo','text'],'recipient'=>['Kính gửi / Nơi nhận','text']]as$key=>$meta):?><div class="<?=$key==='school'?'pr-wide':($key==='recipient'?'pr-full':'')?>"><label for="pr-<?=$key?>"><?=$meta[0]?></label><input id="pr-<?=$key?>" class="form-control" type="<?=$meta[1]?>" name="<?=$key?>" value="<?=pr_escape($report[$key]??'')?>" <?=$key==='number'?'placeholder="…/BC-TCM"':'required'?>></div><?php endforeach;?>
</div>
<p class="pr-format-note"><i class="bi bi-file-earmark-word"></i> Quốc hiệu, tiêu ngữ, tiêu đề, nơi nhận và phần ký được ghép tự động. Bản Word: A4, Times New Roman, cỡ 14; lề trái 30, phải 15, trên/dưới 20 mm.</p>
<?php foreach(pr_sections()as$key=>$label):?><section class="pr-editor-section"><h4><?=$key==='results'?'<span>I. Kết quả thực hiện nhiệm vụ tháng báo cáo</span>':''?><?=pr_escape($label)?></h4><p class="pr-help"><?=['implementation'=>'Nêu kế hoạch, văn bản chỉ đạo và cách triển khai trong tháng.','results'=>'Nêu nhiệm vụ đã thực hiện, kết quả và minh chứng.','issues'=>'Nêu tồn tại, nguyên nhân và giải pháp khắc phục.','next_plan'=>'Nêu nhiệm vụ, thời gian, người thực hiện và kết quả dự kiến.','manual_appendix'=>'Chèn bảng để bổ sung các biểu chưa có trong hồ sơ số.'][$key]?></p><div class="pr-rich" data-rich>
<div class="pr-toolbar" role="toolbar" aria-label="Công cụ soạn thảo <?=pr_escape($label)?>">
<?php foreach(['bold'=>['B','Đậm'],'italic'=>['I','Nghiêng'],'underline'=>['U','Gạch chân'],'strikeThrough'=>['S','Gạch ngang'],'superscript'=>['x²','Chỉ số trên'],'subscript'=>['x₂','Chỉ số dưới'],'justifyLeft'=>['≡','Căn trái'],'justifyCenter'=>['☰','Căn giữa'],'justifyRight'=>['≡→','Căn phải'],'justifyFull'=>['▤','Căn đều'],'insertUnorderedList'=>['•','Danh sách dấu chấm'],'insertOrderedList'=>['1.','Danh sách số'],'outdent'=>['←','Giảm thụt lề'],'indent'=>['→','Tăng thụt lề'],'undo'=>['↶','Hoàn tác'],'redo'=>['↷','Làm lại'],'removeFormat'=>['Tx','Xóa định dạng']]as$cmd=>$meta):?><button type="button" data-cmd="<?=$cmd?>" title="<?=$meta[1]?>" aria-label="<?=$meta[1]?>"><?=$meta[0]?></button><?php endforeach;?>
<select data-size aria-label="Cỡ chữ"><option value="3">12 pt</option><option value="4" selected>14 pt</option><option value="5">16 pt</option><option value="6">18 pt</option></select><label title="Màu chữ" class="pr-color">A<input type="color" data-color value="#000000" aria-label="Màu chữ"></label><button type="button" data-link title="Chèn liên kết">🔗</button><button type="button" data-table title="Chèn bảng">▦ Bảng</button><button type="button" data-row title="Thêm hàng vào bảng">+ Hàng</button><button type="button" data-delete-row title="Xóa hàng trong bảng">− Hàng</button></div>
<div class="pr-editable" contenteditable="<?=$scope['write']?'true':'false'?>" role="textbox" aria-multiline="true" aria-label="<?=pr_escape($label)?>"><?=pr_clean((string)($report['report_sections'][$key]??''))?></div><textarea name="report_sections[<?=$key?>]" hidden><?=pr_escape($report['report_sections'][$key]??'')?></textarea>
</div></section><?php endforeach;?>
<div class="pr-snapshot"><strong><i class="bi bi-table"></i> Phụ lục tự động theo tổ và tháng</strong><p>Sổ đầu bài · Dự giờ · Chấm công · Kiểm tra hồ sơ. Lấy số liệu khi lưu lần đầu. Bản ghi ngoài tổ và ngoài tháng không đưa vào báo cáo.</p><?php if(!empty($existing['snapshot'])):?><small>Đã chốt: <?=pr_escape(date('d/m/Y H:i',strtotime($existing['snapshot']['at'])))?></small><label><input type="checkbox" name="refresh_snapshot" value="1"> Cập nhật phụ lục từ hồ sơ hiện tại khi lưu lại</label><?php else:?><small>Phụ lục sẽ được chốt khi bấm Lưu và xem báo cáo.</small><?php endif;?></div>
<div class="pr-save"><button class="btn btn-primary" type="submit" <?=$group?'':'disabled'?>><i class="bi bi-save"></i> Lưu và xem báo cáo</button><span id="pr-save-note">Sau khi lưu có thể tải Word, in / lưu PDF hoặc lưu vào Drive.</span></div>
</fieldset><?php if(!$scope['write']):?><p class="text-muted mt-3">Tài khoản có quyền xem báo cáo của tổ. TTCM, BGH hoặc quản trị được soạn và lưu.</p><?php endif;?></div></form>
<?php endif;?></main>
<aside class="card pr-archive"><div class="card-header"><i class="bi bi-clock-history"></i> Báo cáo đã lưu</div><div class="card-body"><form method="get" class="pr-filter"><input type="hidden" name="tab" value="dinhky"><?php if($scope['wide']):?><select class="form-select form-select-sm" name="group"><option value="">Tất cả tổ</option><?php foreach($scope['groups']as$g):?><option value="<?=pr_escape($g)?>" <?=($_GET['group']??'')===$g?'selected':''?>><?=pr_escape($g)?></option><?php endforeach;?></select><?php endif;?><input class="form-control form-control-sm" type="month" name="filter_month" aria-label="Lọc tháng" value="<?=pr_escape($_GET['filter_month']??'')?>"><button class="btn btn-sm btn-outline-primary">Lọc</button></form>
<?php $count=0;usort($visibleReports,fn($a,$b)=>strcmp($b['month']??$b['date']??'',$a['month']??$a['date']??''));foreach($visibleReports as$r){if(!empty($_GET['group'])&&($r['report_group']??'')!==$_GET['group'])continue;if(!empty($_GET['filter_month'])&&($r['month']??'')!==$_GET['filter_month'])continue;$count++;?>
<article class="pr-archive-item <?=$id===($r['id']??'')?'selected':''?>"><a href="<?=BASE_URL?>baocao.php?tab=dinhky&id=<?=rawurlencode($r['id'])?>&view=preview"><strong><?=pr_escape($r['title']??'Báo cáo')?></strong><small><?=pr_escape($r['report_group']??'Báo cáo cũ')?> · <?=pr_escape($r['date']??'')?></small></a><?php if($scope['wide']&&cds_can_feature('cm.baocao.dinhky','delete')):?><form method="post" onsubmit="return confirm('Xóa báo cáo này khỏi CDS? Tệp Drive vẫn được giữ.');"><input type="hidden" name="csrf" value="<?=pr_escape($csrf)?>"><input type="hidden" name="id" value="<?=pr_escape($r['id'])?>"><button class="btn btn-sm btn-outline-danger" name="action" value="periodic_delete" title="Xóa báo cáo"><i class="bi bi-trash"></i></button></form><?php endif;?></article>
<?php }if(!$count):?><p class="text-muted small mt-3">Chưa có báo cáo phù hợp.</p><?php endif;?></div></aside></div>
<script src="<?=BASE_URL?>assets/periodic-report.js?v=20261007-1" defer></script>
<?php require __DIR__.'/footer.php'; ?>
