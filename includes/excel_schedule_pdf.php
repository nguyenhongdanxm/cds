<?php
/** The existing print route renders HTML in PHP; local Chromium prints those exact bytes. */
function xlsched_pdf_config(): array {
    $config=(array)cds_instance_config('pdf',[]);
    $node=trim((string)($config['node_binary']??''));
    if($node===''||$node[0]!=='/'||!is_file($node)||!is_executable($node))throw new RuntimeException('Chưa cấu hình Node.js cho PDF. Xem docs/LESSON_BOOK_PDF.md và chạy bin/lesson-book-pdf-check.php.');
    $modules=(string)($config['node_modules']??cds_instance_private_path('lesson-book-pdf/node_modules'));
    if($modules===''||$modules[0]!=='/'||!is_dir($modules))throw new RuntimeException('Chưa cài thư viện PDF: chạy npm ci trong thư mục ứng dụng Node.js và cấu hình pdf.node_modules.');
    $chrome=trim((string)($config['chromium_path']??''));
    if($chrome!==''&&($chrome[0]!=='/'||!is_file($chrome)||!is_executable($chrome)))throw new RuntimeException('Đường dẫn Chromium cho PDF không hợp lệ.');
    return ['node'=>$node,'modules'=>$modules,'chrome'=>$chrome,'no_sandbox'=>!empty($config['no_sandbox'])];
}
function xlsched_pdf_command(?string $input,?string $output,int $timeout): array {
    $config=xlsched_pdf_config();$script=dirname(__DIR__).'/tools/lesson-book-pdf/render.cjs';
    if(!is_file($script)||is_link($script))throw new RuntimeException('Thiếu tools/lesson-book-pdf/render.cjs trên hosting. Hãy triển khai đủ bản sửa.');
    $env=getenv();$env['CDS_PDF_NODE_MODULES']=$config['modules'];$env['CDS_PDF_CHROMIUM']=$config['chrome'];$env['CDS_PDF_NO_SANDBOX']=$config['no_sandbox']?'1':'0';$env['CDS_PDF_TIMEOUT_MS']=(string)(max(5,min(120,$timeout))*1000);
    return [array_merge([$config['node'],$script],$input===null?['--check']:[$input,$output]),$env];
}
function xlsched_pdf_render(string $html,string $output,int $timeout): void {
    [$command,$env]=xlsched_pdf_command($html,$output,max(5,$timeout-3));
    $log=$html.'.pdf-status';$error=$html.'.pdf-stderr';$process=null;
    try{
        $process=proc_open($command,[0=>['file','/dev/null','r'],1=>['file',$log,'w'],2=>['file',$error,'w']],$pipes,dirname(__DIR__),$env);
        if(!is_resource($process))throw new RuntimeException('Không khởi chạy được bộ tạo PDF.');
        $start=microtime(true);$exit=null;
        do{$status=proc_get_status($process);if(!$status['running']){$exit=$status['exitcode'];break;}if(microtime(true)-$start>$timeout)throw new RuntimeException('Hết thời gian tạo PDF sổ đầu bài.');usleep(100000);}while(true);
        proc_close($process);$process=null;$result=json_decode((string)@file_get_contents($log),true);
        if($exit!==0||empty($result['ok'])){error_log('[CDS PDF] '.mb_substr((string)@file_get_contents($error),0,2000));throw new RuntimeException('Tạo PDF thất bại. Chạy bin/lesson-book-pdf-check.php để kiểm tra Node.js/Chromium; PDF chưa được tải lên Drive.');}
    }finally{
        if(is_resource($process)){proc_terminate($process);$until=microtime(true)+2;do{$status=proc_get_status($process);if(!$status['running'])break;usleep(100000);}while(microtime(true)<$until);if($status['running'])proc_terminate($process,9);proc_close($process);}
        @unlink($log);@unlink($error);
    }
}
