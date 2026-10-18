<?php
function bz_can_host(array $user,bool $checkVisibility=true): bool {
    if(empty($user['id'])&&empty($user['username']))return false;
    if(in_array($user['role']??'',['hs','student','parent','phhs'],true))return false;
    if(($user['role']??'')==='admin')return true;
    if(!can_perm_level('hl.xem','view'))return false;
    if(!$checkVisibility)return true;
    $visibility=qp_game_visibility();return !isset($visibility['buzzer'])||(bool)$visibility['buzzer'];
}
function bz_owner(array $user): string { return (string)($user['id']??$user['username']??''); }
