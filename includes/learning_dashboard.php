<?php
/** Input rows already obey the learning hub's approval/owner/viewer visibility policy. */
function lh_dashboard_model(array $visibleItems,array $links,array $catalog,array $visibility,bool $visibilityLoaded,array $permissions,bool $admin): array {
    $result=[];
    foreach(['resources'=>['resource','Học liệu','bi-collection','Bài giảng, tài liệu và nội dung phục vụ dạy học.'],'exams'=>['exam','Kiểm tra và thi','bi-ui-checks','Đề kiểm tra, bài thi thử và các kỳ thi.']]as$key=>[$section,$title,$icon,$description]){
        if(empty($permissions[$key]))continue;
        $rows=array_values(array_filter($visibleItems,static fn($row)=>is_array($row)&&($row['section']??'')===$section));
        // Featured first, then newest; a non-featured approved item is still shown.
        usort($rows,static function($a,$b){$featured=(int)(!empty($b['approved'])&&!empty($b['featured']))<=>(int)(!empty($a['approved'])&&!empty($a['featured']));return $featured?:strcmp((string)($b['created_at']??''),(string)($a['created_at']??''));});
        $result[$key]=['title'=>$title,'icon'=>$icon,'description'=>$description,'count'=>count($rows),'items'=>array_slice($rows,0,6),'unavailable'=>false];
    }
    if(!empty($permissions['games'])){
        $games=$visibilityLoaded?array_values(array_filter($catalog,static fn($game)=>$admin||!array_key_exists($game['key'],$visibility)||(bool)$visibility[$game['key']])):[];
        $result['games']=['title'=>'Trò chơi','icon'=>'bi-controller','description'=>'Hoạt động tương tác giúp lớp học thêm sôi nổi.','count'=>count($games),'items'=>array_slice($games,0,8),'unavailable'=>!$visibilityLoaded];
    }
    if(!empty($permissions['links']))$result['links']=['title'=>'Liên kết hữu ích','icon'=>'bi-link-45deg','description'=>'Các nền tảng và công cụ phục vụ dạy học.','count'=>count($links),'items'=>array_slice($links,0,6),'unavailable'=>false];
    return $result;
}
function lh_dashboard_safe_url(string $url): string {
    if($url===''||preg_match('/[\x00-\x20\\\\]/',$url))return '#';
    $parts=parse_url($url);if($parts===false||isset($parts['user'])||isset($parts['pass']))return '#';
    if(isset($parts['scheme']))return in_array(strtolower($parts['scheme']),['http','https'],true)&&!empty($parts['host'])?$url:'#';
    return !isset($parts['host'])&&!str_starts_with($url,'//')?$url:'#';
}
