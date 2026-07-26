<?php
/**
 * Từ điển dữ liệu CSDL dùng chung (nguồn chuẩn hệ sinh thái).
 * Mọi module (PCCM, Nội trú, Thi đua…) nhập/xuất theo đúng key + nhãn cột này.
 */

/** @return array<string, array{label:string, type?:string, group?:string, pccm_safe?:bool}> */
function csdl_schema_teachers() {
    return [
        'code'            => ['label' => 'Mã GV', 'group' => 'Định danh'],
        'name'            => ['label' => 'Họ và tên', 'group' => 'Định danh'],
        'dob'             => ['label' => 'Ngày sinh', 'type' => 'date', 'group' => 'Hành chính'],
        'gender'          => ['label' => 'Giới tính', 'group' => 'Hành chính'],
        'ethnicity'       => ['label' => 'Dân tộc', 'group' => 'Hành chính'],
        'phone'           => ['label' => 'SĐT', 'group' => 'Liên hệ'],
        'email'           => ['label' => 'Email', 'group' => 'Liên hệ'],
        'hometown'        => ['label' => 'Quê quán', 'group' => 'Hành chính'],
        'address'         => ['label' => 'Địa chỉ', 'group' => 'Hành chính'],
        'teaching_level'  => ['label' => 'Cấp học', 'group' => 'Chuyên môn'],
        'specialty'       => ['label' => 'Môn dạy / Chuyên môn', 'group' => 'Chuyên môn', 'pccm_safe' => true],
        'to_chuyen_mon'   => ['label' => 'Tổ chuyên môn', 'group' => 'Chuyên môn', 'pccm_safe' => true],
        'chuc_vu'         => ['label' => 'Chức vụ (hành chính)', 'group' => 'Chuyên môn'],
        'kiem_nhiem_text' => ['label' => 'Kiêm nhiệm', 'group' => 'Chuyên môn', 'pccm_safe' => true],
        'join_date'       => ['label' => 'Ngày vào ngành', 'type' => 'date', 'group' => 'Lương / ngạch'],
        'bac'             => ['label' => 'Bậc', 'group' => 'Lương / ngạch'],
        'hang'            => ['label' => 'Hạng', 'group' => 'Lương / ngạch'],
        'cap_luong'       => ['label' => 'Cấp', 'group' => 'Lương / ngạch'],
        'he_so'           => ['label' => 'Hệ số', 'group' => 'Lương / ngạch'],
        'he_so_from'      => ['label' => 'Hưởng từ ngày', 'type' => 'date', 'group' => 'Lương / ngạch'],
        'is_probation'    => ['label' => 'Tập sự', 'type' => 'bool', 'group' => 'Cờ', 'pccm_safe' => true],
        'is_principal'    => ['label' => 'Hiệu trưởng', 'type' => 'bool', 'group' => 'Cờ', 'pccm_safe' => true],
        'is_vice'         => ['label' => 'Phó HT', 'type' => 'bool', 'group' => 'Cờ', 'pccm_safe' => true],
        'active'          => ['label' => 'Đang công tác', 'type' => 'bool', 'group' => 'Trạng thái'],
        'note'            => ['label' => 'Ghi chú', 'group' => 'Khác'],
    ];
}

function csdl_schema_classes() {
    return [
        'name'                 => ['label' => 'Tên lớp', 'group' => 'Cơ bản'],
        'grade'                => ['label' => 'Khối', 'group' => 'Cơ bản'],
        'level'                => ['label' => 'Cấp (THCS/THPT)', 'group' => 'Cơ bản'],
        'homeroom_teacher_name'=> ['label' => 'GVCN (họ tên)', 'group' => 'Cơ bản'],
        'room'                 => ['label' => 'Phòng học', 'group' => 'Cơ bản'],
        'capacity'             => ['label' => 'Sĩ số định mức', 'group' => 'Khác'],
        'active'               => ['label' => 'Đang hoạt động', 'type' => 'bool', 'group' => 'Trạng thái'],
        'note'                 => ['label' => 'Ghi chú', 'group' => 'Khác'],
    ];
}

function csdl_schema_students() {
    return [
        'code'          => ['label' => 'Mã HS', 'group' => 'Định danh'],
        'name'          => ['label' => 'Họ và tên', 'group' => 'Định danh'],
        'class_name'    => ['label' => 'Lớp', 'group' => 'Định danh'],
        'dob'           => ['label' => 'Ngày sinh', 'type' => 'date', 'group' => 'Hành chính'],
        'gender'        => ['label' => 'Giới tính', 'group' => 'Hành chính'],
        'ethnicity'     => ['label' => 'Dân tộc', 'group' => 'Hành chính'],
        'hometown'      => ['label' => 'Quê quán', 'group' => 'Hành chính'],
        'address'       => ['label' => 'Địa chỉ', 'group' => 'Hành chính'],
        'phone'         => ['label' => 'SĐT HS', 'group' => 'Liên hệ'],
        'parent_name'   => ['label' => 'Họ tên PH', 'group' => 'Phụ huynh'],
        'parent_phone'  => ['label' => 'SĐT PH', 'group' => 'Phụ huynh'],
        'boarder'       => ['label' => 'Nội trú', 'type' => 'bool', 'group' => 'Nội trú'],
        'room_ktx'      => ['label' => 'Phòng KTX', 'group' => 'Nội trú'],
        'meal_group'    => ['label' => 'Nhóm ăn', 'group' => 'Nội trú'],
        'active'        => ['label' => 'Đang học', 'type' => 'bool', 'group' => 'Trạng thái'],
        'note'          => ['label' => 'Ghi chú', 'group' => 'Khác'],
    ];
}

function csdl_schema_entity($entity) {
    if ($entity === 'teachers') return csdl_schema_teachers();
    if ($entity === 'classes') return csdl_schema_classes();
    if ($entity === 'students') return csdl_schema_students();
    return [];
}

/** Nhãn cột mặc định khi xuất / mẫu nhập (thứ tự ổn định) */
function csdl_schema_labels($entity) {
    $out = [];
    foreach (csdl_schema_entity($entity) as $key => $meta) {
        $out[$key] = $meta['label'];
    }
    return $out;
}
