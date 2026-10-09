(() => {
  const search = document.getElementById('set-search');
  const subjects = document.getElementById('library-subjects');
  if (!search || !subjects) return;
  const cards = [...document.querySelectorAll('.set-item')];
  const grades = [...document.querySelectorAll('[data-library-grade]')];
  let grade = '', subject = '';
  const icons = {'Toán':'📐','Ngữ văn':'📖','Vật lí':'⚡','Hóa học':'🧪','Sinh học':'🌱','Khoa học tự nhiên':'🔬','Lịch sử':'🏛️','Địa lí':'🌍','Lịch sử và Địa lí':'🗺️','Tiếng Anh':'🔤','Tin học':'💻','Công nghệ':'⚙️','Giáo dục công dân':'⚖️','Giáo dục thể chất':'⚽','Âm nhạc':'🎵','Mĩ thuật':'🎨','Kiến thức tổng hợp':'💡'};
  function matchesGrade(card) {
    const value = card.dataset.grade || '';
    return !grade || (grade === 'unclassified' ? !value : value === grade || (grade !== 'all' && value === 'all'));
  }
  function filter() {
    const term = search.value.trim().toLocaleLowerCase('vi');
    let count = 0;
    cards.forEach(card => {
      card.hidden = !matchesGrade(card) || (subject && (card.dataset.subject || 'unclassified') !== subject) || !card.dataset.title.includes(term);
      if (!card.hidden) count++;
    });
    document.getElementById('filter-empty').hidden = count > 0;
    document.getElementById('library-filter-summary').textContent = count + ' bộ câu hỏi' + (/^\d+$/.test(grade) ? ' · gồm bộ dùng chung' : '');
  }
  function renderSubjects() {
    const counts = new Map();
    cards.filter(matchesGrade).forEach(card => {
      const key = card.dataset.subject || 'unclassified';
      counts.set(key, (counts.get(key) || 0) + 1);
    });
    if (subject && !counts.has(subject)) subject = '';
    subjects.replaceChildren();
    const entries = [['', 'Tất cả môn', cards.filter(matchesGrade).length], ...[...counts].sort((a,b)=>a[0].localeCompare(b[0],'vi')).map(([key,count])=>[key,key==='unclassified'?'Chưa chọn môn':key,count])];
    entries.forEach(([key,label,count]) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.setAttribute('aria-pressed', String(key === subject));
      button.textContent = (icons[key] || '📚') + ' ' + label;
      const badge = document.createElement('span');
      badge.className = 'subject-count';badge.textContent = count;
      button.append(badge);
      button.addEventListener('click', () => { subject = key; renderSubjects(); filter(); });
      subjects.append(button);
    });
  }
  grades.forEach(button => button.addEventListener('click', () => {
    grade = button.dataset.libraryGrade;
    subject = '';
    grades.forEach(item => item.setAttribute('aria-pressed', String(item === button)));
    renderSubjects();filter();
  }));
  search.addEventListener('input', filter);
  renderSubjects();filter();
})();
