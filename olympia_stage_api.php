<?php
require_once __DIR__.'/includes/olympia_stage.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function stage_reply(array $value,int $status=200): never {http_response_code($status);echo json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
try {
    $action=(string)($_POST['action']??($_GET['action']??'state'));
    if($_SERVER['REQUEST_METHOD']!=='POST'&&!in_array($action,['state'],true))throw new RuntimeException('Yêu cầu không hợp lệ.');
    if($_SERVER['REQUEST_METHOD']==='POST'){
        $token=(string)($_SESSION['olympia_stage_csrf']??'');
        if($token===''||!hash_equals($token,(string)($_POST['csrf']??'')))throw new RuntimeException('Phiên trình duyệt không hợp lệ.');
    }
    if($action==='create'){
        if(!stage_admin())throw new RuntimeException('Chỉ quản trị viên được tạo phiên.');stage_schema();
        $names=json_decode((string)($_POST['names']??''),true);$names=is_array($names)?array_slice(array_values($names),0,4):[];
        $state=stage_initial();foreach($names as $i=>$name)$state['names'][$i]=stage_text($name,80)?:$state['names'][$i];
        $pins=[];for($i=0;$i<4;$i++)$pins[]=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
        for($attempt=0;$attempt<10;$attempt++){
            $code=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
            try {$stmt=stage_db()->prepare('INSERT INTO cds_olympia_stage_rooms(id,room_code,state_json,seat_hashes,created_by) VALUES(?,?,?,?,?)');$stmt->execute([bin2hex(random_bytes(12)),$code,json_encode($state,JSON_UNESCAPED_UNICODE),json_encode(array_map('password_hash',$pins,array_fill(0,4,PASSWORD_DEFAULT))),stage_text(current_user()['username']??'',120)]);stage_reply(['ok'=>true,'code'=>$code,'pins'=>$pins]);}catch(PDOException $e){if($e->getCode()!=='23000')throw $e;}
        }throw new RuntimeException('Không thể tạo mã phiên, vui lòng thử lại.');
    }
    $code=(string)($_REQUEST['code']??'');
    if($action==='join'){
        $room=stage_room($code);$seat=(int)($_POST['seat']??-1);$hashes=json_decode((string)$room['seat_hashes'],true);
        if($seat<0||$seat>3||!is_array($hashes)||!password_verify((string)($_POST['pin']??''),(string)($hashes[$seat]??'')))throw new RuntimeException('Mã ghế không đúng.');
        $_SESSION['olympia_stage_seats'][$code]=$seat;stage_reply(['ok'=>true,'seat'=>$seat]);
    }
    $room=stage_room($code);$host=stage_admin();$seat=stage_seat($room);
    if($action==='state'){
        $state=stage_state($room);$out=stage_public($state);
        if($seat!==null)$out['my_answer']=$state['answers'][$seat]??null;
        if($host)$out['answer_key']=$state['answer'];
        stage_reply(['ok'=>true,'state'=>$out,'revision'=>(int)$room['revision'],'server_ms'=>(int)round(microtime(true)*1000),'host'=>$host,'seat'=>$seat]);
    }
    if(!$host&&$seat===null)throw new RuntimeException('Hãy nhập mã ghế trước khi chơi.');
    $db=stage_db();$db->beginTransaction();
    try {
        $room=stage_room($code,true);$state=stage_state($room);$now=(int)round(microtime(true)*1000);
        if($action==='buzz'){
            if($seat===null||!in_array($state['round'],['khoi_dong','vuot_chuong_ngai_vat','ve_dich','cau_hoi_phu'],true)||!in_array($state['scene'],['question','results','puzzle','image'],true)||$state['buzz']!==null||!empty($state['eliminated'][$seat]))throw new RuntimeException('Chưa thể bấm chuông ở câu này.');
            if(in_array($state['scene'],['puzzle','image'],true)&&$state['round']!=='vuot_chuong_ngai_vat')throw new RuntimeException('Chưa thể bấm chuông ở cảnh này.');
            if($state['scene']==='results'&&$state['round']!=='ve_dich')throw new RuntimeException('Câu hỏi đã kết thúc.');
            if($state['round']==='khoi_dong'&&$state['mode']!=='common')throw new RuntimeException('Lượt riêng không bấm chuông.');
            if($state['round']==='ve_dich'&&($seat===(int)$state['seat']||empty($state['steal_open'])))throw new RuntimeException('Chưa mở 5 giây giành quyền sau câu trả lời sai.');
            if($state['timer_end']>0&&$now>$state['timer_end']&&$state['round']==='khoi_dong')throw new RuntimeException('Đã hết thời gian giành quyền.');
            if($state['round']==='ve_dich'&&$now>$state['timer_end'])throw new RuntimeException('Đã hết 5 giây giành quyền.');
            if($state['round']==='cau_hoi_phu'&&(!$state['timer_end']||$now>$state['timer_end']))throw new RuntimeException('Chưa có hiệu lệnh hoặc đã hết giờ câu hỏi phụ.');
            if($state['round']==='cau_hoi_phu'&&!in_array($seat,(array)$state['tie_candidates'],true))throw new RuntimeException('Bạn không nằm trong nhóm câu hỏi phụ.');
            $state['buzz']=['seat'=>$seat,'at'=>$now];$state['event_name']='buzz';
            if($state['round']==='khoi_dong')$state['timer_end']=$now+3000;
            if($state['round']==='ve_dich')$state['timer_end']=$now+5000;
        } elseif($action==='submit'){
            if($seat===null||!in_array($state['scene'],['question','results','puzzle','image'],true))throw new RuntimeException('Câu hỏi chưa mở.');
            if(in_array($state['scene'],['puzzle','image'],true)&&($state['round']!=='vuot_chuong_ngai_vat'||($state['buzz']['seat']??null)!==$seat))throw new RuntimeException('Hãy giành quyền để trả lời chướng ngại vật.');
            if($state['scene']==='results'&&$state['round']!=='ve_dich')throw new RuntimeException('Câu hỏi đã kết thúc.');
            $round=$state['round'];$buzzSeat=$state['buzz']['seat']??null;
            if($round==='tang_toc'&&(!$state['timer_end']||$now>$state['timer_end']))throw new RuntimeException('Câu Tăng tốc chưa tính giờ hoặc đã hết giờ.');
            if($round==='khoi_dong'&&($state['mode']==='private'?$seat!==(int)$state['seat']:$seat!==$buzzSeat))throw new RuntimeException('Bạn chưa có quyền trả lời.');
            if($round==='cau_hoi_phu'&&$seat!==$buzzSeat)throw new RuntimeException('Bạn chưa giành quyền trả lời.');
            if($round==='ve_dich'&&$seat!==(int)$state['seat']&&$seat!==$buzzSeat)throw new RuntimeException('Bạn chưa có quyền trả lời.');
            if($round==='vuot_chuong_ngai_vat'&&$buzzSeat!==null&&$seat!==$buzzSeat)throw new RuntimeException('Chuông thuộc thí sinh khác.');
            if($state['timer_end']&&$now>$state['timer_end'])throw new RuntimeException('Đã hết giờ trả lời.');
            if($round==='ve_dich'&&$seat!== (int)$state['seat']&&isset($state['answers'][$seat]))throw new RuntimeException('Chỉ ghi nhận đáp án giành quyền đầu tiên.');
            $state['answers'][$seat]=['text'=>stage_text($_POST['answer']??'',500),'at'=>$now,'elapsed_ms'=>$state['timer_end']?max(0,$now-($state['timer_end']-$state['timer_seconds']*1000)):0];
        } elseif($host) {
            switch($action){
                case 'setup':
                    $names=json_decode((string)($_POST['names']??''),true);if(is_array($names)&&count($names)===4)$state['names']=array_map(fn($x)=>stage_text($x,80),$names);
                    break;
                case 'round':
                    $round=(string)($_POST['round']??'');if(!in_array($round,['khoi_dong','vuot_chuong_ngai_vat','tang_toc','ve_dich','cau_hoi_phu'],true))throw new RuntimeException('Vòng thi không hợp lệ.');
                    $state['round']=$round;$state['undo_judge']=null;$state['scene']='intro';$state['question_no']=1;$state['seat']=0;$state['star_active']=false;
                    if($round==='ve_dich'){$state['finish_done']=[];$order=[0,1,2,3];usort($order,fn($a,$b)=>(($state['scores'][$b]<=>$state['scores'][$a])?:($a<=>$b)));$state['seat']=$order[0];}
                    if($round==='cau_hoi_phu'){$counts=array_count_values($state['scores']);$state['tie_candidates']=array_values(array_filter([0,1,2,3],fn($i)=>($counts[$state['scores'][$i]]??0)>1));$state['tie_winner']=null;}
                    $state['event_name']='round';break;
                case 'tie_candidates':
                    if($state['round']!=='cau_hoi_phu')throw new RuntimeException('Chỉ chọn nhóm đồng điểm ở câu hỏi phụ.');
                    $candidates=json_decode((string)($_POST['seats']??''),true);if(!is_array($candidates))throw new RuntimeException('Danh sách thí sinh không hợp lệ.');
                    $candidates=array_values(array_unique(array_map('intval',$candidates)));if(count($candidates)<2||count($candidates)>4||array_diff($candidates,[0,1,2,3]))throw new RuntimeException('Hãy chọn từ hai đến bốn thí sinh đồng điểm.');
                    $state['tie_candidates']=$candidates;$state['event_name']='scene';break;
                case 'finish_next':
                    if($state['round']!=='ve_dich')throw new RuntimeException('Chỉ dùng khi thi Về đích.');
                    $state['finish_done'][]=(int)$state['seat'];$state['finish_done']=array_values(array_unique($state['finish_done']));
                    $remaining=array_values(array_diff([0,1,2,3],$state['finish_done']));
                    if(!$remaining){$state['scene']='finished';$state['event_name']='finished';break;}
                    usort($remaining,fn($a,$b)=>(($state['scores'][$b]<=>$state['scores'][$a])?:($a<=>$b)));
                    $state['seat']=$remaining[0];$state['pack']=[20,20,20];$state['pack_index']=0;$state['star_active']=false;$state['question_no']=1;$state['scene']='intro';$state['event_name']='round';break;
                case 'scene':
                    $scene=(string)($_POST['scene']??'');if(!in_array($scene,['intro','pack','question','results','image','puzzle','score','star','finished'],true))throw new RuntimeException('Cảnh không hợp lệ.');
                    $state['scene']=$scene;$state['event_name']='scene';break;
                case 'question':
            $number=(int)($_POST['number']??1);$max=$state['round']==='tang_toc'?4:($state['round']==='ve_dich'||$state['round']==='cau_hoi_phu'?3:($state['round']==='vuot_chuong_ngai_vat'?5:((($_POST['mode']??'private')==='common')?12:6)));
            if($number<1||$number>$max)throw new RuntimeException('Số câu vượt phạm vi của phần thi.');
            $state['question']=stage_text($_POST['question']??'');$state['answer']=stage_text($_POST['answer']??'');$state['media']=stage_text($_POST['media']??'',300);$state['question_no']=$number;$state['question_kind']=($_POST['kind']??'normal')==='practice'?'practice':'normal';$state['practice_phase']='thinking';
                    if($state['round']!=='ve_dich')$state['seat']=max(0,min(3,(int)($_POST['seat']??0)));$state['mode']=($_POST['mode']??'private')==='common'?'common':'private';
            $state['answers']=[];$state['judged']=[];$state['undo_judge']=null;$state['buzz']=null;$state['steal_open']=false;$state['owner_debited']=false;$state['revealed']=false;$state['timer_end']=0;$state['timer_seconds']=stage_duration($state);$state['scene']='question';$state['event_name']='question';break;
                case 'timer':
                    if($state['round']==='khoi_dong'&&$state['mode']==='common'&&$state['buzz']!==null)throw new RuntimeException('Đã có chuông; thời gian 3 giây đang tính từ lúc bấm.');
                    if($state['round']==='ve_dich'&&!empty($state['steal_open']))throw new RuntimeException('Đang trong cửa sổ 5 giây giành quyền.');
                    $state['timer_seconds']=stage_duration($state);$state['timer_end']=$now+$state['timer_seconds']*1000;$state['event_name']='timer';break;
                case 'practice_timer':
                    if($state['round']!=='ve_dich'||$state['question_kind']!=='practice')throw new RuntimeException('Đây không phải câu thực hành Về đích.');
                    $state['practice_phase']='doing';$value=(int)($state['pack'][$state['pack_index']]??20);
                    $state['timer_seconds']=($state['buzz']!==null?($value===30?40:20):($value===30?60:30));$state['timer_end']=$now+$state['timer_seconds']*1000;$state['event_name']='timer';break;
                case 'stop':$state['timer_end']=$now;$state['event_name']='stop';break;
                case 'reveal':$state['revealed']=true;$state['scene']='results';$state['timer_end']=$now;$state['event_name']='reveal';break;
                case 'reset_buzz':$state['buzz']=null;$state['event_name']='reset_buzz';break;
                case 'judge':
                    $target=max(0,min(3,(int)($_POST['seat']??0)));$correct=($_POST['correct']??'')==='1';$round=$state['round'];$points=0;
                    if(!$state['revealed'])throw new RuntimeException('Hãy công bố đáp án trước khi chấm điểm.');
                    if(isset($state['judged'][$target]))throw new RuntimeException('Đã chấm thí sinh này trong câu hiện tại.');
                    $state['undo_judge']=['scores'=>$state['scores'],'answers'=>$state['answers'],'judged'=>$state['judged'],'owner_debited'=>$state['owner_debited']??false,'steal_open'=>$state['steal_open']??false,'timer_end'=>$state['timer_end'],'tie_winner'=>$state['tie_winner']??null,'scene'=>$state['scene'],'history'=>$state['history']];
                    if($round==='tang_toc'&&$correct){
                        $at=(int)($state['answers'][$target]['at']??0);if(!$at)throw new RuntimeException('Thí sinh chưa nộp đáp án.');
                        $state['answers'][$target]['approved']=true;
                        foreach($state['answers'] as $i=>$a)if(!empty($a['approved'])){
                            $rank=1;foreach($state['answers'] as $b)if(!empty($b['approved'])&&(int)$b['at']<(int)$a['at'])$rank++;
                            $new=max(10,50-$rank*10);$previous=(int)($state['answers'][$i]['awarded_points']??0);
                            $state['scores'][$i]+=$new-$previous;$state['answers'][$i]['awarded_points']=$new;
                        }
                    } elseif($round==='khoi_dong')$points=$correct?10:($state['mode']==='common'?-5:0);
                    elseif($round==='vuot_chuong_ngai_vat'){
                        $type=(string)($_POST['type']??'horizontal');
                        if($type==='obstacle'){$opened=count(array_filter($state['puzzle_open']));$points=$correct?max(20,60-max(0,$opened-1)*10):0;if(!$correct)$state['eliminated'][$target]=true;}
                        else $points=$correct?10:0;
                    } elseif($round==='ve_dich'){
                        $value=(int)($state['pack'][$state['pack_index']]??20);
                        if($target===(int)$state['seat']){
                            $points=$state['star_active']?($correct?2*$value:-$value):($correct?$value:0);
                            if(!$correct){$state['steal_open']=true;$state['timer_end']=$now+5000;if($state['star_active'])$state['owner_debited']=true;}
                        }else{
                            if(empty($state['steal_open'])||($state['buzz']['seat']??null)!==$target)throw new RuntimeException('Thí sinh này chưa giành quyền.');
                            $points=$correct?$value:-intdiv($value,2);
                            if($correct&&empty($state['owner_debited'])){$state['scores'][(int)$state['seat']]-=$value;$state['owner_debited']=true;}
                            $state['steal_open']=false;
                        }
                    }
                    if($round==='cau_hoi_phu'&&$correct){$state['tie_winner']=$target;$state['scene']='finished';}
                    if($round!=='tang_toc')$state['scores'][$target]+=$points;
                    $state['judged'][$target]=$correct;$state['history'][]=['round'=>$round,'question'=>$state['question_no'],'seat'=>$target,'points'=>$round==='tang_toc'?(int)($state['answers'][$target]['awarded_points']??0):$points,'at'=>$now];
                    $state['history']=array_slice($state['history'],-100);$state['event_name']='score';break;
                case 'undo_judge':
                    if($state['event_name']!=='score'||empty($state['undo_judge']))throw new RuntimeException('Chỉ hoàn tác ngay sau lần chấm gần nhất.');
                    $previous=$state['undo_judge'];foreach($previous as $key=>$value)$state[$key]=$value;
                    unset($state['undo_judge']);$state['event_name']='score_undo';break;
                case 'pack':
                    $pack=json_decode((string)($_POST['pack']??''),true);if(!is_array($pack)||count($pack)!==3||count(array_filter($pack,fn($v)=>in_array($v,[20,30],true)))!==3)throw new RuntimeException('Chọn đúng 3 câu 20 hoặc 30 điểm.');
                    $state['pack']=array_values($pack);$state['pack_index']=0;$state['scene']='pack';$state['event_name']='pack';break;
                case 'pack_index':$state['pack_index']=max(0,min(2,(int)($_POST['index']??0)));$state['star_active']=false;$state['event_name']='pack';break;
                case 'star':
                    $i=(int)$state['seat'];if($state['round']!=='ve_dich'||!in_array($state['scene'],['pack','star'],true)||$state['star_used'][$i])throw new RuntimeException('Ngôi sao chỉ được đặt một lần trước khi hiện câu hỏi.');
                    $state['star_active']=true;$state['star_used'][$i]=true;$state['scene']='star';$state['event_name']='star';break;
                case 'puzzle':
                    $piece=max(0,min(4,(int)($_POST['piece']??0)));$state['puzzle_open'][$piece]=($_POST['open']??'1')==='1';$state['scene']='puzzle';$state['event_name']='puzzle';break;
                case 'puzzle_config':
                    $words=json_decode((string)($_POST['words']??''),true);if(!is_array($words)||count($words)!==4)throw new RuntimeException('Cần bốn từ hàng ngang.');
                    $state['puzzle_words']=array_map(fn($w)=>stage_text($w,80),array_values($words));$state['puzzle_open']=[false,false,false,false,false];$state['scene']='puzzle';$state['event_name']='puzzle';break;
                case 'asset':
                    $key=(string)($_POST['key']??'');$kind=(string)($_POST['kind']??'image');if(!preg_match('/^[a-z_0-9]{1,32}$/',$key)||!in_array($kind,['image','sound'],true))throw new RuntimeException('Loại tài nguyên không hợp lệ.');
                    $url=stage_asset($kind);if($key==='logo')$state['logo']=$url;elseif(preg_match('/^portrait_([0-3])$/',$key,$m))$state['portraits'][(int)$m[1]]=$url;elseif($key==='puzzle_image')$state['puzzle_image']=$url;elseif($kind==='sound')$state['sounds'][$key]=$url;else $state['media']=$url;
                    $state['event_name']='asset';break;
                default:throw new RuntimeException('Thao tác không hợp lệ.');
            }
        }else throw new RuntimeException('Chỉ người điều khiển mới thực hiện được thao tác này.');
        stage_save($room,$state);$db->commit();stage_reply(['ok'=>true,'state'=>$host?array_merge($state,['answer_key'=>$state['answer']]):stage_public($state),'server_ms'=>$now]);
    }catch(Throwable $e){$db->rollBack();throw $e;}
}catch(Throwable $e){stage_reply(['ok'=>false,'message'=>$e->getMessage()],400);}
