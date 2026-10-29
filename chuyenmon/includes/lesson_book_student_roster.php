<?php
/**
 * Shared school roster reader for Chuyên môn.
 * Never import root auth/CSDL bootstrap: its globals conflict with module functions
 * and DATA_PATH here points to chuyenmon/data, not the school's shared data.
 */
function lb_student_roster_rows(string $entity): array {
 if(!in_array($entity,['students','classes'],true))return[];
 $root=dirname(__DIR__,2);
 $sqlReader=$root.'/includes/database_sql_read.php';
 if(is_file($sqlReader))require_once $sqlReader;
 if(function_exists('cds_core_sql_rows')){
  try{$rows=cds_core_sql_rows($entity);if(is_array($rows))return array_values(array_filter($rows,'is_array'));}
  catch(Throwable $error){error_log('[CDS lesson book roster SQL fallback] '.$error->getMessage());}
 }
 // Read the root roster only. Do not seed, recover, migrate or rewrite on a lookup.
 $file=$root.'/data/'.$entity.'.json';
 if(!is_file($file)||!is_readable($file))return[];
 $bytes=file_get_contents($file);$rows=$bytes===false?null:json_decode($bytes,true);
 return is_array($rows)?array_values(array_filter($rows,'is_array')):[];
}
