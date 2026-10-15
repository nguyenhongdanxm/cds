<?php
/** Editor AI: propose first, revalidate and save only on explicit Apply. */
require_once __DIR__.'/timetable_ai.php';
require_once __DIR__.'/timetable_ai_command.php';
require_once __DIR__.'/timetable_ai_language.php';
require_once __DIR__.'/timetable_slot_validation.php';

function tte_ai_context(array $post,array $data,array $assignments,string $csrf): array {
    if($csrf===''||!hash_equals($csrf,(string)($post['csrf']??'')))throw new RuntimeException('Phiên làm việc không hợp lệ.');
    $plan=ttb_plan($data,(string)($data['active_plan']??''));
    if(!$plan)throw new RuntimeException('Hãy chọn phương án TKB trước.');
    if(($post['workspace_id']??'')!==($data['_workspace_id']??'')||($post['plan_id']??'')!==($plan['id']??''))throw new RuntimeException('Tuần hoặc phương án đang mở đã thay đổi. Hãy tải lại trang.');
    if(!hash_equals(ttb_ai_fingerprint($data,$assignments,$plan),(string)($post['fingerprint']??'')))throw new RuntimeException('Lịch hoặc ràng buộc đã thay đổi. Hãy phân tích lại.');
    return $plan;
}
function tte_ai_prepare(array $post,array $data,array $assignments,string $csrf): array {
    $plan=tte_ai_context($post,$data,$assignments,$csrf);
    $text=trim((string)($post['ai_command']??''));
    if($text===''||mb_strlen($text,'UTF-8')>500)throw new RuntimeException('Nhập một yêu cầu rõ ràng, tối đa 500 ký tự.');
    // Bộ hiểu lệnh chỉ trả intent có schema; bộ xếp hiện hữu kiểm tra mọi thay đổi.
    $intent=ttb_ai_interpret($text,$assignments);
    $result=ttb_ai_execute_intent($data,$assignments,$plan,$intent);
    $candidate=!empty($result['changed'])?$result['plan']:null;
    $errors=$candidate?ttb_ai_errors($data,$assignments,$candidate):ttb_ai_errors($data,$assignments,$plan);
    if($candidate&&(ttb_ai_transition_errors($plan,$candidate)||array_diff($errors,ttb_ai_errors($data,$assignments,$plan))))throw new RuntimeException('Đề xuất có vi phạm mới; chưa thay đổi lịch.');
    return ['token'=>bin2hex(random_bytes(24)),'created_at'=>time(),'workspace'=>$data['_workspace_id'],'plan_id'=>$plan['id'],'fingerprint'=>ttb_ai_fingerprint($data,$assignments,$plan),'request'=>$text,'candidate'=>$candidate,'changes'=>$candidate?ttb_ai_changes($plan,$candidate):[],'errors'=>$errors,'message'=>$candidate?'Đề xuất đã được kiểm tra. Xem các tiết thay đổi trước khi áp dụng.':($result['message']??'Không tìm được thay đổi phù hợp.')];
}
function tte_ai_apply(array $post,array $preview,string $csrf): void {
    $workspace=(string)($post['workspace_id']??'');
    if($csrf===''||!hash_equals($csrf,(string)($post['csrf']??'')))throw new RuntimeException('Phiên làm việc không hợp lệ.');
    if(empty($preview['token'])||!hash_equals((string)$preview['token'],(string)($post['preview_token']??''))||time()-(int)($preview['created_at']??0)>900)throw new RuntimeException('Đề xuất đã hết hạn hoặc không hợp lệ. Hãy phân tích lại.');
    if(!ttb_optimizer_lock($workspace))throw new RuntimeException('Tuần này đang được xử lý ở cửa sổ khác. Hãy thử lại sau.');
    try{
        $data=ttb_data();$assignments=ttb_assignments();$plan=tte_ai_context($post,$data,$assignments,$csrf);
        if(($preview['workspace']??'')!==$workspace||($preview['plan_id']??'')!==$plan['id']||($preview['fingerprint']??'')!==ttb_ai_fingerprint($data,$assignments,$plan))throw new RuntimeException('Lịch hoặc ràng buộc đã thay đổi. Hãy phân tích lại.');
        $copy=$preview['candidate']??null;
        if(!is_array($copy)||!ttb_ai_changes($plan,$copy))throw new RuntimeException('Không có thay đổi để áp dụng.');
        $errors=ttb_ai_errors($data,$assignments,$copy);
        if(ttb_ai_transition_errors($plan,$copy)||array_diff($errors,ttb_ai_errors($data,$assignments,$plan)))throw new RuntimeException('Đề xuất không còn hợp lệ; chưa áp dụng.');
        // Chỉ cập nhật nội dung lịch; giữ nguyên danh tính, chế độ và metadata phương án.
        ttb_plan_remember($plan,'Áp dụng đề xuất AI trong Xem & chỉnh');
        foreach(['entries','unplaced','score']as$key)$plan[$key]=$copy[$key];
        $plan['errors']=$errors;$plan['updated_at']=date('c');
        ttb_replace_plan($data,$plan);
        if(!ttb_save($data))throw new RuntimeException('Không lưu được đề xuất. Hãy tải lại để kiểm tra lịch.');
    }finally{ttb_optimizer_unlock($workspace);}
}
