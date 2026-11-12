<?php
/** Presentation only. Does not initialize sessions, read permissions, or mutate records. */
if(defined('CDS_PAGE_NAVIGATION_BUFFERED'))return;
define('CDS_PAGE_NAVIGATION_BUFFERED',true);
function cds_page_navigation_filter(string $html):string {
 if(defined('CDS_EXCEL_BACKGROUND_EXPORT')&&CDS_EXCEL_BACKGROUND_EXPORT)return $html;
 if(!preg_match('~^\s*(?:<!--.*?-->\s*)*(?:<!doctype\s+html[^>]*>\s*)?<html(?:\s|>)~is',$html)||stripos($html,'<html')===false||stripos($html,'</head>')===false||stripos($html,'<body')===false||strpos($html,'id="cdsPageNavigation"')!==false)return $html;
 foreach(headers_list()as$header){if(stripos($header,'Content-Disposition:')===0||stripos($header,'Location:')===0)return $html;if(stripos($header,'Content-Type:')===0&&!preg_match('~^Content-Type:\s*(?:text/html|application/xhtml\+xml)(?:\s*;|\s*$)~i',$header))return $html;}
 // BASE_URL in the separate academic app points to /chuyenmon/, not the ecosystem.
 $base=defined('BASE_URL')?(string)BASE_URL:'/';$home=preg_replace('~/(?:chuyenmon|pccm)/?$~','/',$base);
 if(!is_string($home)||$home===''||$home[0]!=='/'||str_starts_with($home,'//'))$home='/';
 $asset=rtrim($home,'/').'/assets/';$esc=static fn($v)=>htmlspecialchars($v,ENT_QUOTES,'UTF-8');
 $head='<link rel="stylesheet" href="'.$esc($asset.'page-navigation.css?v=20261105-2').'"><script defer src="'.$esc($asset.'page-navigation.js?v=20261105-2').'"></script>';
 $bar='<nav id="cdsPageNavigation" aria-label="Điều hướng nhanh" data-open="false"><button type="button" data-cds-nav-toggle aria-label="Mở điều hướng Quay lại và Trang chủ" aria-expanded="false" aria-controls="cdsPageNavigationPanel" title="Điều hướng nhanh"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m14 6-6 6 6 6"/></svg></button><div id="cdsPageNavigationPanel" data-cds-nav-panel><a data-cds-page-back href="'.$esc($home).'" title="Quay lại trang trước"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5m6-6-6 6 6 6"/></svg> Quay lại</a><a data-cds-page-home href="'.$esc($home).'" title="Về trang chủ hệ sinh thái"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 10 9-7 9 7M5 9v12h14V9M9 21v-8h6v8"/></svg> Trang chủ</a></div></nav>';
 if(($_SESSION['cds_user']['role']??'')==='admin')$head.='<script defer src="'.$asset.'excel-schedule-links.js?v=20261112-2" data-cds-base="'.$esc($home).'" data-cds-workspace="'.$esc((string)($_SESSION['ttb_workspace_id']??'legacy')).'"></script>';
 $html=preg_replace('~</head>~i',$head.'</head>',$html,1)??$html;
 return preg_replace_callback('~<body\b[^>]*>~i',static fn($match)=>$match[0].$bar,$html,1)??$html;
}
ob_start('cds_page_navigation_filter');
