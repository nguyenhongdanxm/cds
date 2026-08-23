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
      if(ps) ps.textContent=att.date ? (String(att.present)+'/'+String(total)) : '—';
      presentCard.title=att.date ? ('Điểm danh '+fmtDate(att.date)+' · '+(att.shift_label||att.shift||'')) : 'Chưa có dữ liệu điểm danh';
    }

    var exitCard=metricByLabel('Phiếu chờ duyệt');
    if(exitCard){ var es=exitCard.querySelector('strong'); if(es)es.textContent=String(Number(data.pending_exits||0)); }
    var healthCard=metricByLabel('Ghi nhận y tế hôm nay');
    if(healthCard){ var hs=healthCard.querySelector('strong'); if(hs)hs.textContent=String(Number(data.health_today||0)); }

    var panel=panelByTitle('Sỹ số điểm danh');
    if(panel){
      var subtitle=panel.querySelector('.overview-panel-title small');
      if(subtitle) subtitle.textContent=att.date ? ('Cập nhật '+fmtDate(att.date)+' · '+(att.shift_label||att.shift||'')) : 'Chưa có dữ liệu';
      var main=panel.querySelector('.overview-att-main');
      if(main){
        var strong=main.querySelector('strong'); if(strong)strong.textContent=att.date ? (String(att.present)+'/'+String(total)) : '—';
        var leftSpan=main.querySelector('div span'); if(leftSpan)leftSpan.textContent=att.date ? ('Có mặt · '+(att.shift_label||att.shift||'')) : 'Chưa có dữ liệu';
        var rightSpan=main.querySelector(':scope > span');
        if(rightSpan){
          if(att.date){
            var reported=Number(att.report_total||0);
            var note='Đã chốt '+(reported>0?reported:total)+' HS';
            if(reported>0 && reported!==total) note+=' · sĩ số hiện tại '+total;
            rightSpan.innerHTML=fmtDate(att.date)+'<br>'+note;
          } else rightSpan.textContent='';
        }
      }
      var minis=panel.querySelectorAll('.overview-mini strong');
      if(minis[0])minis[0].textContent=String(Number(att.absent||0));
      if(minis[1])minis[1].textContent=String(Number(att.excused||0));
      if(minis[2])minis[2].textContent=String(Number(att.late||0));
      panel.dataset.syncSource=att.source||'';
    }
  }
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
