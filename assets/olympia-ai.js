(() => {
  'use strict';
  const panel = document.getElementById('olympia-ai-panel');
  if (!panel) return;
  const find = id => document.getElementById(`oly-ai-${id}`);
  let drafts = [], generating = false, saving = false, stopped = false, controller;
  const status = text => { find('status').textContent = text; };
  const sync = () => {
    find('generate').disabled = generating || saving;
    find('save').disabled = generating || saving;
    find('stop').hidden = !generating;
    panel.querySelectorAll('[data-ai-options] input,[data-ai-options] select,[data-ai-options] textarea')
      .forEach(el => { el.disabled = generating || saving; });
    panel.querySelectorAll('#oly-ai-drafts input,#oly-ai-drafts textarea,#oly-ai-reviewed').forEach(el => { el.disabled = generating || saving; });
    find('save-area').hidden = !drafts.length;
  };
  async function request(action, fields, signal) {
    const body = new URLSearchParams({ action, csrf: panel.dataset.csrf, week_id: panel.dataset.week, ...fields });
    const response = await fetch(location.pathname, { method: 'POST', credentials: 'same-origin', body, signal });
    let data;
    try { data = await response.json(); } catch (_) { throw new Error('Máy chủ chưa trả kết quả hợp lệ. Bản nháp hiện có vẫn được giữ.'); }
    if (!response.ok || !data.ok) throw new Error(data.message || 'Không xử lý được yêu cầu.');
    return data;
  }
  function selected() {
    return [...find('drafts').children].filter(card => card.querySelector('[data-select]').checked).map(card => {
      const row = { ...drafts[Number(card.dataset.index)] };
      row.question_text = card.querySelector('[data-question]').value;
      row.answer_text = card.querySelector('[data-answer]').value;
      return row;
    });
  }
  function render() {
    const host = find('drafts'); host.replaceChildren();
    drafts.forEach((row, index) => {
      const card = document.createElement('div'); card.className = 'card p-3 my-3'; card.dataset.index = String(index);
      const label = document.createElement('label');
      const select = document.createElement('input'); select.type = 'checkbox'; select.checked = true; select.dataset.select = '';
      label.append(select, document.createTextNode(` Chọn câu ${index + 1} · ${row.stage_name} · ${row.grade_scope === 'all' ? 'Tất cả khối' : `Khối ${row.grade_scope}`} · ${row.points} điểm`));
      card.append(label);
      for (const [key, title, max] of [['question_text', 'Nội dung câu hỏi', 2000], ['answer_text', 'Đáp án', 1000]]) {
        const fieldLabel = document.createElement('label'); fieldLabel.className = 'd-block mt-2'; fieldLabel.textContent = title;
        const input = document.createElement('textarea'); input.className = 'form-control'; input.rows = key === 'question_text' ? 3 : 2; input.maxLength = max; input.value = row[key];
        if (key === 'question_text') input.dataset.question = ''; else input.dataset.answer = '';
        input.addEventListener('input', () => { drafts[index][key] = input.value; find('reviewed').checked = false; });
        select.addEventListener('change', () => { find('reviewed').checked = false; });
        fieldLabel.append(input); card.append(fieldLabel);
      }
      const explanation = document.createElement('p'); explanation.className = 'small text-muted mt-2'; explanation.textContent = `Giải thích gợi ý: ${row.explanation || 'Chưa có; cần tự kiểm chứng.'}`; card.append(explanation);
      host.append(card);
    });
    sync();
  }
  find('generate').addEventListener('click', async () => {
    if (generating || saving) return;
    let plan;
    try {
      if (!find('topic').value.trim()) throw new Error('Nhập chủ đề cần tạo.');
      const groups = find('batch').checked
        ? [...panel.querySelectorAll('[data-ai-stage-row]')].filter(row => row.querySelector('[data-ai-stage]').checked).map(row => ({
            stage: row.querySelector('[data-ai-stage]').value, count: Number(row.querySelector('[data-ai-count]').value),
            points: Number(row.querySelector('[data-ai-points]').value), difficulty: row.querySelector('[data-ai-difficulty]').value
          }))
        : [{ stage: find('stage').value, count: Number(find('count').value), points: Number(find('points').value), difficulty: find('difficulty').value }];
      const grades = find('batch').checked ? [...panel.querySelectorAll('[data-ai-grade]:checked')].map(el => el.value) : [find('grade').value];
      if (!grades.length || !groups.length) throw new Error('Chọn ít nhất một khối và một phần thi.');
      for (const group of groups) {
        if (!Number.isInteger(group.count) || group.count < 1 || group.count > 25 || !Number.isInteger(group.points) || group.points < 0 || group.points > 1000)
          throw new Error('Mỗi phần cần 1–25 câu và điểm từ 0–1000.');
      }
      plan = grades.flatMap(grade => groups.map(group => ({ ...group, grade })));
      if (plan.reduce((sum, group) => sum + group.count, 0) > 175) throw new Error('Tối đa 175 câu/lượt; hãy giảm số câu hoặc số khối.');
    } catch (error) { status(error.message); return; }
    if (drafts.length && !window.confirm('Thay bản nháp hiện tại bằng lượt tạo mới? Các câu chưa lưu sẽ bị bỏ.')) return;
    const total = plan.reduce((sum, group) => sum + group.count, 0);
    const fields = { topic: find('topic').value, reference: find('reference').value };
    drafts = []; find('reviewed').checked = false; generating = true; stopped = false; render();
    try {
      for (const group of plan) {
        let completed = 0;
        while (completed < group.count && !stopped) {
          controller = new AbortController(); status(`Đang tạo ${drafts.length}/${total} câu · Khối ${group.grade} · ${group.stage}…`);
          const count = Math.min(2, group.count - completed);
          const data = await request('generate_olympia_ai', { ...fields, grade: group.grade, stage: group.stage,
            difficulty: group.difficulty, points: String(group.points), count: String(count),
            prior: drafts.length ? JSON.stringify({ questions: drafts }) : '' }, controller.signal);
          if (stopped) break;
          drafts.push(...data.questions); completed += data.questions.length; render(); status(`Đã tạo ${drafts.length}/${total} câu.`);
          if (drafts.length < total) await new Promise(resolve => setTimeout(resolve, 2100));
        }
        if (stopped) break;
      }
      status(stopped ? `Đã dừng. Giữ lại ${drafts.length} câu đã tạo.` : `Đã tạo ${drafts.length} câu. Hãy kiểm tra, sửa và chọn trước khi lưu.`);
    } catch (error) { status(error.name === 'AbortError' ? `Đã dừng. Giữ lại ${drafts.length} câu đã tạo.` : `${error.message} Giữ lại ${drafts.length} câu đã tạo.`); }
    finally { generating = false; sync(); }
  });
  find('batch').addEventListener('change', () => { find('batch-options').hidden = !find('batch').checked; });
  find('stop').addEventListener('click', () => { stopped = true; if (controller) controller.abort(); });
  find('save').addEventListener('click', async () => {
    if (generating || saving) return;
    const rows = selected();
    if (!rows.length || !find('reviewed').checked) { status('Chọn ít nhất một câu và xác nhận đã kiểm tra nội dung, đáp án.'); return; }
    saving = true; sync(); status('Đang lưu các câu đã chọn…');
    try {
      const data = await request('import_olympia_ai', { questions: JSON.stringify({ questions: rows }), import_token: panel.dataset.token, reviewed: '1' });
      status(`Đã thêm ${data.saved} câu. Đang tải lại danh sách…`); drafts = []; location.reload();
    } catch (error) { status(error.message); saving = false; sync(); }
  });
  window.addEventListener('beforeunload', event => { if (drafts.length) { event.preventDefault(); event.returnValue = ''; } });
  sync();
})();
