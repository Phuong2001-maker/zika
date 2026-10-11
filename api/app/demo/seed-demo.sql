-- =====================================================================
-- Zìkǎ · seed-demo.sql — dữ liệu MẪU để phát triển/thử (KHÔNG nạp lên web thật)
-- Chạy sau seed-base.sql bằng: php tools/install.php --demo (hoặc tự động khi env = local).
-- Bật HSK 2; 70 bài HSK 1–4 (Nháp) trừ HSK 2 · Bài 6 · Ăn uống (id 21) đang hiện và đủ 6 phần.
-- Sau khi nạp: install.php gọi HanziDataService tải dữ liệu nét cho 12 chữ.
-- =====================================================================
SET NAMES utf8mb4;
SET time_zone = '+07:00';

UPDATE levels SET is_visible = 1 WHERE id = 2;

-- Bài học: HSK 1 (id 1–15), HSK 2 (16–30), HSK 3 (31–50), HSK 4 (51–70). Chỉ id 21 đang hiện.
INSERT INTO lessons (id, level_id, lesson_no, title, sample_text, status, cnt_words, cnt_bt, cnt_kt, cnt_lv, cnt_ht, cnt_np) VALUES
  (1, 1, 1, 'Chào hỏi', '你好 · 再见', 'draft', 0, 0, 0, 0, 0, 0),
  (2, 1, 2, 'Cảm ơn, xin lỗi', '谢谢 · 对不起', 'draft', 0, 0, 0, 0, 0, 0),
  (3, 1, 3, 'Tên và quốc tịch', '名字 · 中国', 'draft', 0, 0, 0, 0, 0, 0),
  (4, 1, 4, 'Gia đình', '爸爸 · 妈妈', 'draft', 0, 0, 0, 0, 0, 0),
  (5, 1, 5, 'Tuổi, số đếm', '岁 · 多少', 'draft', 0, 0, 0, 0, 0, 0),
  (6, 1, 6, 'Ngôn ngữ', '汉语 · 会', 'draft', 0, 0, 0, 0, 0, 0),
  (7, 1, 7, 'Ngày tháng', '今天 · 星期', 'draft', 0, 0, 0, 0, 0, 0),
  (8, 1, 8, 'Đồ uống', '喝 · 茶', 'draft', 0, 0, 0, 0, 0, 0),
  (9, 1, 9, 'Nghề nghiệp', '工作 · 医生', 'draft', 0, 0, 0, 0, 0, 0),
  (10, 1, 10, 'Trường lớp', '学校 · 同学', 'draft', 0, 0, 0, 0, 0, 0),
  (11, 1, 11, 'Giờ giấc', '点 · 分钟', 'draft', 0, 0, 0, 0, 0, 0),
  (12, 1, 12, 'Thời tiết', '天气 · 下雨', 'draft', 0, 0, 0, 0, 0, 0),
  (13, 1, 13, 'Nấu ăn', '菜 · 米饭', 'draft', 0, 0, 0, 0, 0, 0),
  (14, 1, 14, 'Mua sắm', '买 · 衣服', 'draft', 0, 0, 0, 0, 0, 0),
  (15, 1, 15, 'Đi lại', '飞机 · 出租车', 'draft', 0, 0, 0, 0, 0, 0),
  (16, 2, 1, 'Du lịch', '旅游 · 觉得', 'draft', 0, 0, 0, 0, 0, 0),
  (17, 2, 2, 'Sinh hoạt hằng ngày', '起床 · 早上', 'draft', 0, 0, 0, 0, 0, 0),
  (18, 2, 3, 'Màu sắc, vị trí', '颜色 · 右边', 'draft', 0, 0, 0, 0, 0, 0),
  (19, 2, 4, 'Công việc', '介绍 · 公司', 'draft', 0, 0, 0, 0, 0, 0),
  (20, 2, 5, 'Mua quần áo', '便宜 · 件', 'draft', 0, 0, 0, 0, 0, 0),
  (21, 2, 6, 'Ăn uống', '鸡蛋 · 牛奶', 'published', 10, 10, 10, 12, 6, 3),
  (22, 2, 7, 'Khoảng cách', '离 · 远', 'draft', 0, 0, 0, 0, 0, 0),
  (23, 2, 8, 'Hẹn gặp', '告诉 · 等', 'draft', 0, 0, 0, 0, 0, 0),
  (24, 2, 9, 'Thi cử', '考试 · 题', 'draft', 0, 0, 0, 0, 0, 0),
  (25, 2, 10, 'Đồ dùng', '手机 · 桌子', 'draft', 0, 0, 0, 0, 0, 0),
  (26, 2, 11, 'So sánh', '比 · 大', 'draft', 0, 0, 0, 0, 0, 0),
  (27, 2, 12, 'Sức khoẻ', '身体 · 生病', 'draft', 0, 0, 0, 0, 0, 0),
  (28, 2, 13, 'Nhà cửa', '门 · 房间', 'draft', 0, 0, 0, 0, 0, 0),
  (29, 2, 14, 'Phim ảnh', '电影 · 过', 'draft', 0, 0, 0, 0, 0, 0),
  (30, 2, 15, 'Năm mới', '新年 · 快乐', 'draft', 0, 0, 0, 0, 0, 0),
  (31, 3, 1, 'Cuối tuần', '周末', 'draft', 0, 0, 0, 0, 0, 0),
  (32, 3, 2, 'Mạng internet', '上网', 'draft', 0, 0, 0, 0, 0, 0),
  (33, 3, 3, 'Sức khoẻ', '健康', 'draft', 0, 0, 0, 0, 0, 0),
  (34, 3, 4, 'Bốn mùa', '季节', 'draft', 0, 0, 0, 0, 0, 0),
  (35, 3, 5, 'Thói quen', '习惯', 'draft', 0, 0, 0, 0, 0, 0),
  (36, 3, 6, 'Ôn tập', '复习', 'draft', 0, 0, 0, 0, 0, 0),
  (37, 3, 7, 'Công sở', '会议', 'draft', 0, 0, 0, 0, 0, 0),
  (38, 3, 8, 'Nhà hàng', '菜单', 'draft', 0, 0, 0, 0, 0, 0),
  (39, 3, 9, 'Giao thông', '地铁', 'draft', 0, 0, 0, 0, 0, 0),
  (40, 3, 10, 'Thể thao', '比赛', 'draft', 0, 0, 0, 0, 0, 0),
  (41, 3, 11, 'Xuất ngoại', '护照', 'draft', 0, 0, 0, 0, 0, 0),
  (42, 3, 12, 'Âm nhạc', '音乐', 'draft', 0, 0, 0, 0, 0, 0),
  (43, 3, 13, 'Ngân hàng', '银行', 'draft', 0, 0, 0, 0, 0, 0),
  (44, 3, 14, 'Quan hệ', '关系', 'draft', 0, 0, 0, 0, 0, 0),
  (45, 3, 15, 'Ông bà', '爷爷', 'draft', 0, 0, 0, 0, 0, 0),
  (46, 3, 16, 'Lễ hội', '节日', 'draft', 0, 0, 0, 0, 0, 0),
  (47, 3, 17, 'Môi trường', '环境', 'draft', 0, 0, 0, 0, 0, 0),
  (48, 3, 18, 'Thành phố', '城市', 'draft', 0, 0, 0, 0, 0, 0),
  (49, 3, 19, 'Quản lý', '经理', 'draft', 0, 0, 0, 0, 0, 0),
  (50, 3, 20, 'Ước muốn', '愿意', 'draft', 0, 0, 0, 0, 0, 0),
  (51, 4, 1, 'Kinh nghiệm', '经验', 'draft', 0, 0, 0, 0, 0, 0),
  (52, 4, 2, 'Phỏng vấn', '面试', 'draft', 0, 0, 0, 0, 0, 0),
  (53, 4, 3, 'Tôn trọng', '尊重', 'draft', 0, 0, 0, 0, 0, 0),
  (54, 4, 4, 'Cạnh tranh', '竞争', 'draft', 0, 0, 0, 0, 0, 0),
  (55, 4, 5, 'Khoa học', '科学', 'draft', 0, 0, 0, 0, 0, 0),
  (56, 4, 6, 'Kinh tế', '经济', 'draft', 0, 0, 0, 0, 0, 0),
  (57, 4, 7, 'Văn hoá', '文化', 'draft', 0, 0, 0, 0, 0, 0),
  (58, 4, 8, 'Lịch sử', '历史', 'draft', 0, 0, 0, 0, 0, 0),
  (59, 4, 9, 'Pháp luật', '法律', 'draft', 0, 0, 0, 0, 0, 0),
  (60, 4, 10, 'Giáo dục', '教育', 'draft', 0, 0, 0, 0, 0, 0),
  (61, 4, 11, 'Tâm trạng', '心情', 'draft', 0, 0, 0, 0, 0, 0),
  (62, 4, 12, 'Xã hội', '社会', 'draft', 0, 0, 0, 0, 0, 0),
  (63, 4, 13, 'Nghệ thuật', '艺术', 'draft', 0, 0, 0, 0, 0, 0),
  (64, 4, 14, 'Truyền thống', '传统', 'draft', 0, 0, 0, 0, 0, 0),
  (65, 4, 15, 'Áp lực', '压力', 'draft', 0, 0, 0, 0, 0, 0),
  (66, 4, 16, 'Ô nhiễm', '污染', 'draft', 0, 0, 0, 0, 0, 0),
  (67, 4, 17, 'Công nghệ', '技术', 'draft', 0, 0, 0, 0, 0, 0),
  (68, 4, 18, 'Thị trường', '市场', 'draft', 0, 0, 0, 0, 0, 0),
  (69, 4, 19, 'Quảng cáo', '广告', 'draft', 0, 0, 0, 0, 0, 0),
  (70, 4, 20, 'Tương lai', '将来', 'draft', 0, 0, 0, 0, 0, 0);

-- Từ vựng HSK 2 · Bài 6 (id 1–10)
INSERT INTO words (id, level_id, lesson_id, hanzi, pinyin, han_viet, meaning_vi, pos, sort_order) VALUES
  (1, 2, 21, '鸡蛋', 'jīdàn', 'Kê đản', 'trứng gà', 'n', 0),
  (2, 2, 21, '牛奶', 'niúnǎi', 'Ngưu nãi', 'sữa bò', 'n', 1),
  (3, 2, 21, '羊肉', 'yángròu', 'Dương nhục', 'thịt cừu', 'n', 2),
  (4, 2, 21, '西瓜', 'xīguā', 'Tây qua', 'dưa hấu', 'n', 3),
  (5, 2, 21, '面条', 'miàntiáo', 'Miến điều', 'mì sợi', 'n', 4),
  (6, 2, 21, '咖啡', 'kāfēi', 'Ca phi', 'cà phê', 'n', 5),
  (7, 2, 21, '米饭', 'mǐfàn', 'Mễ phạn', 'cơm', 'n', 6),
  (8, 2, 21, '鱼', 'yú', 'Ngư', 'cá', 'n', 7),
  (9, 2, 21, '杯子', 'bēizi', 'Bôi tử', 'cái cốc', 'n', 8),
  (10, 2, 21, '服务员', 'fúwùyuán', 'Phục vụ viên', 'người phục vụ', 'n', 9);

INSERT INTO word_examples (word_id, sort_order, zh, pinyin, vi, highlight) VALUES
  (1, 0, '我早上吃了两个鸡蛋。', 'Wǒ zǎoshang chīle liǎng ge jīdàn.', 'Sáng nay tôi ăn hai quả trứng.', '鸡蛋'),
  (1, 1, '鸡蛋多少钱一斤？', 'Jīdàn duōshao qián yì jīn?', 'Trứng bao nhiêu tiền một cân?', '鸡蛋'),
  (2, 0, '我每天喝一杯牛奶。', 'Wǒ měitiān hē yì bēi niúnǎi.', 'Mỗi ngày tôi uống một cốc sữa.', '牛奶'),
  (2, 1, '牛奶在冰箱里。', 'Niúnǎi zài bīngxiāng li.', 'Sữa ở trong tủ lạnh.', '牛奶'),
  (3, 0, '你吃过羊肉吗？', 'Nǐ chīguo yángròu ma?', 'Bạn ăn thịt cừu bao giờ chưa?', '羊肉'),
  (3, 1, '这家饭馆的羊肉很好吃。', 'Zhè jiā fànguǎn de yángròu hěn hǎochī.', 'Thịt cừu ở quán này rất ngon.', '羊肉'),
  (4, 0, '夏天我喜欢吃西瓜。', 'Xiàtiān wǒ xǐhuan chī xīguā.', 'Mùa hè tôi thích ăn dưa hấu.', '西瓜'),
  (4, 1, '这个西瓜很甜。', 'Zhège xīguā hěn tián.', 'Quả dưa hấu này rất ngọt.', '西瓜'),
  (5, 0, '中午我们吃面条吧。', 'Zhōngwǔ wǒmen chī miàntiáo ba.', 'Trưa nay chúng ta ăn mì nhé.', '面条'),
  (5, 1, '这碗面条太辣了。', 'Zhè wǎn miàntiáo tài là le.', 'Bát mì này cay quá.', '面条'),
  (6, 0, '我想喝一杯咖啡。', 'Wǒ xiǎng hē yì bēi kāfēi.', 'Tôi muốn uống một cốc cà phê.', '咖啡'),
  (6, 1, '这里的咖啡很好喝。', 'Zhèlǐ de kāfēi hěn hǎohē.', 'Cà phê ở đây rất ngon.', '咖啡'),
  (7, 0, '我要一碗米饭。', 'Wǒ yào yì wǎn mǐfàn.', 'Tôi gọi một bát cơm.', '米饭'),
  (7, 1, '中国南方人常常吃米饭。', 'Zhōngguó nánfāng rén chángcháng chī mǐfàn.', 'Người miền Nam Trung Quốc thường ăn cơm.', '米饭'),
  (8, 0, '我喜欢吃鱼。', 'Wǒ xǐhuan chī yú.', 'Tôi thích ăn cá.', '鱼'),
  (8, 1, '这条鱼很新鲜。', 'Zhè tiáo yú hěn xīnxiān.', 'Con cá này rất tươi.', '鱼'),
  (9, 0, '这个杯子是我的。', 'Zhège bēizi shì wǒ de.', 'Cái cốc này là của tôi.', '杯子'),
  (9, 1, '杯子里有牛奶。', 'Bēizi li yǒu niúnǎi.', 'Trong cốc có sữa.', '杯子'),
  (10, 0, '服务员，请给我菜单。', 'Fúwùyuán, qǐng gěi wǒ càidān.', 'Phục vụ ơi, cho tôi xin thực đơn.', '服务员'),
  (10, 1, '这家饭馆的服务员很热情。', 'Zhè jiā fànguǎn de fúwùyuán hěn rèqíng.', 'Phục vụ ở quán này rất nhiệt tình.', '服务员');

-- Chữ luyện viết (id 1–12); dữ liệu nét tải sau khi cài
INSERT INTO characters (id, hanzi, pinyin, han_viet, meaning_vi, strokes, structure, radical, stroke_data_status) VALUES
  (1, '鸡', 'jī', 'KÊ', 'con gà', 7, 'Trái phải: 又 + 鸟', '鸟', 'missing'),
  (2, '蛋', 'dàn', 'ĐẢN', 'quả trứng', 11, 'Trên dưới: 疋 + 虫', '虫', 'missing'),
  (3, '牛', 'niú', 'NGƯU', 'con bò', 4, 'Chữ đơn, cũng là bộ Ngưu', '牛', 'missing'),
  (4, '奶', 'nǎi', 'NÃI', 'sữa', 5, 'Trái phải: 女 + 乃', '女', 'missing'),
  (5, '羊', 'yáng', 'DƯƠNG', 'con dê, con cừu', 6, 'Chữ đơn', '羊', 'missing'),
  (6, '肉', 'ròu', 'NHỤC', 'thịt', 6, 'Chữ đơn, cũng là bộ Nhục', '肉', 'missing'),
  (7, '西', 'xī', 'TÂY', 'phía tây', 6, 'Chữ đơn', '西', 'missing'),
  (8, '瓜', 'guā', 'QUA', 'quả dưa', 5, 'Chữ đơn, cũng là bộ Qua', '瓜', 'missing'),
  (9, '面', 'miàn', 'DIỆN / MIẾN', 'mặt; mì', 9, 'Chữ đơn', '面', 'missing'),
  (10, '条', 'tiáo', 'ĐIỀU', 'sợi, dải', 7, 'Trên dưới: 夂 + 朩', '木', 'missing'),
  (11, '咖', 'kā', 'CA', 'dùng trong 咖啡', 8, 'Trái phải: 口 + 加', '口', 'missing'),
  (12, '啡', 'fēi', 'PHI', 'dùng trong 咖啡', 11, 'Trái phải: 口 + 非', '口', 'missing');

INSERT INTO lesson_chars (lesson_id, char_id, source_word_id, sort_order, is_visible) VALUES
  (21, 1, 1, 0, 1),
  (21, 2, 1, 1, 1),
  (21, 3, 2, 2, 1),
  (21, 4, 2, 3, 1),
  (21, 5, 3, 4, 1),
  (21, 6, 3, 5, 1),
  (21, 7, 4, 6, 1),
  (21, 8, 4, 7, 1),
  (21, 9, 5, 8, 1),
  (21, 10, 5, 9, 1),
  (21, 11, 6, 10, 1),
  (21, 12, 6, 11, 1);

INSERT INTO writing_configs (lesson_id, start_mode, grid, pass_count, hint_after_misses, show_animation, highlight_radical) VALUES
  (21, 'trace', 'mi', 2, 2, 1, 1);

-- Câu hỏi: Bài tập (10 câu, 8 dạng) và Kiểm tra (10 câu, 10 dạng) của HSK 2 · Bài 6
INSERT INTO questions (part, lesson_id, type, payload, explain_vi, score, sort_order) VALUES
  ('bt', 21, 'meaning', '{"word":"牛奶","ask":"Từ này nghĩa là gì?","options":["trứng gà","sữa bò","dưa hấu","mì sợi"],"answer":1}', '牛奶 (niúnǎi) là sữa bò: 牛 là con bò, 奶 là sữa.', 1.0, 0),
  ('bt', 21, 'blank', '{"sentence":"我每天喝一杯___。","vi":"Mỗi ngày tôi uống một cốc ___.","showMeaning":true,"options":["鸡蛋","羊肉","牛奶","西瓜"],"answer":2}', '喝 (uống) đi với đồ uống: 喝牛奶 là uống sữa.', 1.0, 1),
  ('bt', 21, 'audio', '{"audioText":"西瓜","audioUrl":null,"options":["鸡蛋","西瓜","面条","羊肉"],"answer":1}', 'Bạn vừa nghe xīguā, tức là 西瓜 (dưa hấu).', 1.0, 2),
  ('bt', 21, 'pinyin', '{"word":"面条","ask":"Đọc từ này thế nào?","options":["miàntiáo","miàntiào","miǎntiáo","miāntiáo"],"answer":0}', '面 đọc thanh 4 (miàn), 条 đọc thanh 2 (tiáo).', 1.0, 3),
  ('bt', 21, 'order', '{"vi":"Tôi muốn ăn mì.","answer":"我想吃面条","tiles":["我","想","吃","面条"]}', 'Trật tự câu: Chủ ngữ + 想 + Động từ + Tân ngữ.', 1.0, 4),
  ('bt', 21, 'trans', '{"zh":"我要羊肉和米饭。","pinyin":"Wǒ yào yángròu hé mǐfàn.","sample":"Tôi gọi thịt cừu và cơm.","keywords":["thịt cừu|thịt dê","cơm"]}', '羊肉 là thịt cừu, 米饭 là cơm, 要 ở đây là gọi món.', 1.0, 5),
  ('bt', 21, 'tf', '{"passage":"小李说：“我要羊肉和米饭。”","statement":"小李想吃面条。","answer":false}', '小李 gọi thịt cừu và cơm, không phải mì.', 1.0, 6),
  ('bt', 21, 'match', '{"pairs":[["鸡蛋","trứng gà"],["西瓜","dưa hấu"],["咖啡","cà phê"],["米饭","cơm"]]}', '鸡蛋 trứng gà · 西瓜 dưa hấu · 咖啡 cà phê · 米饭 cơm.', 1.0, 7),
  ('bt', 21, 'meaning', '{"word":"羊肉","ask":"Từ này nghĩa là gì?","options":["thịt cừu","thịt bò","cá","trứng"],"answer":0}', '羊 là con dê / cừu, 肉 là thịt.', 1.0, 8),
  ('bt', 21, 'blank', '{"sentence":"这碗___太辣了。","vi":"Bát ___ này cay quá.","showMeaning":true,"options":["面条","牛奶","西瓜","咖啡"],"answer":0}', 'Đựng trong bát (碗) và có vị cay thì là mì.', 1.0, 9),
  ('kt', 21, 'blank', '{"sentence":"这家饭馆的___很好吃。","vi":"Món ___ ở quán này rất ngon.","showMeaning":false,"options":["羊肉","牛奶","考试","手机"],"answer":0}', 'Món ăn thì dùng 好吃; 羊肉 là thịt cừu.', 1.0, 0),
  ('kt', 21, 'listen', '{"audioText":"他想喝牛奶。","audioUrl":null,"options":["Anh ấy muốn uống sữa","Anh ấy muốn ăn mì","Anh ấy muốn ăn dưa hấu","Anh ấy muốn ăn trứng"],"answer":0}', '他想喝牛奶 nghĩa là anh ấy muốn uống sữa.', 1.0, 1),
  ('kt', 21, 'meaning', '{"word":"面条","ask":"Từ này nghĩa là gì?","options":["mì sợi","thịt cừu","sữa bò","cà phê"],"answer":0}', '面条 (miàntiáo) là mì sợi.', 1.0, 2),
  ('kt', 21, 'pick', '{"ask":"Câu nào viết đúng?","options":["我喝一杯牛奶。","我吃一杯牛奶。","我喝一个牛奶。","我一杯喝牛奶。"],"answer":0}', '牛奶 là đồ uống nên dùng 喝, lượng từ là 杯.', 1.0, 3),
  ('kt', 21, 'order', '{"vi":"Tôi muốn ăn mì.","answer":"我想吃面条","tiles":["我","想","吃","面条"]}', 'Trật tự câu: Chủ ngữ + 想 + Động từ + Tân ngữ.', 1.0, 4),
  ('kt', 21, 'trans', '{"zh":"这家饭馆的羊肉很好吃。","pinyin":"Zhè jiā fànguǎn de yángròu hěn hǎochī.","sample":"Thịt cừu của quán ăn này rất ngon.","keywords":["thịt cừu|thịt dê","quán|nhà hàng","ngon"]}', '饭馆 là quán ăn, 羊肉 là thịt cừu, 好吃 là ngon.', 1.0, 5),
  ('kt', 21, 'tf', '{"passage":"小王说：“两杯牛奶，谢谢。”","statement":"小王想喝茶。","answer":false}', '小王 gọi 两杯牛奶 (hai cốc sữa), không phải trà.', 1.0, 6),
  ('kt', 21, 'match', '{"pairs":[["鸡蛋","trứng gà"],["西瓜","dưa hấu"],["咖啡","cà phê"],["米饭","cơm"]]}', '鸡蛋 trứng gà · 西瓜 dưa hấu · 咖啡 cà phê · 米饭 cơm.', 1.0, 7),
  ('kt', 21, 'tone', '{"word":"面","ask":"Chọn thanh điệu đúng","options":["miān","mián","miǎn","miàn"],"answer":3}', '面 đọc thanh 4: miàn.', 1.0, 8),
  ('kt', 21, 'toZh', '{"vi":"Tôi muốn uống một cốc cà phê.","options":["我想喝一杯咖啡。","我想吃一杯咖啡。","我喝想一杯咖啡。","我想喝一个咖啡。"],"answer":0}', '咖啡 là đồ uống: dùng 喝 và lượng từ 杯; 想 đứng trước động từ.', 1.0, 9);

INSERT INTO test_configs (lesson_id, duration_sec, pass_score, listen_limit, attempts_limit, shuffle_questions, shuffle_options, auto_submit, show_answers_after) VALUES
  (21, 300, 6.0, 2, NULL, 1, 1, 1, 1);

-- Hội thoại “Ở nhà hàng”
INSERT INTO dialogues (id, lesson_id, title, scene) VALUES
  (1, 21, 'Ở nhà hàng', '小王 và 小李 vào nhà hàng gọi món. Người phục vụ (服务员) hỏi các bạn ăn uống gì.');

INSERT INTO dialogue_speakers (id, dialogue_id, name, avatar_char, color, role_vi, sort_order) VALUES
  (1, 1, '服务员', '服', '#FFF0B3', 'phục vụ', 0),
  (2, 1, '小王', '王', '#DCEBFF', 'khách', 1),
  (3, 1, '小李', '李', '#CFF5E1', 'khách', 2);

INSERT INTO dialogue_lines (dialogue_id, speaker_id, sort_order, zh, highlight, pinyin, vi) VALUES
  (1, 1, 0, '你好，你们想吃什么？', NULL, 'Nǐ hǎo, nǐmen xiǎng chī shénme?', 'Xin chào, các bạn muốn ăn gì?'),
  (1, 2, 1, '我想吃面条。你呢？', '面条', 'Wǒ xiǎng chī miàntiáo. Nǐ ne?', 'Mình muốn ăn mì. Còn bạn?'),
  (1, 3, 2, '我要羊肉和米饭。', '羊肉', 'Wǒ yào yángròu hé mǐfàn.', 'Mình gọi thịt cừu và cơm.'),
  (1, 1, 3, '喝点儿什么？', NULL, 'Hē diǎnr shénme?', 'Các bạn uống gì?'),
  (1, 2, 4, '两杯牛奶，谢谢。', '牛奶', 'Liǎng bēi niúnǎi, xièxie.', 'Hai cốc sữa, cảm ơn.'),
  (1, 1, 5, '好的，请等一下。', NULL, 'Hǎo de, qǐng děng yíxià.', 'Vâng, xin đợi một chút.');

INSERT INTO dialogue_questions (dialogue_id, sort_order, prompt_zh, options, answer) VALUES
  (1, 0, '小王想吃什么？', '["面条","羊肉","米饭"]', 0);

-- Ngữ pháp (3 điểm)
INSERT INTO grammar_points (lesson_id, sort_order, title, subtitle, blocks, explain_vi, examples, note_vi, practice) VALUES
  (21, 0, 'V + 过', 'Đã từng làm gì', '[{"label":"Chủ ngữ","hz":"我","color":"#DCEBFF"},{"label":"Động từ","hz":"吃","color":"#CFF5E1"},{"label":"Trợ từ","hz":"过","color":"#FFB3AA"},{"label":"Tân ngữ","hz":"羊肉","color":"#FFF0B3"}]', '过 đặt ngay sau động từ, dùng để nói về việc mình đã từng trải qua ít nhất một lần trong quá khứ.', '[{"kind":"Khẳng định","formula":"S + V + 过 + O","zh":"我吃过羊肉。","highlight":"过","pinyin":"Wǒ chīguo yángròu.","vi":"Tôi từng ăn thịt cừu."},{"kind":"Phủ định","formula":"S + 没 + V + 过 + O","zh":"我没吃过羊肉。","highlight":"过","pinyin":"Wǒ méi chīguo yángròu.","vi":"Tôi chưa từng ăn thịt cừu."},{"kind":"Câu hỏi","formula":"S + V + 过 + O + 吗？","zh":"你吃过羊肉吗？","highlight":"过","pinyin":"Nǐ chīguo yángròu ma?","vi":"Bạn ăn thịt cừu bao giờ chưa?"}]', 'Phủ định dùng 没, không dùng 不: nói 我没吃过, không nói 我不吃过.', '{"answer":"我没吃过羊肉","vi":"Tôi chưa từng ăn thịt cừu.","tiles":["我","没","吃","过","羊肉"]}'),
  (21, 1, 'Adj + 一点儿', 'Hơn một chút, một chút', '[{"label":"Tính từ","hz":"便宜","color":"#CFF5E1"},{"label":"Bổ ngữ","hz":"一点儿","color":"#FFB3AA"}]', '一点儿 đứng sau tính từ để nói “hơn một chút” khi đề nghị hoặc so sánh. Đứng trước danh từ thì nghĩa là “một ít”.', '[{"kind":"Đề nghị","formula":"Adj + 一点儿 + 吧","zh":"便宜一点儿吧。","highlight":"一点儿","pinyin":"Piányi yìdiǎnr ba.","vi":"Rẻ hơn một chút đi."},{"kind":"Nhờ ai làm","formula":"请 + V + Adj + 一点儿","zh":"请说慢一点儿。","highlight":"一点儿","pinyin":"Qǐng shuō màn yìdiǎnr.","vi":"Xin nói chậm một chút."},{"kind":"Một ít","formula":"V + 一点儿 + N","zh":"我想喝一点儿牛奶。","highlight":"一点儿","pinyin":"Wǒ xiǎng hē yìdiǎnr niúnǎi.","vi":"Tôi muốn uống một chút sữa."}]', 'Không đặt 一点儿 trước tính từ: nói 便宜一点儿, không nói 一点儿便宜.', '{"answer":"便宜一点儿吧","vi":"Rẻ hơn một chút đi.","tiles":["便宜","一点儿","吧"]}'),
  (21, 2, '要 + N', 'Muốn, gọi (món)', '[{"label":"Chủ ngữ","hz":"我","color":"#DCEBFF"},{"label":"Động từ","hz":"要","color":"#FFB3AA"},{"label":"Tân ngữ","hz":"两杯牛奶","color":"#FFF0B3"}]', '要 đứng trước danh từ để nói mình muốn có cái gì, hay dùng khi gọi món. Đứng trước động từ thì nghĩa là “muốn làm, sắp làm”.', '[{"kind":"Gọi món","formula":"S + 要 + N","zh":"我要两杯牛奶。","highlight":"要","pinyin":"Wǒ yào liǎng bēi niúnǎi.","vi":"Tôi gọi hai cốc sữa."},{"kind":"Muốn làm","formula":"S + 要 + V","zh":"我要去北京。","highlight":"要","pinyin":"Wǒ yào qù Běijīng.","vi":"Tôi muốn đi Bắc Kinh."},{"kind":"Câu hỏi","formula":"S + 要 + 什么？","zh":"你要什么？","highlight":"要","pinyin":"Nǐ yào shénme?","vi":"Bạn muốn gì?"}]', 'Phủ định của “muốn” thường dùng 不想, ví dụ 我不想喝牛奶.', '{"answer":"我要两杯牛奶","vi":"Tôi gọi hai cốc sữa.","tiles":["我","要","两杯","牛奶"]}');

-- Từ điển nội bộ (mẫu; nạp đầy đủ bằng tools/import_hsk_js.php)
INSERT INTO dict_words (hanzi, pinyin, han_viet, meaning_vi, pos) VALUES
  ('鸡蛋', 'jīdàn', 'Kê đản', 'trứng gà', 'n'),
  ('牛奶', 'niúnǎi', 'Ngưu nãi', 'sữa bò', 'n'),
  ('羊肉', 'yángròu', 'Dương nhục', 'thịt cừu', 'n'),
  ('西瓜', 'xīguā', 'Tây qua', 'dưa hấu', 'n'),
  ('面条', 'miàntiáo', 'Miến điều', 'mì sợi', 'n'),
  ('咖啡', 'kāfēi', 'Ca phi', 'cà phê', 'n'),
  ('米饭', 'mǐfàn', 'Mễ phạn', 'cơm', 'n'),
  ('鱼', 'yú', 'Ngư', 'cá', 'n'),
  ('杯子', 'bēizi', 'Bôi tử', 'cái cốc', 'n'),
  ('服务员', 'fúwùyuán', 'Phục vụ viên', 'người phục vụ', 'n'),
  ('资源', 'zīyuán', 'Tư nguyên', 'tài nguyên', 'n'),
  ('媒体', 'méitǐ', 'Môi thể', 'truyền thông', 'n');

-- Link Shopee mẫu: URL giả, đều đang tắt; công tắc tổng support.enabled = false
INSERT INTO affiliate_links (name, url, place_desk, place_done, is_active, sort_order) VALUES
  ('Vở ô 田 luyện viết chữ Hán', 'https://s.shopee.vn/THAY-BANG-LINK-THAT', 1, 1, 0, 0),
  ('Bút lông luyện thư pháp', 'https://s.shopee.vn/THAY-BANG-LINK-THAT', 1, 0, 0, 1),
  ('Sách luyện thi HSK 1–2', 'https://s.shopee.vn/THAY-BANG-LINK-THAT', 1, 0, 0, 2),
  ('Hộp thẻ giấy trắng 500 tờ', 'https://s.shopee.vn/THAY-BANG-LINK-THAT', 0, 1, 0, 3),
  ('Tai nghe học online', 'https://s.shopee.vn/THAY-BANG-LINK-THAT', 0, 1, 0, 4),
  ('Giá đỡ điện thoại để bàn', 'https://s.shopee.vn/THAY-BANG-LINK-THAT', 0, 0, 0, 5),
  ('Bảng viết nước luyện chữ', 'https://s.shopee.vn/THAY-BANG-LINK-THAT', 0, 0, 0, 6);
