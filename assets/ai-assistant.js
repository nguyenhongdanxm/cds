(function(){
  var submit=document.getElementById('aiSubmit');if(!submit)return;
  var input=document.getElementById('aiInput'),reference=document.getElementById('aiReference'),task=document.getElementById('aiTask'),result=document.getElementById('aiResult'),status=document.getElementById('aiStatus'),copy=document.getElementById('aiCopy');
  submit.addEventListener('click',async function(){
    var text=input.value.trim();if(!text){input.focus();status.textContent='Vui lòng nhập nội dung.';return}
    submit.disabled=true;status.textContent='Đang xử lý…';result.classList.add('empty');result.textContent='AI đang chuẩn bị kết quả…';
    try{
      var response=await fetch('ai_api.php',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({csrf:window.CDS_AI.csrf,assistant:window.CDS_AI.assistant,task:task.value,input:text,reference:reference.value.trim()})});
      var data=await response.json();if(!response.ok||!data.ok)throw new Error(data.message||'Không xử lý được yêu cầu.');
      result.classList.remove('empty');result.textContent=data.content;status.textContent='Đã xử lý xong.';
    }catch(error){result.classList.add('empty');result.textContent='Không nhận được kết quả.';status.textContent='Lỗi: '+error.message}
    finally{submit.disabled=false}
  });
  copy.addEventListener('click',async function(){var text=result.textContent.trim();if(!text||result.classList.contains('empty'))return;try{await navigator.clipboard.writeText(text);status.textContent='Đã sao chép kết quả.'}catch(e){status.textContent='Không sao chép được trên trình duyệt này.'}});
})();
