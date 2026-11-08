<?php
// Frozen pre-optimization functions for differential regression coverage.
function reference_lb_curriculum_for(string $subject,string $grade,$ppct,string $class=''): ?array {
 $grade=preg_replace('/\\D+/','',(string)$grade);$ppct=(int)$ppct;if($ppct<1)return null;
 $variant=lb_curriculum_subject_for_class($subject,$class);
 if(!lb_same($variant,$subject))foreach(lb_curriculum_exact_rows($variant,$grade,$class)as$r)if((int)($r['period']??0)===$ppct)return$r;
 if($class!=='')foreach(lb_curriculum()as$r){$rowSubject=(string)($r['subject']??'');if((int)($r['period']??0)!==$ppct||preg_replace('/\\D+/','',$r['grade']??'')!==$grade||!lb_subject_match($subject,$rowSubject))continue;if(lb_is_gdtc_subject($subject)&&!lb_is_gdtc_subject($rowSubject)&&str_starts_with(lb_norm($rowSubject),'gdtc-'))continue;$scope=(string)($r['scope_type']??'school');$target=(string)($r['scope_target']??'');if($scope==='class'&&lb_same($class,$target))return$r;}
 $idx=lb_curriculum_index();foreach([lb_subject_key($subject).'|'.$grade,lb_norm($subject).'|'.$grade,$subject.'|'.$grade]as$k)if(!empty($idx[$k][$ppct]))return$idx[$k][$ppct];
 foreach(($idx['_all'][$grade]??[])as$r){if((int)($r['period']??0)!==$ppct)continue;$ps=(string)($r['subject']??'');if(lb_is_gdtc_subject($subject)&&str_starts_with(lb_norm($ps),'gdtc-'))continue;if(lb_subject_match($subject,$ps)||lb_same_subject($subject,$ps))return$r;$a=lb_subject_key($subject);$b=lb_subject_key($ps);if($a!==''&&$b!==''&&(str_contains($a,$b)||str_contains($b,$a)))return$r;}
 return null;
}
function reference_lb_curriculum_titles_for(string $subject,string $grade,string $class=''):array{
 $grade=preg_replace('/\\D+/','',(string)$grade);$out=[];$variant=lb_curriculum_subject_for_class($subject,$class);
 if(!lb_same($variant,$subject)){foreach(lb_curriculum_exact_rows($variant,$grade,$class)as$r){$p=(int)($r['period']??0);$t=trim((string)($r['title']??''));if($p>0&&$t!=='')$out[(string)$p]=$t;}if($out){ksort($out,SORT_NUMERIC);return$out;}}
 $k=lb_subject_key($subject).'|'.$grade;
 if($class!=='')foreach(lb_curriculum()as$r){$rowSubject=(string)($r['subject']??'');if((int)($r['period']??0)<1||preg_replace('/\\D+/','',(string)($r['grade']??''))!==$grade||!lb_subject_match($subject,$rowSubject))continue;if(lb_is_gdtc_subject($subject)&&str_starts_with(lb_norm($rowSubject),'gdtc-'))continue;if((string)($r['scope_type']??'school')==='class'&&lb_same($class,(string)($r['scope_target']??''))){$p=(int)$r['period'];$t=trim((string)($r['title']??''));if($t!=='')$out[(string)$p]=$t;}}
 if($out){ksort($out,SORT_NUMERIC);return$out;}foreach((array)(lb_curriculum_index()[$k]??[])as$period=>$r){$title=trim((string)($r['title']??''));if((int)$period>0&&$title!=='')$out[(string)$period]=$title;}if($out){ksort($out,SORT_NUMERIC);return$out;}
 foreach(lb_curriculum()as$r){$rs=(string)($r['scope_type']??'school');$rowSubject=(string)($r['subject']??'');if($rs==='class'&&($class===''||!lb_same($class,(string)($r['scope_target']??''))))continue;if(lb_is_gdtc_subject($subject)&&str_starts_with(lb_norm($rowSubject),'gdtc-'))continue;if(lb_subject_match($rowSubject,$subject)&&preg_replace('/\\D+/','',(string)($r['grade']??''))===$grade){$period=(int)($r['period']??0);$title=trim((string)($r['title']??''));if($period>0&&$title!=='')$out[(string)$period]=$title;}}
 ksort($out,SORT_NUMERIC);return$out;
}
