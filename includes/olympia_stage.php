<?php
/** Olympia sân khấu: độc lập với cuộc thi Olympia theo tuần/lớp. */
require_once __DIR__.'/auth.php';
require_once __DIR__.'/database.php';

function stage_db(): PDO { return cds_db(); }
function stage_schema(): void {
    stage_db()->exec("CREATE TABLE IF NOT EXISTS cds_olympia_stage_rooms (id VARCHAR(32) NOT NULL PRIMARY KEY, room_code CHAR(6) NOT NULL UNIQUE, state_json LONGTEXT NOT NULL, seat_hashes LONGTEXT NOT NULL, revision BIGINT UNSIGNED NOT NULL DEFAULT 0, created_by VARCHAR(120) NOT NULL DEFAULT '', created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function stage_admin(): bool { return (current_user()['role']??'')==='admin'; }
function stage_initial(): array {
    return ['edition'=>['title'=>'','year'=>2026,'quarter'=>1,'month'=>1],'question_bank'=>[],'round'=>'khoi_dong','scene'=>'intro','mode'=>'private','question_no'=>1,'seat'=>0,'question'=>'','answer'=>'','media'=>'','media_type'=>'image','options'=>'','question_kind'=>'normal','practice_phase'=>'thinking','revealed'=>false,'timer_end'=>0,'timer_seconds'=>3,'buzz'=>null,'answers'=>[],'scores'=>[0,0,0,0],'names'=>['Thí sinh 1','Thí sinh 2','Thí sinh 3','Thí sinh 4'],'portraits'=>['','','',''],'logo'=>'','intro_video'=>'','sounds'=>[],'pack'=>[20,20,20],'pack_index'=>0,'finish_done'=>[],'star_used'=>[false,false,false,false],'star_active'=>false,'eliminated'=>[false,false,false,false],'puzzle_open'=>[false,false,false,false,false],'puzzle_words'=>['','','',''],'puzzle_image'=>'','tie_candidates'=>[],'tie_winner'=>null,'event'=>0,'event_name'=>'','history'=>[]];
}
function stage_room(string $code,bool $lock=false): array {
    if(!preg_match('/^[0-9]{6}$/',$code))throw new RuntimeException('Mã phiên không hợp lệ.');
    $stmt=stage_db()->prepare('SELECT * FROM cds_olympia_stage_rooms WHERE room_code=?'.($lock?' FOR UPDATE':''));$stmt->execute([$code]);$row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row)throw new RuntimeException('Không tìm thấy phiên sân khấu.');return $row;
}
function stage_state(array $room): array { $state=json_decode((string)$room['state_json'],true);return is_array($state)?array_replace(stage_initial(),$state):stage_initial(); }
function stage_save(array $room,array $state): void {
    $state['event']=(int)$state['event']+1;
    $stmt=stage_db()->prepare('UPDATE cds_olympia_stage_rooms SET state_json=?,revision=revision+1 WHERE id=?');
    $stmt->execute([json_encode($state,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),$room['id']]);
}
function stage_public(array $state): array {
    unset($state['undo_judge'],$state['question_bank']);
    if($state['revealed'])$state['answer_key']=$state['answer'];
    unset($state['answer']);
    if(!$state['revealed'])$state['answers']=[];
    if(!$state['revealed'])$state['puzzle_words']=array_map(fn($i,$word)=>!empty($state['puzzle_open'][$i])?(string)$word:str_repeat('●',mb_strlen((string)$word)),array_keys((array)$state['puzzle_words']),(array)$state['puzzle_words']);
    return $state;
}
function stage_seat(array $room): ?int {
    $code=(string)$room['room_code'];$seat=$_SESSION['olympia_stage_seats'][$code]??null;
    return is_int($seat)&&$seat>=0&&$seat<4?$seat:null;
}
function stage_duration(array $state): int {
    switch($state['round']) {
        case 'khoi_dong':return 3;
        case 'vuot_chuong_ngai_vat':return 15;
        case 'tang_toc':return [20,20,30,30][min(3,max(0,(int)$state['question_no']-1))];
        case 've_dich':return ($state['pack'][(int)$state['pack_index']]??20)==30?20:15;
        default:return 15;
    }
}
function stage_text($value,int $limit=4000): string { return mb_substr(trim((string)$value),0,$limit); }
function stage_asset(string $kind): string {
    $file=$_FILES['file']??null;if(!$file||($file['error']??1)!==UPLOAD_ERR_OK||!is_uploaded_file((string)($file['tmp_name']??'')))throw new RuntimeException('Không nhận được tệp hợp lệ.');
    $allowed=$kind==='video'?['video/mp4'=>'mp4','video/webm'=>'webm']:($kind==='sound'?['audio/mpeg'=>'mp3','audio/ogg'=>'ogg','audio/wav'=>'wav','audio/x-wav'=>'wav']:['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp']);
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$size=(int)$file['size'];if(!isset($allowed[$mime])||$size<1||$size>($kind==='video'?120:($kind==='sound'?25:8))*1024*1024)throw new RuntimeException('Định dạng hoặc dung lượng tệp không hợp lệ.');
    $dir=DATA_PATH.'/game_assets/olympia_stage';if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('Không thể tạo thư mục tài nguyên.');
    $name=bin2hex(random_bytes(16)).'.'.$allowed[$mime];if(!move_uploaded_file($file['tmp_name'],$dir.'/'.$name))throw new RuntimeException('Không lưu được tài nguyên.');
    return BASE_URL.'data/game_assets/olympia_stage/'.$name;
}
