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

// Only token-authenticated read paths can skip account/session bootstrap.
// Every state/audio read still checks the room's player or screen credentials.
function bz_token_read_request(array $server,array $query): bool {
    return ($server['REQUEST_METHOD']??'GET')==='GET'
        &&in_array($query['role']??'', ['player','screen'],true)
        &&in_array($query['action']??'state',['state','audio','open_rooms','join_options'],true);
}
