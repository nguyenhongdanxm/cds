<?php
/** Synthetic AI responses only: no provider calls, configuration or production database. */
require __DIR__ . '/../includes/olympia_ai.php';
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
function reject(callable $call, string $label) {
    try { $call(); } catch (InvalidArgumentException | DomainException $e) { return; }
    throw new RuntimeException('Not rejected: '.$label);
}
function olympia_admin() { return $GLOBALS['admin'] ?? true; }
function olympia_week($id) { return $id === 'week-fixture' ? ['id'=>$id] : null; }
function cds_ai_call($assistant, $task, $input, $reference) {
    $GLOBALS['calls'][] = [$assistant,$task,$input,$reference];
    return $GLOBALS['response'];
}
$row=['question_text'=>'Thủ đô của Việt Nam là thành phố nào?', 'answer_text'=>'Hà Nội',
    'grade_scope'=>'6','stage_name'=>'Khởi động','points'=>10,'explanation'=>'Hà Nội là thủ đô Việt Nam.'];
$encode=static fn($rows)=>json_encode(['questions'=>$rows],JSON_UNESCAPED_UNICODE);
check(olympia_ai_validate($encode([$row]))===[$row], 'Valid Vietnamese draft');
foreach(['null','false','[]','{"questions":[]}','{"questions":{}}','{"questions":[1]}','{broken'] as $json) reject(fn()=>olympia_ai_validate($json),'Malformed schema');
foreach(['question_text','answer_text','grade_scope','stage_name','points'] as $field) {
    $bad=$row; $bad[$field]=[]; reject(fn()=>olympia_ai_validate($encode([$bad])),'Wrong type '.$field);
}
foreach([['answer_text',''],['grade_scope','13'],['stage_name','Vòng tự tạo'],['points',-1],['points',1001],['question_text',str_repeat('a',2001)]] as [$key,$value]) {
    $bad=$row;$bad[$key]=$value;reject(fn()=>olympia_ai_validate($encode([$bad])),'Invalid '.$key);
}
$duplicate=$row;$duplicate['question_text']='THỦ ĐÔ CỦA VIỆT NAM LÀ THÀNH PHỐ NÀO!!!';
reject(fn()=>olympia_ai_validate($encode([$duplicate]),[$row]),'Normalized bank duplicate');
reject(fn()=>olympia_ai_validate($encode([$row,$duplicate])),'Duplicate in batch');
reject(fn()=>olympia_ai_validate($encode(array_fill(0,26,$row))),'Oversized batch');
$request=['topic'=>'Địa lí Việt Nam','reference'=>'Nội dung giả lập','grade'=>'7','stage'=>'Tăng tốc','difficulty'=>'medium','count'=>'1','points'=>'20'];
$GLOBALS['response']=['ok'=>true,'content'=>$encode([array_merge($row,['grade_scope'=>'12','stage_name'=>'Về đích','points'=>999])])];
$result=olympia_ai_generate($request,[]);
check($result['questions'][0]['grade_scope']==='7'&&$result['questions'][0]['stage_name']==='Tăng tốc'&&$result['questions'][0]['points']===20,'Administrator owns scope/stage/points');
check($GLOBALS['calls'][0][0]==='dayhoc'&&$GLOBALS['calls'][0][1]==='quiz_json','Reuse configured AI service');
check(!isset($result['content']),'Raw provider content not exposed');
$GLOBALS['response']=['ok'=>false,'message'=>'AI chưa bật'];check(olympia_ai_generate($request,[])===['ok'=>false,'message'=>'AI chưa bật'],'Provider unavailable preserves clear message');
$GLOBALS['response']=['ok'=>true,'content'=>'```json {"questions":[]} ```'];reject(fn()=>olympia_ai_generate($request,[]),'Malformed AI response');
$GLOBALS['response']=['ok'=>true,'content'=>$encode([$row,$row])];reject(fn()=>olympia_ai_generate($request,[]),'Wrong question count');
$before=count($GLOBALS['calls']);
foreach(['gv','gvcn','bgh'] as $role){$GLOBALS['admin']=false;reject(fn()=>olympia_ai_request(['action'=>'generate_olympia_ai','csrf'=>'csrf','week_id'=>'week-fixture'],'csrf'),'Non-admin '.$role);}
$GLOBALS['admin']=true;
reject(fn()=>olympia_ai_request(['csrf'=>'wrong'],'csrf'),'Invalid CSRF');
reject(fn()=>olympia_ai_request(['csrf'=>'csrf','week_id'=>'missing'],'csrf'),'Missing week');
reject(fn()=>olympia_ai_request(['action'=>'import_olympia_ai','csrf'=>'csrf','week_id'=>'week-fixture','import_token'=>'invalid'],'csrf'),'Invalid import token');
$_SESSION['olympia_ai_imports']['valid']='week-fixture';
reject(fn()=>olympia_ai_request(['action'=>'import_olympia_ai','csrf'=>'csrf','week_id'=>'week-fixture','import_token'=>'valid'],'csrf'),'Missing human review');
$_SESSION['olympia_ai_last_call']=time();
reject(fn()=>olympia_ai_request(['action'=>'generate_olympia_ai','csrf'=>'csrf','week_id'=>'week-fixture'],'csrf'),'Rate limit');
check(count($GLOBALS['calls'])===$before,'Authorization/CSRF/review/rate checks never call provider');
echo "PASS Olympia AI: schema, Vietnamese duplicates, classification, provider errors, admin-only, CSRF, review, token and rate checks\n";
