const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm'),assert=require('node:assert/strict'),cp=require('node:child_process');
const root=path.resolve(__dirname,'..');
const scanner=fs.readFileSync(path.join(root,'hoclieu_game_qr_trial.php'),'utf8');
const initializer=scanner.match(/if\(paperCode\|\|setId\)\{state\.questions=bank\.map[^\n]+/)[0];
for(const setId of ['', 'public-set']) {
 const context={paperCode:'12345678',setId,bank:[{text:'Question',key:'A'}],savedAnswers:{0:{student:'A'}},state:{questions:[],index:-1}};
 vm.runInNewContext(initializer,context);
 assert.equal(context.state.questions.length,1);
 assert.equal(context.state.index,0);
 assert.equal(context.state.questions[0].answers.student,'A');
}
const helper=fs.readFileSync(path.join(root,'includes/quiz_paper_store.php'),'utf8').match(/function qp_can_play_set\(array \$set\): bool \{[\s\S]*?\n\}/)[0];
const php=`<?php function qp_admin():bool{return $GLOBALS['admin'];}function qp_owner():string{return 'viewer';}${helper}
$GLOBALS['admin']=false;
foreach([[['owner_id'=>'other','is_public'=>1],true],[['owner_id'=>'other','is_public'=>0],false],[['owner_id'=>'viewer','is_public'=>0],true]] as [$set,$expected])if(qp_can_play_set($set)!==$expected)exit(1);
$GLOBALS['admin']=true;if(!qp_can_play_set(['owner_id'=>'other','is_public'=>0]))exit(2);echo 'PASS';`;
assert.equal(cp.execFileSync(process.env.PHP_BINARY||'php',['-n'],{input:php,encoding:'utf8'}),'PASS');
console.log('PASS: public/private/owner/admin access and fresh scanner question initialization for public or unavailable source sets.');
