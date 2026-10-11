// Chạy đồng bộ trong <head>: đọc cài đặt học đã lưu (localStorage zk.settings) và gắn thuộc tính lên <html>
// trước khi trang được vẽ, tránh nháy sáng/tối. Trang quản trị luôn dùng bảng màu sáng.
(function () {
  try {
    var s = JSON.parse(localStorage.getItem('zk.settings') || '{}') || {};
    var el = document.documentElement;
    var admin = location.pathname.indexOf('/admin') === 0;
    var theme = s.theme === 'dark' || (s.theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
    el.setAttribute('data-theme', admin ? 'light' : theme);
    if (s.hanziFont === 'serif' || s.hanziFont === 'kai') el.setAttribute('data-hz-font', s.hanziFont);
    if (s.fontSize === 's' || s.fontSize === 'l') el.setAttribute('data-hz-size', s.fontSize);
    if (s.toneColors === false) el.classList.add('no-tone');
  } catch (e) {
    /* localStorage bị chặn: dùng mặc định */
  }
})();
