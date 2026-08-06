<?php
/**
 * Gửi yêu cầu xóa phân công thủ công bằng một form độc lập.
 * Tránh lỗi form lồng nhau trong các trang Chuyên môn.
 */
if (!isset($current) || !in_array($current, ['them', 'danhsach'], true)) return;
?>
<script>
(function(){
  function submitDelete(id, returnPage){
    id=String(id||'').trim();
    if(!id)return;
    if(!window.confirm('Xóa phân công thủ công này?'))return;

    var form=document.createElement('form');
    form.method='post';
    form.action=window.location.pathname+window.location.search;
    form.style.display='none';

    var values={
      cds_manual_action:'delete',
      manual_id:id,
      manual_return_page:returnPage||<?= json_encode($current, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>
    };
    Object.keys(values).forEach(function(name){
      var input=document.createElement('input');
      input.type='hidden';input.name=name;input.value=values[name];
      form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
  }

  document.addEventListener('click',function(e){
    var button=e.target.closest('button');
    if(!button)return;
    var form=button.closest('form');
    if(!form)return;
    var action=form.querySelector('input[name="cds_manual_action"]');
    var id=form.querySelector('input[name="manual_id"]');
    if(!action||action.value!=='delete'||!id)return;

    e.preventDefault();
    e.stopPropagation();
    if(e.stopImmediatePropagation)e.stopImmediatePropagation();
    var page=form.querySelector('input[name="manual_return_page"]');
    submitDelete(id.value,page?page.value:'');
  },true);

  document.addEventListener('submit',function(e){
    var form=e.target;
    if(!(form instanceof HTMLFormElement))return;
    var action=form.querySelector('input[name="cds_manual_action"]');
    var id=form.querySelector('input[name="manual_id"]');
    if(!action||action.value!=='delete'||!id)return;

    e.preventDefault();
    e.stopPropagation();
    if(e.stopImmediatePropagation)e.stopImmediatePropagation();
    var page=form.querySelector('input[name="manual_return_page"]');
    submitDelete(id.value,page?page.value:'');
  },true);
})();
</script>
