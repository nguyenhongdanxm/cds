<?php
/** Validation and ownership for the secret doors question bank. */
function sd_text($value, int $max): string {
    if (!is_string($value) || strlen($value)>$max) throw new InvalidArgumentException('Nội dung thiếu hoặc quá dài.');
    return trim($value);
}
function sd_questions($rows): array {
    if (!is_array($rows) || count($rows)<1 || count($rows)>500) throw new InvalidArgumentException('Cần từ 1 đến 500 câu hỏi.');
    $out=[];
    foreach ($rows as $i=>$r) {
        if (!is_array($r)) throw new InvalidArgumentException('Câu hỏi không hợp lệ.');
        $q=['text'=>sd_text($r['text']??'',15000),'answer'=>sd_text($r['answer']??'',10000),'points'=>filter_var($r['points']??15,FILTER_VALIDATE_INT),'image'=>sd_text($r['image']??'',2000),'choices'=>[]];
        if ($q['text']==='' || $q['answer']==='' || $q['points']<5 || $q['points']>100) throw new InvalidArgumentException('Câu '.($i+1).': cần câu hỏi, đáp án và điểm từ 5 đến 100.');
        if ($q['image']!=='' && (!filter_var($q['image'],FILTER_VALIDATE_URL) || strtolower((string)parse_url($q['image'],PHP_URL_SCHEME))!=='https' || parse_url($q['image'],PHP_URL_USER)!==null)) throw new InvalidArgumentException('Ảnh minh họa phải là liên kết HTTPS.');
        if (!is_array($r['choices']??[])) throw new InvalidArgumentException('Lựa chọn không hợp lệ.');
        foreach ($r['choices']??[] as $c) $q['choices'][]=sd_text($c,6000);
        if (count($q['choices'])!==0 && (count($q['choices'])!==4 || in_array('',$q['choices'],true) || !in_array($q['answer'],$q['choices'],true))) throw new InvalidArgumentException('Trắc nghiệm cần 4 lựa chọn và đáp án đúng trùng một lựa chọn.');
        $out[]=$q;
    }
    if(strlen(json_encode($out,JSON_UNESCAPED_UNICODE))>2000000)throw new InvalidArgumentException('Bộ câu hỏi quá lớn.');
    return $out;
}
function sd_can_edit(array $set, array $u): bool { return ($u['role']??'')==='admin' || (string)($set['owner_id']??'')===(string)($u['id']??''); }
function sd_schema(PDO $db): void {
    $db->exec('CREATE TABLE IF NOT EXISTS cds_secret_door_sets (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, owner_id VARCHAR(191) NOT NULL, owner_name VARCHAR(255) NOT NULL, title VARCHAR(180) NOT NULL, shared TINYINT NOT NULL DEFAULT 0, questions_json MEDIUMTEXT NOT NULL, revision INT NOT NULL DEFAULT 1, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX owner_idx (owner_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
function sd_excel(string $path): array {
    require_once __DIR__.'/keoco_excel.php';
    $rows=kc_excel_read($path);$header=array_shift($rows);$h=$header['values']??[];
    if(array_values($h)===['Câu hỏi','A','B','C','D','Đáp án']){
        require_once __DIR__.'/keoco_match.php';$result=[];
        foreach(kc_excel_questions($path) as $q){$choices=[$q['option_a'],$q['option_b'],$q['option_c'],$q['option_d']];$result[]=['text'=>$q['question_text'],'answer'=>$choices[$q['correct_option']-1],'choices'=>$choices,'points'=>15,'image'=>''];}
        return sd_questions($result);
    }
    if(array_map(fn($i)=>$h[$i]??'',range(0,3))!==['Câu hỏi','Đáp án','Điểm','Ảnh'])throw new InvalidArgumentException('Dùng mẫu 4 cột: Câu hỏi, Đáp án, Điểm, Ảnh; hoặc mẫu trắc nghiệm của Kéo co.');
    $result=[];foreach($rows as $r){$v=$r['values'];$result[]=['text'=>$v[0]??'','answer'=>$v[1]??'','points'=>($v[2]??'')===''?15:$v[2],'image'=>$v[3]??''];}return sd_questions($result);
}
