<?php
// Render the real page against synthetic data only; deny any unexpected file access.
function require_login(){}
function current_user(){return ['role'=>'admin','name'=>'Quản trị'];}
function can_perm_level($a,$b){return false;}
function csdl_year_current(){return ['label'=>'2027 - 2028'];}
define('SCHOOL_YEAR','2027 - 2028');define('SCHOOL_NAME','Trường thử nghiệm');define('BASE_URL','/');define('DATA_PATH','fixture-only');
$rows=[];for($i=0;$i<21;$i++)$rows[]=['batch_id'=>'fixture','borrower'=>'Nguyễn A','subject'=>'Toán','school_year'=>'2026 - 2027','equipment_name'=>'Thiết bị <script>alert(1)</script>','quantity'=>2,'borrowed_at'=>$i===0?'invalid':'2026-10-20','teaching_grade'=>'6A','lesson_period'=>'12','returned_at'=>$i===1?'2026-10-21':'','return_condition'=>'Tốt','note'=>'Ghi chú'];
$GLOBALS['fixture']=['equipment_loans'=>$rows];function load_json($file,$default){if($file!=='fixture-only/library_equipment.json')throw new RuntimeException('Unexpected file');return $GLOBALS['fixture'];}
$_GET=['batch'=>'fixture'];$before=serialize($GLOBALS['fixture']);$source=file_get_contents(__DIR__.'/../thietbi_phieu.php');
foreach(["require_once __DIR__ . '/includes/auth.php';","require_once __DIR__ . '/includes/csdl_store.php';"]as$line)$source=str_replace($line,';',$source);
ob_start();eval('?>'.$source);$html=ob_get_clean();
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
check(str_contains($html,'A4 landscape'),'Landscape page');check(substr_count($html,'class="sheet"')===2,'21 rows split into two sheets');
check(substr_count($html,'colspan="4">Mượn')===2&&substr_count($html,'colspan="5">Trả')===2,'Grouped borrowing/return headings');
check(!str_contains($html,'<script>')&&str_contains($html,'&lt;script&gt;'),'Escape equipment names');
check(!str_contains($html,'01/01/1970'),'Invalid dates stay blank');check(str_contains($html,'21/10/2026'),'Actual return date');
check(str_contains($html,'2026 - 2027')&&!str_contains($html,'2027 - 2028'),'Original loan academic year');
check(substr_count($html,'Kiểm tra, nhận xét, đánh giá của Ban giám hiệu')===2,'Review area on each sheet');
check(serialize($GLOBALS['fixture'])===$before,'Printing does not mutate loans');
check(eqp_date('')===''&&eqp_date('2026-02-30')==='','Missing/impossible dates blank');
echo "PASS equipment print: grouped columns, 21-item pagination, escaping, dates, original academic year and unchanged loans\n";

if(in_array('--html',$argv,true))file_put_contents('/tmp/equipment-print-fixture.html',$html);
