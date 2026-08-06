<?php
/** Giữ màu khi in PDF và hoàn thiện cover trang trí thẻ học sinh. */
if (defined('STUDENT_CARD_PRINT_COVER_FIX')) return;
define('STUDENT_CARD_PRINT_COVER_FIX', true);

function student_card_print_cover_fix_filter(string $html): string {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    if (!preg_match('~/csdl_student_cards\.php$~', $path) || stripos($html, '</head>') === false) return $html;

    $css = <<<'HTML'
<style id="studentCardPrintCoverFix">
/* Cover đầu thẻ phủ trọn khu vực logo và tên trường */
.card-face .decor-top{
  height:18mm!important;
  border-radius:0 0 62% 16%!important;
  background:
    linear-gradient(145deg,var(--main) 0 52%,var(--second) 52% 82%,var(--accent) 82% 100%)!important;
  overflow:hidden;
}
.card-face .decor-top::after{
  content:"";
  position:absolute;
  left:-8%;right:-8%;bottom:-5.2mm;height:9mm;
  border-radius:50% 50% 0 0;
  background:rgba(255,255,255,.18);
  transform:rotate(-2deg);
}
.card-face .school-head{position:relative;z-index:2;min-height:15mm;padding:1mm 1.2mm 0}
.card-face .agency,.card-face .school-name{color:#fff!important;text-shadow:0 1px 1px rgba(0,0,0,.28)}
.card-face .logo{filter:drop-shadow(0 1px 1px rgba(0,0,0,.2))}

/* Cover chân thẻ nhiều lớp, cân đối hai mặt */
.card-face .decor-bottom{
  height:10mm!important;
  border-radius:58% 18% 0 0!important;
  background:linear-gradient(135deg,var(--main),var(--second))!important;
  overflow:hidden;
}
.card-face .decor-bottom::before{
  content:"";
  position:absolute;
  left:-10%;right:-4%;top:-4.2mm;height:7mm;
  border-radius:0 0 50% 50%;
  background:var(--accent);
  opacity:.92;
  transform:rotate(1.5deg);
}
.card-face .decor-bottom::after{
  content:"";
  position:absolute;
  left:18%;right:-12%;top:-2.6mm;height:6mm;
  border-radius:0 0 55% 55%;
  background:rgba(255,255,255,.2);
  transform:rotate(-2deg);
}

/* Giữ nội dung không bị cover che */
.card-face .face-content{padding-top:2.2mm!important;padding-bottom:8.5mm!important}
.card-face.horizontal .face-content{padding-top:2mm!important;padding-bottom:7.5mm!important}
.card-face .title{margin-top:2mm!important}

/* Bản in/PDF phải giữ đúng màu và hình nền */
@media print{
  html,body,.card-face,.card-face *{
    -webkit-print-color-adjust:exact!important;
    print-color-adjust:exact!important;
    color-adjust:exact!important;
  }
  .card-face,.decor-top,.decor-bottom,.rule-title{
    -webkit-print-color-adjust:exact!important;
    print-color-adjust:exact!important;
  }
  .fold-line::after{display:none!important;content:none!important}
  .fold-line{border-left:1px dashed #64748b!important}
  .card-face .agency,.card-face .school-name{color:#fff!important}
}
</style>
HTML;

    return preg_replace('/<\/head>/i', $css . '</head>', $html, 1) ?? $html;
}
ob_start('student_card_print_cover_fix_filter');
