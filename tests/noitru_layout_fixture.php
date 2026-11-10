<?php
// Synthetic presentation-only fixture; never loads auth, accounts or school data.
define('BASE_URL','/school/');define('SCHOOL_SHORT','Trường thử nghiệm');
function e($value){return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
function can_perm($permission){return true;}
$_SERVER['SCRIPT_NAME']='/fixture.php';$_GET=[];$nt_sec='attendance';
$view=file_get_contents(__DIR__.'/../includes/noitru_att_view.php');preg_match('~<style>(.*?)</style>~s',$view,$style);
?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link href="/bootstrap.css" rel="stylesheet"><link href="<?=BASE_URL?>assets/noitru-layout.css?v=20261110-1" rel="stylesheet"><style><?=$style[1]??''?></style></head><body class="nt-body"><?php require __DIR__.'/../includes/noitru_shell.php';?><main class="nt-main"><div class="nt-content"><div class="nt-page-head"><h1>Điểm danh nội trú</h1></div><section class="att-shell"><div class="att-tabs"><a class="active">Điểm danh</a><a>Lịch sử</a></div><div class="att-panel"><div class="att-controls"><div><label>Ngày</label><input class="form-control" type="date" value="2026-11-10"></div><div><label>Buổi tự động theo giờ</label><select class="form-select"><option>Điểm danh đột xuất</option></select></div></div><div class="att-summary"><div><strong>100</strong>Sĩ số</div><div><strong>99</strong>Có mặt</div><div><strong>1</strong>Vắng</div></div><button class="btn btn-nt att-save">Xác nhận điểm danh</button></div></section></div></main></body></html>
