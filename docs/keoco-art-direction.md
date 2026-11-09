# Kéo co: hình họa và âm thanh – 09/11/2026

Tạo bằng công cụ sinh ảnh tích hợp, sau đó đóng gói WebP cho trò chơi. Đây là các nhân vật nguyên bản; không dùng hình có watermark trong ảnh tham khảo. Chỉ thu nhỏ ảnh nguồn, giữ kênh alpha; không vẽ lại hoặc tẩy nền bằng xử lý pixel.

## Tài nguyên cuối

| Tệp | Kích thước | Cách dùng |
|---|---|---|
| `assets/keoco/pullers-blue-v1.webp` | 1536 × 512, RGBA | Ba học sinh đội xanh, ba ô vuông bằng nhau |
| `assets/keoco/pullers-red-v1.webp` | 1536 × 512, RGBA | Ba học sinh đội đỏ, lật cả đội bằng CSS |
| `assets/keoco/schoolyard-v1.webp` | 1920 × 640, RGB | Sân trường, cổng giữa và hàng phượng |

Mỗi đội có một nam tóc ngắn, một nữ buộc tóc và một nam đeo kính. Nhân vật hướng phải, người ngả trái, hai chân dang rộng, một gối gập và cả hai chân đặt đất. Hai tay nắm một dây vô hình; dây được vẽ riêng để luôn dịch cùng tỷ số. Sprite đặt điểm nắm tại x khoảng 75–77%, y 49%; hiệu ứng xoay quanh điểm này. Gương đội đỏ tạo lực kéo ngược chiều. Chân và đầu được giữ trong khung, không ghép một bức tranh tĩnh có sẵn dây vào sân.

Biển trên ảnh sân để trống; chữ thật **TRƯỜNG PTDTNT THCS&THPT XÍN MẦN** được đặt theo tọa độ biển trong ảnh, tính lại khi khung thay đổi. Không dùng chữ do mô hình ảnh sinh để tránh sai tên/dấu.

## Prompt set cuối

Hai sprite sheet được tạo bằng cùng prompt dưới đây, thay TEAMCOLOR lần lượt bằng **royal BLUE** và **bright RED**:

> Use case: illustration-story. Asset type: production game character sprite sheet with TRUE transparent background. Create ORIGINAL premium cute Vietnamese schoolchildren for an educational tug-of-war game, polished hand-drawn 2D cartoon, large expressive heads, warm faces, crisp dark brown outlines, beautiful cel shading, detailed sport shoes. Exactly THREE individual full-body children in ONE horizontal row of THREE equally wide separate cells. Portrait subject within each cell, lots of transparent gap between cells, all three same ground baseline and scale. No background, no lettering, no logos, no watermark, no numbers, no cast shadows. Each child faces RIGHT and leans their torso BACK toward LEFT at 25 degrees, knees deeply bent, feet far apart planted firmly, left foot back and right foot forward, a physically convincing tug-of-war stance. Both arms reach forward to the RIGHT, elbows a little bent, both closed hands together gripping an INVISIBLE horizontal rope at exactly the SAME HEIGHT across all 3 cells, approximately 54% of canvas height. NO ROPE drawn: the game will draw it through hands. Hands should be unobstructed near the right of each cell at 76% of cell width. Body and head to left of hands. Determined, excited effort, eyebrows focused, cheerful exertion, no crying. Never crossing legs, never walking pose, never floating. Clean separated silhouettes; complete shoes/hair/hands inside canvas, no clipping. Wide landscape canvas. Student 1: boy short black hair. Student 2: girl black ponytail red hair tie. Student 3: boy black hair and round glasses. All wear matching TEAMCOLOR sports jerseys with white side panels, navy shorts, white socks and sport sneakers with TEAMCOLOR accents. Transparent background must be truly transparent alpha.

Prompt sân trường:

> Use case: illustration-story. Asset type: panoramic 2D background for a Vietnamese educational tug-of-war game. Beautiful polished colorful cartoon, crisp outlines, detailed but uncluttered, consistent with cute premium chibi children. VERY WIDE PANORAMA 3:1 landscape. A sunny Vietnamese boarding school in Xín Mần, pale golden-yellow two-storey classroom buildings, blue windows, red tiled roofs, distant soft green mountains, brilliant blue sky with small white clouds. CENTER school entrance: two symmetrical gold gate pillars at x35% and x65%, red metal gate between them. A compact blank navy-blue horizontal school sign directly over the gate at center x50%, y32% of image, extending x34% to66%, framed with thin gold trim. Leave this sign COMPLETELY BLANK for real HTML school name. Lush flamboyant trees with vivid red-orange flowers at BOTH outer edges, the main left canopy extending across upper left. A few flowering shrubs along school wall. Lower 42% of image is an OPEN LEVEL school sports courtyard with soft sandy ochre ground and a narrow grass edge, subtle light markings. Clear roomy foreground, no people, no animals, no balls, no rope, no UI, no writing anywhere, no logo or watermark. Camera straight-on at child eye level, horizontal ground, bright celebratory school sports day atmosphere. All important gate architecture in central 60% to remain visible when cropped on mobile.

## Chuyển động và nhạc

Hoa và chim là các hình SVG nhỏ trang trí, chuyển động bằng CSS transform; không tải ảnh/clip bên ngoài. Có 22 hoa và ba chim, tắt khi reduced motion. Không chạy vòng lặp JavaScript cho từng khung hình. Hàng đợi sự kiện đúng/sai có số thứ tự để tránh phát lặp khi poll.

Nhạc và cue âm thanh là tác phẩm tổng hợp bằng Web Audio, không dùng bản ghi nhạc thương mại. Bộ kiểm tra xác nhận nốt bass/trống/giai điệu, bật/tắt và vòng đời bộ lập lịch bằng AudioContext giả lập; chưa thay thế kiểm tra nghe trên thiết bị thật. Ảnh nguồn đã được xem trực tiếp. Bố cục trò chơi vẫn cần xác nhận trực quan trong trình duyệt; phiên trình duyệt hiện bị giới hạn lượt sử dụng.
