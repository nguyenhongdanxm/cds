(function(root){'use strict';
// Dates refer to the observance itself, not the official holiday leave schedule.
const entries=[
['newyear','🎆','Tết Dương lịch',1,1,'Khởi đầu năm mới theo dương lịch, dịp gửi lời chúc và đặt mục tiêu mới.'],
['tet','🧧','Tết Nguyên đán',1,1,'Ngày đầu năm âm lịch; dịp sum họp gia đình, tưởng nhớ tổ tiên và đón xuân.','lunar'],
['hung','🏯','Giỗ Tổ Hùng Vương',3,10,'Ngày tưởng nhớ các Vua Hùng và truyền thống dựng nước của dân tộc.','lunar'],
['midautumn','🏮','Tết Trung thu',8,15,'Đêm rằm tháng Tám âm lịch; gắn với đoàn viên, rước đèn và niềm vui của trẻ em.','lunar'],
['doanngo','🌿','Tết Đoan Ngọ',5,5,'Ngày mùng 5 tháng 5 âm lịch, gắn với phong tục truyền thống và chăm sóc sức khỏe.','lunar'],
['teachersvn','💐','Ngày Nhà giáo Việt Nam',11,20,'Dịp tri ân thầy cô và tôn vinh những đóng góp cho sự nghiệp giáo dục.'],
['christmas','🎄','Giáng sinh · Noel',12,25,'Lễ kỷ niệm Chúa Giêsu ra đời của Kitô giáo; thường gắn với lời chúc an lành và sẻ chia.'],
['liberation','🇻🇳','Ngày Thống nhất đất nước',4,30,'Kỷ niệm ngày 30/4/1975, giải phóng miền Nam và thống nhất đất nước.'],
['labour','🛠️','Quốc tế Lao động',5,1,'Tôn vinh người lao động và những đóng góp của họ cho xã hội.'],
['national','🇻🇳','Quốc khánh Việt Nam',9,2,'Kỷ niệm ngày Chủ tịch Hồ Chí Minh đọc Tuyên ngôn Độc lập năm 1945.'],
['womensvn','🌷','Phụ nữ Việt Nam',10,20,'Tôn vinh phụ nữ Việt Nam và ghi nhận vai trò của phụ nữ trong gia đình, xã hội.'],
['digital','💻','Chuyển đổi số quốc gia',10,10,'Nâng cao nhận thức và thúc đẩy ứng dụng công nghệ số trong đời sống.'],
['army','🎖️','Quân đội nhân dân Việt Nam',12,22,'Kỷ niệm ngày thành lập Quân đội nhân dân Việt Nam, giáo dục truyền thống bảo vệ Tổ quốc.'],
['women','🌺','Quốc tế Phụ nữ',3,8,'Tôn vinh phụ nữ, thúc đẩy bình đẳng giới và quyền của phụ nữ.'],
['education','📚','Quốc tế Giáo dục',1,24,'Nhấn mạnh vai trò của giáo dục đối với hòa bình và phát triển.'],
['water','💧','Nước Thế giới',3,22,'Nhắc nhở bảo vệ nguồn nước và sử dụng nước bền vững.'],
['earth','🌍','Ngày Trái Đất',4,22,'Khuyến khích hành động bảo vệ môi trường và hành tinh.'],
['environment','🌱','Môi trường Thế giới',6,5,'Nâng cao nhận thức và thúc đẩy hành động bảo vệ môi trường.'],
['children','🎈','Quốc tế Thiếu nhi',6,1,'Dịp quan tâm, chăm sóc và mang niềm vui đến trẻ em.'],
['literacy','📖','Quốc tế Xóa mù chữ',9,8,'Đề cao khả năng đọc, viết và cơ hội học tập cho mọi người.'],
['peace','🕊️','Quốc tế Hòa bình',9,21,'Kêu gọi hòa bình, đối thoại và cùng chung sống hòa hợp.'],
['teachersworld','👩‍🏫','Nhà giáo Thế giới',10,5,'Tôn vinh giáo viên trên toàn thế giới và nâng cao vị thế nghề dạy học.'],
['halloween','🎃','Halloween',10,31,'Lễ hội ngày 31/10, thường có hóa trang, trang trí bí ngô và hoạt động vui chơi.'],
['childrights','🤝','Trẻ em Thế giới',11,20,'Thúc đẩy quyền trẻ em và một tương lai tốt đẹp cho mọi trẻ em.']
];
function next(e,now=Date.now()){const p=new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Ho_Chi_Minh',year:'numeric',month:'2-digit',day:'2-digit'}).formatToParts(now),get=k=>p.find(v=>v.type===k).value;const today=get('year')+'-'+get('month')+'-'+get('day'),year=Number(get('year'));for(let y=year;y<=year+2;y++){const date=e[6]==='lunar'?root.CDSCalendar.lunarDate(y,e[3],e[4]):y+'-'+String(e[3]).padStart(2,'0')+'-'+String(e[4]).padStart(2,'0');if(date>=today)return {date,ms:Date.parse(date+'T00:00:00+07:00'),today:date===today};}throw Error('Không tìm thấy ngày sự kiện.');}
root.CDSEvents={entries,next};
})(typeof window==='undefined'?globalThis:window);
