/* Vietnamese lunisolar calendar, UTC+7. Astronomical rules: Hồ Ngọc Đức,
 * https://www.xemamlich.uhm.vn/calrules.html. Independent interval-based implementation.
 * Supported civil dates: 1800–2199. Gregorian calendar throughout. */
(function(root){'use strict';
const floor=Math.floor,rad=Math.PI/180,DAY=86400000,sin=a=>Math.sin(a*rad),mod=(n,m)=>(n%m+m)%m;
function serial(s){const [y,m,d]=s.split('-').map(Number),v=new Date(0);v.setUTCFullYear(y,m-1,d);v.setUTCHours(0,0,0,0);if(v.getUTCFullYear()!==y||v.getUTCMonth()+1!==m||v.getUTCDate()!==d)throw Error('Ngày không hợp lệ.');return v.getTime()/DAY;}
function civil(jd){return new Date((jd-2440588)*DAY).toISOString().slice(0,10);}
function moon(k){const t=k/1236.85,t2=t*t,t3=t2*t;
const sun=359.2242+29.10535608*k-.0000333*t2-.00000347*t3;
const lunar=306.0253+385.81691806*k+.0107306*t2+.00001236*t3;
const f=21.2964+390.67050646*k-.0016528*t2-.00000239*t3;
const terms=[[.1734-.000393*t,sun],[.0021,2*sun],[-.4068,lunar],[.0161,2*lunar],[-.0004,3*lunar],[.0104,2*f],[-.0051,sun+lunar],[-.0074,sun-lunar],[.0004,2*f+sun],[-.0004,2*f-sun],[-.0006,2*f+lunar],[.001,2*f-lunar],[.0005,2*lunar+sun]];
const mean=2415020.75933+29.53058868*k+.0001178*t2-.000000155*t3+.00033*sin(166.56+132.87*t-.009173*t2);
const delta=-.000278+.000265*t+.000262*t2;
return floor(mean+terms.reduce((s,[a,b])=>s+a*sin(b),0)-delta+.5+7/24);}
function sector(jd){const t=(jd-.5-7/24-2451545)/36525,t2=t*t;
const anomaly=357.5291+35999.0503*t-.0001559*t2-.00000048*t*t2;
const longitude=280.46645+36000.76983*t+.0003032*t2+(1.9146-.004817*t-.000014*t2)*sin(anomaly)+(.019993-.000101*t)*sin(2*anomaly)+.00029*sin(3*anomaly);
return floor(mod(longitude,360)/30);}
function winter(year){const end=serial(year+'-12-31')+2440588;let k=floor((end-2415021)/29.530588853),d=moon(k);if(sector(d)>=9)d=moon(--k);return {k,jd:d};}
const cache=new Map();function cycle(year){if(cache.has(year))return cache.get(year);const a=winter(year),b=winter(year+1),starts=[];for(let k=a.k;moon(k)<=b.jd;k++)starts.push(moon(k));let leap=-1;if(starts.length===14){for(let i=1;i<starts.length-1;i++){if(sector(starts[i])===sector(starts[i+1])){leap=i;break;}}}
let month=11,lunarYear=year;const rows=[];for(let i=0;i<starts.length-1;i++){const isLeap=i===leap;if(i>0&&!isLeap){month=month%12+1;if(month===1)lunarYear++;}rows.push({start:starts[i],end:starts[i+1],month,year:lunarYear,leap:isLeap});}cache.set(year,rows);return rows;}
function lunar(s){const y=Number(s.slice(0,4));if(y<1800||y>2199)throw Error('Lịch âm hỗ trợ năm 1800–2199.');const jd=serial(s)+2440588;const row=cycle(jd<winter(y).jd?y-1:y).find(r=>r.start<=jd&&jd<r.end);if(!row)throw Error('Không đổi được lịch âm.');return {day:jd-row.start+1,month:row.month,year:row.year,leap:row.leap};}
function lunarDate(year,month,day){if(year<1800||year>2199)throw Error('Lịch âm hỗ trợ năm 1800–2199.');const row=[...cycle(year-1),...cycle(year)].find(r=>r.year===year&&r.month===month&&!r.leap);if(!row||day<1||day>row.end-row.start)throw Error('Ngày âm không hợp lệ.');return civil(row.start+day-1);}
const stems=['Giáp','Ất','Bính','Đinh','Mậu','Kỷ','Canh','Tân','Nhâm','Quý'],branches=['Tý','Sửu','Dần','Mão','Thìn','Tỵ','Ngọ','Mùi','Thân','Dậu','Tuất','Hợi'],animals=['🐭','🐃','🐯','🐱','🐉','🐍','🐴','🐐','🐒','🐓','🐕','🐖'];
function zodiac(year){return {name:stems[mod(year+6,10)]+' '+branches[mod(year+8,12)],icon:animals[mod(year+8,12)]};}
root.CDSCalendar={lunar,lunarDate,zodiac};
})(typeof window==='undefined'?globalThis:window);
