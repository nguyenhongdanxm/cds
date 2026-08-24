(function(){
  'use strict';
  if (!/\/noitru\.php$/i.test(location.pathname) || new URLSearchParams(location.search).get('tab') !== 'overview') return;

  function text(el){ return (el && el.textContent || '').trim(); }
  function metricByLabel(label){
    return Array.from(document.querySelectorAll('.overview-metric')).find(function(card){
      var small=card.querySelector('small'); return small && text(small).toLowerCase()===label.toLowerCase();
    }) || null;
  }
  function panelByTitle(title){
    return Array.from(document.querySelectorAll('.overview-panel')).find(function(panel){
      var h=panel.querySelector('.overview-panel-title h6'); return h && text(h).toLowerCase()===title.toLowerCase();
    }) || null;
  }
  function fmtDate(value){
    var m=/^(\d{4})-(\d{2})-(\d{2})$/.exec(value||''); return m ? m[3]+'/'+m[2]+'/'+m[1] : value||'';
  }
  function update(data){
    if(!data || !data.ok) return;
    var total=Number(data.students && data.students.total || 0);
    var att=data.attendance || {};

    var totalCard=metricByLabel('Học sinh nội trú');
    if(totalCard){ var s=totalCard.querySelector('strong'); if(s)s.textContent=String(total); }

    var presentCard=metricByLabel('Có mặt gần nhất');
    if(presentCard){
      var ps=presentCard.querySelector('strong');
      if(ps) ps.textContent=att.date ? (String(att.present)+'/'+String(att.report_total||total)) : '—';
      presentCard.title=att.date ? ('Điểm danh '+fmtDate(att.date)+' · '+(att.shift_label||att.shift||'')+(att.by?' · '+att.by:'')) : 'Chưa có dữ liệu điểm danh';
    }

    var exitCard=metricByLabel('Phiếu chờ duyệt');
    if(exitCard){ var es=exitCard.querySelector('strong'); if(es)es.textContent=String(Number(data.pending_exits||0)); }
    var healthCard=metricByLabel('Ghi nhận y tế hôm nay');
    if(healthCard){ var hs=healthCard.querySelector('strong'); if(hs)hs.textContent=String(Number(data.health_today||0)); }

    var panel=panelByTitle('Sỹ số điểm danh');
    if(panel){
      var subtitle=panel.querySelector('.overview-panel-title small');
      var recent=Array.isArray(data.attendance_recent)?data.attendance_recent:[];
      if(subtitle) subtitle.textContent=recent.length ? (String(recent.length)+' lần điểm danh gần nhất') : 'Chưa có dữ liệu';
      var list=panel.querySelector('[data-attendance-list]');
      if(!list && recent.length){list=document.createElement('div');list.className='overview-att-list';list.dataset.attendanceList='';var body=panel.querySelector('.overview-panel-body');if(body){body.innerHTML='';body.appendChild(list);}}
      if(list && recent.length){
        list.innerHTML=recent.map(function(row,index){
          var reporter=row.by ? ('Người điểm danh: '+escapeHtml(row.by)) : 'Đã chốt đủ báo cáo';
          return '<article class="overview-att-entry'+(index===0?' latest':'')+'"><div><strong>'+Number(row.present||0)+'/'+Number(row.total||total)+'</strong><span>'+escapeHtml(row.shift_label||row.shift||'')+' · '+fmtDate(row.date)+'</span></div><div class="overview-att-counts"><span><b>'+Number(row.absent||0)+'</b> vắng</span><span><b>'+Number(row.excused||0)+'</b> phép</span><span><b>'+Number(row.late||0)+'</b> muộn</span></div><small>'+reporter+'</small></article>';
        }).join('');
      }
      panel.dataset.syncSource=att.source||'';
    }
  }
  function escapeHtml(value){var el=document.createElement('span');el.textContent=String(value||'');return el.innerHTML;}
  function sync(){
    fetch('noitru_overview_api.php?_=' + Date.now(), {credentials:'same-origin',cache:'no-store'})
      .then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json();})
      .then(update)
      .catch(function(err){console.warn('Không đồng bộ được Tổng quan Nội trú:',err);});
  }
  sync();
  var timer=setInterval(sync,60000);
  document.addEventListener('visibilitychange',function(){if(document.visibilityState==='visible')sync();});
  window.addEventListener('beforeunload',function(){clearInterval(timer);});
})();
