<?php
require __DIR__.'/../../includes/buzzer_game.php';
$r=json_decode(stream_get_contents(STDIN),true,64,JSON_THROW_ON_ERROR);
try{
    if($r['action']==='initial')$result=bz_initial($r['title']);
    elseif($r['action']==='teams')$result=bz_teams($r['state'],$r['teams']);
    elseif($r['action']==='view')$result=bz_view($r['state'],$r['now'],$r['role'],$r['id']??'');
    else $result=bz_transition($r['state'],$r['action'],$r['input'],$r['now']);
    echo json_encode(['ok'=>true,'result'=>$result],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
}catch(BuzzerError $e){echo json_encode(['ok'=>false,'message'=>$e->getMessage(),'status'=>$e->getCode()]);}
