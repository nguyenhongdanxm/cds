(function(){
  var submit=document.getElementById('aiSubmit');if(!submit)return;
  var input=document.getElementById('aiInput'),reference=document.getElementById('aiReference'),task=document.getElementById('aiTask'),result=document.getElementById('aiResult'),status=document.getElementById('aiStatus'),copy=document.getElementById('aiCopy'),template=document.getElementById('aiTemplate'),sourceFile=document.getElementById('aiSourceFile'),files=document.getElementById('aiReferenceFiles'),download=document.getElementById('aiDownload');
  function addText(parent,tag,className,text){var el=document.createElement(tag);if(className)el.className=className;el.textContent=text;parent.appendChild(el);return el}
  function renderDocument(text,preview){
    result.textContent='';result.classList.remove('empty');result.classList.add('doc-preview');result.dataset.raw=text;
    var paper=document.createElement('article');paper.className='document-paper';result.appendChild(paper);
    var header=preview&&Array.isArray(preview.header)?preview.header:[];
    if(header.length){
      var official=document.createElement('div');official.className='official-header';paper.appendChild(official);
      var national=header.findIndex(function(line){return /CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM/i.test(line)});
      if(national>0){
        var left=document.createElement('div'),right=document.createElement('div');left.className='official-left';right.className='official-right';
        header.slice(0,national).forEach(function(line){addText(left,'div','',line)});
        header.slice(national).forEach(function(line){addText(right,'div','',line)});
        official.appendChild(left);official.appendChild(right);
      }else header.forEach(function(line){addText(official,'div','official-center',line)});
    }
    var body=document.createElement('div');body.className='official-body';paper.appendChild(body);
    text.split(/\r?\n/).forEach(function(raw){
      var line=raw.trim();if(!line){addText(body,'div','doc-space','');return}
      var upper=line.toLocaleUpperCase('vi-VN'),cls='doc-paragraph';
      if(/^(QUYẾT ĐỊNH|KẾ HOẠCH|HƯỚNG DẪN|QUY CHẾ|THÔNG BÁO|TỜ TRÌNH|BÁO CÁO):?$/.test(upper))cls='doc-main-title';
      else if(/^VỀ VIỆC\b/i.test(line))cls='doc-subtitle';
      else if(/^Căn cứ\b/i.test(line))cls='doc-basis';
      else if(/^QUYẾT ĐỊNH:$/.test(upper))cls='doc-main-title';
      else if(/^Điều\s+\d+/i.test(line))cls='doc-article';
      else if(/^(NƠI NHẬN|KT\.|TL\.|TM\.|HIỆU TRƯỞNG|PHÓ HIỆU TRƯỞNG)/i.test(upper))cls='doc-signature';
      else if(/^[A-ZÀ-Ỹ0-9][A-ZÀ-Ỹ0-9\s().,\-–—:]{7,}$/.test(line))cls='doc-section';
      addText(body,'p',cls,line);
    });
  }
  submit.addEventListener('click',async function(){
    var text=input.value.trim();if(!text&&!(sourceFile&&sourceFile.files.length)){input.focus();status.textContent='Vui lòng nhập nội dung hoặc chọn văn bản chính.';return}
    submit.disabled=true;status.textContent='Đang đọc mẫu, kiểm tra căn cứ và soạn văn bản…';result.className='result empty';result.textContent='AI đang chuẩn bị văn bản theo Nghị định 30/2020/NĐ-CP…';
    try{
      var form=new FormData();form.append('csrf',window.CDS_AI.csrf);form.append('assistant',window.CDS_AI.assistant);form.append('task',task.value);form.append('input',text);form.append('reference',reference.value.trim());form.append('template_id',template?template.value:'');
      if(sourceFile&&sourceFile.files[0])form.append('source_document',sourceFile.files[0]);
      if(files)Array.from(files.files).forEach(function(file){form.append('references[]',file)});
      var response=await fetch('ai_api.php',{method:'POST',credentials:'same-origin',body:form});
      var data=await response.json();if(!response.ok||!data.ok)throw new Error(data.message||'Không xử lý được yêu cầu.');
      renderDocument(data.content,data.template_preview||null);result.dataset.templateId=data.template_id||'';
      status.textContent='Đã soạn theo mẫu. Hãy kiểm tra các vị trí [CẦN BỔ SUNG/XÁC MINH] trước khi ban hành.';if(download)download.disabled=false;
    }catch(error){result.className='result empty';result.textContent='Không nhận được kết quả.';delete result.dataset.raw;status.textContent='Lỗi: '+error.message}
    finally{submit.disabled=false}
  });
  copy.addEventListener('click',async function(){var text=(result.dataset.raw||'').trim();if(!text||result.classList.contains('empty'))return;try{await navigator.clipboard.writeText(text);status.textContent='Đã sao chép nội dung văn bản.'}catch(e){status.textContent='Không sao chép được trên trình duyệt này.'}});
  if(download)download.addEventListener('click',function(){
    var text=(result.dataset.raw||'').trim();if(!text||result.classList.contains('empty'))return;
    var form=document.createElement('form');form.method='post';form.action='ai_document_download.php';form.style.display='none';
    [['csrf',window.CDS_AI.csrf],['content',text],['template_id',result.dataset.templateId||'']].forEach(function(pair){var i=document.createElement('input');i.type='hidden';i.name=pair[0];i.value=pair[1];form.appendChild(i)});
    document.body.appendChild(form);form.submit();setTimeout(function(){form.remove()},1000);
  });
})();
