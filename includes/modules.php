<?php
/** Danh sách module hệ sinh thái: live | link | soon */
function get_ecosystem_modules() {
    $modules = [
        [
            'id' => 'tintuc', 'num' => 1,
            'title' => 'Tin tức',
            'subtitle' => 'Website · tin bài · thông báo sự kiện nhà trường',
            'icon' => 'bi-newspaper', 'color' => '#0d6efd',
            'status' => 'link', 'url' => URL_TIN_TUC, 'external' => true,
        ],
        [
            'id' => 'chuyenmon', 'num' => 2,
            'title' => 'Chuyên môn',
            'subtitle' => 'Phân công – kế hoạch – tiến độ - thống kê',
            'icon' => 'bi-journal-bookmark-fill', 'color' => '#198754',
            'status' => 'live', 'url' => URL_CHUYEN_MON, 'external' => false,
        ],
        [
            'id' => 'vanban', 'num' => 3,
            'title' => 'Văn bản',
            'subtitle' => 'Văn thư nội bộ · lấy số · văn bản mẫu',
            'icon' => 'bi-file-earmark-text', 'color' => '#6f42c1',
            'status' => 'live', 'url' => BASE_URL . 'vanban.php', 'external' => false,
        ],
        [
            'id' => 'thuvien', 'num' => 4,
            'title' => 'Thư viện – Thiết bị',
            'subtitle' => 'Sách · mượn trả · kho thiết bị · thống kê',
            'icon' => 'bi-book-half', 'color' => '#6f42c1',
            'status' => 'live', 'url' => BASE_URL . 'thuvien.php', 'external' => false,
        ],
        [
            'id' => 'csdl', 'num' => 5,
            'title' => 'Cơ sở dữ liệu',
            'subtitle' => 'Nguồn chuẩn · tìm kiếm · module khác đồng bộ 1 chiều',
            'icon' => 'bi-database', 'color' => '#20c997',
            'status' => 'live', 'url' => URL_CSDL, 'external' => false,
        ],
        [
            'id' => 'hoclieu', 'num' => 6,
            'title' => 'Học liệu & thi',
            'subtitle' => 'Học liệu số · ngân hàng đề · thi trực tuyến',
            'icon' => 'bi-laptop', 'color' => '#0dcaf0',
            'status' => 'live', 'url' => BASE_URL . 'hoclieu.php', 'external' => false,
        ],
        [
            'id' => 'noitru', 'num' => 7,
            'title' => 'Quản lý nội trú',
            'subtitle' => 'KTX · điểm danh · báo ăn · xin ra vào',
            'icon' => 'bi-building', 'color' => '#d63384',
            'status' => 'live', 'url' => URL_NOITRU, 'external' => false,
        ],
        [
            'id' => 'thidua', 'num' => 8,
            'title' => 'Thi đua',
            'subtitle' => 'Thi đua · khen thưởng · xếp loại',
            'icon' => 'bi-trophy', 'color' => '#ffc107',
            'status' => 'live', 'url' => BASE_URL . 'thidua.php', 'external' => false,
        ],
    ];

    /*
     * Cấu hình module theo từng trường chỉ tác động đến danh sách hiển thị ở
     * trang hệ sinh thái. Không chặn URL, không đổi quyền và không xóa dữ liệu.
     * Nếu cấu hình không khai báo module thì mặc định vẫn hiển thị để tương
     * thích với các bản CDS cũ.
     */
    if (function_exists('cds_school_config')) {
        $configured = cds_school_config('modules', null);
        if (is_array($configured)) {
            $modules = array_values(array_filter($modules, static function (array $module) use ($configured): bool {
                $id = (string)($module['id'] ?? '');
                return !array_key_exists($id, $configured) || (bool)$configured[$id];
            }));
        }
    }

    return $modules;
}

/*
 * Tổng quan: gom các dòng dạy thay cùng ngày/người nghỉ thành một dòng duy nhất.
 * Không thay đổi dữ liệu nguồn; chỉ tối giản cách trình bày trong khối Nhân sự.
 */
if (basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'admin.php' && (string)($_GET['view'] ?? '') === '') {
    ob_start(static function (string $html): string {
        $script = <<<'HTML'
<script id="cds-dashboard-substitution-compact">
(function(){
  var list=document.querySelector('.leave-panel .leave-list');
  if(!list)return;
  var rows=Array.from(list.children).filter(function(el){return el.querySelector('time')&&el.querySelector('p strong');});
  var groups={};
  rows.forEach(function(row){
    var day=(row.querySelector('time strong')||{}).textContent||'';
    var month=((row.querySelector('time span')||{}).textContent||'').replace(/\D+/g,'');
    var name=((row.querySelector('p strong')||{}).textContent||'').trim();
    var small=row.querySelector('p small');
    var text=small?(small.textContent||'').trim():'';
    var key=day+'|'+month+'|'+name;
    if(!groups[key])groups[key]={row:row,day:day,month:month,name:name,hasSub:false};
    if(/^Dạy thay\s*:/i.test(text)||text==='Dạy thay')groups[key].hasSub=true;
    if(groups[key].row!==row)row.remove();
  });
  Object.keys(groups).forEach(function(key){
    var g=groups[key]; if(!g.hasSub)return;
    var small=g.row.querySelector('p small'); if(!small)return;
    var now=new Date(),year=now.getFullYear(),m=parseInt(g.month||'0',10),d=parseInt(g.day||'0',10);
    var candidate=new Date(year,m-1,d); var today=new Date(year,now.getMonth(),now.getDate());
    if(candidate<today&&Math.round((today-candidate)/86400000)>30)year++;
    var date=String(year)+'-'+String(m).padStart(2,'0')+'-'+String(d).padStart(2,'0');
    var href='/chuyenmon/thoikhoabieu.php?tab=substitution&date='+encodeURIComponent(date)+'&absent_teacher='+encodeURIComponent(g.name);
    small.innerHTML='<a href="'+href+'" style="font-weight:700;text-decoration:none">Dạy thay <i class="bi bi-arrow-right-short"></i></a>';
  });
})();
</script>
HTML;
        return str_replace('</body>', $script . '</body>', $html);
    });
    require_once __DIR__ . '/admin_dashboard_compact.php';
}
