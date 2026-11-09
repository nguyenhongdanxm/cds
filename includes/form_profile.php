<?php
/** Shared presentation defaults only. Saved documents and signatures are never rewritten. */
require_once __DIR__ . '/school_config.php';
function cds_form_value(string $key, string $fallback = ''): string
{
    $value = trim((string)cds_school_config('forms.' . $key, ''));
    return $value !== '' ? $value : $fallback;
}
function cds_form_school_name(): string { return cds_form_value('school_name', school_name()); }
function cds_form_department(): string { return cds_form_value('department', school_department()); }
function cds_form_place(): string { return cds_form_value('place', cds_instance_legacy_install() ? 'Pà Vầy Sủ' : ''); }
function school_place(): string { return cds_form_place(); }
function cds_form_signer_name(): string { return cds_form_value('signer_name'); }
function cds_form_signer_title(): string { return cds_form_value('signer_title', 'HIỆU TRƯỞNG'); }
function cds_form_footer(): string { return cds_form_value('footer'); }
function cds_form_js(string $value): string
{
    return (string)json_encode($value, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
