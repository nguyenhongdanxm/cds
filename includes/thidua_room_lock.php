<?php
/** Khóa dữ liệu chấm phòng nội trú sau 1 ngày; quản trị có thể mở khóa thủ công. */
function tdr_lock_data_file(): string { return DATA_PATH . '/thidua_rooms.json'; }
function tdr_lock_load(): array {$d=load_json(tdr_lock_data_file(),[]);if(!is_array($d))$d=[];$d['unlocked_dates']=is_array($d['unlocked_dates']??null)?$d['unlocked_dates']:[];return$d;}
function tdr_is_room_admin(): bool {return function_exists('can_perm_level')&&can_perm_level('td.student_room_settings','edit');}
function tdr_date_locked(string $date,?array $data=null):bool{if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))return true;$data=$data??tdr_lock_load();if(!empty($data['unlocked_dates'][$date]))return false;$cutoff=strtotime($date.' +2 days 00:00:00');return$cutoff!==false&&time()>=$cutoff;}
function tdr_set_date_unlock(string $date,bool $unlock,string $by=''):bool{$data=tdr_lock_load();if($unlock)$data['unlocked_dates'][$date]=['by'=>$by,'at'=>date('c')];else unset($data['unlocked_dates'][$date]);return save_json(tdr_lock_data_file(),$data);}
function tdr_assert_date_editable(string $date):void{if(tdr_date_locked($date)){http_response_code(423);exit('Dữ liệu ngày '.date('d/m/Y',strtotime($date)).' đã khóa. Quản trị hệ thống cần mở khóa ngày này trong tab Lịch sử trước khi sửa hoặc xóa.');}}
