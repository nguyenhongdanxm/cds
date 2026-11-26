<?php
/** Append-only, school-local classroom rewards; no student information leaves CDS. */
require_once __DIR__.'/json_store.php';
function ab_path(): string {return DATA_PATH.'/achievement_board.json';}
function ab_empty(): array {return ['grants'=>[],'criteria'=>[],'events'=>[],'requests'=>[]];}
function ab_check(array $s): array {foreach(array_keys(ab_empty()) as $key)if(!is_array($s[$key]??null))throw new RuntimeException('Dữ liệu Bảng thành tích không hợp lệ; không ghi đè.');return $s;}
function ab_board_path(string $key): string {if(!preg_match('/^[a-f0-9]{64}$/D',$key))throw new RuntimeException('Mã bảng không hợp lệ.');return DATA_PATH.'/achievement_boards/'.$key.'.json';}
function ab_load(?string $key=null): array {
    $meta=ab_check(cds_json_load_for_update(ab_path(),ab_empty()));
    if($key===null)return $meta;
    $board=ab_check(cds_json_load_for_update(ab_board_path($key),ab_empty()));
    $board['grants']=$meta['grants'];return $board;
}
function ab_admin(array $u): bool {return ($u['role']??'')==='admin';}
function ab_actor(array $u): string {return (string)($u['id']??$u['username']??'');}
function ab_now(): DateTimeImmutable {return new DateTimeImmutable('now',new DateTimeZone('Asia/Ho_Chi_Minh'));}
function ab_date(string $s): ?DateTimeImmutable {$d=DateTimeImmutable::createFromFormat('!Y-m-d',$s,new DateTimeZone('Asia/Ho_Chi_Minh'));return $d&&$d->format('Y-m-d')===$s&&$s>='2000-01-01'&&$s<='2100-12-31'?$d:null;}
function ab_csrf(): string {if(empty($_SESSION['achievement_csrf']))$_SESSION['achievement_csrf']=bin2hex(random_bytes(24));return $_SESSION['achievement_csrf'];}
function ab_text(array $in,string $key,int $limit=300): string {if(!is_string($in[$key]??''))throw new RuntimeException('Dữ liệu nhập không hợp lệ.');$v=trim($in[$key]??'');if(strlen($v)>$limit||!preg_match('//u',$v))throw new RuntimeException('Nội dung quá dài hoặc không hợp lệ.');return $v;}
function ab_key(string $year,string $class,string $subject): string {return hash('sha256',json_encode([$year,$class,$subject],JSON_UNESCAPED_UNICODE));}
function ab_feature(array $u,string $level): bool {if(($u['role']??'')==='viewer')return $level==='view';return level_rank(permission_access('hl.thanhtich',$u))>=level_rank($level);}
function ab_class_allowed(array $u,string $class): bool {
    if(ab_admin($u)||($u['role']??'')==='viewer')return true;
    $classes=is_array($u['classes']??null)?$u['classes']:[];
    if(in_array('gvcn',$u['groups']??[],true))return in_array($class,array_merge($classes,(array)($u['homeroom_classes']??[])),true);
    return !$classes||in_array($class,$classes,true);
}
function ab_scope_allowed(array $store,array $u,string $class,string $subject,string $level='view'): bool {
    if(!ab_feature($u,$level)||!ab_class_allowed($u,$class))return false;
    if(ab_admin($u)||(($u['role']??'')==='viewer'&&$level==='view'))return true;
    foreach($store['grants'] as $g)if(($g['user_id']??'')===ab_actor($u)&&($g['class']??'')===$class&&($g['subject']??'')===$subject)return true;
    return false;
}
function ab_presets(): array {return [
    ['id'=>'participate','name'=>'Tích cực phát biểu','points'=>1,'icon'=>'🙋'],['id'=>'homework','name'=>'Hoàn thành bài tập','points'=>2,'icon'=>'📚'],['id'=>'team','name'=>'Hợp tác tốt','points'=>2,'icon'=>'🤝'],['id'=>'progress','name'=>'Có tiến bộ','points'=>3,'icon'=>'🌱'],['id'=>'help','name'=>'Giúp đỡ bạn','points'=>2,'icon'=>'💛'],['id'=>'excellent','name'=>'Thành tích nổi bật','points'=>5,'icon'=>'🏆'],
    ['id'=>'late','name'=>'Đi học muộn','points'=>-1,'icon'=>'⏰'],['id'=>'missing','name'=>'Chưa hoàn thành bài','points'=>-2,'icon'=>'📝'],['id'=>'focus','name'=>'Cần tập trung hơn','points'=>-1,'icon'=>'🎯'],['id'=>'rules','name'=>'Chưa thực hiện nội quy','points'=>-2,'icon'=>'📌']];}
function ab_criteria(array $s,string $key): array {return array_values($s['criteria'][$key]??ab_presets());}
function ab_range(array $in): array {
    $anchor=ab_date(ab_text($in,'anchor',10))??ab_now()->setTime(0,0);$mode=ab_text($in,'period',20)?:'week';
    if($mode==='week'){$from=$anchor->modify('-'.((int)$anchor->format('N')-1).' days');$to=$from->modify('+6 days');}
    elseif($mode==='month'){$from=$anchor->modify('first day of this month');$to=$anchor->modify('last day of this month');}
    elseif($mode==='range'){$from=ab_date(ab_text($in,'from',10));$to=ab_date(ab_text($in,'to',10));if(!$from||!$to||$from>$to||$from->diff($to)->days>1096)throw new RuntimeException('Chọn khoảng ngày hợp lệ, tối đa 3 năm.');}
    else throw new RuntimeException('Kỳ thống kê không hợp lệ.');
    return [$from->format('Y-m-d'),$to->format('Y-m-d')];
}
/** Apply one mutation inside the shared atomic JSON lock. Context always comes from CSDL. */
function ab_apply(array $s,array $in,array $u,array $ctx): array {
    ab_check($s);$action=ab_text($in,'action',30);
    if($action==='grant_save'||$action==='grant_delete'){
        if(!ab_admin($u))throw new RuntimeException('Chỉ quản trị viên được phân quyền lớp và môn.');
        $id=ab_text($in,'user_id',100);$class=ab_text($in,'class',100);$subject=ab_text($in,'subject',150);
        if($action==='grant_save'&&(!isset($ctx['users'][$id])||!isset($ctx['classes'][$class])||!in_array($subject,$ctx['subjects'],true)))throw new RuntimeException('Chọn đúng tài khoản, lớp và môn trong danh mục.');
        $s['grants']=array_values(array_filter($s['grants'],fn($g)=>!($g['user_id']===$id&&$g['class']===$class&&$g['subject']===$subject)));
        if($action==='grant_save')$s['grants'][]=['user_id'=>$id,'class'=>$class,'subject'=>$subject,'by'=>ab_actor($u),'at'=>ab_now()->format(DATE_ATOM)];
        return $s;
    }
    $class=ab_text($in,'class',100);$subject=ab_text($in,'subject',150);$year=ab_text($in,'year',100);
    if(!isset($ctx['classes'][$class])||!in_array($subject,$ctx['subjects'],true)||!isset($ctx['years'][$year])||!ab_scope_allowed($s,$u,$class,$subject,'edit'))throw new RuntimeException('Bạn chưa có quyền tích điểm cho lớp hoặc môn này.');
    $key=ab_key($year,$class,$subject);
    if($action==='criterion_save'||$action==='criterion_delete'){
        $id=ab_text($in,'criterion_id',80);$list=ab_criteria($s,$key);
        if($action==='criterion_save'){
            $name=ab_text($in,'name',180);$icon=ab_text($in,'icon',32);$points=filter_var($in['points']??null,FILTER_VALIDATE_INT);
            if($name===''||$points===false||$points===0||abs($points)>100)throw new RuntimeException('Nhập tên tiêu chí và điểm nguyên từ -100 đến 100, khác 0.');
            if($id!==''&&!in_array($id,array_column($list,'id'),true))throw new RuntimeException('Tiêu chí không còn tồn tại.');
            $id=$id?:'skill_'.bin2hex(random_bytes(8));$list=array_values(array_filter($list,fn($r)=>$r['id']!==$id));
            if(count($list)>=40)throw new RuntimeException('Mỗi bảng có tối đa 40 tiêu chí.');
            $list[]=['id'=>$id,'name'=>$name,'icon'=>$icon?:'⭐','points'=>$points];
        }else $list=array_values(array_filter($list,fn($r)=>$r['id']!==$id));
        $s['criteria'][$key]=$list;return $s;
    }
    if($action==='undo'){
        $id=ab_text($in,'event_id',100);$found=false;
        foreach($s['events'] as &$event)if($event['id']===$id&&$event['board']===$key){
            if(!empty($event['voided_at']))throw new RuntimeException('Lượt điểm đã được hoàn tác.');
            if(!ab_admin($u)&&($event['by']??'')!==ab_actor($u)&&!ab_feature($u,'delete'))throw new RuntimeException('Bạn chỉ được hoàn tác lượt điểm do mình nhập.');
            $event['voided_at']=ab_now()->format(DATE_ATOM);$event['voided_by']=ab_actor($u);$found=true;break;
        }unset($event);if(!$found)throw new RuntimeException('Không tìm thấy lượt điểm trong bảng này.');return $s;
    }
    if($action!=='award')throw new RuntimeException('Thao tác không hợp lệ.');
    $request=ab_text($in,'request_id',100);if(!preg_match('/^[a-zA-Z0-9_-]{16,100}$/D',$request))throw new RuntimeException('Mã lượt nhập không hợp lệ. Hãy thử lại.');
    $requestKey=hash('sha256',ab_actor($u).'|'.$request);if(isset($s['requests'][$requestKey]))return $s;
    $criterion=ab_text($in,'criterion_id',80);$skill=null;foreach(ab_criteria($s,$key) as $r)if($r['id']===$criterion)$skill=$r;
    if(!$skill)throw new RuntimeException('Tiêu chí đã thay đổi, vui lòng tải lại bảng.');
    $ids=$in['students']??null;if(!is_array($ids)||count($ids)<1||count($ids)>100)throw new RuntimeException('Chọn từ 1 đến 100 học sinh.');
    foreach($ids as $id)if(!is_string($id)||!isset($ctx['students'][$id])||$ctx['students'][$id]['class']!==$class)throw new RuntimeException('Có học sinh không thuộc lớp được chọn.');
    $ids=array_values(array_unique($ids));$date=ab_text($in,'date',10);if(!ab_date($date)||$date>ab_now()->format('Y-m-d'))throw new RuntimeException('Ngày ghi điểm không hợp lệ hoặc nằm trong tương lai.');
    $yearInfo=$ctx['years'][$year];if((!empty($yearInfo['start'])&&$date<$yearInfo['start'])||(!empty($yearInfo['end'])&&$date>$yearInfo['end']))throw new RuntimeException('Ngày ghi điểm phải thuộc năm học đã chọn.');
    $note=ab_text($in,'note',1000);$batch='reward_'.bin2hex(random_bytes(12));
    foreach($ids as $id)$s['events'][]=['id'=>'point_'.bin2hex(random_bytes(12)),'batch'=>$batch,'board'=>$key,'year'=>$year,'class'=>$class,'subject'=>$subject,'student_id'=>$id,'student_name'=>$ctx['students'][$id]['name'],'points'=>(int)$skill['points'],'criterion_id'=>$skill['id'],'criterion'=>$skill['name'],'icon'=>$skill['icon'],'date'=>$date,'note'=>$note,'by'=>ab_actor($u),'by_name'=>(string)($u['name']??$u['username']??''),'at'=>ab_now()->format(DATE_ATOM)];
    $s['requests'][$requestKey]=$batch;return $s;
}
function ab_save(array $in,array $u,array $ctx): void {
    if(!is_string($in['csrf']??null)||!hash_equals(ab_csrf(),$in['csrf']))throw new RuntimeException('Phiên làm việc không hợp lệ. Hãy tải lại trang.');
    $error='Không lưu được điểm. Vui lòng thử lại.';
    // The small metadata lock also serializes scope revocations with board writes.
    // Events stay in separate year/class/subject files, avoiding a school-wide ledger read.
    $ok=cds_json_update(ab_path(),function($meta)use($in,$u,$ctx,&$error){try{
        ab_check($meta);$action=ab_text($in,'action',30);
        if(in_array($action,['grant_save','grant_delete'],true))return ab_apply($meta,$in,$u,$ctx);
        $key=ab_key(ab_text($in,'year',100),ab_text($in,'class',100),ab_text($in,'subject',150));
        $board=ab_check(cds_json_load_for_update(ab_board_path($key),ab_empty()));$board['grants']=$meta['grants'];
        $next=ab_apply($board,$in,$u,$ctx);$next['grants']=[];
        if(!cds_json_save(ab_board_path($key),$next))throw new RuntimeException('Không lưu được bảng điểm. Hãy kiểm tra quyền ghi dữ liệu.');
        return $meta;
    }catch(RuntimeException $e){$error=$e->getMessage();return false;}},ab_empty());
    if(!$ok)throw new RuntimeException($error);
}
function ab_report(array $s,array $students,string $key,string $from,string $to): array {
    $scores=[];foreach($students as $id=>$student)$scores[$id]=['id'=>$id,'name'=>$student['name'],'reward'=>0,'penalty'=>0,'total'=>0,'count'=>0];
    $history=[];$trend=[];
    foreach($s['events'] as $e){if($e['board']!==$key||$e['date']<$from||$e['date']>$to)continue;$history[]=$e;if(!empty($e['voided_at']))continue;
        $id=$e['student_id'];if(!isset($scores[$id]))$scores[$id]=['id'=>$id,'name'=>$e['student_name'],'reward'=>0,'penalty'=>0,'total'=>0,'count'=>0,'archived'=>true];
        $points=(int)$e['points'];$scores[$id][$points>0?'reward':'penalty']+=abs($points);$scores[$id]['total']+=$points;$scores[$id]['count']++;
        $trend[$e['date']]=($trend[$e['date']]??0)+$points;
    }
    $rows=array_values($scores);usort($rows,fn($a,$b)=>($b['total']<=>$a['total'])?:($b['reward']<=>$a['reward'])?:strcmp($a['name'],$b['name']));
    $prior=null;$rank=0;foreach($rows as $i=>&$row){$metric=[$row['total'],$row['reward']];if($metric!==$prior)$rank=$i+1;$row['rank']=$rank;$prior=$metric;}unset($row);
    usort($history,fn($a,$b)=>strcmp($b['at'],$a['at'])?:strcmp($b['id'],$a['id']));ksort($trend);
    return ['scores'=>$rows,'history'=>array_slice($history,0,200),'history_total'=>count($history),'trend'=>$trend,'reward'=>array_sum(array_column($rows,'reward')),'penalty'=>array_sum(array_column($rows,'penalty')),'total'=>array_sum(array_column($rows,'total'))];
}
