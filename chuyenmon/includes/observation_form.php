<?php

function cm_observation_form_criteria(): array {
    return [
        ['group'=>'1. Kế hoạch bài dạy','text'=>'Mức độ phù hợp của các hoạt động học với mục tiêu, nội dung và phương pháp dạy học được sử dụng.','max'=>1],
        ['group'=>'1. Kế hoạch bài dạy','text'=>'Mức độ rõ ràng, chính xác của mục tiêu, nội dung, sản phẩm, cách thức tổ chức thực hiện mỗi hoạt động học của học sinh.','max'=>2],
        ['group'=>'1. Kế hoạch bài dạy','text'=>'Mức độ phù hợp của thiết bị dạy học và học liệu được sử dụng để tổ chức các hoạt động học của học sinh.','max'=>1],
        ['group'=>'1. Kế hoạch bài dạy','text'=>'Mức độ phù hợp của phương án kiểm tra, đánh giá trong quá trình tổ chức hoạt động học của học sinh.','max'=>2],
        ['group'=>'2. Hoạt động của giáo viên','text'=>'Mức độ chính xác, phù hợp, sinh động, hấp dẫn của nội dung, phương pháp và hình thức giao nhiệm vụ học tập cho học sinh.','max'=>2],
        ['group'=>'2. Hoạt động của giáo viên','text'=>'Khả năng theo dõi, quan sát, phát hiện kịp thời những khó khăn của học sinh.','max'=>1],
        ['group'=>'2. Hoạt động của giáo viên','text'=>'Mức độ phù hợp, hiệu quả của các biện pháp hỗ trợ và khuyến khích học sinh hợp tác, giúp đỡ nhau khi thực hiện nhiệm vụ học tập.','max'=>2],
        ['group'=>'2. Hoạt động của giáo viên','text'=>'Mức độ chính xác, hiệu quả trong việc tổng hợp, phân tích, đánh giá quá trình và kết quả học tập của học sinh (làm rõ những nội dung/yêu cầu về kiến thức, kĩ năng học sinh cần ghi nhận, thực hiện).','max'=>2],
        ['group'=>'3. Hoạt động của học sinh','text'=>'Khả năng tiếp nhận và sẵn sàng thực hiện nhiệm vụ học tập của học sinh trong lớp.','max'=>2],
        ['group'=>'3. Hoạt động của học sinh','text'=>'Mức độ tích cực, chủ động, sáng tạo, hợp tác của học sinh trong việc thực hiện các nhiệm vụ học tập.','max'=>2],
        ['group'=>'3. Hoạt động của học sinh','text'=>'Mức độ tham gia tích cực của học sinh trong trình bày, thảo luận về kết quả thực hiện nhiệm vụ học tập.','max'=>2],
        ['group'=>'3. Hoạt động của học sinh','text'=>'Mức độ đúng đắn, chính xác, phù hợp của các kết quả thực hiện nhiệm vụ học tập của học sinh.','max'=>1],
    ];
}

function cm_observation_form_rating($score): string {
    if ($score === '' || $score === null || !is_numeric($score)) return '';
    $score=(float)$score;
    return $score>=18?'Giỏi':($score>=13.5?'Khá':($score>=10?'Trung bình':'Không đạt'));
}

function cm_observation_form_recalculate(array &$record): void {
    $assigned=array_values(array_filter(array_map('strval',(array)($record['observers']??$record['assignees']??[]))));
    $evaluations=is_array($record['evaluations']??null)?$record['evaluations']:[];
    $completed=[];
    foreach($evaluations as $key=>$evaluation){
        if(empty($evaluation['completed']))continue;
        $observer=(string)($evaluation['observer']??'');
        if($observer===''||!in_array($observer,$assigned,true))continue;
        $completed[]=(float)($evaluation['total']??0);
    }
    if($completed){
        $record['score']=round(array_sum($completed)/count($completed),2);
        $record['rating']=cm_observation_form_rating($record['score']);
        $record['completed_forms']=count($completed);
    }else{
        $record['score']='';$record['rating']='';$record['completed_forms']=0;
    }
    $record['assigned_forms']=count($assigned);
}

function cm_observation_evaluation_key(string $observer): string {
    return sha1(function_exists('mb_strtolower')?mb_strtolower(trim($observer),'UTF-8'):strtolower(trim($observer)));
}
