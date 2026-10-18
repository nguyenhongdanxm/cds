<?php
/** Bấm chuông: state machine độc lập; thời gian và thứ tự do máy chủ quyết định. */
declare(strict_types=1);
class BuzzerError extends RuntimeException {}
function bz_now(): int { return (int)floor(microtime(true)*1000); }
function bz_fail(string $message,int $status=409): void { throw new BuzzerError($message,$status); }
function bz_text($value,int $max): string {
    if(!is_string($value))bz_fail('Thông tin không hợp lệ.',422);
    if(preg_match('/[\x00-\x1f\x7f]/u',$value))bz_fail('Tên chứa ký tự không hợp lệ.',422);
    $value=preg_replace('/\s+/u',' ',trim($value));
    if($value===null||$value===''||mb_strlen($value,'UTF-8')>$max||preg_match('/[\x00-\x1f\x7f]/u',$value))bz_fail('Tên phải có từ 1 đến '.$max.' ký tự.',422);
    return $value;
}
function bz_initial(string $title): array {
    return ['title'=>bz_text($title,80),'version'=>1,'status'=>'lobby','question'=>0,'race'=>0,'duration_ms'=>30000,'remaining_ms'=>30000,'started_at'=>0,'winner_id'=>'','winner_event'=>0,'players'=>[],'queue'=>[],'last_result'=>null];
}
function bz_remaining(array $s,int $now): int {
    return max(0,(int)$s['remaining_ms']-($s['status']==='running'?max(0,$now-(int)$s['started_at']):0));
}
function bz_player(array $s,string $id,string $token): array {
    $p=$s['players'][$id]??null;
    if(!$p||strlen($token)!==64||!hash_equals($p['key_hash'],hash('sha256',$token)))bz_fail('Phiên thí sinh không hợp lệ. Hãy vào lại phòng.',403);
    return $p;
}
function bz_transition(array $s,string $action,array $input,int $now): array {
    if($s['status']==='closed')bz_fail('Phòng đã đóng.');
    if($s['status']==='running'&&bz_remaining($s,$now)===0){$s['remaining_ms']=0;$s['status']='finished';}
    if($action==='join'){
        $token=(string)($input['join_token']??'');
        if(!preg_match('/^[a-f0-9]{64}$/D',$token))bz_fail('Mã thiết bị không hợp lệ.',422);
        foreach($s['players']as$p)if(hash_equals($p['key_hash'],hash('sha256',$token)))return $s;
        if(!in_array($s['status'],['lobby','reading','finished'],true))bz_fail('Câu đang diễn ra. Hãy chờ giáo viên sang câu mới.');
        if(count($s['players'])>=100)bz_fail('Phòng đã đủ 100 thí sinh.');
        $name=bz_text($input['name']??'',60);$key=mb_strtolower($name,'UTF-8');
        foreach($s['players']as$p)if(mb_strtolower($p['name'],'UTF-8')===$key)bz_fail('Tên đã có trong phòng. Hãy thêm lớp hoặc số thứ tự.');
        $id=bin2hex(random_bytes(12));$s['players'][$id]=['id'=>$id,'name'=>$name,'key_hash'=>hash('sha256',$token),'blocked'=>false];
    }elseif($action==='press'){
        $id=(string)($input['player_id']??'');$p=bz_player($s,$id,(string)($input['token']??''));
        if((int)($input['question']??-1)!==$s['question']||(int)($input['race']??-1)!==$s['race'])bz_fail('Lượt bấm đã thay đổi. Chờ cập nhật rồi bấm lại.');
        if($p['blocked'])bz_fail('Bạn đã trả lời sai và hết quyền bấm ở câu này.');
        foreach($s['queue']as$row)if($row['id']===$id)return $s;
        if(!in_array($s['status'],['running','answering'],true))bz_fail('Chưa mở chuông hoặc đã hết giờ.');
        $elapsed=max(0,$now-(int)$s['started_at']);
        $s['queue'][]=['id'=>$id,'rank'=>count($s['queue'])+1,'elapsed_ms'=>$elapsed];
        if($s['status']==='running'){
            $s['remaining_ms']=bz_remaining($s,$now);$s['status']='answering';$s['winner_id']=$id;$s['winner_event']++;
        }
    }else{
        if((int)($input['version']??-1)!==$s['version'])bz_fail('Phòng vừa thay đổi. Hãy xem trạng thái mới rồi thao tác lại.');
        if($action==='question'){
            $seconds=filter_var($input['seconds']??30,FILTER_VALIDATE_INT,['options'=>['min_range'=>5,'max_range'=>300]]);
            if($seconds===false)bz_fail('Thời gian từ 5 đến 300 giây.',422);
            $s['question']++;$s['race']++;$s['status']='reading';$s['duration_ms']=$seconds*1000;$s['remaining_ms']=$seconds*1000;$s['started_at']=0;$s['queue']=[];$s['winner_id']='';$s['last_result']=null;
            foreach($s['players']as&$p)$p['blocked']=false;unset($p);
        }elseif($action==='start'){
            if($s['status']!=='reading'||!$s['players'])bz_fail('Chuẩn bị câu mới và chờ thí sinh vào trước khi bắt đầu.');
            $s['status']='running';$s['started_at']=$now;
        }elseif(in_array($action,['wrong','correct'],true)){
            if($s['status']!=='answering'||$s['winner_id']==='')bz_fail('Chưa có thí sinh giành quyền trả lời.');
            $id=$s['winner_id'];$s['last_result']=['id'=>$id,'name'=>$s['players'][$id]['name'],'result'=>$action];
            if($action==='correct'){$s['status']='finished';}
            else{
                $s['players'][$id]['blocked']=true;$s['queue']=[];$s['winner_id']='';$s['race']++;
                $available=array_filter($s['players'],fn($p)=>!$p['blocked']);
                $s['status']=$available&&$s['remaining_ms']>0?'running':'finished';$s['started_at']=$now;
            }
        }elseif($action==='stop'){
            if(!in_array($s['status'],['running','answering'],true))bz_fail('Câu đã dừng.');
            $s['remaining_ms']=bz_remaining($s,$now);$s['status']='finished';
        }elseif($action==='remove'){
            if(!in_array($s['status'],['lobby','reading','finished'],true))bz_fail('Chỉ bỏ thí sinh khi câu chưa bắt đầu hoặc đã kết thúc.');
            $id=(string)($input['player_id']??'');unset($s['players'][$id]);
            $s['queue']=array_values(array_filter($s['queue'],fn($r)=>$r['id']!==$id));
            if($s['winner_id']===$id)$s['winner_id']='';
        }elseif($action==='close')$s['status']='closed';
        else bz_fail('Thao tác không hợp lệ.',422);
    }
    $s['version']++;return $s;
}
function bz_view(array $s,int $now,string $role,string $id=''): array {
    $remaining=bz_remaining($s,$now);$status=$s['status']==='running'&&$remaining===0?'finished':$s['status'];
    $rank=array_column($s['queue'],null,'id');$players=[];
    foreach($s['players']as$p){if($role==='player'&&$p['id']!==$id)continue;$players[]=['id'=>$p['id'],'name'=>$p['name'],'blocked'=>$p['blocked'],'rank'=>$rank[$p['id']]['rank']??null,'elapsed_ms'=>$rank[$p['id']]['elapsed_ms']??null];}
    return ['title'=>$s['title'],'version'=>$s['version'],'status'=>$status,'question'=>$s['question'],'race'=>$s['race'],'duration_ms'=>$s['duration_ms'],'remaining_ms'=>$remaining,'server_ms'=>$now,'winner_id'=>$s['winner_id'],'winner_event'=>$s['winner_event'],'players'=>$players,'audio'=>$role==='player'?[]:($s['audio']??[]),'last_result'=>$role==='player'?null:$s['last_result']];
}
