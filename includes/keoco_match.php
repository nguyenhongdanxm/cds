<?php
/** Server-authoritative, short-lived matches. Never expose answer keys to team screens. */
function kc_questions(array $rows): array {
 if(count($rows)<1||count($rows)>500)throw new InvalidArgumentException('Cần từ 1 đến 500 câu hỏi.');
 $out=[];foreach($rows as$i=>$row){$q=[];foreach(['question_text','option_a','option_b','option_c','option_d']as$k){$v=$row[$k]??null;if(!is_string($v)||trim($v)===''||strlen($v)>15000)throw new InvalidArgumentException('Câu '.($i+1).': cần nội dung và đủ 4 đáp án, không quá dài.');$q[$k]=trim($v);}$a=filter_var($row['correct_option']??null,FILTER_VALIDATE_INT);if($a===false||$a<1||$a>4)throw new InvalidArgumentException('Câu '.($i+1).': đáp án phải là A–D hoặc 1–4.');if(count(array_unique(array_map(fn($k)=>trim($q[$k]),['option_a','option_b','option_c','option_d'])))!==4)throw new InvalidArgumentException('Câu '.($i+1).': bốn lựa chọn phải khác nhau.');$q['correct_option']=$a;$out[]=$q;}return $out;
}
function kc_new(array $rows,array $settings,float $now): array {
 $rows=kc_questions($rows);if(count($rows)<4)throw new InvalidArgumentException('Cần ít nhất 4 câu để thi đấu.');
 $names=[];foreach(['b','r']as$s){$name=is_string($settings[$s]??null)?trim($settings[$s]):'';preg_match('/^.{0,60}/us',$name,$parts);$names[$s]=($parts[0]??'')?:($s==='b'?'Đội Xanh':'Đội Đỏ');}
 $order=range(0,count($rows)-1);shuffle($order);$other=$order; // Same difficulty pool, different starting question for split mode.
 $other[]=array_shift($other);
 return ['expires'=>$now+7200,'phase'=>'waiting','mode'=>($settings['mode']??'')==='shared'?'shared':'split','names'=>$names,'winBy'=>max(2,min(30,(int)($settings['winBy']??5))),'duration'=>max(60,min(3600,(int)($settings['duration']??180))),'seconds'=>max(0,min(300,(int)($settings['seconds']??30))),'freeze'=>max(0,min(30,(int)($settings['freeze']??5))),'loop'=>!empty($settings['loop']),'bank'=>$rows,'order'=>['b'=>$order,'r'=>$other],'cursor'=>['b'=>0,'r'=>0],'serial'=>['b'=>1,'r'=>1],'score'=>['b'=>0,'r'=>0],'ready'=>['b'=>0,'r'=>0],'due'=>['b'=>0,'r'=>0],'feedback'=>['b'=>null,'r'=>null],'tokens'=>['host'=>bin2hex(random_bytes(32)),'b'=>bin2hex(random_bytes(32)),'r'=>bin2hex(random_bytes(32))],'end'=>0,'winner'=>null,'version'=>0,'last_action'=>null,'events'=>[]];
}
function kc_actor(array $m,string $token): string {foreach($m['tokens']as$role=>$secret)if($token!==''&&hash_equals($secret,$token))return $role;throw new RuntimeException('Liên kết phòng không hợp lệ hoặc đã hết hạn.',403);}
function kc_current(array $m,string $s): ?array {$i=$m['cursor'][$s];if(!$m['loop']&&$i>=count($m['order'][$s]))return null;return $m['bank'][$m['order'][$s][$i%count($m['order'][$s])]];}
function kc_next(array &$m,string $s,float $now,float $delay): void {$m['cursor'][$s]++;$m['serial'][$s]++;$m['ready'][$s]=$now+$delay;$m['due'][$s]=$m['seconds']?$now+$delay+$m['seconds']:0;}
function kc_advance(array &$m,float $now): void {
 if($m['phase']!=='playing')return;
 if($now>=$m['end']){$m['phase']='ended';$m['winner']=$m['score']['b']===$m['score']['r']?null:($m['score']['b']>$m['score']['r']?'b':'r');return;}
 foreach(['b','r']as$s){if($m['due'][$s]&&$now>=$m['due'][$s]&&kc_current($m,$s)){kc_next($m,$s,$now,.8);if($m['mode']==='shared'){kc_next($m,$s==='b'?'r':'b',$now,.8);break;}}}
 if(!kc_current($m,'b')&&!kc_current($m,'r')){$m['phase']='ended';$m['winner']=$m['score']['b']===$m['score']['r']?null:($m['score']['b']>$m['score']['r']?'b':'r');}
}
function kc_command(array &$m,string $role,string $action,array $input,float $now): void {
 kc_advance($m,$now);
 if($action==='begin'||$action==='stop'){
  if($role!=='host')throw new RuntimeException('Chỉ giáo viên điều khiển trận.',403);
  if($action==='stop'){$m['phase']='ended';$m['winner']=$m['score']['b']===$m['score']['r']?null:($m['score']['b']>$m['score']['r']?'b':'r');return;}
  if($m['phase']!=='waiting')throw new RuntimeException('Trận đã bắt đầu.',409);
  $m['phase']='playing';$m['end']=$now+$m['duration'];foreach(['b','r']as$s)$m['due'][$s]=$m['seconds']?$now+$m['seconds']:0;$m['version']++;return;
 }
 if($action!=='answer')throw new InvalidArgumentException('Thao tác không hợp lệ.');
 $s=$role==='host'?(string)($input['side']??''):$role;
 if(!in_array($s,['b','r'],true)||$m['phase']!=='playing')throw new RuntimeException('Trận chưa bắt đầu hoặc đã kết thúc.',409);
 if($now<$m['ready'][$s]||(int)($input['serial']??0)!==$m['serial'][$s])throw new RuntimeException('Câu đã đổi hoặc đội đang nghỉ. Hãy chờ cập nhật.',409);
 $q=kc_current($m,$s);$answer=filter_var($input['answer']??null,FILTER_VALIDATE_INT);if(!$q||$answer===false||$answer<0||$answer>3)throw new InvalidArgumentException('Đáp án không hợp lệ.');
 $ok=$answer===$q['correct_option']-1;$m['feedback'][$s]=['correct'=>$ok,'answer'=>$q['correct_option']-1,'serial'=>$m['serial'][$s],'until'=>$now+1.2];
 if($ok)$m['score'][$s]++;
 $delay=$ok?.9:max(.9,$m['freeze']);kc_next($m,$s,$now,$delay);
 if($m['mode']==='shared'){$other=$s==='b'?'r':'b';kc_next($m,$other,$now,$delay);}
 $m['version']++;
 $m['last_action']=['id'=>$m['version'],'side'=>$s,'correct'=>$ok];
 $m['events']=array_slice(array_merge($m['events']??[],[$m['last_action']]),-12);
 if(abs($m['score']['b']-$m['score']['r'])>=$m['winBy']){$m['phase']='ended';$m['winner']=$m['score']['b']>$m['score']['r']?'b':'r';}
 kc_advance($m,$now);
}
function kc_view(array $m,string $role,float $now): array {
 $v=array_intersect_key($m,array_flip(['phase','mode','names','winBy','score','winner','version','last_action','events']));$v['remaining']=max(0,(int)ceil($m['end']-$now));$v['teams']=[];
 foreach($role==='host'?['b','r']:[$role]as$s){$q=kc_current($m,$s);if($q)unset($q['correct_option']);$v['teams'][$s]=['serial'=>$m['serial'][$s],'question'=>$m['phase']==='playing'&&$now>=$m['ready'][$s]?$q:null,'wait'=>max(0,(int)ceil($m['ready'][$s]-$now)),'seconds'=>$m['due'][$s]?max(0,(int)ceil($m['due'][$s]-$now)):null,'feedback'=>($m['feedback'][$s]['until']??0)>$now?$m['feedback'][$s]:null];}return $v;
}
function kc_dir(): string {$dir=sys_get_temp_dir().'/cds-keoco-'.substr(hash('sha256',__DIR__),0,16);if(!is_dir($dir)&&!mkdir($dir,0700,true)&&!is_dir($dir))throw new RuntimeException('Không tạo được thư mục phòng.');return $dir;}
function kc_create_file(array $m): string {
 $dir=kc_dir();$code=bin2hex(random_bytes(6));$path=$dir.'/'.$code.'.json';$f=fopen($path,'x');if(!$f)throw new RuntimeException('Không tạo được phòng.');chmod($path,0600);try{if(fwrite($f,json_encode($m,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE))===false)throw new RuntimeException('Không lưu được phòng.');}finally{fclose($f);}
 if(random_int(1,20)===1)foreach(glob($dir.'/*.json')as$old)if(filemtime($old)<time()-7300)@unlink($old);return $code;
}
function kc_room(string $code,string $token,string $action,array $input=[]): array {
 if(!preg_match('/^[a-f0-9]{12}$/D',$code))throw new RuntimeException('Mã phòng không hợp lệ.',404);
 $path=kc_dir().'/'.$code.'.json';$f=@fopen($path,'r+');if(!$f)throw new RuntimeException('Phòng không tồn tại hoặc đã hết hạn.',404);
 try{if(!flock($f,LOCK_EX))throw new RuntimeException('Phòng đang bận.');$m=json_decode(stream_get_contents($f),true,32,JSON_THROW_ON_ERROR);$now=microtime(true);if(($m['expires']??0)<$now)throw new RuntimeException('Phòng đã hết hạn. Tạo phòng mới.',410);$role=kc_actor($m,$token);kc_advance($m,$now);if($action!=='state')kc_command($m,$role,$action,$input,$now);$json=json_encode($m,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);rewind($f);if(fwrite($f,$json)!==strlen($json))throw new RuntimeException('Không lưu được trạng thái.');ftruncate($f,strlen($json));fflush($f);return kc_view($m,$role,$now);}finally{flock($f,LOCK_UN);fclose($f);}
}
