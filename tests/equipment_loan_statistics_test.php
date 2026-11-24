<?php
require __DIR__.'/../includes/library_equipment_ledgers.php';
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$rows=[
 ['id'=>'a','borrower'=>'Giáo viên A','subject'=>'Toán','school_year'=>'2026-2027','quantity'=>30,'due_at'=>'2026-11-23','returned_at'=>''],
 ['id'=>'b','borrower'=>' GIÁO VIÊN A ','subject'=>'Lý','school_year'=>'2025-2026','due_at'=>'2026-11-20','returned_at'=>'2026-11-24'],
 ['id'=>'c','borrower'=>'Giáo viên B <script>','due_at'=>'2026-11-24'],
 ['id'=>'d','borrower'=>'Giáo viên B <script>','due_at'=>'2026-02-31'],
 ['id'=>'e','borrower'=>'Giáo viên B <script>','due_at'=>''],
 ['id'=>'f','borrower'=>'','due_at'=>'2026-11-25'],
];
$before=serialize($rows);$stats=lib_eq_loan_stats($rows,'2026-11-24');
check($stats===['total'=>6,'returned'=>1,'open'=>5,'overdue'=>1],'Counts rows, returned excludes overdue, today not overdue, invalid dates ignored');
$teachers=lib_eq_teacher_report($rows,'2026-11-24');
check(count($teachers)===3,'Normalized teacher names combined across subjects/years');
$key=hash('sha256',lib_eq_norm('Giáo viên A'));
check($teachers[$key]['stats']===['total'=>2,'returned'=>1,'open'=>1,'overdue'=>1],'Per-teacher totals');
check(lib_eq_loan_stats([],'2026-11-24')['total']===0,'Empty stats');
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
function lib_date($s){return e($s);}
define('BASE_URL','/');$data=['equipment_loans'=>$rows];$canEquipmentLoanEdit=false;$canEquipmentLoanDelete=false;$userName='Reader';$csrf='token';
function render($teacher){global $data,$canEquipmentLoanEdit,$canEquipmentLoanDelete,$userName,$csrf;$_GET=['eq_teacher'=>$teacher];ob_start();include __DIR__.'/../includes/library_equipment_ledgers_view.php';return ob_get_clean();}
$html=render($key);
check(substr_count($html,'data-equipment-loan=')===2,'Teacher filter shows rows across teacher subjects/years');
check(str_contains($html,'data-equipment-stat="total">6</strong>'),'Whole-school stats unaffected by teacher filter');
check(!str_contains($html,'<script>')&&str_contains($html,'&lt;script&gt;'),'Teacher name escaped in summary/options');
check(substr_count(render('unknown'),'data-equipment-loan=')===0,'Unknown teacher does not expose all rows');
check(substr_count(render(''),'data-equipment-loan=')===6,'Clear filter restores all');
$missing=hash('sha256','');check(substr_count(render($missing),'data-equipment-loan=')===1,'Missing teacher distinct from all');
check(serialize($rows)===$before,'Stats and filtering do not mutate history');
echo "PASS equipment statistics, overdue boundaries, normalized teacher report, view filter, escaping, school totals and no mutation\n";
