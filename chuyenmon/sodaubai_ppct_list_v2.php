<?php
require_once __DIR__.'/includes/functions.php';
require_once __DIR__.'/includes/lesson_book_curriculum_v2.php';
require_login();
if(!lb_can_manage_curriculum()){http_response_code(403);header('Content-Type: application/json');echo json_encode(['ok'=>false]);exit;}
$rows=lb_curriculum_visible_rows();usort($rows,fn($a,$b)=>[(int)($a['grade']??0),lb_norm((string)($a['subject']??'')),(int)($a['order']??$a['period']??0)]<=>[(int)($b['grade']??0),lb_norm((string)($b['subject']??'')),(int)($b['order']??$b['period']??0)]);header('Content-Type: application/json; charset=utf-8');echo json_encode(['ok'=>true,'rows'=>$rows],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
