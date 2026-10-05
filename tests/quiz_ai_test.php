<?php
require_once __DIR__.'/../includes/quiz_ai.php';
require_once __DIR__.'/../includes/quiz_paper_store.php';
$checks=0;
function check($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
function rejected($rows,$existing=[]){try{qp_ai_validate(json_encode(['questions'=>$rows]),$existing);throw new RuntimeException('Invalid input accepted');}catch(InvalidArgumentException $e){check(true,'Rejected');}}
$base=['text'=>'Hai cộng hai bằng bao nhiêu?','seconds'=>20,'explanation'=>'2 + 2 = 4.','choices'=>['A'=>'1','B'=>'2','C'=>'3','D'=>'4'],'key'=>'D'];
$fixtures=[array_merge($base,['type'=>'single']),array_merge($base,['type'=>'paper_logic','text'=>'A: 2+2=4. B: 1+1=3.','key'=>'A']),array_merge($base,['type'=>'multi','text'=>'Chọn các số chẵn.','choices'=>['A'=>'1','B'=>'2','C'=>'3','D'=>'4'],'keys'=>['B','D']]),array_merge($base,['type'=>'fill','text'=>'Điền kết quả 2+2.','fill_answers'=>['4','bốn']]),array_merge($base,['type'=>'match','text'=>'Nối số với cách viết.','pairs'=>[['1','một'],['2','hai']]]),array_merge($base,['type'=>'order','text'=>'Sắp thứ tự từ bé đến lớn.','steps'=>['1','2','3']])];
$answer=['D','A','["B","D"]','4','["một","hai"]','["1","2","3"]'];
$valid=qp_ai_validate(json_encode(['questions'=>$fixtures]));
foreach($valid as$i=>$q)check(qp_answer_correct($q,$answer[$i]),'Game scorer accepts '.$q['type']);
check(count($valid)===6,'Six types');
check(count(qp_ai_validate("```json\n".json_encode(['questions'=>[$fixtures[0]]])."\n```"))===1,'JSON fence');
rejected([$fixtures[0],$fixtures[0]]);rejected([$fixtures[0]],[$fixtures[0]]);
foreach([['type'=>'unknown'],['key'=>'E'],['seconds'=>9],['seconds'=>301],['choices'=>['A'=>'same','B'=>'same','C'=>'3','D'=>'4']],['text'=>''],['text'=>str_repeat('a',2001)]]as$change)rejected([array_merge($fixtures[0],$change)]);
rejected([array_merge($fixtures[2],['keys'=>['A','A']])]);rejected([array_merge($fixtures[2],['keys'=>[['A'],'B']])]);rejected([array_merge($fixtures[4],['pairs'=>[['1','same'],['2','same']]])]);rejected([array_merge($fixtures[5],['steps'=>['1']])]);
rejected([$fixtures[0]],array_fill(0,100,['text'=>'existing']));
$media=qp_ai_validate(json_encode(['questions'=>[array_merge($fixtures[0],['image'=>'https://bad.test','audio'=>'secret','video'=>'secret','extra'=>'secret'])]]))[0];
check($media['image']===''&&$media['audio']===''&&$media['video']===''&&!isset($media['extra']),'Only whitelisted fields');
function cds_ai_call($module,$task,$prompt,$content){global $fixtures,$stub;check($module==='dayhoc'&&$task==='quiz_json','Uses configured teaching service');check(strpos($prompt,'Tạo đúng 1')!==false,'Requested count in prompt');return $stub??['ok'=>true,'content'=>json_encode(['questions'=>[$fixtures[0]]]),'model'=>'stub','provider'=>'stub'];}
$request=['topic'=>'Phép cộng','grade'=>'6','type'=>'single','difficulty'=>'easy','count'=>1];
$result=qp_ai_generate($request);check(count($result['questions'])===1&&!isset($result['content']),'Structured result');
$stub=['ok'=>false,'message'=>'Provider unavailable'];check(qp_ai_generate($request)===$stub,'Provider failure preserved');
$stub=['ok'=>true,'content'=>json_encode(['questions'=>[$fixtures[1]]])];try{qp_ai_generate($request);throw new RuntimeException('Wrong type accepted');}catch(InvalidArgumentException $e){check(true,'Wrong generated type rejected');}
echo "Quiz AI: $checks checks passed.\n";
