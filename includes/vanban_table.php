<?php $shown = $tab === 'overview' ? array_slice($filteredDocuments, 0, 8) : $filteredDocuments; ?>
<div class="table-wrap desktop-table">
  <table class="table">
    <thead><tr><th>Số, ký hiệu</th><th>Tên văn bản</th><th>Loại</th><th>Ngày ban hành</th><th>Đơn vị</th><th></th></tr></thead>
    <tbody>
    <?php if (!$shown): ?><tr><td colspan="6"><div class="empty"><i class="bi bi-inbox"></i>Chưa có văn bản phù hợp.</div></td></tr>
    <?php else: foreach ($shown as $row): $fileUrl=vb_file_url((string)($row['file_path']??'')); ?>
      <tr>
        <td><strong><?=e($row['symbol']??'')?></strong><div class="sub"><?=e($row['field']??'')?> <?=!empty($row['featured'])?'· ⭐ Nổi bật':''?> <?=!empty($row['dashboard_visible'])?'· 📌 Tổng quan':''?></div></td>
        <td><a class="title doc-title-link" target="_blank" rel="noopener" href="<?=e($fileUrl)?>"><?=e($row['title']??'')?></a><div class="sub"><?=e($row['signer']??'')?></div></td>
        <td><span class="pill"><?=e($row['type']??'')?></span></td><td><?=vb_fmt_date((string)($row['issued_date']??''))?></td>
        <td><?=e($row['issuer']??'')?><div class="sub"><?=e($row['issuer_level']??'')?></div></td>
        <td><?php if($tab==='documents'&&$canManage):?><div class="row-actions"><button type="button" class="btn btn-outline edit-document" data-record="<?=e(json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>"><i class="bi bi-pencil"></i> Sửa</button><form method="post" onsubmit="return confirm('Xóa nội dung văn bản này? Tệp trên Drive vẫn được giữ.');"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="delete_document"><input type="hidden" name="return_tab" value="documents"><input type="hidden" name="id" value="<?=e($row['id']??'')?>"><button class="btn btn-danger" type="submit"><i class="bi bi-trash"></i></button></form></div><?php endif;?></td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<div class="doc-cards">
<?php if(!$shown):?><div class="empty"><i class="bi bi-inbox"></i>Chưa có văn bản phù hợp.</div>
<?php else:foreach($shown as $row):$fileUrl=vb_file_url((string)($row['file_path']??''));?><article class="card doc-card">
  <a class="title doc-title-link" target="_blank" rel="noopener" href="<?=e($fileUrl)?>"><?=e($row['title']??'')?> <?=!empty($row['featured'])?'⭐':''?></a>
  <div class="sub"><?=e($row['symbol']??'')?> · <?=vb_fmt_date((string)($row['issued_date']??''))?></div><div class="meta"><span class="pill"><?=e($row['type']??'')?></span><span class="pill"><?=e($row['field']??'')?></span><?php if(!empty($row['dashboard_visible'])):?><span class="pill success">📌 Tổng quan</span><?php endif;?></div><div class="sub"><?=e($row['issuer']??'')?></div>
  <?php if($tab==='documents'&&$canManage):?><div class="row-actions"><button type="button" class="btn btn-outline edit-document" data-record="<?=e(json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>"><i class="bi bi-pencil"></i> Sửa</button><form method="post" onsubmit="return confirm('Xóa nội dung văn bản này?');"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="delete_document"><input type="hidden" name="return_tab" value="documents"><input type="hidden" name="id" value="<?=e($row['id']??'')?>"><button class="btn btn-danger"><i class="bi bi-trash"></i></button></form></div><?php endif;?>
</article><?php endforeach;endif;?></div>
