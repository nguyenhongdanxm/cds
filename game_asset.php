<?php
// These images/audio/video were public static game assets; never serve arbitrary data.
require_once __DIR__.'/includes/config.php';
require_once __DIR__.'/includes/local_download.php';
$group=(string)($_GET['group']??'');$name=(string)($_GET['name']??'');
if(!in_array($group,['wheel_music','duck_race_music','olympia_stage'],true)||!preg_match('/^[A-Za-z0-9_.-]+\.(?:mp3|ogg|wav|m4a|mp4|webm|png|jpe?g|webp)$/iD',$name)){http_response_code(404);exit;}
$base=realpath(DATA_PATH.'/game_assets/'.$group);$path=realpath(DATA_PATH.'/game_assets/'.$group.'/'.$name);
if(is_link(DATA_PATH.'/game_assets')||is_link(DATA_PATH.'/game_assets/'.$group)||!$base||!$path||!str_starts_with($path,$base.'/')||is_link(DATA_PATH.'/game_assets/'.$group.'/'.$name)){http_response_code(404);exit;}
cds_stream_local_file($path);
