<?php
/** Read-only lesson aggregation; all report-only annotations live separately. */
function th_categories():array{return ['main'=>'Dạy chính khóa','tn'=>'Ôn thi TN','vao10'=>'Ôn thi vào 10','hsg'=>'Ôn HSG','other'=>'Chưa phân loại / khác'];}
function th_key(string $s):string{return mb_strtolower(preg_replace('/\s+/u',' ',trim($s)),'UTF-8');}
function th_name_key(string $name):string{
 // Preserve T/V as distinct identities; only normalize the same suffix's parentheses.
 $name=th_key($name);return preg_replace('/\s*\(([tv])\)\s*$/u',' $1',$name);
}
function th_date(string $s):bool{$d=DateTimeImmutable::createFromFormat('!Y-m-d',$s);return $d&&$d->format('Y-m-d')===$s;}
function th_dates(string $from,string $to):void{if(!th_date($from)||!th_date($to)||$from>$to||strtotime($to)-strtotime($from)>370*86400)throw new RuntimeException('Chọn khoảng thời gian hợp lệ, tối đa một năm học (371 ngày).');}
function th_read(string $file):array{
 if(!is_file($file))return ['revision'=>0,'roles'=>[],'categories'=>[],'audit'=>[]];
 $prefix="<?php http_response_code(404); exit; ?>\n";$raw=file_get_contents($file);$data=is_string($raw)&&str_starts_with($raw,$prefix)?json_decode(substr($raw,strlen($prefix)),true):null;
 if(!is_array($data))throw new RuntimeException('Không đọc được dữ liệu tổng hợp giờ dạy.');return $data;
}
function th_write(string $file,int $revision,callable $change,string $actor):void{
 $lock=fopen($file.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Không khóa được dữ liệu tổng hợp.');
 try{$data=th_read($file);if((int)$data['revision']!==$revision)throw new RuntimeException('Dữ liệu đã thay đổi ở cửa sổ khác. Hãy tải lại trang.');$data=$change($data);$data['revision']=$revision+1;$data['audit'][]=['at'=>date('c'),'actor'=>$actor,'revision'=>$data['revision']];$data['audit']=array_slice($data['audit'],-300);
 $tmp=tempnam(dirname($file),'.hours-');if($tmp===false)throw new RuntimeException('Không tạo được file tổng hợp.');try{$raw="<?php http_response_code(404); exit; ?>\n".json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(file_put_contents($tmp,$raw)!==strlen($raw)||!chmod($tmp,0640)||!rename($tmp,$file))throw new RuntimeException('Không lưu được dữ liệu tổng hợp.');}finally{if(is_file($tmp))unlink($tmp);}
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function th_catalog(array $staff):array{
 $out=[];$names=[];foreach($staff as$r){if(!is_array($r))continue;$code=trim((string)($r['code']??''));$id=trim((string)($r['id']??''));$name=trim((string)($r['name']??''));if($name==='')continue;$key=$code!==''?'code:'.$code:($id!==''?'id:'.$id:'name:'.th_key($name));$out[$key][]=['key'=>$key,'code'=>$code,'id'=>$id,'name'=>$name,'group'=>trim((string)($r['to_chuyen_mon']??$r['pccm_group']??''))];$names[th_name_key($name)][]=$key;}
 return ['staff'=>$out,'names'=>$names];
}
function th_person(string $name,array $catalog):?array{$keys=$catalog['names'][th_name_key($name)]??[];if(count($keys)!==1)return null;$rows=$catalog['staff'][$keys[0]]??[];return count($rows)===1?$rows[0]:null;}
function th_signed_rows(array $rows,string $from,string $to,array $categories,array $catalog,callable $visible):array{
 th_dates($from,$to);$latest=[];$warnings=[];$out=[];$groups=[];$adminSigned=0;
 $warn=static function(string $key,string $message,array $row)use(&$groups):void{
  if(!isset($groups[$key]))$groups[$key]=['message'=>$message,'count'=>0,'examples'=>[]];
  $groups[$key]['count']++;if(count($groups[$key]['examples'])<3)$groups[$key]['examples'][]=(string)($row['date']??'').' · '.(string)($row['class']??'').' · '.(string)($row['slot_id']??$row['id']??'');
 };

 foreach($rows as$r){if(!is_array($r))continue;$date=(string)($r['date']??'');if($date<$from||$date>$to)continue;$id=trim((string)($r['slot_id']??$r['id']??''));if($id==='')continue;if(!isset($latest[$id])||strcmp((string)($r['updated_at']??$r['signed_at']??''),(string)($latest[$id]['updated_at']??$latest[$id]['signed_at']??''))>=0)$latest[$id]=$r;}
 $physical=[];
 foreach($latest as$id=>$r){if(empty($r['signed_at'])||!in_array($r['status']??'',['taught','substitute','makeup','online','fill'],true))continue;
 $name=trim((string)($r['signed_by']??''));$legacy=$name==='';if($legacy)$name=trim((string)($r['actual_teacher']??''));$person=th_person($name,$catalog);$attribution=$legacy?'legacy_actual':'signer';
 // A generic administrator is an actor, never an invented staff member.
 // Fall back only to the explicit actual teacher, never to a scheduled teacher or a near name.
 $adminActor=!$person&&in_array(th_key($name),['quản trị hệ thống','admin','administrator'],true);
 if($adminActor){$actual=trim((string)($r['actual_teacher']??''));$person=th_person($actual,$catalog);if($person)$attribution='admin_signed_actual';}
 if(!$visible($person,$person?$person['name']:$name))continue;
 if(!$person){$warn('unmatched|'.th_name_key($name),'Chưa ghép được mã giáo viên duy nhất: '.$name.($adminActor?'; cần đối chiếu giáo viên thực tế đã lưu trên tiết học':''),$r);continue;}
 $qty=isset($r['actual_periods'])?(float)$r['actual_periods']:1.0;if(!is_finite($qty)||$qty<=0){$warn('quantity|'.$person['key'],$person['name'].': số tiết thực tế không hợp lệ',$r);continue;}
 if($legacy)$warn('legacy|'.$person['key'],$person['name'].': thiếu tên người ký; tạm lấy giáo viên thực tế',$r);
 if($attribution==='admin_signed_actual')$adminSigned++;
 elseif(!empty($r['actual_teacher'])&&th_name_key($r['actual_teacher'])!==th_name_key($name))$warn('mismatch|'.$person['key'].'|'.th_name_key($r['actual_teacher']),$person['name'].': người ký khác giáo viên thực tế '.$r['actual_teacher'],$r);
 $physicalKey=implode('|',[$person['key'],$r['date']??'',$r['session']??'',$r['period']??'',$r['class']??'']);
 if(!empty($r['period'])&&isset($physical[$physicalKey])){$warn('duplicate|'.$person['key'],$person['name'].': bỏ dòng trùng cùng giáo viên, ngày, buổi, tiết và lớp',$r);continue;}$physical[$physicalKey]=true;
 $kind=(string)($categories[$id]??$r['teaching_category']??'');if(!isset(th_categories()[$kind]))$kind=($r['book_type']??'main')==='session2'?'other':'main';
 $out[]=array_merge($r,['report_id'=>$id,'person'=>$person,'teacher'=>$person['name'],'category'=>$kind,'quantity'=>$qty,'attribution'=>$attribution]);
 }usort($out,fn($a,$b)=>[$a['date'],$a['person']['name'],$a['period']??0]<=>[$b['date'],$b['person']['name'],$b['period']??0]);foreach($groups as$g)$warnings[]=$g['message'].' — '.$g['count'].' dòng. Ví dụ: '.implode('; ',$g['examples']).'.';return ['rows'=>$out,'warnings'=>$warnings,'warning_groups'=>array_values($groups),'notices'=>$adminSigned?['Đã đối chiếu '.$adminSigned.' dòng do tài khoản quản trị ký theo giáo viên thực tế ghi trên tiết học. Tên người ký gốc vẫn giữ nguyên trong bảng chi tiết và Excel.']:[]];
}
function th_role_validate(array $input,array $catalog):array{
 $key=(string)($input['person_key']??'');$people=$catalog['staff'][$key]??[];if(count($people)!==1)throw new RuntimeException('Chọn giáo viên có mã duy nhất trong CSDL.');
 $role=trim((string)($input['role']??''));if($role===''||mb_strlen($role)>150)throw new RuntimeException('Nhập tên nhiệm vụ kiêm nhiệm.');
 $rate=filter_var($input['rate']??'',FILTER_VALIDATE_FLOAT);$weeks=filter_var($input['weeks']??'',FILTER_VALIDATE_FLOAT);
 if($rate===false||$weeks===false||!is_finite($rate)||!is_finite($weeks)||$rate<0||$rate>100||$weeks<0||$weeks>53)throw new RuntimeException('Tiết/tuần từ 0–100; số tuần tính từ 0–53.');
 $from=(string)($input['from']??'');$to=(string)($input['to']??'');th_dates($from,$to);if($weeks>ceil((strtotime($to)-strtotime($from)+86400)/604800))throw new RuntimeException('Số tuần tính vượt độ dài khoảng thời gian.');
 return ['id'=>bin2hex(random_bytes(12)),'person_key'=>$key,'teacher'=>$people[0]['name'],'code'=>$people[0]['code'],'group'=>$people[0]['group'],'role'=>$role,'scope'=>mb_substr(trim((string)($input['scope']??'')),0,100),'rate'=>$rate,'weeks'=>$weeks,'from'=>$from,'to'=>$to,'note'=>mb_substr(trim((string)($input['note']??'')),0,500)];
}
function th_role_add(array $existing,array $new):array{
 foreach($existing as$r)if($r['person_key']===$new['person_key']&&th_key($r['role'])===th_key($new['role'])&&th_key($r['scope'])===th_key($new['scope'])&&$r['from']<=$new['to']&&$r['to']>=$new['from'])throw new RuntimeException('Nhiệm vụ này đã có khoảng thời gian chồng lấn. Hãy xóa dòng sai trước khi nhập lại.');$existing[]=$new;return $existing;
}
function th_roles_range(array $roles,string $from,string $to,callable $visible,array $catalog):array{
 $out=[];$warnings=[];foreach($roles as$r){$staff=$catalog['staff'][$r['person_key']]??[];$p=count($staff)===1?$staff[0]:null;if(!$visible($p,(string)$r['teacher']))continue;
 if($r['to']<$from||$r['from']>$to)continue;if($r['from']<$from||$r['to']>$to){$warnings[]=$r['teacher'].' · '.$r['role'].': kỳ đã khai '.$r['from'].'–'.$r['to'].' giao một phần với khoảng đang xem; chưa cộng, cần chia kỳ/số tuần phù hợp.';continue;}
 $r['quantity']=(float)$r['rate']*(float)$r['weeks'];$r['person']=$p??['key'=>$r['person_key'],'code'=>$r['code'],'name'=>$r['teacher'],'group'=>$r['group']];$out[]=$r;
 }return ['rows'=>$out,'warnings'=>$warnings];
}
function th_totals(array $actual,array $roles):array{
 $out=[];$add=function($p)use(&$out){$k=$p['key'];if(!isset($out[$k]))$out[$k]=['person'=>$p,'main'=>0.0,'tn'=>0.0,'vao10'=>0.0,'hsg'=>0.0,'other'=>0.0,'roles'=>0.0,'actual'=>0.0,'total'=>0.0];return $k;};
 foreach($actual as$r){$k=$add($r['person']);$out[$k][$r['category']]+=$r['quantity'];$out[$k]['actual']+=$r['quantity'];}
 foreach($roles as$r){$k=$add($r['person']);$out[$k]['roles']+=$r['quantity'];}
 foreach($out as&$r)$r['total']=$r['actual']+$r['roles'];unset($r);uasort($out,fn($a,$b)=>strnatcasecmp($a['person']['name'],$b['person']['name']));return $out;
}
