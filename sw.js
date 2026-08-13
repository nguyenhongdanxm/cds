const CACHE='cds-shell-v2';
const SHELL=['/manifest.webmanifest','/assets/icons/cds-192.png','/assets/icons/cds-512.png'];
self.addEventListener('install',event=>{event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(SHELL)).catch(()=>null));self.skipWaiting()});
self.addEventListener('activate',event=>event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>key!==CACHE).map(key=>caches.delete(key)))).then(()=>self.clients.claim())));
self.addEventListener('push',event=>{
  let data={title:'CDS – Thông báo mới',body:'Bạn có nội dung mới trên CDS.',url:'/admin.php'};
  try{data=Object.assign(data,event.data?event.data.json():{})}catch(error){}
  const urgent=data.level==='urgent';
  event.waitUntil(Promise.all([
    self.registration.showNotification(data.title,{body:data.body,icon:'/assets/icons/cds-192.png',badge:'/assets/icons/cds-badge-96.png',tag:data.id||'cds-notification',renotify:urgent,requireInteraction:urgent,data:{url:data.url||'/admin.php',id:data.id||''},vibrate:urgent?[250,100,250]:[150,70,150]}),
    'setAppBadge' in self.navigator?self.navigator.setAppBadge(Number(data.badgeCount)||1):Promise.resolve()
  ]));
});
self.addEventListener('notificationclick',event=>{
  event.notification.close();const raw=event.notification.data?.url||'/admin.php';const url=new URL(raw,self.location.origin).href;
  event.waitUntil(fetch('/push_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'mark_read',id:event.notification.data?.id||''}),credentials:'include'}).catch(()=>null).then(()=>clients.matchAll({type:'window',includeUncontrolled:true})).then(list=>{for(const client of list){if('focus'in client){client.navigate(url);return client.focus()}}return clients.openWindow(url)}));
});
