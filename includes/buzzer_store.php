<?php
declare(strict_types=1);
require_once __DIR__.'/buzzer_game.php';
function bz_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS cds_buzzer_rooms (
        code CHAR(8) NOT NULL PRIMARY KEY, owner_id VARCHAR(100) NOT NULL,
        screen_key CHAR(48) NOT NULL, state_json MEDIUMTEXT NOT NULL,
        expires_at BIGINT NOT NULL, KEY idx_bz_owner(owner_id), KEY idx_bz_expiry(expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function bz_global_audio_schema(PDO $db): void {
    $db->exec("CREATE TABLE IF NOT EXISTS cds_buzzer_audio_settings (kind VARCHAR(16) PRIMARY KEY, metadata_json TEXT NOT NULL, updated_by VARCHAR(100) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function bz_global_audio(PDO $db): array {
    try{$rows=$db->query('SELECT kind,metadata_json FROM cds_buzzer_audio_settings')->fetchAll(PDO::FETCH_ASSOC);}
    catch(PDOException $e){if(($e->errorInfo[1]??0)===1146)return [];throw $e;}
    $out=[];foreach($rows as$r)if(in_array($r['kind'],['background','bell'],true))$out[$r['kind']]=json_decode($r['metadata_json'],true,16,JSON_THROW_ON_ERROR);return $out;
}
function bz_audio_effective(PDO $db,array $s): array {$s['_shared_audio']=bz_global_audio($db);$s['audio']=array_replace($s['audio']??[],$s['_shared_audio']);return $s;}
function bz_code($code): string {
    if(!is_string($code)||!preg_match('/^[0-9]{8}$/D',$code))bz_fail('Mã phòng gồm 8 chữ số.',422);return $code;
}
function bz_create(PDO $db,string $owner,string $title): array {
    if($owner==='')bz_fail('Chưa đăng nhập giáo viên.',403);$state=bz_initial($title);bz_schema($db);
    $db->exec('DELETE FROM cds_buzzer_rooms WHERE expires_at<'.time().' LIMIT 100');
    $st=$db->prepare('SELECT COUNT(*) FROM cds_buzzer_rooms WHERE owner_id=? AND expires_at>?');$st->execute([$owner,time()]);
    if((int)$st->fetchColumn()>=10)bz_fail('Bạn đã có 10 phòng trong 24 giờ. Hãy dùng lại phòng đã tạo.');
    for($n=0;$n<5;$n++){
        $code=(string)random_int(10000000,99999999);$screen=bin2hex(random_bytes(24));
        try{$st=$db->prepare('INSERT INTO cds_buzzer_rooms(code,owner_id,screen_key,state_json,expires_at) VALUES(?,?,?,?,?)');$st->execute([$code,$owner,$screen,json_encode($state,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),time()+86400]);return ['code'=>$code,'screen_key'=>$screen];}
        catch(PDOException $e){if(($e->errorInfo[1]??0)!==1062)throw $e;}
    }
    bz_fail('Chưa tạo được mã phòng mới. Hãy thử lại.',503);
}
function bz_room(PDO $db,string $code,bool $lock=false): array {
    bz_code($code);$q=$db->prepare('SELECT * FROM cds_buzzer_rooms WHERE code=?'.($lock?' FOR UPDATE':''));$q->execute([$code]);$row=$q->fetch(PDO::FETCH_ASSOC);
    if(!$row||(int)$row['expires_at']<=time())bz_fail('Phòng không tồn tại hoặc đã hết hạn 24 giờ.',404);
    $row['state']=json_decode($row['state_json'],true,64,JSON_THROW_ON_ERROR);return $row;
}
function bz_authorize(array $row,array $actor): void {
    $role=$actor['role']??'';
    if($role==='host'){
        if(($actor['owner']??'')===''||!hash_equals($row['owner_id'],(string)$actor['owner']))bz_fail('Bạn không quản lý phòng này.',403);
    }elseif($role==='screen'){
        if(!hash_equals($row['screen_key'],(string)($actor['token']??'')))bz_fail('Liên kết trình chiếu không hợp lệ.',403);
    }elseif($role==='player')bz_player($row['state'],(string)($actor['id']??''),(string)($actor['token']??''));
    else bz_fail('Không có quyền truy cập.',403);
}
function bz_read(PDO $db,string $code,array $actor): array {
    $row=bz_room($db,$code);bz_authorize($row,$actor);$view=bz_view($actor['role']==='player'?$row['state']:bz_audio_effective($db,$row['state']),bz_now(),$actor['role'],(string)($actor['id']??''));
    $view['code']=$code;
    if($actor['role']==='host')$view['screen_key']=$row['screen_key'];return $view;
}
function bz_write(PDO $db,string $code,array $actor,string $action,array $input): array {
    if(!in_array($action,['join','press','question','start','wrong','correct','stop','remove','close'],true))bz_fail('Thao tác không hợp lệ.',422);
    if($action!=='join'&&($actor['role']??'')!==($action==='press'?'player':'host'))bz_fail('Không có quyền điều khiển.',403);
    $db->beginTransaction();
    try{
        $row=bz_room($db,$code,true);$s=$row['state'];
        if($action!=='join')bz_authorize($row,$actor);
        if($action==='press'){$input['player_id']=$actor['id'];$input['token']=$actor['token'];}
        $next=bz_transition($s,$action,$input,bz_now());
        if($next!==$s){$q=$db->prepare('UPDATE cds_buzzer_rooms SET state_json=? WHERE code=?');$q->execute([json_encode($next,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$code]);}
        $result=[];
        if($action==='join'){
            foreach($next['players']as$p)if(hash_equals($p['key_hash'],hash('sha256',(string)$input['join_token']))){$actor=['role'=>'player','id'=>$p['id']];$result['player_id']=$p['id'];break;}
        }
        $result['state']=bz_view($actor['role']==='player'?$next:bz_audio_effective($db,$next),bz_now(),$actor['role'],(string)($actor['id']??''));$result['state']['code']=$code;
        if($actor['role']==='host')$result['state']['screen_key']=$row['screen_key'];
        $db->commit();return $result;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function bz_list(PDO $db,string $owner): array {
    bz_schema($db);$q=$db->prepare('SELECT code,state_json FROM cds_buzzer_rooms WHERE owner_id=? AND expires_at>? ORDER BY expires_at DESC LIMIT 10');$q->execute([$owner,time()]);$out=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC)as$r){$s=json_decode($r['state_json'],true,64,JSON_THROW_ON_ERROR);$out[]=['code'=>$r['code'],'title'=>$s['title'],'status'=>$s['status']];}return $out;
}
