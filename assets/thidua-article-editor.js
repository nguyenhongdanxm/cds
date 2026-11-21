(function () {
  'use strict';
  var host = document.getElementById('tdArticleEditor'), field = document.getElementById('td_article_content');
  if (!host || !field) return;
  var form = field.closest('form'), manager = host.closest('details'), status = document.getElementById('tdEditorStatus');
  var pending = null, submitting = false, submitted = false, dirty = false, MAX_IMAGE = 3 * 1024 * 1024, MAX_BODY = 6 * 1024 * 1024;
  function message(text, error) { status.textContent = text; status.classList.toggle('is-error', !!error); }
  function current() { return window.tinymce && window.tinymce.get(field.id); }
  function byteSize(text) { return new Blob([text]).size; }
  function validImage(file) { return /^image\/(?:jpeg|png|gif|webp)$/i.test(file.type) && file.size <= MAX_IMAGE; }
  function readImage(file) {
    return new Promise(function (resolve, reject) {
      if (!validImage(file)) { reject(new Error('Chỉ nhận ảnh JPG, PNG, GIF hoặc WebP, tối đa 3 MB mỗi ảnh.')); return; }
      var reader = new FileReader(); reader.onload = function () { resolve(reader.result); }; reader.onerror = function () { reject(new Error('Không đọc được ảnh. Vui lòng chọn lại.')); }; reader.readAsDataURL(file);
    });
  }
  function setContent(value) {
    field.value = value || ''; var editor = current();
    if (editor && editor.initialized) { editor.setContent(field.value); editor.save(); }
    dirty = false;
  }
  // Keep the existing edit/reset buttons and UTF-8 article loading API.
  host.articleSetContent = setContent;
  window.setArticleEditorContent = function (id, value) { if (id === host.id) setContent(value); };
  function start() {
    if (current() || pending) return pending;
    if (!window.tinymce) { message('Chưa tải được bộ công cụ. Nội dung vẫn có thể nhập/sửa bằng mã HTML trong ô bên trên; tải lại trang để dùng đầy đủ công cụ.', true); return null; }
    message('Đang tải bộ công cụ soạn thảo…');
    window.tinymce.addI18n('vi', {
      'File':'Tệp','Edit':'Chỉnh sửa','View':'Hiển thị','Insert':'Chèn','Format':'Định dạng','Tools':'Công cụ','Table':'Bảng','Help':'Trợ giúp',
      'Undo':'Hoàn tác','Redo':'Làm lại','Cut':'Cắt','Copy':'Sao chép','Paste':'Dán','Select all':'Chọn tất cả','Bold':'Đậm','Italic':'Nghiêng','Underline':'Gạch chân','Strikethrough':'Gạch ngang',
      'Paragraph':'Đoạn văn','Headings':'Tiêu đề','Heading 1':'Tiêu đề 1','Heading 2':'Tiêu đề 2','Heading 3':'Tiêu đề 3','Heading 4':'Tiêu đề 4','Heading 5':'Tiêu đề 5','Heading 6':'Tiêu đề 6',
      'Font':'Phông chữ','Font sizes':'Cỡ chữ','Font family':'Phông chữ','Text color':'Màu chữ','Background color':'Màu nền chữ','Align left':'Căn trái','Align center':'Căn giữa','Align right':'Căn phải','Justify':'Căn đều',
      'Bullet list':'Danh sách ký hiệu','Numbered list':'Danh sách đánh số','Increase indent':'Tăng thụt lề','Decrease indent':'Giảm thụt lề','Line height':'Giãn dòng',
      'Insert/edit link':'Chèn/sửa liên kết','Insert/edit image':'Chèn/sửa ảnh','Insert table':'Chèn bảng','Table properties':'Thuộc tính bảng','Cell':'Ô','Row':'Hàng','Column':'Cột','Delete table':'Xóa bảng',
      'Insert row before':'Chèn hàng phía trên','Insert row after':'Chèn hàng phía dưới','Delete row':'Xóa hàng','Insert column before':'Chèn cột bên trái','Insert column after':'Chèn cột bên phải','Delete column':'Xóa cột',
      'Merge cells':'Gộp ô','Split cell':'Tách ô','Cell properties':'Thuộc tính ô','Row properties':'Thuộc tính hàng','Find and replace':'Tìm và thay thế',
      'Source code':'Mã HTML','Preview':'Xem trước','Fullscreen':'Toàn màn hình','Remove formatting':'Xóa định dạng','Special character':'Ký tự đặc biệt','Horizontal line':'Đường kẻ ngang','Subscript':'Chỉ số dưới','Superscript':'Chỉ số trên',
      'Save':'Lưu','Cancel':'Hủy','Close':'Đóng','Ok':'Đồng ý','Insert':'Chèn','Update':'Cập nhật','Image':'Ảnh','Link':'Liên kết','URL':'Địa chỉ','Alternative description':'Mô tả ảnh','Width':'Chiều rộng','Height':'Chiều cao','General':'Chung','Advanced':'Nâng cao','Upload':'Tải lên','Browse for an image':'Chọn ảnh từ máy','Word count':'Đếm từ','Words':'Từ'
    });
    try {
      pending = Promise.resolve(window.tinymce.init({
        target: field, language: 'vi', height: 560, min_height: 360, resize: true,
        menubar: 'edit view insert format table tools help',
        plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen table help wordcount',
        toolbar: [
          'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | removeformat',
          'alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | lineheight | subscript superscript | blockquote hr',
          'link unlink anchor | image table | charmap | searchreplace visualblocks | preview fullscreen code help'
        ],
        toolbar_mode: 'wrap', promotion: false, branding: false,
        font_family_formats: 'Arial=arial,helvetica,sans-serif;Times New Roman=times new roman,times,serif;Calibri=calibri,arial,sans-serif;Tahoma=tahoma,arial,sans-serif;Verdana=verdana,geneva,sans-serif;Georgia=georgia,palatino,serif;Courier New=courier new,courier,monospace',
        font_size_formats: '8pt 9pt 10pt 11pt 12pt 13pt 14pt 16pt 18pt 20pt 24pt 28pt 32pt 36pt 48pt',
        line_height_formats: '1 1.15 1.3 1.5 1.6 1.8 2',
        table_toolbar: 'tableprops tabledelete | tableinsertrowbefore tableinsertrowafter tabledeleterow | tableinsertcolbefore tableinsertcolafter tabledeletecol | tablemergecells tablesplitcells',
        contextmenu: 'link image table', image_caption: true, image_advtab: true,
        paste_data_images: true, automatic_uploads: true, images_upload_credentials: false,
        images_file_types: 'jpg,jpeg,png,gif,webp', file_picker_types: 'image', convert_urls: false,
        images_upload_handler: function (blobInfo) { return readImage(blobInfo.blob()); },
        file_picker_callback: function (callback) {
          var input = document.createElement('input'); input.type = 'file'; input.accept = 'image/jpeg,image/png,image/gif,image/webp';
          input.addEventListener('change', function () { var file = input.files && input.files[0]; if (file) readImage(file).then(function (url) { callback(url, {alt:file.name}); }).catch(function (error) { message(error.message, true); }); }); input.click();
        },
        content_style: 'body{font:16px/1.65 Arial,sans-serif;color:#172033;margin:18px}img{max-width:100%;height:auto}table{border-collapse:collapse;max-width:100%}td,th{border:1px solid #94a3b8;padding:6px}blockquote{border-left:4px solid #cbd5e1;padding-left:16px}p{margin:0 0 .7em}',
        setup: function (editor) {
          editor.on('init', function () { editor.setContent(field.value); editor.save(); message('Đã sẵn sàng · Ảnh được lưu cùng bài viết.'); });
          editor.on('input change undo redo', function () { dirty = true; });
          editor.on('LoadError', function () { message('Một phần bộ công cụ chưa tải được. Hãy lưu bản sao nội dung trước khi tải lại trang.', true); });
          editor.on('Paste', function (event) {
            var files = Array.from(event.clipboardData && event.clipboardData.files || []);
            if (files.some(function (file) { return !validImage(file); })) { event.preventDefault(); message('Ảnh dán phải là JPG, PNG, GIF hoặc WebP và không quá 3 MB mỗi ảnh.', true); }
          });
        }
      })).then(function (editors) { if (!editors || !editors.length) throw new Error('Không khởi tạo được bộ soạn thảo.'); return editors[0]; }).catch(function () { message('Không tải được đầy đủ bộ công cụ. Nội dung gốc vẫn giữ trong ô nhập; vui lòng sao chép nội dung rồi tải lại trang.', true); return null; }).finally(function () { pending = null; });
    } catch (_) { pending = null; message('Không khởi tạo được bộ công cụ; nội dung gốc vẫn được giữ trong ô nhập.', true); }
    return pending;
  }
  field.addEventListener('input', function () { dirty = true; });
  if (manager) manager.addEventListener('toggle', function () { if (manager.open) start(); });
  if (!manager || manager.open) start();
  form.addEventListener('submit', async function (event) {
    event.preventDefault(); if (submitting || submitted) return; submitting = true;
    var buttons = Array.from(form.querySelectorAll('button[type="submit"]')); buttons.forEach(function (button) { button.disabled = true; });
    try {
      if (pending) await pending;
      var editor = current();
      if (editor && editor.initialized) { var uploads = await editor.uploadImages(); if (Array.isArray(uploads) && uploads.some(function (item) { return item.status === false; })) throw new Error('Có ảnh chưa xử lý được. Vui lòng kiểm tra ảnh rồi thử lưu lại.'); editor.save(); }
      if (/\bsrc\s*=\s*["']blob:/i.test(field.value)) throw new Error('Ảnh chưa được xử lý xong. Vui lòng chờ rồi lưu lại.');
      if (byteSize(field.value) > MAX_BODY) throw new Error('Nội dung quá lớn. Vui lòng giảm số lượng hoặc dung lượng ảnh rồi lưu lại.');
      dirty = false; HTMLFormElement.prototype.submit.call(form); submitted = true;
    } catch (error) { message(error.message || 'Chưa lưu được nội dung. Bài viết vẫn còn trong khung soạn thảo.', true); }
    finally { submitting = false; if (!submitted) buttons.forEach(function (button) { button.disabled = false; }); }
  });
  window.addEventListener('beforeunload', function (event) { if (dirty) { event.preventDefault(); event.returnValue = ''; } });
})();
