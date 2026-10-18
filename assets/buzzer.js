/* Server authoritative buzzer. No browser-local ordering or winner election. */
(function () {
  'use strict';
  const cfg = JSON.parse(document.getElementById('buzzerConfig').textContent);
  const $ = id => document.getElementById(id);
  const base = cfg.base, endpoint = base + 'hoclieu_game_buzzer_api.php';
  const fragment = new URLSearchParams(location.hash.slice(1));
  let code = cfg.code || fragment.get('code') || '', screenKey = fragment.get('screen') || '';
  let identity = null, state = null, received = 0, online = false, pending = false, pollTimer, polling = false;
  let uploading = false;
  let soundReady = false, audio, seenSound = null, noticeTimer, lastRender = '';
  const clips = {}; let musicSource = null, musicGain = null, musicOffset = 0, musicStarted = 0, musicQuestion = -1;
  const volume = kind => Number($(kind === 'background' ? 'musicVolume' : 'bellVolume')?.value ?? (kind === 'background' ? 20 : 80)) / 100;
  function stopMusic() {
    if (!musicSource) return;
    musicOffset += Math.max(0, audio.currentTime - musicStarted);
    try { musicSource.stop(); } catch (_) {} musicSource = null;
  }
  function syncMusic() {
    if (cfg.view !== 'screen' || !audio) return;
    if (state?.question !== musicQuestion) { stopMusic(); musicOffset = 0; musicQuestion = state?.question; }
    if (!soundReady || !online || state?.status !== 'running' || currentRemaining() <= 0 || !clips.background?.buffer) { stopMusic(); return; }
    if (musicGain) musicGain.gain.value = volume('background');
    if (musicSource) return;
    musicSource = audio.createBufferSource(); musicSource.buffer = clips.background.buffer; musicSource.loop = true;
    musicGain = audio.createGain(); musicGain.gain.value = volume('background'); musicSource.connect(musicGain); musicGain.connect(audio.destination);
    musicStarted = audio.currentTime; musicSource.start(0, musicOffset % musicSource.buffer.duration);
  }
  async function loadClips() {
    if (cfg.view !== 'screen' || !audio || !state) return;
    for (const kind of ['background','bell']) {
      const meta = state.audio?.[kind];
      if (!meta) { if (kind === 'background') stopMusic(); delete clips[kind]; continue; }
      if (clips[kind]?.id === meta.id) continue;
      if (kind === 'background') { stopMusic(); musicOffset = 0; }
      clips[kind] = {id:meta.id, buffer:null};
      try {
        const response = await fetch(endpoint + '?' + new URLSearchParams({action:'audio',role:'screen',code,kind}), {headers:{'X-Buzzer-Token':screenKey},cache:'no-store'});
        if (!response.ok) throw new Error('audio');
        const buffer = await audio.decodeAudioData(await response.arrayBuffer());
        if (state.audio?.[kind]?.id === meta.id) { clips[kind] = {id:meta.id,buffer}; syncMusic(); }
      } catch (_) { tell('Chưa phát được ' + (kind === 'background' ? 'nhạc nền' : 'chuông riêng') + '. Kiểm tra file hoặc tải lại trình chiếu; chuông mặc định vẫn dùng được.'); }
    }
  }
  const phases = {lobby:'Chờ thí sinh vào phòng', reading:'Giáo viên đang đọc câu hỏi', running:'Chuông đã mở · hãy bấm ngay!', answering:'Đã có người giành quyền trả lời', finished:'Câu đã kết thúc', closed:'Phòng đã đóng'};
  const setText = (id, text) => { if ($(id)) $(id).textContent = text; };
  const storageKey = () => 'cds-buzzer-player:' + code;
  function remember(value) { try { localStorage.setItem(storageKey(), JSON.stringify(value)); } catch (_) {} }
  function recall() { try { return JSON.parse(localStorage.getItem(storageKey()) || 'null'); } catch (_) { return null; } }
  function tell(text) { clearTimeout(noticeTimer); setText('notice', text); $('notice').classList.add('visible'); noticeTimer = setTimeout(() => $('notice').classList.remove('visible'), 7000); }
  function connection(ok) { online = ok; setText('connection', ok ? 'Đã kết nối · thứ tự bấm được ghi nhận tại máy chủ' : 'Mất kết nối · đang thử lại, chưa thể xác nhận thao tác'); $('connection').classList.toggle('offline', !ok); syncMusic(); updateButtons(); }
  async function request(action, payload = {}, get = false) {
    const role = cfg.view === 'play' ? 'player' : cfg.view === 'screen' ? 'screen' : 'host';
    const args = {action, role, code, ...payload};
    if (identity?.player_id) args.player_id = identity.player_id;
    const headers = {}, options = {credentials:'same-origin', cache:'no-store', headers};
    const token = role === 'screen' ? screenKey : identity?.token;
    if (token) headers['X-Buzzer-Token'] = token;
    let url = endpoint;
    if (get) url += '?' + new URLSearchParams(args);
    else { options.method = 'POST'; headers['Content-Type'] = 'application/json'; options.body = JSON.stringify({...args, csrf:cfg.csrf}); }
    const controller = new AbortController(); options.signal = controller.signal;
    const timeout = setTimeout(() => controller.abort(), 8000);
    try {
      const response = await fetch(url, options); const data = await response.json();
      if (!response.ok || !data.ok) { const error = new Error(data.message || 'Chưa xác nhận được thao tác.'); error.status = response.status; throw error; }
      return data;
    } finally { clearTimeout(timeout); }
  }
  function sound() {
    if (!soundReady || !audio) return;
    if (clips.bell?.buffer) { const source = audio.createBufferSource(), gain = audio.createGain(); source.buffer = clips.bell.buffer; gain.gain.value = volume('bell'); source.connect(gain); gain.connect(audio.destination); source.start(); return; }
    const start = audio.currentTime;
    [880,1320,1760].forEach((hz, i) => { const oscillator = audio.createOscillator(), gain = audio.createGain(); oscillator.type = 'sine'; oscillator.frequency.value = hz; gain.gain.setValueAtTime(0.0001, start); gain.gain.exponentialRampToValueAtTime(Math.max(0.0001,0.2 * volume('bell') / (i + 1)), start + 0.008); gain.gain.exponentialRampToValueAtTime(0.0001, start + 1.3); oscillator.connect(gain); gain.connect(audio.destination); oscillator.start(start); oscillator.stop(start + 1.4); });
  }
  function currentRemaining() { return state ? Math.max(0, state.remaining_ms - (state.status === 'running' ? Math.max(0, performance.now() - received) : 0)) : 30000; }
  function updateButtons() {
    if (!state) return;
    const enabled = online && !pending, status = state.status;
    if (cfg.view === 'host') {
      audioButtons();
      $('newQuestion').disabled = !enabled || status === 'closed';
      $('start').disabled = !enabled || status !== 'reading' || !state.players.length;
      $('correct').disabled = $('wrong').disabled = !enabled || status !== 'answering';
      $('stop').disabled = !enabled || !['running','answering'].includes(status);
      $('closeRoom').disabled = !enabled || status === 'closed';
    } else if (cfg.view === 'play') {
      const me = state.players.find(p => p.id === identity?.player_id);
      $('bell').disabled = !enabled || !me || me.blocked || me.rank !== null || !['running','answering'].includes(status) || (status === 'running' && currentRemaining() <= 0);
    }
  }
  function renderPlayers() {
    if (!$('players')) return;
    const stamp = JSON.stringify([state.players, state.winner_id, state.status]);
    if (stamp === lastRender) return; lastRender = stamp;
    const list = state.players.slice().sort((a,b) => (a.rank ?? 999) - (b.rank ?? 999));
    $('players').replaceChildren();
    if (!list.length) { const p = document.createElement('p'); p.className = 'muted'; p.textContent = 'Chưa có thí sinh. Chia sẻ mã phòng để bắt đầu.'; $('players').append(p); }
    list.forEach(p => {
      const card = document.createElement('article'); card.className = 'player-card' + (p.blocked ? ' blocked' : '') + (p.id === state.winner_id ? (state.status === 'answering' ? ' winner' : ' winner-decided') : '');
      if (cfg.view === 'host' && ['lobby','reading','finished'].includes(state.status)) { const remove = document.createElement('button'); remove.className = 'plain remove'; remove.textContent = 'Bỏ'; remove.disabled = !online || pending; remove.addEventListener('click', () => { if (confirm('Bỏ ' + p.name + ' khỏi phòng?')) command('remove', {player_id:p.id}); }); card.append(remove); }
      const rank = document.createElement('div'); rank.className = 'rank'; rank.textContent = p.blocked ? 'ĐÃ TRẢ LỜI SAI · KHÓA CÂU NÀY' : p.rank ? 'THỨ TỰ #' + p.rank : 'ĐANG CHỜ';
      const name = document.createElement('h3'); name.textContent = p.name;
      const timing = document.createElement('div'); timing.className = 'timing'; timing.textContent = p.elapsed_ms === null ? 'Sẵn sàng giành quyền trả lời' : (p.elapsed_ms / 1000).toFixed(3) + ' giây từ khi mở lượt';
      card.append(rank, name, timing); $('players').append(card);
    });
  }
  function accept(next) {
    if (next.code !== code) return;
    if (state && (next.version < state.version || (next.version === state.version && next.server_ms < state.server_ms))) return;
    state = next; received = performance.now(); connection(true);
    if (cfg.view === 'screen') {
      loadClips(); syncMusic();
      if (seenSound !== null && next.winner_event > seenSound && next.status === 'answering') sound();
      seenSound = next.winner_event;
    }
    setText('title', next.title); setText('roomCode', cfg.view === 'screen' ? 'MÃ PHÒNG ' + code : code);
    setText('questionLabel', next.question ? 'CÂU ' + next.question + ' · LƯỢT ' + next.race : 'CHUẨN BỊ');
    setText('phase', phases[next.status] || 'Đang chờ');
    const winner = next.players.find(p => p.id === next.winner_id);
    if (cfg.view === 'screen') {
      const ringing = Boolean(winner && next.status === 'answering');
      $('ringingBell').hidden = !ringing;
      $('winner').classList.toggle('ringing', ringing);
      document.querySelector('.stage').classList.toggle('buzzer-active', ringing);
      setText('winnerText', winner ? winner.name + ' giành quyền trả lời' : '');
    } else setText('winner', winner ? '🔔 ' + winner.name + ' giành quyền trả lời' : '');
    setText('lastResult', next.last_result ? next.last_result.name + (next.last_result.result === 'wrong' ? ' trả lời sai · mở lại chuông cho các bạn còn lại' : ' trả lời đúng!') : '');
    if (cfg.view === 'host') {
      audioLabels(next.shared_audio || {});
      $('setup').hidden = true; $('hostRoom').hidden = false; setText('playerCount', next.players.length + ' thí sinh');
      $('playerLink').href = base + 'hoclieu_game_buzzer.php?view=play&code=' + code;
      $('screenLink').href = base + 'hoclieu_game_buzzer.php?view=screen#' + new URLSearchParams({code, screen:next.screen_key});
    }
    if (cfg.view === 'play') {
      const me = next.players.find(p => p.id === identity?.player_id);
      if (!me) { connection(false); return; }
      $('joinPanel').hidden = true; $('playerPanel').hidden = false; setText('myName', me.name);
      setText('myRank', me.blocked ? 'Bạn đã hết quyền bấm ở câu này. Chờ câu mới.' : me.rank ? (me.rank === 1 ? 'Bạn giành quyền trả lời!' : 'Bạn bấm thứ ' + me.rank + ' · chờ giáo viên quyết định') : '');
      if (me.blocked) setText('phase','Chờ câu hỏi mới');
    }
    renderPlayers(); updateButtons();
  }
  async function poll() {
    clearTimeout(pollTimer);
    if (!code || polling) return;
    polling = true;
    try { const data = await request('state', {}, true); accept(data.state); }
    catch (e) { connection(false); if ([403,404].includes(e.status)) tell(e.message); }
    finally { polling = false; pollTimer = setTimeout(poll, !online ? 4000 : document.hidden ? 5000 : state?.status === 'running' || state?.status === 'answering' ? (cfg.view === 'play' ? 1000 : 300) : 1500); }
  }
  async function command(action, payload = {}) {
    if (pending || !state || !online) return;
    pending = true; updateButtons();
    try { const data = await request(action, {version:state.version, question:state.question, race:state.race, ...payload}); accept(data.state); }
    catch (e) { tell(e.message); if (!e.status || e.status >= 500) connection(false); }
    finally { pending = false; lastRender = ''; if (state) renderPlayers(); updateButtons(); poll(); }
  }
  function audioButtons() { document.querySelectorAll('[data-audio-upload],[data-audio-save],[data-audio-reset]').forEach(input => {input.disabled = uploading || !cfg.canConfigureAudio;}); }
  function audioLabels(settings) { for (const kind of ['background','bell']) setText('audio-name-' + kind, settings[kind]?.name || (kind === 'background' ? 'Không có nhạc nền chung' : 'Chuông mặc định')); }
  async function uploadAudio(kind) {
    const input = $('audio-' + kind), file = input.files[0];
    if (uploading || !cfg.canConfigureAudio) return;
    if (!file) {tell('Chọn file âm thanh trước khi bấm tải lên.');return;}
    if (file.size < 1 || file.size > 10485760) {tell('File phải từ 1 byte đến 10 MB.');return;}
    uploading = true; audioButtons(); let uploadId;
    const progress = text => setText('audio-progress-' + kind,text);
    async function retry(action,data) {let error;for(let n=0;n<3;n++){try{return await request(action,data);}catch(e){error=e;if(e.status && e.status<500)throw e;}}throw error;}
    try {
      progress('Đang bắt đầu tải lên…');
      const started = await request('audio_begin',{kind,name:file.name,size:file.size});uploadId=started.upload_id;
      let offset = 0;
      while(offset < file.size) {
        const bytes = new Uint8Array(await file.slice(offset,offset+131072).arrayBuffer());let binary='';for(let n=0;n<bytes.length;n+=8192)binary+=String.fromCharCode(...bytes.subarray(n,n+8192));
        const result = await retry('audio_chunk',{upload_id:uploadId,offset,data:btoa(binary)});offset=result.offset;
        progress('Đã tải ' + Math.floor(offset/file.size*100) + '% · đang kiểm tra/lưu…');
      }
      const result = await retry('audio_finish',{upload_id:uploadId});audioLabels(result.audio);input.value='';progress('Đã lưu và áp dụng cho tất cả các phòng.');tell('Đã áp dụng âm thanh chung.');if(code)poll();
    } catch(error) {
      progress('Chưa lưu: '+error.message);tell(error.message);
      if(uploadId)request('audio_cancel',{upload_id:uploadId}).catch(()=>{});
    } finally {uploading=false;audioButtons();updateButtons();}
  }
  function startRoom(value) { code = value; state = null; lastRender = ''; seenSound = null; history.replaceState(null, '', base + 'hoclieu_game_buzzer.php?code=' + code); poll(); }
  if (cfg.view === 'host') {
    audioButtons();
    request('audio_settings',{},true).then(result => audioLabels(result.audio)).catch(error => tell(error.message));
    document.querySelectorAll('[data-audio-upload]').forEach(input => input.addEventListener('change', () => {
      const file = input.files[0];setText('audio-progress-' + input.dataset.audioUpload,file ? 'Đã chọn: ' + file.name + '. Bấm Tải lên & áp dụng.' : '');
    }));
    document.querySelectorAll('[data-audio-save]').forEach(button => button.addEventListener('click', () => uploadAudio(button.dataset.audioSave)));
    document.querySelectorAll('[data-audio-reset]').forEach(button => button.addEventListener('click', async () => {
      if (uploading || !cfg.canConfigureAudio || !confirm('Áp dụng thay đổi âm thanh cho tất cả các phòng?')) return;
      try { const result = await request('audio_reset',{kind:button.dataset.audioReset});audioLabels(result.audio);tell('Đã áp dụng cho tất cả các phòng.');if(code)poll(); } catch(error) {tell(error.message);}
    }));
    $('createForm').addEventListener('submit', async event => { event.preventDefault(); const button = event.submitter; button.disabled = true;
      try { const data = await request('create',{title:$('roomTitle').value}); startRoom(data.code); }
      catch (e) { tell(e.message); } finally { button.disabled = false; }
    });
    request('list',{},true).then(data => { $('recentRooms').replaceChildren(); if (!data.rooms.length) setText('recentRooms','Chưa có phòng. Tạo phòng đầu tiên ở trên.'); data.rooms.forEach(r => { const b = document.createElement('button'); b.textContent = r.code + ' · ' + r.title + (r.status === 'closed' ? ' (đã đóng)' : ''); b.addEventListener('click', () => startRoom(r.code)); $('recentRooms').append(b); }); }).catch(e => tell(e.message));
    $('newQuestion').addEventListener('click', () => { if (['running','answering'].includes(state?.status) && !confirm('Kết thúc câu hiện tại và mở quyền cho tất cả ở câu mới?')) return; command('question',{seconds:$('seconds').value}); });
    ['start','correct','wrong','stop'].forEach(action => $(action).addEventListener('click', () => command(action)));
    $('closeRoom').addEventListener('click', () => { if (confirm('Đóng phòng và dừng nhận chuông?')) command('close'); });
    if (code) poll(); else { setText('connection','Tạo phòng hoặc mở lại phòng gần đây'); }
  } else if (cfg.view === 'screen') {
    $('sound').addEventListener('click', async () => { try { const Audio = window.AudioContext || window.webkitAudioContext; audio = audio || new Audio(); await audio.resume(); soundReady = true; await loadClips(); sound(); syncMusic(); setText('sound','✓ Âm thanh đã bật'); } catch (_) { tell('Trình duyệt chưa cho phát âm thanh. Hãy kiểm tra loa và thử lại.'); } });
    $('musicVolume').addEventListener('input', syncMusic);
    $('fullscreen').addEventListener('click', () => { if (!document.fullscreenElement) document.documentElement.requestFullscreen?.().catch(() => {}); else document.exitFullscreen?.(); });
    if (code && screenKey) poll(); else tell('Mở màn hình trình chiếu từ bảng điều khiển giáo viên.');
  } else {
    identity = code ? recall() : null;
    if (identity?.player_id) poll();
    $('joinForm').addEventListener('submit', async event => { event.preventDefault(); const button = event.submitter; button.disabled = true;
      try {
        const nextCode = $('joinCode').value.trim(), name = $('playerName').value.trim();
        if (nextCode !== code) { code = nextCode; identity = recall(); state = null; lastRender = ''; }
        if (!identity || identity.name !== name) { const bytes = crypto.getRandomValues(new Uint8Array(32)); identity = {name, token:Array.from(bytes,x => x.toString(16).padStart(2,'0')).join('')}; remember(identity); }
        const data = await request('join',{name,join_token:identity.token}); identity.player_id = data.player_id; remember(identity); accept(data.state); poll();
        history.replaceState(null, '', base + 'hoclieu_game_buzzer.php?view=play&code=' + code);
      } catch (e) { tell(e.message); } finally { button.disabled = false; }
    });
    $('bell').addEventListener('click', () => command('press'));
    $('leave').addEventListener('click', () => { clearTimeout(pollTimer); code = ''; state = null; identity = null; $('playerPanel').hidden = true; $('joinPanel').hidden = false; $('playerName').value = ''; history.replaceState(null, '', base + 'hoclieu_game_buzzer.php?view=play'); });
  }
  setInterval(() => { setText('clock',(currentRemaining()/1000).toFixed(1)); syncMusic(); updateButtons(); }, 50);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
})();
