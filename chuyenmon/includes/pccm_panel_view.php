<?php
/** Isolate optional editors so malformed data cannot truncate the main assignment board. */
function pccm_render_panel(string $file,array $context=[]): void {
    $bufferLevel=ob_get_level();ob_start();
    try{
        extract($context,EXTR_SKIP);
        require $file;
        $html=(string)ob_get_clean();
    }catch(Throwable $error){
        while(ob_get_level()>$bufferLevel)ob_end_clean();
        error_log('[CDS PCCM editor '.basename($file).'] '.$error->getMessage());
        $html='<div class="alert alert-warning" role="alert">Chưa tải được bộ nhập này. Bảng phân công vẫn được giữ. Vui lòng báo quản trị kiểm tra nhật ký lỗi.</div>';
    }
    echo $html;
}
