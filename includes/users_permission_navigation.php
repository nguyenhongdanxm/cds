<?php
/** Presentation only: canonical codes/permissions remain in permissions.php. */
function up_permission_navigation(array $catalog):array {
 $cm=[
 'cm.dashboard'=>['Tổng quan / Giảng dạy','Sổ đầu bài · Số liệu Tổng quan','Một mã quyền đang dùng cho Sổ đầu bài và số liệu tuần trên Tổng quan.'],
 'cm.tracuu'=>['Giảng dạy','Thời khóa biểu · Tra cứu phân công','Mã dùng chung với Kết quả phiên bản phân công.'],
 'cm.tkb'=>['Giảng dạy','Xếp thời khóa biểu',''],
 'cm.baocao.tiendo'=>['Giảng dạy','Tiến độ chương trình',''],
 'cm.baocao.dugio'=>['Đánh giá','Dự giờ',''],
 'cm.baocao.kythi'=>['Đánh giá','Kiểm tra chuyên môn','Giữ nguyên mã quyền cũ cm.baocao.kythi.'],
 'cm.pccm'=>['Phân công chuyên môn','Phân công · Danh sách · Đổi chéo · Rà soát',''],
 'cm.nhaplieu'=>['Phân công chuyên môn','Danh mục giáo viên · Môn học · Lớp · Kiêm nhiệm',''],
 'cm.thongke'=>['Phân công chuyên môn','Thống kê PCCM · Xuất bảng',''],
 'cm.kehoach'=>['Báo cáo / Học tập','Kế hoạch giáo dục · CLB · Học Online','Điều hướng các mục này dùng chung cm.kehoach; gồm Thông báo và Chỉ tiêu. Từng trang vẫn áp dụng quy tắc thao tác hiện có.'],
 'cm.baocao.dinhky'=>['Báo cáo','Báo cáo định kỳ',''],
 ];
 $td=['td.teacher_attendance'=>'CBGVNV / Chấm công','td.teacher_achievement'=>'CBGVNV / Thành tích','td.teacher_rating'=>'CBGVNV / Đánh giá tháng và năm','td.student_score'=>'Học sinh / Nề nếp - Học tập','td.student_profile'=>'Học sinh / Hồ sơ thi đua','td.student_room_input'=>'Phòng nội trú / Nhập liệu','td.student_room_stats'=>'Phòng nội trú / Thống kê - Xếp loại','td.student_room_settings'=>'Phòng nội trú / Cài đặt','td.stats'=>'Thống kê','td.all_data'=>'Phạm vi dữ liệu'];
 $nt=['nt.tongquan'=>'Tổng quan','nt.danhsach'=>'Danh sách học sinh','nt.chiaphong'=>'Danh sách / Chia phòng','nt.chiamam'=>'Bữa ăn / Chia mâm','nt.diemdanh'=>'Điểm danh','nt.diemdanh.quantri'=>'Điểm danh / Quản trị','nt.baoan'=>'Bữa ăn / Báo ăn','nt.buaan.tonghop'=>'Bữa ăn / Tổng hợp - Chốt','nt.gao'=>'Bữa ăn / Kho gạo','nt.ravao'=>'Xin ra vào KTX','nt.yte'=>'Y tế','nt.lichtruc'=>'Lịch trực / Biên bản trực','nt.thucdon'=>'Bữa ăn / Thực đơn','nt.thongke'=>'Thống kê'];
 $out=[];foreach($catalog as$code=>$meta){$ui=$meta;$ui['ui_label']=$meta['label'];$ui['ui_menu']=$meta['group']??'Chung';$ui['ui_note']='';
  if(isset($cm[$code]))[$ui['ui_menu'],$ui['ui_label'],$ui['ui_note']]=$cm[$code];
  if(isset($td[$code]))$ui['ui_menu']=$td[$code];if(isset($nt[$code]))$ui['ui_menu']=$nt[$code];
  if($code==='csdl.students.homeroom_edit')$ui['ui_menu']='Học sinh / Phạm vi GVCN';if($code==='csdl.year')$ui['ui_menu']='Năm học / Tuần học';
  $out[$meta['module']][$code]=$ui;
 }
 // Presentation order only; no code is copied, dropped, or assigned a new default.
 if(isset($out['chuyenmon'])){$ordered=[];foreach(array_keys($cm)as$code)if(isset($out['chuyenmon'][$code]))$ordered[$code]=$out['chuyenmon'][$code];$out['chuyenmon']=$ordered+$out['chuyenmon'];}
 return$out;
}
function up_escape($value):string{return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function up_permission_hint(string $code,array $meta):string {
 $html='<div class="up-path">'.up_escape($meta['ui_menu']??$meta['group']??'Chung').'</div>';
 $html.='<div class="up-code">'.up_escape($code).'</div>';
 if(!empty($meta['ui_note']))$html.='<div class="up-note">'.up_escape($meta['ui_note']).'</div>';return$html;
}
function up_permission_toolbar(array $modules,string $context):string {
 $labels=['group'=>'Chỉ mục có quyền','person'=>'Chỉ quyền cá nhân','popup'=>'Chỉ mục chọn áp dụng'];
 $html='<div class="up-filterbar"><label>Tìm menu / chức năng<input class="form-control form-control-sm" type="search" data-up-search placeholder="Tên menu, chức năng hoặc mã quyền…" aria-label="Tìm menu và mã quyền"></label><label>Phân hệ<select class="form-select form-select-sm" data-up-module><option value="">Tất cả phân hệ</option>';
 foreach($modules as$key=>$module)$html.='<option value="'.up_escape($key).'">'.up_escape($module['label']??$key).'</option>';
 return$html.'</select></label><label>Hiển thị<select class="form-select form-select-sm" data-up-state><option value="">Tất cả quyền</option><option value="'.$context.'">'.$labels[$context].'</option></select></label><span data-up-count role="status" aria-live="polite"></span><button type="button" data-up-clear>Xóa bộ lọc</button></div><p class="up-note">Lọc chỉ thay đổi cách hiển thị. Các quyền đang ẩn vẫn được giữ khi lưu.</p>';
}
