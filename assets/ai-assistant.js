(function(){
  var submit=document.getElementById('aiSubmit');if(!submit)return;
  var input=document.getElementById('aiInput'),reference=document.getElementById('aiReference'),task=document.getElementById('aiTask'),result=document.getElementById('aiResult'),status=document.getElementById('aiStatus'),copy=document.getElementById('aiCopy'),template=document.getElementById('aiTemplate'),files=document.getElementById('aiReferenceFiles'),download=document.getElementById('aiDownload');
  submit.addEventListener('click',async function(){
    var text=input.value.trim();if(!text){input.focus();status.textContent='Vui lòng nhập nội dung.';return}
    submit.disabled=true;status.textContent='Đang đọc mẫu và soạn văn bản…';result.classList.add('empty');result.textContent='AI đang chuẩn bị văn bản…';
    try{
      var form=new FormData();form.append('csrf',window.CDS_AI.csrf);form.append('assistant',window.CDS_AI.assistant);form.append('task',task.value);form.append('input',text);form.append('reference',reference.value.trim());form.append('template_id',template?template.value:'');
      if(files)Array.from(files.files).forEach(function(file){form.append('references[]',file)});
      var response=await fetch('ai_api.php',{method:'POST',credentials:'same-origin',body:form});
      var data=await response.json();if(!response.ok||!data.ok)throw new Error(data.message||'Không xử lý được yêu cầu.');
      result.classList.remove('empty');result.textContent=data.content;result.dataset.templateId=data.template_id||'';status.textContent='Đã soạn xong. Hãy kiểm tra trước khi tải Word.';if(download)download.disabled=false;
    }catch(error){result.classList.add('empty');result.textContent='Không nhận được kết quả.';status.textContent='Lỗi: '+error.message}
    finally{submit.disabled=false}
  });
  copy.addEventListener('click',async function(){var text=result.textContent.trim();if(!text||result.classList.contains('empty'))return;try{await navigator.clipboard.writeText(text);status.textContent='Đã sao chép kết quả.'}catch(e){status.textContent='Không sao chép được trên trình duyệt này.'}});
  if(download)download.addEventListener('click',function(){
    var text=result.textContent.trim();if(!text||result.classList.contains('empty'))return;
    var form=document.createElement('form');form.method='post';form.action='ai_document_download.php';form.style.display='none';
    [['csrf',window.CDS_AI.csrf],['content',text],['template_id',result.dataset.templateId||'']].forEach(function(pair){var i=document.createElement('input');i.type='hidden';i.name=pair[0];i.value=pair[1];form.appendChild(i)});
    document.body.appendChild(form);form.submit();setTimeout(function(){form.remove()},1000);
  });
})();