<?php
function tkb_activity_visible(array $special,string $mode):bool{
    $groups=$special['display_groups']??['school'];
    if(!is_array($groups))return false;
    return in_array('school',$groups,true)||($mode==='teacher'&&in_array('teacher',$groups,true))||(in_array($mode,['class','school'],true)&&in_array('student',$groups,true));
}
/** Presentation only: never persist or alter scheduling constraints. */
function tkb_activity_escape(string $value):string{return htmlspecialchars($value,ENT_QUOTES,'UTF-8');}
function tkb_activity_compact_rows(array $rows):array{
    $out=[];$groups=[];
    foreach($rows as$row){
        if(empty($row['_special'])){$out[]=$row;continue;}
        $key=json_encode([(int)($row['day']??0),(string)($row['session']??''),(int)($row['period']??0),(string)($row['subject']??'')]);
        if(!isset($groups[$key])){$groups[$key]=$row;$groups[$key]['_activity_scopes']=[];}
        foreach((array)($row['_activity_scopes']??[$row['_special_scope']??'Hoạt động chung'])as$scope)$groups[$key]['_activity_scopes'][(string)$scope]=(string)$scope;
    }
    foreach($groups as$row){$row['_activity_scopes']=array_values($row['_activity_scopes']);$out[]=$row;}
    return$out;
}
function tkb_activity_scope(array $row):string{
    $scopes=(array)($row['_activity_scopes']??[$row['_special_scope']??'Hoạt động chung']);
    if(in_array('Toàn trường',$scopes,true))return'Toàn trường';
    if(count($scopes)===1)return(string)reset($scopes);
    $classes=array_filter($scopes,fn($scope)=>str_starts_with($scope,'Lớp '));
    return count($classes)===count($scopes)?count($classes).' lớp':count($scopes).' nhóm áp dụng';
}
function tkb_activity_card(array $row,string $mode):string{
    return '<div class="tkb-activity-card">'.tkb_activity_escape((string)($row['subject']??'Hoạt động chung')).'</div>';
}
function tkb_activity_agenda(array $rows,string $mode):void{
    $groups=[];
    foreach(tkb_activity_compact_rows($rows)as$row){if(empty($row['_special']))continue;
        $scopes=(array)$row['_activity_scopes'];sort($scopes,SORT_NATURAL);
        $key=json_encode([$row['day'],$row['session'],$row['subject'],$scopes]);
        if(!isset($groups[$key]))$groups[$key]=['row'=>$row,'periods'=>[]];
        $groups[$key]['periods'][(int)$row['period']]=(int)$row['period'];
    }
    if(!$groups)return;
    uasort($groups,fn($a,$b)=>[$a['row']['day'],$a['row']['session']==='Sáng'?0:1,min($a['periods'])]<=>[$b['row']['day'],$b['row']['session']==='Sáng'?0:1,min($b['periods'])]);
    echo'<section class="tkb-activity-agenda"><h6>Lịch hoạt động chung'.($mode==='teacher'?' · Tham khảo, tham gia theo phân công':'').'</h6><ul>';
    foreach($groups as$group){$row=$group['row'];$periods=array_values($group['periods']);sort($periods);$ranges=[];$start=$last=array_shift($periods);foreach($periods as$p){if($p===$last+1){$last=$p;continue;}$ranges[]=$start===$last?(string)$start:$start.'–'.$last;$start=$last=$p;}$ranges[]=$start===$last?(string)$start:$start.'–'.$last;
        echo'<li><strong>'.tkb_activity_escape('Thứ '.$row['day'].' · '.$row['session'].' · Tiết '.implode(', ',$ranges)).'</strong> — '.tkb_activity_escape((string)$row['subject']);
        if($mode!=='class')echo' · '.tkb_activity_escape(tkb_activity_scope($row));
        echo'<details><summary>Xem chi tiết</summary>'.tkb_activity_escape($mode==='class'?'Áp dụng cho lớp đang xem':implode(' · ',$row['_activity_scopes'])).'</details></li>';
    }
    echo'</ul></section>';
}
