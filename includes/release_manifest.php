<?php
/** Source-only deployment, shared by the release packager and hosting updater. */
function cds_release_path_allowed(string $path): bool
{
    $path = str_replace('\\','/',$path);
    if ($path==='' || $path[0]==='/' || preg_match('#(?:^|/)\.\.(?:/|$)#',$path)) return false;
    if (preg_match('#(?:^|/)(?:data|uploads|cache|logs|vendor|node_modules|cds_private|tests|\.github|\.git)(?:/|$)#i',$path)) return false;
    if (preg_match('#(?:^|/)(?:instance_binding\.php|\.env(?:\..*)?|\.user\.ini|error_log|release-manifest\.json)$#i',$path)) return false;
    if (preg_match('/\.(?:conf|sql|bak|log|pem|key|zip)$/i',$path)) return false;
    return true;
}
function cds_release_manifest(string $root): array
{
    if (is_dir($root.'/.git') || is_file($root.'/.git')) {
        $proc=proc_open(['git','-C',$root,'ls-files','-z'],[1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if(!is_resource($proc))throw new RuntimeException('Cannot read tracked source manifest.');
        $raw=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
        if(proc_close($proc)!==0)throw new RuntimeException('Cannot read tracked source manifest.');
        $paths=array_filter(explode("\0",$raw));
    } else {
        $raw=json_decode((string)@file_get_contents($root.'/release-manifest.json'),true);
        if(!is_array($raw)||!isset($raw['files'])||!is_array($raw['files']))throw new RuntimeException('Missing release manifest; deploy from Git or a CDS release package.');
        $paths=array_keys($raw['files']);
        foreach($raw['files'] as $path=>$sha) if(!is_string($sha)||!cds_release_path_allowed((string)$path)||!is_file($root.'/'.$path)||!hash_equals($sha,hash_file('sha256',$root.'/'.$path)))throw new RuntimeException('Release integrity check failed.');
    }
    $paths=array_values(array_filter($paths,static fn($p)=>cds_release_path_allowed($p)));
    foreach($paths as $path) if(is_link($root.'/'.$path)||!is_file($root.'/'.$path))throw new RuntimeException('Source missing or symlink: '.$path);
    sort($paths,SORT_STRING);return $paths;
}
