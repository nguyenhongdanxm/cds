/* PCCM – giữ vị trí cuộn + bộ lọc (sessionStorage) */
(function (w) {
  var KEY = 'pccm_them_state';
  function load() {
    try { return JSON.parse(sessionStorage.getItem(KEY) || '{}'); } catch (e) { return {}; }
  }
  function save(patch) {
    var s = load();
    for (var k in patch) if (Object.prototype.hasOwnProperty.call(patch, k)) s[k] = patch[k];
    sessionStorage.setItem(KEY, JSON.stringify(s));
  }
  w.PCCMState = { load: load, save: save, KEY: KEY };
})(window);
