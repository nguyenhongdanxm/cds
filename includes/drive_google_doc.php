<?php
/** Tạo Google Docs từ HTML sinh bởi hệ thống, giữ file trong đúng thư mục Drive đã cấu hình. */
if (!function_exists('cds_drive_upload_google_doc')) {
    function cds_drive_upload_google_doc(string $html, string $title, string $type, array $extra = []): array {
        $settings = cds_drive_settings();
        $folder = cds_drive_folder($type, $settings);
        if (empty($settings['enabled']) || $folder === '') {
            return ['ok'=>false,'message'=>'Google Drive chưa bật hoặc chưa cấu hình thư mục cho loại tài liệu này.'];
        }
        $token = cds_drive_token($settings);
        if (empty($token['ok'])) return $token;

        $title = cds_drive_clean_filename_part($title);
        if ($title === '') $title = 'Biên bản trực nội trú';
        if (function_exists('mb_strcut')) $title = mb_strcut($title, 0, 180, 'UTF-8');
        else $title = substr($title, 0, 180);

        $boundary = 'cds-doc-' . bin2hex(random_bytes(12));
        $meta = json_encode([
            'name' => $title,
            'mimeType' => 'application/vnd.google-apps.document',
            'parents' => [$folder],
            'appProperties' => [
                'cdsType' => $type,
                'cdsGenerated' => '1',
                'cdsSource' => (string)($extra['source_action'] ?? 'noitru-duty-report'),
            ],
        ], JSON_UNESCAPED_UNICODE);
        $body = "--{$boundary}\r\n"
            . "Content-Type: application/json; charset=UTF-8\r\n\r\n{$meta}\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n"
            . "--{$boundary}--";

        $res = cds_drive_http(
            'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&supportsAllDrives=true&fields=id,name,webViewLink,mimeType',
            'POST',
            ['Authorization: Bearer ' . $token['token'], 'Content-Type: multipart/related; boundary=' . $boundary],
            $body
        );
        $json = json_decode($res['body'], true);
        if (!$res['ok'] || empty($json['id'])) {
            return ['ok'=>false,'message'=>$json['error']['message'] ?? $res['error'] ?? ('Không tạo được Google Docs (HTTP ' . $res['status'] . ').')];
        }

        $id = (string)$json['id'];
        $view = (string)($json['webViewLink'] ?? ('https://docs.google.com/document/d/' . rawurlencode($id) . '/view'));
        $download = 'https://docs.google.com/document/d/' . rawurlencode($id) . '/export?format=pdf';
        cds_drive_history_add(array_merge([
            'action'=>'create_google_doc',
            'type'=>$type,
            'name'=>$json['name'] ?? $title,
            'file_id'=>$id,
            'folder_id'=>$folder,
            'mime'=>'application/vnd.google-apps.document',
            'web_view'=>$view,
        ], $extra));
        return ['ok'=>true,'id'=>$id,'name'=>$json['name'] ?? $title,'webViewLink'=>$view,'downloadPdf'=>$download];
    }
}
