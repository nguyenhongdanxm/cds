<?php
/** No network, real instance configuration or production data is used. */
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
function reject(callable $call){$before=$GLOBALS['instance'];try{$call();}catch(InvalidArgumentException $e){check($before===$GLOBALS['instance'],'Rejected changes never saved');return;}throw new RuntimeException('Invalid settings accepted');}
function cds_instance_config($key=null,$default=[]){return $key===null?$GLOBALS['instance']:($GLOBALS['instance'][$key]??$default);}
function cds_instance_save($value){$GLOBALS['instance']=$value;return true;}
function cds_ai_http($url,$headers,$payload){$GLOBALS['http'][]=[$url,$headers,$payload];return $GLOBALS['response'];}
if(!function_exists('curl_init')){function curl_init(){throw new RuntimeException('Network forbidden');}}
define('SCHOOL_NAME','Trường kiểm thử');
// Replace only the transport boundary; evaluate all actual settings/routing/parser code.
$source=file_get_contents(__DIR__.'/../includes/ai_service.php');$source=str_replace('function cds_ai_http(', 'function cds_ai_unused_real_http(',$source);eval(substr($source,5));
$GLOBALS['instance']=['other'=>['unchanged'=>true],'ai'=>['enabled'=>true,'provider'=>'openai','providers'=>['openai'=>['api_key'=>'SECRET_OPENAI','model'=>'fixture-openai'],'gemini'=>['api_key'=>'SECRET_GEMINI','model'=>'fixture-gemini']],'document_profile'=>['location'=>'Địa danh thử'],'custom'=>'keep']];
$settings=cds_ai_settings();check(array_keys(cds_ai_active_providers($settings))===['openai'],'Legacy selected provider stays active, others stay off');
$values=['enabled'=>'1','provider_selection_present'=>'1','provider'=>'openai','use_provider_openai'=>'1','use_provider_gemini'=>'1'];
check(cds_ai_save_settings($values),'Save multiple');$settings=cds_ai_settings();check(count(cds_ai_active_providers($settings))===2,'Two active platforms');check($settings['providers']['gemini']['api_key']==='SECRET_GEMINI','Blank password retains saved key');check($settings['custom']==='keep'&&$settings['document_profile']['location']==='Địa danh thử'&&$GLOBALS['instance']['other']['unchanged'],'Unrelated settings preserved');
cds_ai_save_settings(array_replace($values,['use_provider_openai'=>'']));$settings=cds_ai_settings();check($settings['provider']==='gemini'&&$settings['providers']['openai']['api_key']==='SECRET_OPENAI','Untick preserves key and selects allowed default');
reject(fn()=>cds_ai_save_settings(array_replace($values,['use_provider_openai'=>'','use_provider_gemini'=>''])));
reject(fn()=>cds_ai_save_settings($values+['api_key_openai'=>"bad\r\nkey"]));reject(fn()=>cds_ai_save_settings($values+['model_openai'=>[]]));
reject(fn()=>cds_ai_save_settings($values+['clear_key_openai'=>'1','api_key_openai'=>'replacement']));
cds_ai_save_settings(['provider_selection_present'=>'1','clear_key_openai'=>'1']);check(cds_ai_settings()['providers']['openai']['api_key']===''&&!cds_ai_settings()['enabled'],'Explicit key removal and all-off saved');
$GLOBALS['instance']['ai']=['enabled'=>false,'api_key'=>'LEGACY_SECRET','model'=>'legacy-model','provider'=>'codecraft'];check(cds_ai_settings()['providers']['codecraft']['api_key']==='LEGACY_SECRET','Original flat config loads');cds_ai_save_settings(['provider_selection_present'=>'1','clear_key_codecraft'=>'1']);check(cds_ai_settings()['providers']['codecraft']['api_key']===''&&!isset($GLOBALS['instance']['ai']['api_key']),'Deleted legacy key cannot revive');
$GLOBALS['instance']['ai']=cds_ai_defaults();$GLOBALS['instance']['ai']['enabled']=true;
foreach(cds_ai_provider_catalog() as $key=>$meta)$GLOBALS['instance']['ai']['providers'][$key]=['enabled'=>true,'api_key'=>'SECRET_'.$key,'model'=>'fixture-'.$key];
$GLOBALS['instance']['ai']['provider']='openai';
$fixture=['choices'=>[['message'=>['content'=>'CodeCraft fixture']]],'output_text'=>'OpenAI fixture','candidates'=>[['content'=>['parts'=>[['text'=>'Gemini fixture']]]]],'content'=>[['type'=>'text','text'=>'Claude fixture']]];
$GLOBALS['response']=['ok'=>true,'json'=>$fixture];$GLOBALS['http']=[];
foreach(['codecraft','openai','gemini','anthropic'] as $key){$result=cds_ai_call('dayhoc','questions','Nội dung giả','',$key);check($result['ok']&&$result['provider']===$key&&$result['model']==='fixture-'.$key,'Explicit selected platform routed and parsed');check(count($GLOBALS['http'])===array_search($key,['codecraft','openai','gemini','anthropic'],true)+1,'Exactly one request per selection');}
check(cds_ai_call('dayhoc','questions','Nội dung giả')['provider']==='openai','Default shared with other AI utilities');
$GLOBALS['instance']['ai']['providers']['gemini']['enabled']=false;$count=count($GLOBALS['http']);check(!cds_ai_call('dayhoc','questions','x','','gemini')['ok']&&!cds_ai_call('dayhoc','questions','x','','unknown')['ok']&&count($GLOBALS['http'])===$count,'Disabled/unknown providers blocked before transport');
$GLOBALS['response']=['ok'=>false,'message'=>'Mock error','status'=>429];check(!cds_ai_call('dayhoc','questions','x')['ok']&&count($GLOBALS['http'])===$count+1,'Provider failure never forwards content elsewhere');
$GLOBALS['instance']['ai']['enabled']=false;$count=count($GLOBALS['http']);check(!cds_ai_call('dayhoc','questions','x')['ok']&&count($GLOBALS['http'])===$count,'Global off prevents any request');
function e($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}$isAdmin=true;$providers=cds_ai_provider_catalog();$settings=cds_ai_settings();$csrf='fixture';ob_start();require __DIR__.'/../includes/ai_provider_settings_view.php';$html=ob_get_clean();check(!str_contains($html,'SECRET_'),'Saved secrets never rendered');check(substr_count($html,'data-provider-toggle=')===4,'Four editable platform cards');$isAdmin=false;ob_start();require __DIR__.'/../includes/ai_provider_settings_view.php';check(ob_get_clean()==='','Non-admin settings view empty');
if(in_array('--html',$argv,true))file_put_contents('/tmp/ai-providers-fixture.html',$html);
echo "PASS AI providers: migration, multi-toggle, key preservation/removal, defaults, routing, disabled rejection, no fallback, global off and secret-free admin UI\n";
