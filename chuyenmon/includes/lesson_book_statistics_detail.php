<?php
/** Read-only drilldown; totals continue to use the original aggregation. */
function lb_stats_metric_labels():array{return ['scheduled'=>'Tổng tiết TKB','completed'=>'Đã hoàn thành (ký)','incomplete'=>'Chưa hoàn thành','taught'=>'Đã dạy','substitute'=>'Dạy thay','makeup'=>'Dạy bù','off'=>'Nghỉ / hoãn / hủy','missing_ppct'=>'Môn chưa có PPCT','rate'=>'Tỷ lệ hoàn thành'];}
function lb_stats_metric(string $value):string{return isset(lb_stats_metric_labels()[$value])?$value:'';}
function lb_stats_panel(string $value):string{return in_array($value,['teacher','subject','class','group','week'],true)?$value:'teacher';}
function lb_stats_metric_rows(array $rows,string $metric):array {
 if(in_array($metric,['scheduled','rate'],true))return $rows;
 return array_values(array_filter($rows,static function($r)use($metric){
  $signed=!empty($r['signed_at'])||!empty($r['_completed']);
  if($metric==='completed')return $signed;
  if($metric==='incomplete')return !$signed;
  if($metric==='missing_ppct')return !empty($r['_ppct_missing']);
  $status=lb_stat_status($r);
  return $metric==='off'?in_array($status,['holiday','teacher_absent','class_absent','postponed','cancelled'],true):$status===$metric;
 }));
}
