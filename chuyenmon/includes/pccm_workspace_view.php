<?php
/** Same-page editors. Existing forms/actions and permissions remain unchanged. */
?>
<script>
document.addEventListener('DOMContentLoaded',()=>{
 const editor=document.getElementById('pccmCompose');if(!editor||typeof bootstrap==='undefined')return;
 const manual=document.getElementById('cdsManualAssignments'),host=document.getElementById('pccmManualHost');if(manual&&host)host.append(manual);
 function openProgram(){bootstrap.Tab.getOrCreateInstance(document.getElementById('pccmProgramTab')).show();bootstrap.Modal.getOrCreateInstance(editor).show();}
 document.querySelectorAll('[data-pccm-program]').forEach(b=>b.addEventListener('click',openProgram));
 const params=new URL(location.href).searchParams;
 if(params.has('program'))openProgram();
 if(location.hash==='#rasoat')bootstrap.Modal.getOrCreateInstance(document.getElementById('modalAudit')).show();
 if(location.hash==='#doicheo')bootstrap.Modal.getOrCreateInstance(document.getElementById('modalSwap')).show();
 // The form stays directly accessible; only the existing configuration list is collapsed.
 const panels=document.querySelectorAll('#tabProgram .gdp-wrap>.gdp-panel');if(panels.length>1){const list=panels[panels.length-1],details=document.createElement('details'),label=document.createElement('summary');label.textContent=list.querySelector('h6')?.textContent||'Các lớp đã cấu hình';details.className='small border-top pt-2';details.append(label);list.before(details);details.append(list);const title=list.querySelector('h6');if(title)title.hidden=true;}
});
</script>
