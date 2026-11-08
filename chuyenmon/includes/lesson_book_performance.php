<?php
/** Request-local measurements only. Never stores identities, filters or lesson content. */
function lb_perf_record(string $stage,float $milliseconds):void{
 if(!in_array($stage,['rows','range','slots','statistics','write','save','sign'],true)||!is_finite($milliseconds)||$milliseconds<0)return;
 if(!isset($GLOBALS['lb_perf_metrics'][$stage]))$GLOBALS['lb_perf_metrics'][$stage]=['ms'=>0.0,'calls'=>0];
 $GLOBALS['lb_perf_metrics'][$stage]['ms']+=$milliseconds;$GLOBALS['lb_perf_metrics'][$stage]['calls']++;
}
function lb_perf_snapshot():array{return (array)($GLOBALS['lb_perf_metrics']??[]);}
function lb_perf_header():string{
 $parts=[];foreach(lb_perf_snapshot()as$name=>$metric)$parts[]='lb_'.$name.';dur='.number_format($metric['ms'],2,'.','');return implode(', ',$parts);
}
if(PHP_SAPI!=='cli'){
 header_register_callback(static function():void{$value=lb_perf_header();if($value!=='')header('Server-Timing: '.$value,false);});
 if(getenv('CDS_LB_PERF_LOG')==='1'||(defined('CDS_LB_PERF_LOG')&&CDS_LB_PERF_LOG===true))register_shutdown_function(static function():void{
  $start=(float)($_SERVER['REQUEST_TIME_FLOAT']??microtime(true));
  error_log('[CDS lesson book performance] '.json_encode(['elapsed_ms'=>round((microtime(true)-$start)*1000,2),'peak_bytes'=>memory_get_peak_usage(true),'status'=>http_response_code(),'stages'=>lb_perf_snapshot()],JSON_UNESCAPED_SLASHES));
 });
}
