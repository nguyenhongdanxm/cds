/* Layered, accessory-free animal UI illustrations. Faces are drawn from current points. */
(()=>{'use strict';
const species=[
 ['Cáo','fox','#ed9958'],['Thỏ','rabbit','#efc3d9'],['Ếch','frog','#8ed692'],['Mèo','cat','#c9a3ee'],['Gấu trúc','panda','#e3e4f3'],['Vịt','duck','#ffda70'],['Voi','elephant','#91c9ed'],['Sóc','squirrel','#e3ac7a'],['Sư tử','lion','#efc86e'],['Rùa','turtle','#83c9b0'],['Heo','pig','#f1aabd'],['Cún','dog','#c5a082'],['Koala','koala','#b6c6db'],['Gấu','bear','#cc9b7c'],['Cừu','sheep','#e8dce8'],['Cánh cụt','penguin','#8aa7c9'],
 ['Hà mã','hippo','#baa7e8'],['Hổ','tiger','#f2b36c'],['Hải cẩu','seal','#b4dce8'],['Hươu','deer','#d9ac79'],['Hamster','hamster','#edc18d'],['Gấu mèo','raccoon','#b4bdca'],['Cú','owl','#bd9fce'],['Nhím','hedgehog','#c79c8c'],['Cá sấu','crocodile','#99d098'],['Dê','goat','#dfc2ad'],['Cá voi','whale','#89c4ec'],['Lạc đà','camel','#e2c096'],['Tắc kè','chameleon','#b1d780'],['Gà','chicken','#ffe3a2'],['Hồng hạc','flamingo','#f3a6c8'],['Chinchilla','chinchilla','#c4bedf'],
 ['Gấu trúc đỏ','redpanda','#de9475'],['Rái cá','otter','#b9947c'],['Thạch sùng','gecko','#a4d8ce'],['Gấu Bắc cực','polarbear','#e4eaf2'],['Tê giác','rhino','#b7b4d3'],['Ngựa vằn','zebra','#dce2eb'],['Ngựa','horse','#c19b82'],['Dơi','bat','#b6a0d8'],['Ong','bee','#efce6f'],['Bướm','butterfly','#c19be3'],['Bạch tuộc','octopus','#d0a3e4'],['Thú mỏ vịt','platypus','#a3c9c3'],['Sao biển','starfish','#f0b6a0'],['Cá mập','shark','#91bed4'],['Ốc sên','snail','#bed3a1'],['Kangaroo','kangaroo','#d6ae89']
];
const legacy={mint:['Mầm xanh','plant','#95d5af'],bunny:species[1],dragon:['Rồng','dragon','#a9d29a'],robot:['Robot','robot','#9ecadf'],cat:species[3],frog:species[2],chick:species[29],octopus:species[42]};
const escape=s=>String(s).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));let serial=0;
function descriptor(id){const m=/^animal-([1-3])-(\d{1,2})$/.exec(id);return m&&+m[2]<16?species[(+m[1]-1)*16+ +m[2]]:Object.hasOwn(legacy,id)?legacy[id]:null;}
function mood(total,step=10,negative=5){total=Number(total)||0;return total>0?Math.min(4,1+Math.floor(total/Math.max(1,step))):total<0?-Math.min(3,1+Math.floor(-total/Math.max(1,negative))):0;}
function render(id,state=0,className=''){const d=descriptor(id);if(!d)return '';const [name,type,color]=d,uid='abAnimal'+(++serial),round=(x,y,rx,ry,fill,extra='')=>`<ellipse cx="${x}" cy="${y}" rx="${rx}" ry="${ry}" fill="${fill}" ${extra}/>`,path=(d,fill,extra='')=>`<path d="${d}" fill="${fill}" ${extra}/>`;let back='',ears='',marks='',snout='',body='',head='';const fur=`url(#${uid})`,pink='#f3bdce',dark='#564561',cream='#fff2dd';
// Silhouettes and natural features belong to the animal, never to an earned outfit.
if(['rabbit','kangaroo'].includes(type))ears=round(57,38,13,32,fur,'transform="rotate(-12 57 38)"')+round(103,38,13,32,fur,'transform="rotate(12 103 38)"')+round(57,37,6,22,pink)+round(103,37,6,22,pink);
else if(['fox','cat','tiger','redpanda'].includes(type))ears=path('M39 66 Q24 13 62 48L100 48Q136 13 121 66Z',fur,'stroke="#fff8" stroke-width="2"')+path('M40 50L39 33L56 49M105 49L122 33L120 52',pink);
else if(['dog','goat','sheep'].includes(type))ears=round(35,77,13,26,fur,'transform="rotate(24 35 77)"')+round(125,77,13,26,fur,'transform="rotate(-24 125 77)"');
else if(['horse','deer','camel','zebra'].includes(type))ears=round(49,43,10,23,fur,'transform="rotate(-30 49 43)"')+round(111,43,10,23,fur,'transform="rotate(30 111 43)"');
else if(['bear','panda','polarbear','koala','hippo','hamster','chinchilla','raccoon','otter','pig'].includes(type)){const r=type==='koala'?23:type==='chinchilla'?19:15;ears=round(43,51,r,r,fur)+round(117,51,r,r,fur)+round(43,51,r*.55,r*.55,pink)+round(117,51,r*.55,r*.55,pink);}
if(['frog','crocodile','gecko','chameleon','snail'].includes(type))ears=round(52,49,18,18,fur)+round(108,49,18,18,fur);
if(type==='elephant')ears=round(31,84,24,35,fur)+round(129,84,24,35,fur)+round(31,84,15,24,pink)+round(129,84,15,24,pink);
if(type==='lion')back=round(80,79,60,56,'#cf9570','stroke="#edba72" stroke-width="7"');
if(type==='hedgehog')back=path('M25 127L16 105L25 94L15 79L31 70L26 51L45 49L51 29L69 37L82 22L96 38L114 28L119 49L138 53L132 75L146 91L131 105L136 125Z','#a47c76');
if(['squirrel','redpanda','raccoon','fox'].includes(type))back=path('M109 153Q169 147 139 95Q114 70 121 123Q127 141 109 139Z',fur,'stroke="#fff5" stroke-width="3"');
if(['bat','butterfly','bee','dragon'].includes(type)){const wing=type==='bat'?'#9984b9':type==='butterfly'?'#f3b9dd': '#c2e9f3';back+=round(35,105,29,44,wing,'transform="rotate(-25 35 105)"')+round(125,105,29,44,wing,'transform="rotate(25 125 105)"');}
if(type==='turtle')back=round(80,130,53,42,'#669c86','stroke="#c0e0ac" stroke-width="7"');
if(['whale','shark','seal'].includes(type))back=path('M111 133Q136 148 151 129L149 155L125 160L109 149Z',fur)+path('M40 126L12 148Q39 155 53 139Z',fur);
if(['deer','goat'].includes(type))back+=path('M54 49Q43 30 48 17M48 30L37 24M106 49Q117 30 112 17M112 30L123 24','none','stroke="#b89a81" stroke-width="7" stroke-linecap="round"');
if(type==='rhino')ears+=path('M72 61L82 22L94 62Z','#e4ddd2');
if(['bee','butterfly','snail'].includes(type))ears+=path('M60 48Q46 26 41 29M100 48Q114 26 119 29','none',`stroke="${dark}" stroke-width="4" stroke-linecap="round"`)+round(41,29,5,5,pink)+round(119,29,5,5,pink);
if(type==='plant')ears=path('M80 63Q33 51 42 19Q83 17 80 63Q80 21 119 19Q129 50 80 63Z',fur);
if(type==='robot')ears=path('M80 48V28','none','stroke="#7994b4" stroke-width="5"')+round(80,24,7,7,pink);
body=round(80,135,35,33,fur)+round(80,138,23,25,cream)+round(52,158,17,10,fur)+round(108,158,17,10,fur)+round(43,126,11,20,fur,'transform="rotate(25 43 126)"')+round(117,126,11,20,fur,'transform="rotate(-25 117 126)"');
if(['flamingo','chicken','duck','penguin','owl'].includes(type))body=round(80,134,34,34,fur)+round(80,140,24,25,cream)+path('M63 162L49 173L72 173M97 162L111 173L88 173','#edb76c');
if(['whale','shark','seal','snail'].includes(type))body=round(80,140,49,25,fur)+round(76,148,33,12,cream);
if(type==='snail')body+=round(106,135,29,27,'#e3b1c9','stroke="#fbe5ef" stroke-width="3"')+path('M108 151Q86 145 94 129Q104 115 119 129Q125 142 111 143Q102 141 109 133','none','stroke="#b889aa" stroke-width="4"');
if(type==='octopus')body=Array.from({length:6},(_,i)=>round(30+i*20,147,12,22,fur,`transform="rotate(${(i-2.5)*13} ${30+i*20} 147)"`)).join('');
head=round(80,83,46,43,fur,'stroke="#fff5" stroke-width="2"');
if(['crocodile','horse','camel','zebra','platypus'].includes(type))head=round(80,85,40,47,fur);
if(type==='robot')head='<rect x="32" y="44" width="96" height="81" rx="26" fill="'+fur+'" stroke="#e7f8ff" stroke-width="4"/>';
if(type==='starfish'){body='';head=path('M80 24Q88 20 95 59L130 55Q151 54 120 89L137 128Q146 149 103 126L80 158Q72 169 61 127L23 135Q6 137 38 98L17 67Q8 50 55 60Z',fur,'stroke="#fff7" stroke-width="3"');}
if(['panda','raccoon','redpanda','penguin','owl'].includes(type)){const mask=type==='panda'?'#8c849a':type==='raccoon'?'#7e8095':cream;marks+=round(58,84,20,23,mask,'transform="rotate(-15 58 84)"')+round(102,84,20,23,mask,'transform="rotate(15 102 84)"');}
if(['fox','cat','dog','bear','polarbear','lion','squirrel','otter','hamster','chinchilla','koala','deer','kangaroo'].includes(type))snout=round(80,101,27,18,cream)+round(80,97,type==='koala'?9:5,type==='koala'?11:4,dark);
if(type==='pig'||type==='hippo')snout=round(80,101,25,16,type==='pig'?'#e397b2':'#b59ace')+round(72,99,3,4,dark)+round(88,99,3,4,dark);
if(['duck','chicken','flamingo','platypus'].includes(type))snout=round(80,101,type==='platypus'?29:20,11,'#efb66d');
if(type==='elephant')snout=path('M70 96Q67 140 89 137Q105 136 101 122Q90 131 89 99Z',fur,'stroke="#7fa9d0" stroke-width="2"');
if(type==='tiger'||type==='zebra')marks+=path('M43 67L58 73L44 79M117 67L102 73L116 79M75 43L80 62L85 43M38 94L52 98M122 94L108 98','none',`stroke="${dark}" stroke-width="5" stroke-linecap="round"`);
if(['dragon','crocodile','gecko','chameleon'].includes(type))marks+=round(44,104,5,4,'#6fb594')+round(116,104,5,4,'#6fb594');
if(type==='chicken')ears+=path('M65 49Q54 28 67 28Q78 26 80 41Q88 20 97 29Q108 41 95 49Z','#e9a1a8');
const eyes=state>=3?path('M49 83Q57 70 65 83M95 83Q103 70 111 83','none',`stroke="${dark}" stroke-width="5" stroke-linecap="round"`):round(57,82,6,state<0?6:8,dark)+round(103,82,6,state<0?6:8,dark)+round(55,79,2,2,'white')+round(101,79,2,2,'white');
const eyebrows=state<0?path(`M48 ${69+Math.min(3,-state)}L65 67M95 67L112 ${69+Math.min(3,-state)}`,'none',`stroke="${dark}" stroke-width="3" stroke-linecap="round"`):'';
const mouth=state>=3?path('M66 106Q80 112 94 106Q92 126 80 125Q68 124 66 106Z','#8f5268')+round(80,121,8,3,'#ed9eaf'):path(state>0?`M68 107Q80 ${113+state*2} 92 107`:state<0?`M68 112Q80 ${105+state*2} 92 112`:'M71 109Q80 109 89 109','none',`stroke="${dark}" stroke-width="3" stroke-linecap="round"`);
const blush=state>0?round(43,99,8,5,pink,'opacity=".7"')+round(117,99,8,5,pink,'opacity=".7"'):'';
return `<svg class="${escape(className)} ab-layered-animal" viewBox="0 0 160 180" role="img" aria-label="${escape(name)} · ${state>0?'vui vẻ':state<0?'cần động viên':'bình thường'}" data-species="${type}" data-expression="${state}" xmlns="http://www.w3.org/2000/svg"><defs><radialGradient id="${uid}" cx="30%" cy="20%" r="85%"><stop stop-color="#fff" stop-opacity=".85"/><stop offset=".28" stop-color="${color}"/><stop offset="1" stop-color="${color}" stop-opacity=".9"/></radialGradient></defs><g class="ab-animal-body">${back}${body}${ears}${head}${marks}${snout}<g class="ab-animal-face">${eyes}${eyebrows}${mouth}${blush}</g></g></svg>`;}
window.AchievementAnimals={render,mood,descriptor};
})();
