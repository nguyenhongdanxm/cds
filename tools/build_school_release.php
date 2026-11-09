<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__).'/includes/release_manifest.php';
try{
    $options=getopt('',['output:']);$output=(string)($options['output']??'');$root=dirname(__DIR__);
    if($output===''||$output[0]!=='/'||file_exists($output))throw new RuntimeException('Provide a new absolute --output=/path/cds-release.zip');
    if(!class_exists('ZipArchive'))throw new RuntimeException('PHP zip extension is required.');
    // Refuse tracked edits; runtime/untracked files are never included.
    $process=proc_open(['git','-C',$root,'diff','--quiet','HEAD'],[], $pipes);
    if(!is_resource($process)||proc_close($process)!==0)throw new RuntimeException('Commit tracked changes before packaging.');
    $paths=cds_release_manifest($root);$manifest=['version'=>1,'files'=>[]];$zip=new ZipArchive();
    if($zip->open($output,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RuntimeException('Cannot create ZIP.');
    foreach($paths as $path){$manifest['files'][$path]=hash_file('sha256',$root.'/'.$path);if(!$zip->addFile($root.'/'.$path,$path))throw new RuntimeException('Cannot package source.');}
    $zip->addFromString('release-manifest.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
    if(!$zip->close())throw new RuntimeException('Cannot finish ZIP.');
    echo 'CDS_RELEASE_OK '.count($paths).' source files; SHA256 '.hash_file('sha256',$output)."\n";
}catch(Throwable $e){fwrite(STDERR,'RELEASE_FAILED: '.$e->getMessage()."\n");exit(1);}
