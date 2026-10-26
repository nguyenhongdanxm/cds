<?php if(empty($isAdmin))return; ?>
<section class="panel settings-card"><div class="panel-head"><h2>Cấu hình nhiều nền tảng AI</h2><a href="trolyai.php">Quay lại</a></div><div class="panel-body">
<form method="post" id="aiProvidersForm"><input type="hidden" name="action" value="save_settings"><input type="hidden" name="csrf" value="<?=e($csrf)?>"><input type="hidden" name="provider_selection_present" value="1">
<div class="field switch"><input style="width:auto;height:auto" type="checkbox" id="enabled" name="enabled" value="1" <?=!empty($settings['enabled'])?'checked':''?>><label style="margin:0" for="enabled">Bật Trợ lý AI</label></div>
<p>Nhập và lưu sẵn API của từng nền tảng. Tích để cho phép sử dụng, bỏ tích để tạm ngừng; khóa và mô hình đã lưu vẫn được giữ.</p>
<div class="field"><label for="aiProvider">Nền tảng mặc định</label><select name="provider" id="aiProvider"><?php foreach($providers as $key=>$meta):?><option value="<?=e($key)?>" <?=($settings['provider']??'codecraft')===$key?'selected':''?>><?=e($meta['label'])?></option><?php endforeach;?></select><div class="help">Khi bật nhiều nền tảng, chọn mặc định ở đây hoặc chọn riêng cho từng yêu cầu trong trang Trợ lý AI. Các tiện ích AI khác dùng mặc định. Mỗi yêu cầu chỉ gửi tới một nền tảng được chọn.</div></div>
<?php foreach($providers as $key=>$meta):$savedProvider=(array)($settings['providers'][$key]??[]);?>
<section data-provider-panel="<?=e($key)?>" style="padding:16px;margin:14px 0;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc">
<h3 style="margin:0 0 12px;font-size:16px"><?=e($meta['label'])?> <span class="help"><?=empty($savedProvider['api_key'])?'Chưa lưu khóa':'Đã lưu khóa'?></span></h3>
<div class="field switch"><input style="width:auto;height:auto" type="checkbox" id="use-<?=e($key)?>" name="use_provider_<?=e($key)?>" value="1" data-provider-toggle="<?=e($key)?>" <?=!empty($savedProvider['enabled'])?'checked':''?>><label style="margin:0" for="use-<?=e($key)?>">Cho phép sử dụng <?=e($meta['label'])?></label></div>
<div class="field"><label for="key-<?=e($key)?>">API key</label><input id="key-<?=e($key)?>" type="password" name="api_key_<?=e($key)?>" autocomplete="new-password" maxlength="8192" placeholder="<?=empty($savedProvider['api_key'])?'Nhập API key':'Đã lưu · để trống để giữ nguyên'?>"></div>
<label class="help"><input type="checkbox" name="clear_key_<?=e($key)?>" value="1"> Xóa khóa đã lưu của nền tảng này</label>
<div class="field" style="margin-top:12px"><label for="model-<?=e($key)?>">Model ID</label><input id="model-<?=e($key)?>" name="model_<?=e($key)?>" value="<?=e($savedProvider['model']??'')?>" maxlength="200" list="models_<?=e($key)?>"><datalist id="models_<?=e($key)?>"><?php foreach($meta['models'] as $model):?><option value="<?=e($model)?>"><?php endforeach;?></datalist><div class="help">Endpoint: <?=e($meta['endpoint'])?>. Nhập Model ID mà tài khoản API của thầy/cô được hỗ trợ.</div></div>
</section><?php endforeach;?>
<div class="field"><label for="max-input">Giới hạn ký tự mỗi yêu cầu</label><input id="max-input" type="number" name="max_input_chars" min="1000" max="50000" value="<?=e($settings['max_input_chars'])?>"></div>
<div class="field"><label for="max-output">Giới hạn token đầu ra</label><input id="max-output" type="number" name="max_tokens" min="500" max="8000" value="<?=e($settings['max_tokens'])?>"></div>
<button class="primary">Lưu cấu hình</button></form>
<p class="notice" style="margin-top:16px">API key được giữ ở máy chủ, ngoài thư mục website; không điền khóa đã lưu vào trang cấu hình.</p>
</div></section>
<script>
(()=>{const select=document.getElementById('aiProvider'),toggles=[...document.querySelectorAll('[data-provider-toggle]')];function sync(){const active=toggles.filter(el=>el.checked).map(el=>el.dataset.providerToggle);[...select.options].forEach(el=>{el.disabled=!active.includes(el.value);});if(!active.includes(select.value))select.value=active[0]||'';}toggles.forEach(el=>el.addEventListener('change',sync));sync();})();
</script>
