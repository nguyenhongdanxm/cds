<?php
// Caller supplies $loginNotice; gate context resumes the original destination on close.
if(empty($loginNotice))return;
$lnTheme=in_array($loginNotice['theme']??'',['blue','green','amber','violet'],true)?$loginNotice['theme']:'blue';
$lnIcon=array_key_exists($loginNotice['icon']??'',cds_ln_icons())?$loginNotice['icon']:'📢';
?>
<link rel="stylesheet" href="<?=e(BASE_URL)?>assets/login-notice.css?v=20261119-1">
<dialog id="cdsLoginNotice" class="cds-ln-dialog cds-ln-<?=e($lnTheme)?>" aria-labelledby="cdsLnTitle" aria-describedby="cdsLnBody" data-expires="<?=!empty($loginNotice['ends_at'])?(int)strtotime($loginNotice['ends_at']):0?>">
  <form method="post" action="<?=e(BASE_URL)?>login_notice.php" id="cdsLnForm" data-cds-progress="off">
    <input type="hidden" name="action" value="dismiss"><input type="hidden" name="csrf" value="<?=e(cds_ln_csrf())?>"><input type="hidden" name="notice_id" value="<?=e($loginNotice['id'])?>"><input type="hidden" name="context" value="<?=!empty($loginNoticeGate)?'gate':'dashboard'?>">
    <div class="cds-ln-hero"><span class="cds-ln-icon" aria-hidden="true"><?=e($lnIcon)?></span><span class="cds-ln-kicker">THÔNG BÁO NHÀ TRƯỜNG</span><button type="submit" class="cds-ln-x" aria-label="Đóng thông báo">×</button><h2 id="cdsLnTitle"><?=e($loginNotice['title'])?></h2></div>
    <div id="cdsLnBody" class="cds-ln-body"><?=cds_ln_clean((string)$loginNotice['body'])?></div>
    <footer class="cds-ln-footer"><small>Đóng để không hiện lại thông báo này.</small><button type="submit" class="cds-ln-close">Đã hiểu · Đóng</button><p class="cds-ln-error" role="alert" hidden></p></footer>
  </form>
</dialog>
<script src="<?=e(BASE_URL)?>assets/login-notice.js?v=20261119-3" defer></script>
<noscript><style>#cdsLoginNotice{display:block;position:relative;margin:30px auto}</style></noscript>

