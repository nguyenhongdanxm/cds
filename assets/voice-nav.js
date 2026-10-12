(function () {
  'use strict';
  var dataEl = document.getElementById('voiceNavTargets');
  var buttons = document.querySelectorAll('[data-voice-open]');
  if (!dataEl || !buttons.length) return;
  var targets = [];
  try { targets = JSON.parse(dataEl.textContent) || []; } catch (e) { return; }
  var SR = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!SR) return; // Trình duyệt không hỗ trợ (ví dụ Firefox): giữ nút ẩn.
  buttons.forEach(function (b) { b.hidden = false; });

  function norm(s) {
    return String(s || '').toLowerCase().replace(/đ/g, 'd').normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9\s]/g, ' ').replace(/\s+/g, ' ').trim();
  }
  var PREFIX = /^(hay |xin |vui long )?(mo|chuyen den|chuyen toi|chuyen sang|chuyen|di den|di toi|di|den|toi|vao|truy cap|xem)( trang| module| muc| phan)?\s+/;
  var prepared = targets.map(function (t) {
    var names = [norm(t.name)].concat((t.keywords || []).map(norm)).filter(Boolean);
    return { name: t.name, url: t.url, terms: names };
  });

  function scoreTerm(q, term) {
    if (!q || !term) return 0;
    if (q === term) return 1;
    var re = new RegExp('(^| )' + term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '( |$)');
    if (re.test(q)) return 0.9;
    var qw = q.split(' '), tw = term.split(' '), hit = 0;
    tw.forEach(function (w) { if (qw.indexOf(w) !== -1) hit++; });
    var s = hit / tw.length * 0.8;
    return hit === tw.length ? Math.max(s, 0.85) : (tw.length > 1 && hit ? s * 0.8 : (hit ? 0.5 : 0));
  }
  function match(text) {
    var q = norm(text).replace(PREFIX, '');
    var list = prepared.map(function (t) {
      var best = 0;
      t.terms.forEach(function (term) {
        var sc = scoreTerm(q, term) * (1 - Math.min(0.05, Math.abs(term.length - q.length) / 500));
        if (sc > best) best = sc;
      });
      return { t: t, s: best };
    }).filter(function (x) { return x.s >= 0.5; }).sort(function (a, b) { return b.s - a.s; });
    var seen = {};
    return list.filter(function (x) { if (seen[x.t.url]) return false; seen[x.t.url] = 1; return true; });
  }
  function go(t) {
    var allowed = prepared.some(function (p) { return p.url === t.url; });
    if (allowed) window.location.href = t.url;
  }

  var root = document.createElement('div');
  root.className = 'voice-sheet';
  root.hidden = true;
  root.setAttribute('role', 'dialog');
  root.setAttribute('aria-label', 'Điều hướng bằng giọng nói');
  root.innerHTML = '<div class="voice-card"><div class="voice-wave" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>' +
    '<strong class="voice-status" aria-live="polite" role="status"></strong>' +
    '<p class="voice-text" aria-live="polite"></p><div class="voice-options"></div>' +
    '<div class="voice-actions"><button type="button" class="voice-retry">Nói lại</button><button type="button" class="voice-stop">Đóng</button></div></div>';
  document.body.appendChild(root);
  var elStatus = root.querySelector('.voice-status'), elText = root.querySelector('.voice-text'),
    elOpts = root.querySelector('.voice-options'), elStop = root.querySelector('.voice-stop'),
    elRetry = root.querySelector('.voice-retry');
  var rec = null, listening = false, lastFocus = null, finalText = '', gotResult = false, failed = false;

  function setState(state, status, text) {
    root.dataset.state = state;
    elStatus.textContent = status;
    elText.textContent = text || '';
    elRetry.hidden = state === 'listening';
  }
  function showOptions(list) {
    elOpts.textContent = '';
    list.forEach(function (x) {
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = x.t.name;
      b.addEventListener('click', function () { go(x.t); });
      elOpts.appendChild(b);
    });
  }
  function handle(text) {
    var list = match(text);
    if (!list.length) {
      setState('error', 'Không hiểu lệnh', '“' + text + '”. Hãy thử: “mở nội trú”, “chuyển đến thư viện”, “đi tới văn bản”.');
      return;
    }
    if (list.length === 1 || list[0].s - list[1].s >= 0.1) {
      setState('done', 'Đang mở ' + list[0].t.name + '…', text);
      go(list[0].t);
      return;
    }
    setState('choose', 'Bạn muốn mở trang nào?', text);
    showOptions(list.slice(0, 5));
  }
  function stop() {
    if (rec) { try { rec.abort(); } catch (e) {} }
    listening = false;
    root.hidden = true;
    elOpts.textContent = '';
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  function start() {
    elOpts.textContent = '';
    finalText = ''; gotResult = false; failed = false;
    rec = new SR();
    rec.lang = 'vi-VN';
    rec.interimResults = true;
    rec.continuous = false;
    rec.maxAlternatives = 1;
    rec.onstart = function () { listening = true; setState('listening', 'Đang nghe…', ''); };
    rec.onresult = function (ev) {
      var interim = '';
      for (var i = ev.resultIndex; i < ev.results.length; i++) {
        var r = ev.results[i];
        if (r.isFinal) finalText += r[0].transcript; else interim += r[0].transcript;
      }
      gotResult = true;
      elText.textContent = finalText + interim;
    };
    rec.onerror = function (ev) {
      failed = true;
      var m = {
        'not-allowed': 'Chưa cấp quyền micro. Hãy cho phép micro trong cài đặt trình duyệt.',
        'service-not-allowed': 'Chưa cấp quyền micro. Hãy cho phép micro trong cài đặt trình duyệt.',
        'no-speech': 'Không nghe thấy giọng nói. Hãy thử lại.',
        'audio-capture': 'Không tìm thấy micro.',
        'network': 'Lỗi mạng khi nhận dạng giọng nói.'
      };
      if (ev.error === 'aborted') return;
      setState('error', m[ev.error] || 'Không thể nhận dạng giọng nói.', '');
    };
    rec.onend = function () {
      listening = false;
      if (failed || root.hidden) return;
      var t = (finalText || elText.textContent).trim();
      if (t) handle(t); else if (root.dataset.state === 'listening') setState('error', 'Không nghe thấy giọng nói. Hãy thử lại.', '');
    };
    try { rec.start(); } catch (e) { setState('error', 'Không thể bật micro.', ''); }
  }
  function open() {
    lastFocus = document.activeElement;
    root.hidden = false;
    setState('listening', 'Đang nghe…', '');
    start();
    elStop.focus();
  }

  buttons.forEach(function (b) { b.addEventListener('click', function () { if (root.hidden) open(); else stop(); }); });
  elStop.addEventListener('click', stop);
  elRetry.addEventListener('click', function () { if (rec) { try { rec.abort(); } catch (e) {} } start(); });
  root.addEventListener('click', function (e) { if (e.target === root) stop(); });
  document.addEventListener('keydown', function (e) {
    if (e.altKey && !e.ctrlKey && !e.metaKey && (e.key === 'v' || e.key === 'V')) {
      e.preventDefault();
      if (root.hidden) open(); else stop();
    } else if (e.key === 'Escape' && !root.hidden) stop();
  });
})();
