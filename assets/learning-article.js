/* Rich article authoring; images are embedded so saved articles do not expire. */
function initArticleEditor() {
  if (!window.tinymce) {
    document.getElementById('editorError').textContent = 'Không tải được bộ soạn thảo. Vui lòng kiểm tra kết nối rồi tải lại trang.';
    return;
  }
  if (tinymce.get('articleBody')) return;
  tinymce.init({
    selector: '#articleBody', height: 520, menubar: 'edit view insert format table tools help',
    plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen table help wordcount',
    toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | subscript superscript charmap | removeformat searchreplace preview fullscreen code',
    toolbar_mode: 'sliding', paste_data_images: true, automatic_uploads: false,
    images_file_types: 'jpg,jpeg,png,gif,webp', file_picker_types: 'image',
    convert_urls: false, promotion: false,
    content_style: 'body{font:16px/1.6 Arial,sans-serif}img{max-width:100%;height:auto}table{border-collapse:collapse}td,th{border:1px solid #aaa;padding:6px}',
    file_picker_callback: function (callback) {
      var input = document.createElement('input'); input.type = 'file'; input.accept = 'image/jpeg,image/png,image/gif,image/webp';
      input.onchange = function () {
        var file = input.files[0]; if (!file) return;
        if (file.size > 4 * 1024 * 1024) { alert('Vui lòng chọn ảnh không quá 4 MB.'); return; }
        var reader = new FileReader(); reader.onload = function () { callback(reader.result, { alt: file.name }); }; reader.readAsDataURL(file);
      }; input.click();
    }
  });
}
if (typeof sourceKind !== 'undefined' && sourceKind) sourceKind.dispatchEvent(new Event('change'));
// Bootstrap's focus trap otherwise prevents typing into editor dialogs.
document.addEventListener('focusin', function (event) {
  if (event.target.closest('.tox-tinymce-aux')) event.stopImmediatePropagation();
});
var articleModal = document.getElementById('itemModal');
if (articleModal) articleModal.addEventListener('hidden.bs.modal', function () {
  itemForm.reset(); document.getElementById('itemId').value = '';
  if (window.tinymce && tinymce.get('articleBody')) tinymce.get('articleBody').setContent('');
  document.getElementById('saveButton').disabled = false;
  document.getElementById('saveButton').textContent = 'Lưu và gửi duyệt';
  articleModal.querySelector('.modal-title').textContent = 'Thêm nội dung';
  sourceKind.dispatchEvent(new Event('change'));
});
