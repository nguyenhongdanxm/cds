(function () {
 'use strict';
 const sheet=document.querySelector('.club-print-sheet');
 const button=Array.from(document.querySelectorAll('button')).find(b=>b.textContent.includes('In danh sách các câu lạc bộ'));
 if(!sheet||!button)return;
 const articles=Array.from(sheet.querySelectorAll('article'));
 const select=document.createElement('select');
 select.className='form-select d-inline-block me-2';select.style.maxWidth='360px';
 select.setAttribute('aria-label','Chọn câu lạc bộ cần in');
 select.add(new Option('Chọn câu lạc bộ cần in…',''));
 select.add(new Option('Tất cả câu lạc bộ','all'));
 articles.forEach((article,i)=>select.add(new Option(article.querySelector('h2').textContent,String(i))));
 button.before(select);button.removeAttribute('onclick');
 button.addEventListener('click',()=>{
  if(select.value===''){alert('Hãy chọn câu lạc bộ cần in.');return;}
  document.getElementById('clubPrintFrame')?.remove();
  const frame=document.createElement('iframe');frame.id='clubPrintFrame';frame.title='Bản in danh sách câu lạc bộ';
  frame.style.cssText='position:fixed;left:-10000px;top:0;width:794px;height:1123px;border:0';
  document.body.appendChild(frame);
  const doc=frame.contentDocument;doc.open();doc.write('<!doctype html><html lang="vi"><head><meta charset="utf-8"><title>Danh sách thành viên câu lạc bộ</title></head><body></body></html>');doc.close();
  const style=doc.createElement('style');style.textContent=`
   @page{size:A4 portrait;margin:20mm 15mm 20mm 30mm}
   *{box-sizing:border-box}body{margin:0;color:#000;background:#fff;font:13pt/1.3 "Times New Roman",serif}
   .club-print-letterhead{display:grid;grid-template-columns:1fr 1.2fr;gap:4mm;text-align:center;font-size:12pt;margin-bottom:6mm}
   .club-print-letterhead strong{display:block;font-weight:700}.club-print-authority{text-transform:uppercase;font-weight:400}.club-print-school{text-transform:uppercase;font-weight:700}.club-print-school-rule{width:40%;border-bottom:.6pt solid #000;margin:2mm auto 0}.club-print-motto{display:inline-block;font-size:13pt;font-weight:700;border-bottom:.6pt solid #000;padding-bottom:1mm;margin:0 auto}.club-print-letterhead{line-height:1.15;break-inside:avoid}
   h1{text-align:center;font-size:16pt;margin:0 0 3mm}h2{text-transform:uppercase;text-align:center;font-size:14pt;margin:0 0 3mm;break-after:avoid}
   .club-print-date{text-align:right;margin-bottom:5mm;font-size:11pt}
   article{margin:0;break-inside:auto}article+article{break-before:page}
   article>div{margin-bottom:3mm;break-after:avoid}table{width:100%;border-collapse:collapse;table-layout:fixed}
   thead{display:table-header-group}tr{break-inside:avoid}th,td{border:1px solid #555;padding:2mm 2.5mm;font-size:11pt;overflow-wrap:anywhere}
   th{background:#eee;print-color-adjust:exact}th:first-child{width:9%}th:nth-child(2){width:46%}th:nth-child(3){width:15%}
   td:first-child,td:nth-child(3){text-align:center}
  `;doc.head.appendChild(style);
  for(const el of sheet.children){
   if(el.tagName!=='ARTICLE')doc.body.appendChild(el.cloneNode(true));
  }
  const chosen=select.value==='all'?articles:[articles[Number(select.value)]];
  chosen.forEach(article=>{
   const copy=article.cloneNode(true),body=copy.querySelector('tbody');
   const rows=Array.from(body.rows).filter(row=>row.cells.length===4);
   rows.sort((a,b)=>{
    const byClass=a.cells[2].textContent.localeCompare(b.cells[2].textContent,'vi',{numeric:true});
    const name=row=>{const full=row.cells[1].textContent.trim();return full.split(/\s+/).pop()+' '+full;};
    return byClass||name(a).localeCompare(name(b),'vi');
   }).forEach((row,i)=>{row.cells[0].textContent=i+1;body.appendChild(row);});
   doc.body.appendChild(copy);
  });
  // Print only this document, without hidden application panels occupying pages.
  const print=()=>{frame.contentWindow.focus();frame.contentWindow.print();};
  Promise.resolve(doc.fonts?.ready).then(()=>frame.contentWindow.requestAnimationFrame(print));
 });
})();

