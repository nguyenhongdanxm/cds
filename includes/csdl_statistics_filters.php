<?php
/** Shared read-only filters: display and XLSX must use the same population. */
function csdl_sf_value(array $input, string $key): string {
    return isset($input[$key]) && is_scalar($input[$key]) ? trim((string)$input[$key]) : '';
}
function csdl_sf_lower(string $s): string {
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
}
function csdl_sf_level(array $row, bool $teacher = false): string {
    if (!$teacher) {
        $g = (int)($row['grade'] ?? 0);
        return $g >= 6 && $g <= 9 ? 'THCS' : ($g >= 10 && $g <= 12 ? 'THPT' : 'unknown');
    }
    $v = strtoupper((string)($row['teaching_level'] ?? ''));
    $a = strpos($v, 'THCS') !== false; $b = strpos($v, 'THPT') !== false;
    return $a && $b ? 'both' : ($a ? 'THCS' : ($b ? 'THPT' : 'unknown'));
}
function csdl_sf_gender(array $row): string {
    $g = csdl_sf_lower(trim((string)($row['gender'] ?? '')));
    return in_array($g, ['nam','m','male'], true) ? 'Nam' : (in_array($g, ['nữ','nu','f','female'], true) ? 'Nữ' : ($g === '' ? 'Chưa cập nhật' : 'Khác'));
}
function csdl_sf_definitions(): array {
    return [
        'stat_gender'=>['Giới tính','common','gender'], 'stat_ethnicity'=>['Dân tộc','common','ethnicity'],
        'stat_specialty'=>['Chuyên môn','teachers','specialty'], 'stat_position'=>['Chức vụ','teachers','chuc_vu'],
        'stat_rank'=>['Hạng','teachers','hang'], 'stat_room'=>['Phòng KTX','students','room_ktx'],
        'stat_meal'=>['Nhóm ăn','students','meal_group'],
    ];
}
function csdl_sf_filters(array $input): array {
    $out = [];
    foreach (array_merge(['stat_level','stat_q','stat_boarder','stat_missing'], array_keys(csdl_sf_definitions())) as $key) $out[$key] = csdl_sf_value($input, $key);
    if (!in_array($out['stat_level'], ['', 'THCS','THPT','both','unknown'], true)) $out['stat_level']='';
    if (!in_array($out['stat_boarder'], ['', 'yes','no'], true)) $out['stat_boarder']='';
    return $out;
}
function csdl_sf_field(array $r, string $field): string {
    if ($field === 'gender') return csdl_sf_gender($r);
    if ($field === 'team') return trim((string)($r['to_chuyen_mon'] ?? '')) ?: trim((string)($r['pccm_group'] ?? ''));
    return trim((string)($r[$field] ?? ''));
}
function csdl_sf_match(array $row, bool $teacher, array $classMap, array $filters): bool {
    $level = $teacher ? csdl_sf_level($row,true) : csdl_sf_level($classMap[(string)($row['class_id']??'')]??[]);
    $want = $filters['stat_level'];
    if ($want !== '' && $level !== $want && !($teacher && $level === 'both' && in_array($want,['THCS','THPT'],true))) return false;
    foreach (csdl_sf_definitions() as $key => [$label,$scope,$field]) {
        if ($scope !== 'common' && ($teacher ? 'teachers' : 'students') !== $scope) continue;
        if ($filters[$key] !== '' && csdl_sf_lower(csdl_sf_field($row,$field) ?: 'Chưa cập nhật') !== csdl_sf_lower($filters[$key])) return false;
    }
    if (!$teacher && $filters['stat_boarder'] !== '' && (!empty($row['boarder'])) !== ($filters['stat_boarder']==='yes')) return false;
    $missing = explode(':', $filters['stat_missing'], 2);
    if (count($missing)===2 && $missing[0]===($teacher?'teachers':'students')) {
        $allowed = $teacher ? ['code','gender','dob','phone','email','ethnicity','specialty','teaching_level','hang'] : ['code','gender','dob','ethnicity','class_id','parent_name','parent_phone','room_ktx'];
        if (in_array($missing[1],$allowed,true) && trim((string)($row[$missing[1]]??'')) !== '') return false;
    }
    $q=csdl_sf_lower($filters['stat_q']);
    if ($q !== '') {
        $fields=$teacher?['name','code','specialty','chuc_vu','to_chuyen_mon','phone','ethnicity','hometown','address']:['name','code','parent_name','parent_phone','ethnicity','hometown','address','room_ktx'];
        $parts=[];foreach($fields as $f)$parts[]=csdl_sf_field($row,$f);
        if (!$teacher) $parts[]=$classMap[(string)($row['class_id']??'')]['name']??'';
        if (strpos(csdl_sf_lower(implode(' ',$parts)),$q)===false) return false;
    }
    return true;
}
