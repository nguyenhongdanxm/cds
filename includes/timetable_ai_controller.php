<?php
require_once __DIR__.'/timetable_ai.php';
if($_SERVER['REQUEST_METHOD']==='POST'&&in_array($_POST['action']??'', ['ttb_ai_analyze','ttb_ai_apply','ttb_ai_command'],true)){
    try{
        if(!hash_equals($csrf,(string)($_POST['csrf']??'')))throw new RuntimeException('Phiên làm việc không hợp lệ.');
        $plan=ttb_plan($data,(string)$data['active_plan']);if(!$plan)throw new RuntimeException('Hãy tạo hoặc chọn phương án TKB trước.');
        if($_POST['action']==='ttb_ai_command'){
            if(!hash_equals(ttb_ai_fingerprint($data,$assignments,$plan),(string)($_POST['command_fingerprint']??'')))throw new RuntimeException('TKB hoặc ràng buộc đã thay đổi. Tải lại trang rồi thực hiện lệnh trên bản hiện tại.');
            require_once __DIR__.'/timetable_ai_command.php';
            $text=trim((string)($_POST['ai_command']??''));
            if(mb_strlen($text,'UTF-8')>500)throw new RuntimeException('Lệnh quá dài; hãy nhập một yêu cầu chuyển buổi.');
            $result=ttb_ai_transfer_command($data,$assignments,$plan,ttb_ai_parse_transfer($text));
            ttb_replace_plan($data,$result['plan']);if(!ttb_save($data))throw new RuntimeException('Không lưu được lịch; chưa xác nhận chuyển thành công.');
            $_SESSION['ttb_ai_command_result']=['text'=>$text,'changes'=>$result['changes'],'count'=>$result['count'],'scope'=>$result['scope'],'workspace'=>ttb_workspace_id()];
            unset($_SESSION['ttb_ai_preview']);
            flash('Đã chuyển '.$result['count'].' tiết của '.$result['scope'].'. Xem kết quả bên dưới; có thể hoàn tác ở Xem & chỉnh.','success');
            ttb_go('ai');
        }
        $fingerprint=ttb_ai_fingerprint($data,$assignments,$plan);
        if($_POST['action']==='ttb_ai_apply'){
            $preview=$_SESSION['ttb_ai_preview']??[];
            if(!hash_equals((string)($preview['token']??''),(string)($_POST['preview_token']??''))||($preview['fingerprint']??'')!==$fingerprint)throw new RuntimeException('TKB hoặc ràng buộc đã thay đổi. Hãy phân tích lại trước khi áp dụng.');
            $index=filter_var($_POST['candidate']??null,FILTER_VALIDATE_INT);$candidate=$index!==false?($preview['candidates'][$index]??null):null;
            if(!$candidate)throw new RuntimeException('Phương án xem trước không hợp lệ.');
            $copy=$candidate['plan'];$errors=ttb_ai_errors($data,$assignments,$copy);
            if(ttb_ai_transition_errors($plan,$copy)||array_diff($errors,ttb_ai_errors($data,$assignments,$plan)))throw new RuntimeException('Phương án có vi phạm mới; chưa áp dụng.');
            ttb_plan_remember($plan,'Áp dụng phương án trợ lý AI');
            foreach(['entries','unplaced','score']as $key)$plan[$key]=$copy[$key];$plan['errors']=$errors;$plan['updated_at']=date('c');
            ttb_replace_plan($data,$plan);if(!ttb_save($data))throw new RuntimeException('Không lưu được phương án.');
            unset($_SESSION['ttb_ai_preview']);flash('Đã áp dụng vào bản nháp. Có thể hoàn tác ở Xem & chỉnh; TKB công bố chưa thay đổi.','success');
        }else{
            $mode=(string)($_POST['ai_mode']??'check');if(!in_array($mode,['check','suggest','rearrange'],true))$mode='check';
            $request=mb_substr(trim((string)($_POST['ai_request']??'')),0,1500,'UTF-8');
            $errors=ttb_ai_errors($data,$assignments,$plan);$candidates=[];
            if($mode==='suggest')$candidates=ttb_ai_candidates($data,$assignments,$plan,(string)($_POST['activity_id']??''));
            if($mode==='rearrange'){
                if(!empty($plan['manual_mode']))throw new RuntimeException('Phương án thủ công: chọn Đề xuất đổi/xếp tiết để giữ nguyên các buổi học.');
                $locked=array_values(array_filter($plan['entries']??[],fn($e)=>!empty($e['locked'])));
                $generated=ttb_generate($data,$assignments,$locked);$generatedErrors=ttb_ai_errors($data,$assignments,$generated);
                if(!ttb_ai_transition_errors($plan,$generated)&&!array_diff($generatedErrors,$errors)){$copy=$plan;foreach(['entries','unplaced','score']as $key)$copy[$key]=$generated[$key];$copy['errors']=$generatedErrors;$candidates[]=['plan'=>$copy,'changes'=>ttb_ai_changes($plan,$copy),'score'=>$copy['score'],'errors'=>$generatedErrors];}
            }
            $_SESSION['ttb_ai_preview']=['token'=>bin2hex(random_bytes(16)),'fingerprint'=>$fingerprint,'errors'=>$errors,'unplaced'=>count($plan['unplaced']??[]),'candidates'=>$candidates,'request'=>$request,'answer'=>'','message'=>''];
            require_once __DIR__.'/ai_service.php';
            $context=['phuong_an'=>$plan['name']??'','yeu_cau'=>$request,'loi'=>$errors,'chua_xep'=>array_slice($plan['unplaced']??[],0,12),'rang_buoc'=>$data['settings'],'chan_doan'=>ttb_plan_diagnostics($data,$assignments,$plan),'quy_tac_mon'=>$data['subject_rules'],'phuong_an_thay_the'=>[]];
            foreach($candidates as $i=>$c)$context['phuong_an_thay_the'][]=['so'=>$i+1,'so_tiet_thay_doi'=>count($c['changes']),'thay_doi'=>array_slice($c['changes'],0,15),'loi_con_lai'=>$c['errors'],'chua_xep'=>count($c['plan']['unplaced']??[])];
            $limit=(int)(cds_ai_settings()['max_input_chars']??20000);
            $input=json_encode($context,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
            if(mb_strlen($input,'UTF-8')>$limit){$context['chua_xep']=[];$context['loi']=array_slice($errors,0,20);foreach($context['phuong_an_thay_the']as &$row){$row['thay_doi']=array_slice($row['thay_doi'],0,3);$row['loi_con_lai']=array_slice($row['loi_con_lai'],0,5);}unset($row);$context['luu_y']='Dữ liệu được rút gọn; không suy đoán phần chưa cung cấp.';$input=json_encode($context,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);}
            $response=cds_ai_call('tkb','analyze',$input);
            $_SESSION['ttb_ai_preview']['answer']=!empty($response['ok'])?(string)$response['content']:'';
            $_SESSION['ttb_ai_preview']['message']=empty($response['ok'])?(string)($response['message']??'Chưa gọi được AI.'):'';
        }
    }catch(Throwable $e){flash($e->getMessage(),'danger');}
    ttb_go('ai');
}
