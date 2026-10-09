<?php
function tkbs_team_leader(array $user): bool {
    return cds_user_has_group($user,'totruong');
}
function tkbs_wide(array $user): bool {
    return ($user['role']??'')==='admin'||cds_can_feature('cm.pccm','edit');
}
function tkbs_can_approve(array $user): bool {
    return tkbs_wide($user)||tkbs_team_leader($user);
}
function tkbs_teacher_in_scope(array $user,string $teacher): bool {
    if(tkbs_wide($user))return true;
    if(!tkbs_team_leader($user)||trim($teacher)==='')return false;
    $own=trim((string)get_teacher_group((string)($user['teacher_name']??$user['name']??'')));
    $group=trim((string)get_teacher_group($teacher));
    return $own!==''&&$group!==''&&tkb_key($own)===tkb_key($group);
}
function tkbs_row_in_scope(array $user,array $row): bool {
    $teacher=trim((string)($row['absent_teacher']??''));
    return tkbs_teacher_in_scope($user,$teacher!==''?$teacher:(string)($row['substitute_teacher']??''));
}
