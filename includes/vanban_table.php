<?php $shown = $tab === 'overview' ? array_slice($filteredDocuments, 0, 8) : $filteredDocuments; ?>
<div class="table-wrap desktop-table">
  <table class="table document-table <?= $tab === 'overview' ? 'overview-table' : 'manage-table' ?>">
    <thead><tr><th>Số, ký hiệu</th><th>Tên văn bản</th><th>Loại</th><th>Ngày ban hành</th><th>Đơn vị</th><?php if($tab==='documents'&&$canManage):?><th class="action-column">Thao tác</th><?php endif;?></tr></thead>
    <tbody>
    <?php if (!$shown): ?><tr><td colspan="<?= $tab==='documents'&&$canManage?6:5 ?>"><div class="empty"><i class="bi bi-inbox"></i>Chưa có văn bản phù hợp.</div></td></tr>
    <?php else: foreach ($shown as $row): $attachments=vb_document_attachments($row);$fileUrl=vb_file_url((string)($attachments[0]['path']??'')); ?>
      <tr>
        <td><strong><?=e($row['symbol']??'')?></strong><div class="sub"><?=e($row['field']??'')?> <?=!empty($row['dashboard_visible'])?'· 📌 Thông báo Tổng quan':''?></div></td>
        <td class="document-title-cell" data-full-title="<?=e($row['title']??'')?>"><a class="title doc-title-link" target="_blank" rel="noopener" href="<?=e($fileUrl)?>"><?=e($row['title']??'')?></a><div class="sub"><?=e($row['signer']??'')?><?php if(count($attachments)>1):?> · <?=count($attachments)?> tệp<?php endif;?></div><?php if(count($attachments)>1):?><div class="attachment-links"><?php foreach($attachments as $index=>$item):?><a target="_blank" rel="noopener" href="<?=e(vb_file_url($item['path']))?>"><i class="bi bi-paperclip"></i> Tệp <?=$index+1?></a><?php endforeach;?></div><?php endif;?></td>
        <td><span class="pill"><?=e($row['type']??'')?></span></td><td><?=vb_fmt_date((string)($row['issued_date']??''))?></td>
        <td><?=e($row['issuer']??'')?><div class="sub"><?=e($row['issuer_level']??'')?></div></td>
        <?php if($tab==='documents'&&$canManage):?><td class="action-column"><div class="row-actions"><button type="button" class="btn btn-outline edit-document" data-record="<?=e(json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>"><i class="bi bi-pencil"></i> Sửa</button><form method="post" onsubmit="return confirm('Xóa nội dung văn bản này? Tệp trên Drive vẫn được giữ.');"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="delete_document"><input type="hidden" name="return_tab" value="documents"><input type="hidden" name="id" value="<?=e($row['id']??'')?>"><button class="btn btn-danger" type="submit"><i class="bi bi-trash"></i></button></form></div></td><?php endif;?>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
<div class="doc-cards">
<?php if(!$shown):?><div class="empty"><i class="bi bi-inbox"></i>Chưa có văn bản phù hợp.</div>
<?php else:foreach($shown as $row):$attachments=vb_document_attachments($row);$fileUrl=vb_file_url((string)($attachments[0]['path']??''));?><article class="card doc-card">
  <a class="title doc-title-link" target="_blank" rel="noopener" href="<?=e($fileUrl)?>"><?=e($row['title']??'')?></a>
  <div class="sub"><?=e($row['symbol']??'')?> · <?=vb_fmt_date((string)($row['issued_date']??''))?></div><div class="meta"><span class="pill"><?=e($row['type']??'')?></span><span class="pill"><?=e($row['field']??'')?></span><?php if(count($attachments)>1):?><span class="pill"><?=count($attachments)?> tệp</span><?php endif;?><?php if(!empty($row['dashboard_visible'])):?><span class="pill success">📌 Tổng quan</span><?php endif;?></div><div class="sub"><?=e($row['issuer']??'')?></div>
  <?php if($tab==='documents'&&$canManage):?><div class="row-actions"><button type="button" class="btn btn-outline edit-document" data-record="<?=e(json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>"><i class="bi bi-pencil"></i> Sửa</button><form method="post" onsubmit="return confirm('Xóa nội dung văn bản này?');"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="delete_document"><input type="hidden" name="return_tab" value="documents"><input type="hidden" name="id" value="<?=e($row['id']??'')?>"><button class="btn btn-danger"><i class="bi bi-trash"></i></button></form></div><?php endif;?>
</article><?php endforeach;endif;?></div>
