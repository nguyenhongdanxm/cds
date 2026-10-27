<form method="post" id="vbQuickSeenForm" style="padding:1rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap">
<input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="action" value="mark_documents_seen"><input type="hidden" name="return_tab" value="documents"><div id="vbQuickSeenIds"></div>
<label><input type="checkbox" class="vb-quick-seen-all"> Chọn tất cả văn bản đang hiển thị</label>
<button type="submit" class="btn btn-primary" id="vbQuickSeenButton" disabled>Xử lý nhanh – Đã xem (0)</button>
<span class="sub" id="vbQuickSeenStatus" role="status" aria-live="polite">Ghi nhận đã xem cho tài khoản của bạn; không thay đổi dữ liệu văn bản.</span>
</form><script src="<?=e(BASE_URL)?>assets/vanban-quick-seen.js?v=20261027-1" defer></script>
