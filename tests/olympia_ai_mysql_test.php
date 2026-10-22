<?php
/** This test can only connect to an empty disposable GitHub Actions service. */
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
check(getenv('GITHUB_ACTIONS')==='true'&&getenv('CDS_TEST_MYSQL')==='disposable-ci','Refusing non-disposable database');
$port=(string)getenv('CDS_TEST_MYSQL_PORT');check(ctype_digit($port)&&(int)$port>0,'Missing fixture port');
$pdo=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname=cds_disposable_test;charset=utf8mb4','cds_fixture','fixture-only-password',[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
check((int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn()===0,'Database must be empty');
require __DIR__.'/../includes/olympia_ai.php';
function olympia_db():PDO{return $GLOBALS['pdo'];}
function olympia_admin():bool{return $GLOBALS['admin']??true;}
function olympia_week($id){$q=olympia_db()->prepare('SELECT * FROM cds_olympia_weeks WHERE id=?');$q->execute([$id]);return $q->fetch()?:null;}
function olympia_uid($prefix){return !empty($GLOBALS['collision'])?'collision-id':$prefix.'_'.bin2hex(random_bytes(8));}
function cds_ai_call($assistant,$task,$input,$reference){$GLOBALS['calls']=1+($GLOBALS['calls']??0);return ['ok'=>true,'content'=>json_encode(['questions'=>[['question_text'=>'Câu hỏi AI giả lập?','answer_text'=>'Đáp án giả lập','explanation'=>'Giải thích giả lập']]])];}
// Only the two tables this feature uses; production-compatible key sizes and collation.
$pdo->exec("CREATE TABLE cds_olympia_weeks(id VARCHAR(40) PRIMARY KEY,title VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE cds_olympia_questions(id VARCHAR(40) PRIMARY KEY,week_id VARCHAR(40) NOT NULL,set_id VARCHAR(40) NOT NULL DEFAULT '',grade_scope VARCHAR(40) NOT NULL DEFAULT 'all',stage_name VARCHAR(60) NOT NULL,question_text TEXT NOT NULL,answer_text TEXT NOT NULL,points INT NOT NULL,sort_order INT NOT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,FOREIGN KEY(week_id) REFERENCES cds_olympia_weeks(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
$pdo->exec("CREATE TABLE cds_olympia_scores(id INT PRIMARY KEY,points INT NOT NULL) ENGINE=InnoDB");
$pdo->exec("INSERT INTO cds_olympia_weeks VALUES('week-one','Tuần một'),('week-two','Tuần hai')");
$seed=$pdo->prepare('INSERT INTO cds_olympia_questions(id,week_id,set_id,stage_name,question_text,answer_text,points,sort_order) VALUES(?,?,?,?,?,?,?,?)');
$seed->execute(['old','week-one','','Khởi động','Câu đã dùng?','Đáp án cũ',10,7]);
$seed->execute(['other','week-two','','Khởi động','Câu tuần trước?','Giữ nguyên',20,1]);
$seed->execute(['personal','week-one','personal-set','Khởi động','Câu trong bộ cá nhân?','Kho riêng',30,1]);
$pdo->exec('INSERT INTO cds_olympia_scores VALUES(1,123)');
$before=$pdo->query('SELECT * FROM cds_olympia_questions ORDER BY id')->fetchAll();
$_SESSION=['olympia_ai_imports'=>['save-token'=>'week-one','rollback-token'=>'week-one']];
$base=['csrf'=>'fixture-csrf','week_id'=>'week-one'];
$generation=$base+['action'=>'generate_olympia_ai','topic'=>'Kiến thức giả lập','reference'=>'','grade'=>'6','stage'=>'Khởi động','difficulty'=>'easy','count'=>'1','points'=>'10'];
$draft=olympia_ai_request($generation,'fixture-csrf');
check($draft['ok']&&$GLOBALS['calls']===1,'Configured service called once');
check($pdo->query('SELECT * FROM cds_olympia_questions ORDER BY id')->fetchAll()===$before,'Generation cannot write bank');
$draft['questions'][0]['question_text']='Câu đã duyệt và sửa?';
$request=$base+['action'=>'import_olympia_ai','import_token'=>'save-token','reviewed'=>'1','questions'=>json_encode(['questions'=>$draft['questions']])];
$saved=olympia_ai_request($request,'fixture-csrf');check($saved['saved']===1,'Reviewed draft appended');
$actual=$pdo->query("SELECT * FROM cds_olympia_questions WHERE id NOT IN ('old','other','personal')")->fetchAll();
check(count($actual)===1&&$actual[0]['set_id']===''&&$actual[0]['week_id']==='week-one'&&$actual[0]['sort_order']===8&&$actual[0]['question_text']==='Câu đã duyệt và sửa?','Correct official bank, week, append order and edited text');
check($pdo->query("SELECT * FROM cds_olympia_questions WHERE id IN ('old','other','personal') ORDER BY id")->fetchAll()===$before,'All historical/personal rows unchanged');
check((int)$pdo->query('SELECT points FROM cds_olympia_scores WHERE id=1')->fetchColumn()===123,'Scores unchanged');
try{olympia_ai_request($request,'fixture-csrf');throw new RuntimeException('Replay accepted');}catch(DomainException $e){check($e->getCode()===409,'Replay rejected');}
$row=$draft['questions'][0];$row['question_text']='Câu tuần trước!!!';
$_SESSION['olympia_ai_imports']['duplicate-token']='week-one';
try{olympia_ai_request($base+['action'=>'import_olympia_ai','import_token'=>'duplicate-token','reviewed'=>'1','questions'=>json_encode(['questions'=>[$row]])],'fixture-csrf');throw new RuntimeException('Duplicate accepted');}catch(InvalidArgumentException $e){}
check(isset($_SESSION['olympia_ai_imports']['duplicate-token']),'Failed review retains token');
$row['question_text']='Câu thứ nhất chưa có?';$second=$row;$second['question_text']='Câu thứ hai chưa có?';$GLOBALS['collision']=true;
try{olympia_ai_request($base+['action'=>'import_olympia_ai','import_token'=>'rollback-token','reviewed'=>'1','questions'=>json_encode(['questions'=>[$row,$second]])],'fixture-csrf');throw new RuntimeException('Injected SQL failure accepted');}catch(PDOException $e){}
check((int)$pdo->query('SELECT COUNT(*) FROM cds_olympia_questions')->fetchColumn()===4,'SQL failure rolls back entire batch');
check(isset($_SESSION['olympia_ai_imports']['rollback-token'])&&!$pdo->inTransaction(),'Rollback releases transaction and retains draft token');
echo 'PASS real Olympia SQL: preview-only generation, reviewed append, exact history/scores, official scope, cross-week duplicates, replay and full rollback. Server: '.$pdo->query('SELECT VERSION()')->fetchColumn().PHP_EOL;

$batch=[];foreach(range(6,12) as $grade)foreach(olympia_ai_stages() as $index=>$stage)for($i=0;$i<($index===0?10:5);$i++)$batch[]=array_merge($row,['question_text'=>"Câu giả lập khối $grade phần $index số $i",'grade_scope'=>(string)$grade,'stage_name'=>$stage]);
$_SESSION['olympia_ai_imports']['batch-token']='week-one';$GLOBALS['collision']=false;
$result=olympia_ai_request($base+['action'=>'import_olympia_ai','import_token'=>'batch-token','reviewed'=>'1','questions'=>json_encode(['questions'=>$batch])],'fixture-csrf');
check($result['saved']===175,'Full seven-grade draft saves atomically');
check((int)$pdo->query('SELECT COUNT(*) FROM cds_olympia_questions')->fetchColumn()===179,'All batch rows appended');
check($pdo->query("SELECT * FROM cds_olympia_questions WHERE id IN ('old','other','personal') ORDER BY id")->fetchAll()===$before,'Batch preserves all historical rows');
check((int)$pdo->query('SELECT points FROM cds_olympia_scores WHERE id=1')->fetchColumn()===123,'Batch preserves scores');
echo "PASS 175-question multi-grade reviewed import preserves history and scores\n";
