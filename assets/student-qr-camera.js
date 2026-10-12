/* Same full-frame camera profile as the health card scanner. */
window.StudentQrCamera = class {
  constructor(reader,status,onScan,zoom,torch) { Object.assign(this,{reader,status,onScan,zoom,torch});this.session=0;this.running=false;this.queue=Promise.resolve(); }
  stop() { ++this.session;this.queue=this.queue.then(async()=>{if(this.running){this.running=false;try{await this.scanner.stop()}catch(_){}}});return this.queue; }
  start() {
    const ticket=++this.session;
    this.queue=this.queue.then(async()=>{
      if(this.running){this.running=false;try{await this.scanner.stop()}catch(_){}}
      if(ticket!==this.session)return;
      try{
        if(!window.isSecureContext)throw Error('Camera cần HTTPS. Hãy nhập mã học sinh bên dưới.');
        if(typeof Html5Qrcode==='undefined')throw Error('Chưa tải được bộ quét QR. Hãy tải lại hoặc nhập mã học sinh.');
        this.scanner ||= new Html5Qrcode(this.reader,{formatsToSupport:[Html5QrcodeSupportedFormats.QR_CODE],experimentalFeatures:{useBarCodeDetectorIfSupported:true}});
        this.status.textContent='Đang mở camera…';const camera={facingMode:{ideal:'environment'}};
        const success=text=>{if(this.running&&ticket===this.session)this.onScan(text)};
        try{await this.scanner.start(camera,{fps:15,videoConstraints:{...camera,width:{ideal:1920},height:{ideal:1080},frameRate:{ideal:30}}},success,()=>{})}
        catch(error){if(ticket!==this.session)return;await this.scanner.start(camera,{fps:12},success,()=>{})}
        this.running=true;
        if(ticket!==this.session){this.running=false;await this.scanner.stop();return;}
        let caps={};try{caps=this.scanner.getRunningTrackCapabilities()}catch(_){}
        for(const key of ['focusMode','exposureMode','whiteBalanceMode'])if(caps[key]?.includes('continuous')){try{await this.scanner.applyVideoConstraints({advanced:[{[key]:'continuous'}]})}catch(_){}}
        if(this.zoom){const input=this.zoom.querySelector('input');this.zoom.hidden=!(caps.zoom&&caps.zoom.max>caps.zoom.min);if(!this.zoom.hidden){input.min=caps.zoom.min;input.max=Math.min(caps.zoom.max,6);input.step=caps.zoom.step||.1;const desired=Math.min(Number(input.max),Math.max(Number(input.min),1.75));const value=Math.min(Number(input.max),Number(input.min)+Math.round((desired-Number(input.min))/Number(input.step))*Number(input.step));try{await this.scanner.applyVideoConstraints({advanced:[{zoom:value}]})}catch(_){}input.value=this.scanner.getRunningTrackSettings().zoom??input.min;this.zoom.querySelector('strong').textContent=Number(input.value).toFixed(1)+'×';input.oninput=()=>this.adjust({zoom:Number(input.value)},()=>this.zoom.querySelector('strong').textContent=Number(input.value).toFixed(1)+'×');}}
        if(this.torch){this.torch.hidden=!caps.torch;this.light=false;this.torch.textContent='Bật đèn';this.torch.onclick=()=>{const value=!this.light;this.adjust({torch:value},()=>{this.light=value;this.torch.textContent=value?'Tắt đèn':'Bật đèn';this.torch.setAttribute('aria-pressed',String(value))})};}
        if(ticket===this.session)this.status.textContent='Camera sẵn sàng. Giữ đủ xa để lấy nét; có thể zoom hoặc bật đèn.';
      }catch(error){if(ticket===this.session)this.status.textContent=error.name==='NotAllowedError'?'Chưa được phép dùng camera. Bấm Quét lại sau khi cấp quyền, hoặc nhập mã học sinh.':error.message;}
    });return this.queue;
  }
  adjust(value,done){const ticket=this.session;this.queue=this.queue.then(async()=>{if(!this.running||ticket!==this.session)return;try{await this.scanner.applyVideoConstraints({advanced:[value]});if(ticket===this.session)done()}catch(_){this.status.textContent='Máy không hỗ trợ điều chỉnh này. Có thể nhập mã học sinh bên dưới.'}});}
};
