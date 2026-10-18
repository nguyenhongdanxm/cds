<?php
require __DIR__.'/../includes/buzzer_audio.php';
function check($ok,$label){if(!$ok)throw new RuntimeException($label);}
function reject(callable $call,$label){try{$call();}catch(BuzzerError $e){return;}throw new LogicException('Not rejected: '.$label);}
$dir=sys_get_temp_dir().'/cds-buzzer-audio-'.bin2hex(random_bytes(8));mkdir($dir,0700);define('DATA_PATH',$dir);
try{
    $file=$dir.'/fixture.wav';$pcm=str_repeat("\0",1600);
    file_put_contents($file,'RIFF'.pack('V',36+strlen($pcm)).'WAVEfmt '.pack('VvvVVvv',16,1,1,8000,16000,2,16).'data'.pack('V',strlen($pcm)).$pcm);
    check(in_array(bz_audio_validate($file,filesize($file)),['audio/wav','audio/x-wav'],true),'Actual WAV accepted');
    file_put_contents($dir.'/payload','<?php echo "not audio";');reject(fn()=>bz_audio_validate($dir.'/payload',20),'PHP payload rejected regardless of filename');
    reject(fn()=>bz_audio_validate($file,0),'Empty');reject(fn()=>bz_audio_validate($file,10485761),'Oversize');
    foreach(['../../config.php','../audio','https://x','']as$id)reject(fn()=>bz_audio_path($id),'Traversal/id');
    check(bz_audio_path(str_repeat('a',48))===$dir.'/buzzer_audio/'.str_repeat('a',48).'.bin','Opaque non-executable path');
    reject(fn()=>bz_audio_kind('arbitrary'),'Unknown kind');
    $s=bz_initial('Audio test');$s['audio']=['background'=>['id'=>str_repeat('a',48),'name'=>'Nền.wav','mime'=>'audio/wav']];
    check(bz_view($s,1000,'screen')['audio']===$s['audio']&&bz_view($s,1000,'player')['audio']===[],'Only host/screen receive audio metadata');
    echo "PASS audio: WAV content detection, executable/oversize/empty rejection, traversal prevention, private metadata and non-executable storage\n";
}finally{foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}
