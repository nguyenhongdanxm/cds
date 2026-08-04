(function(){
function command(editor,cmd,value){editor.focus();document.execCommand(cmd,false,value||null)}
document.querySelectorAll('[data-article-editor]').forEach(function(box){
  var editor=box.querySelector('[data-editor-area]'),hidden=document.getElementById(box.dataset.hidden);
  if(!editor||!hidden)return;
  editor.innerHTML=hidden.value||'';
  box.querySelectorAll('[data-cmd]').forEach(function(button){button.addEventListener('click',function(){var cmd=button.dataset.cmd,value=button.dataset.value||'';if(cmd==='createLink')value=prompt('Nhập địa chỉ liên kết:','https://')||'';if(value||cmd!=='createLink')command(editor,cmd,value)})});
  box.querySelectorAll('[data-format]').forEach(function(select){select.addEventListener('change',function(){command(editor,'formatBlock',select.value);select.selectedIndex=0})});
  var form=box.closest('form');if(form)form.addEventListener('submit',function(){hidden.value=editor.innerHTML.trim()});
  box.articleSetContent=function(value){editor.innerHTML=value||'';hidden.value=value||''};
});
window.setArticleEditorContent=function(boxId,value){var box=document.getElementById(boxId);if(box&&box.articleSetContent)box.articleSetContent(value)};
})();