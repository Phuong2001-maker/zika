<?php
declare(strict_types=1);

/**
 * Nạp dữ liệu mẫu cho Zika.
 *
 * Chạy:  php api/db/seed.php
 * Yêu cầu: đã chạy 001_schema.sql và đã có api/config.php.
 *
 * Nội dung nạp vào lấy đúng từ bộ thiết kế (thư mục mockup):
 *   - 4 cấp HSK đang mở (1-4) + 2 cấp ẩn (5-6)
 *   - 70 bài học với tên bài và từ gợi ý
 *   - HSK 2 · Bài 6 "Ăn uống" soạn đủ cả 6 phần để bấm vào là học được ngay
 *   - vài tài khoản mẫu kèm phiên truy cập, để trang Quản trị có số liệu thật
 */

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Zika\\')) {
        return;
    }
    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, 5)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use Zika\Auth;
use Zika\Config;
use Zika\Ctrl\AdminContentCtrl;
use Zika\Db;
use Zika\Settings;
use Zika\Util;

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Ho_Chi_Minh');
Config::load(dirname(__DIR__) . '/config.php');

$say = static fn(string $s) => print($s . PHP_EOL);

$say('Nạp dữ liệu mẫu Zika...');
Db::pdo()->exec('SET FOREIGN_KEY_CHECKS = 0');
foreach ([
    'user_word_progress', 'user_part_progress', 'test_attempts', 'study_sessions',
    'auth_tokens', 'password_resets', 'user_settings', 'users',
    'word_examples', 'words', 'hanzi_strokes', 'hanzi', 'questions',
    'dialogue_lines', 'dialogue_speakers', 'dialogues',
    'grammar_forms', 'grammar_blocks', 'grammar_points',
    'lesson_parts', 'mock_exams', 'lessons', 'levels',
    'news', 'feedback', 'support_links', 'settings',
] as $t) {
    Db::pdo()->exec("TRUNCATE TABLE `$t`");
}
Db::pdo()->exec('SET FOREIGN_KEY_CHECKS = 1');

// ───────────────────────── Cấp HSK ─────────────────────────

$levels = [
    [1, '你好 · 喜欢', 150, 1],
    [2, '旅游 · 鸡蛋', 150, 1],
    [3, '环境 · 习惯', 300, 1],
    [4, '经验 · 尊重', 600, 1],
    [5, '资源 · 媒体', 1300, 0],
    [6, '辩论 · 倡导', 2500, 0],
];
foreach ($levels as [$hsk, $sample, $total, $visible]) {
    Db::insert('levels', ['hsk' => $hsk, 'sample' => $sample, 'total_words' => $total, 'visible' => $visible]);
}
$say('  · 6 cấp HSK');

// ───────────────────────── Bài học ─────────────────────────

$lessonData = [
    1 => [
        ['Chào hỏi', '你好 · 再见'], ['Cảm ơn, xin lỗi', '谢谢 · 对不起'], ['Tên và quốc tịch', '名字 · 中国'],
        ['Gia đình', '爸爸 · 妈妈'], ['Tuổi, số đếm', '岁 · 多少'], ['Ngôn ngữ', '汉语 · 会'],
        ['Ngày tháng', '今天 · 星期'], ['Đồ uống', '喝 · 茶'], ['Nghề nghiệp', '工作 · 医生'],
        ['Trường lớp', '学校 · 同学'], ['Giờ giấc', '点 · 分钟'], ['Thời tiết', '天气 · 下雨'],
        ['Nấu ăn', '菜 · 米饭'], ['Mua sắm', '买 · 衣服'], ['Đi lại', '飞机 · 出租车'],
    ],
    2 => [
        ['Du lịch', '旅游 · 觉得'], ['Sinh hoạt hằng ngày', '起床 · 早上'], ['Màu sắc, vị trí', '颜色 · 右边'],
        ['Công việc', '介绍 · 公司'], ['Mua quần áo', '便宜 · 件'], ['Ăn uống', '鸡蛋 · 牛奶'],
        ['Khoảng cách', '离 · 远'], ['Hẹn gặp', '告诉 · 等'], ['Thi cử', '考试 · 题'],
        ['Đồ dùng', '手机 · 桌子'], ['So sánh', '比 · 大'], ['Sức khỏe', '身体 · 生病'],
        ['Nhà cửa', '门 · 房间'], ['Phim ảnh', '电影 · 过'], ['Năm mới', '新年 · 快乐'],
    ],
    3 => [
        ['Cuối tuần', '周末'], ['Mạng internet', '上网'], ['Sức khỏe', '健康'], ['Bốn mùa', '季节'],
        ['Thói quen', '习惯'], ['Ôn tập', '复习'], ['Công sở', '会议'], ['Nhà hàng', '菜单'],
        ['Giao thông', '地铁'], ['Thể thao', '比赛'], ['Xuất ngoại', '护照'], ['Âm nhạc', '音乐'],
        ['Ngân hàng', '银行'], ['Quan hệ', '关系'], ['Ông bà', '爷爷'], ['Lễ hội', '节日'],
        ['Môi trường', '环境'], ['Thành phố', '城市'], ['Quản lý', '经理'], ['Ước muốn', '愿意'],
    ],
    4 => [
        ['Kinh nghiệm', '经验'], ['Phỏng vấn', '面试'], ['Tôn trọng', '尊重'], ['Cạnh tranh', '竞争'],
        ['Khoa học', '科学'], ['Kinh tế', '经济'], ['Văn hóa', '文化'], ['Lịch sử', '历史'],
        ['Pháp luật', '法律'], ['Giáo dục', '教育'], ['Tâm trạng', '心情'], ['Xã hội', '社会'],
        ['Nghệ thuật', '艺术'], ['Truyền thống', '传统'], ['Áp lực', '压力'], ['Ô nhiễm', '污染'],
        ['Công nghệ', '技术'], ['Thị trường', '市场'], ['Quảng cáo', '广告'], ['Tương lai', '将来'],
    ],
];

/** @var array<string,int> $L  khoá "hsk.no" -> lesson_id */
$L = [];
foreach ($lessonData as $hsk => $rows) {
    foreach ($rows as $i => [$title, $sample]) {
        $no  = $i + 1;
        $id  = Db::insert('lessons', [
            'hsk'    => $hsk,
            'no'     => $no,
            'title'  => $title,
            'sample' => $sample,
            'status' => 'published',
            'sort'   => $no,
        ]);
        $L["$hsk.$no"] = $id;
    }
}
$say('  · ' . count($L) . ' bài học');

// ───────────────────────── Từ vựng ─────────────────────────

/**
 * Mỗi từ: [hz, pinyin (cách nhau bằng dấu trắng), hán việt, nghĩa, từ loại, ví dụ[]]
 * Ví dụ: [pre, target, post, pinyin, nghĩa]
 */
$wordSets = [
    '1.1' => [
        ['你好', 'nǐ hǎo', 'NỊ HẢO', 'xin chào', 'thán từ', [
            ['', '你好', '，我叫小明。', 'Nǐ hǎo, wǒ jiào Xiǎomíng.', 'Xin chào, tôi tên Tiểu Minh.'],
        ]],
        ['喜欢', 'xǐ huan', 'HỈ HOAN', 'thích', 'động từ', [
            ['我很', '喜欢', '中国菜。', 'Wǒ hěn xǐhuan Zhōngguó cài.', 'Tôi rất thích món Trung Quốc.'],
        ]],
        ['学生', 'xué sheng', 'HỌC SINH', 'học sinh', 'danh từ', [
            ['我是', '学生', '。', 'Wǒ shì xuésheng.', 'Tôi là học sinh.'],
        ]],
        ['朋友', 'péng you', 'BẰNG HỮU', 'bạn bè', 'danh từ', [
            ['他是我的', '朋友', '。', 'Tā shì wǒ de péngyou.', 'Cậu ấy là bạn của tôi.'],
        ]],
        ['谢谢', 'xiè xie', 'TẠ TẠ', 'cảm ơn', 'thán từ', [
            ['', '谢谢', '你！', 'Xièxie nǐ!', 'Cảm ơn bạn!'],
        ]],
    ],
    '2.1' => [
        ['旅游', 'lǚ yóu', 'LỮ DU', 'du lịch', 'động từ', [
            ['我想去北京', '旅游', '。', 'Wǒ xiǎng qù Běijīng lǚyóu.', 'Tôi muốn đi Bắc Kinh du lịch.'],
        ]],
        ['便宜', 'pián yi', 'TIỆN NGHI', 'rẻ', 'tính từ', [
            ['这件衣服很', '便宜', '。', 'Zhè jiàn yīfu hěn piányi.', 'Cái áo này rất rẻ.'],
        ]],
        ['准备', 'zhǔn bèi', 'CHUẨN BỊ', 'chuẩn bị', 'động từ', [
            ['我在', '准备', '考试。', 'Wǒ zài zhǔnbèi kǎoshì.', 'Tôi đang chuẩn bị thi.'],
        ]],
        ['觉得', 'jué de', 'GIÁC ĐẮC', 'cảm thấy, thấy rằng', 'động từ', [
            ['我', '觉得', '很好。', 'Wǒ juéde hěn hǎo.', 'Tôi thấy rất ổn.'],
        ]],
        ['已经', 'yǐ jīng', 'DĨ KINH', 'đã', 'phó từ', [
            ['他', '已经', '走了。', 'Tā yǐjīng zǒu le.', 'Anh ấy đã đi rồi.'],
        ]],
    ],
    // Bài trọng tâm: soạn đủ 6 phần
    '2.6' => [
        ['鸡蛋', 'jī dàn', 'KÊ ĐẢN', 'trứng gà', 'danh từ', [
            ['我早上吃了两个', '鸡蛋', '。', 'Wǒ zǎoshang chīle liǎng ge jīdàn.', 'Sáng nay tôi ăn hai quả trứng.'],
            ['', '鸡蛋', '多少钱一斤？', 'Jīdàn duōshao qián yì jīn?', 'Trứng bao nhiêu tiền một cân?'],
        ]],
        ['牛奶', 'niú nǎi', 'NGƯU NÃI', 'sữa bò', 'danh từ', [
            ['我每天喝一杯', '牛奶', '。', 'Wǒ měitiān hē yì bēi niúnǎi.', 'Mỗi ngày tôi uống một cốc sữa.'],
            ['', '牛奶', '在冰箱里。', 'Niúnǎi zài bīngxiāng li.', 'Sữa ở trong tủ lạnh.'],
        ]],
        ['羊肉', 'yáng ròu', 'DƯƠNG NHỤC', 'thịt cừu', 'danh từ', [
            ['你吃过', '羊肉', '吗？', 'Nǐ chīguo yángròu ma?', 'Bạn ăn thịt cừu bao giờ chưa?'],
            ['这家饭馆的', '羊肉', '很好吃。', 'Zhè jiā fànguǎn de yángròu hěn hǎochī.', 'Thịt cừu ở quán này rất ngon.'],
        ]],
        ['西瓜', 'xī guā', 'TÂY QUA', 'dưa hấu', 'danh từ', [
            ['夏天我喜欢吃', '西瓜', '。', 'Xiàtiān wǒ xǐhuan chī xīguā.', 'Mùa hè tôi thích ăn dưa hấu.'],
            ['这个', '西瓜', '很甜。', 'Zhège xīguā hěn tián.', 'Quả dưa hấu này rất ngọt.'],
        ]],
        ['面条', 'miàn tiáo', 'MIẾN ĐIỀU', 'mì sợi', 'danh từ', [
            ['中午我们吃', '面条', '吧。', 'Zhōngwǔ wǒmen chī miàntiáo ba.', 'Trưa nay chúng ta ăn mì nhé.'],
            ['这碗', '面条', '太辣了。', 'Zhè wǎn miàntiáo tài là le.', 'Bát mì này cay quá.'],
        ]],
        ['咖啡', 'kā fēi', 'CA PHI', 'cà phê', 'danh từ', [
            ['我想喝一杯', '咖啡', '。', 'Wǒ xiǎng hē yì bēi kāfēi.', 'Tôi muốn uống một cốc cà phê.'],
        ]],
        ['米饭', 'mǐ fàn', 'MỄ PHẠN', 'cơm', 'danh từ', [
            ['我要羊肉和', '米饭', '。', 'Wǒ yào yángròu hé mǐfàn.', 'Tôi gọi thịt cừu và cơm.'],
        ]],
        ['杯', 'bēi', 'BÔI', 'cốc, ly (lượng từ)', 'lượng từ', [
            ['两', '杯', '牛奶，谢谢。', 'Liǎng bēi niúnǎi, xièxie.', 'Hai cốc sữa, cảm ơn.'],
        ]],
        ['等', 'děng', 'ĐẲNG', 'đợi', 'động từ', [
            ['请', '等', '一下。', 'Qǐng děng yíxià.', 'Xin đợi một chút.'],
        ]],
        ['好吃', 'hǎo chī', 'HẢO XÍCH', 'ngon', 'tính từ', [
            ['这个菜很', '好吃', '。', 'Zhège cài hěn hǎochī.', 'Món này rất ngon.'],
        ]],
    ],
    '2.7' => [
        ['离', 'lí', 'LY', 'cách, rời', 'giới từ', [
            ['我家', '离', '学校很近。', 'Wǒ jiā lí xuéxiào hěn jìn.', 'Nhà tôi cách trường rất gần.'],
        ]],
        ['远', 'yuǎn', 'VIỄN', 'xa', 'tính từ', [
            ['这里', '远', '吗？', 'Zhèli yuǎn ma?', 'Ở đây có xa không?'],
        ]],
        ['近', 'jìn', 'CẬN', 'gần', 'tính từ', [
            ['银行很', '近', '。', 'Yínháng hěn jìn.', 'Ngân hàng rất gần.'],
        ]],
        ['走', 'zǒu', 'TẨU', 'đi bộ, rời đi', 'động từ', [
            ['我们', '走', '吧。', 'Wǒmen zǒu ba.', 'Chúng ta đi nhé.'],
        ]],
        ['从', 'cóng', 'TÒNG', 'từ', 'giới từ', [
            ['', '从', '这儿到那儿要十分钟。', 'Cóng zhèr dào nàr yào shí fēnzhōng.', 'Từ đây đến đó mất mười phút.'],
        ]],
        ['到', 'dào', 'ĐÁO', 'đến', 'động từ', [
            ['我六点', '到', '家。', 'Wǒ liù diǎn dào jiā.', 'Sáu giờ tôi về đến nhà.'],
        ]],
    ],
    '3.1' => [
        ['环境', 'huán jìng', 'HOÀN CẢNH', 'môi trường', 'danh từ', [
            ['这里的', '环境', '很好。', 'Zhèli de huánjìng hěn hǎo.', 'Môi trường ở đây rất tốt.'],
        ]],
        ['检查', 'jiǎn chá', 'KIỂM TRA', 'kiểm tra', 'động từ', [
            ['医生给我', '检查', '了身体。', 'Yīshēng gěi wǒ jiǎnchále shēntǐ.', 'Bác sĩ đã kiểm tra sức khoẻ cho tôi.'],
        ]],
        ['经常', 'jīng cháng', 'KINH THƯỜNG', 'thường xuyên', 'phó từ', [
            ['他', '经常', '去运动。', 'Tā jīngcháng qù yùndòng.', 'Anh ấy thường xuyên đi tập.'],
        ]],
        ['满意', 'mǎn yì', 'MÃN Ý', 'hài lòng', 'tính từ', [
            ['我对结果很', '满意', '。', 'Wǒ duì jiéguǒ hěn mǎnyì.', 'Tôi rất hài lòng với kết quả.'],
        ]],
        ['担心', 'dān xīn', 'ĐẢM TÂM', 'lo lắng', 'động từ', [
            ['别', '担心', '，会好的。', 'Bié dānxīn, huì hǎo de.', 'Đừng lo, sẽ ổn thôi.'],
        ]],
    ],
    '4.1' => [
        ['经验', 'jīng yàn', 'KINH NGHIỆM', 'kinh nghiệm', 'danh từ', [
            ['他有很多工作', '经验', '。', 'Tā yǒu hěn duō gōngzuò jīngyàn.', 'Anh ấy có nhiều kinh nghiệm làm việc.'],
        ]],
        ['尊重', 'zūn zhòng', 'TÔN TRỌNG', 'tôn trọng', 'động từ', [
            ['我们要', '尊重', '别人。', 'Wǒmen yào zūnzhòng biéren.', 'Chúng ta phải tôn trọng người khác.'],
        ]],
        ['适应', 'shì yìng', 'THÍCH ỨNG', 'thích nghi', 'động từ', [
            ['我慢慢', '适应', '了这里。', 'Wǒ mànmàn shìyìngle zhèli.', 'Tôi dần thích nghi với nơi này.'],
        ]],
        ['竞争', 'jìng zhēng', 'CẠNH TRANH', 'cạnh tranh', 'danh từ', [
            ['市场', '竞争', '很激烈。', 'Shìchǎng jìngzhēng hěn jīliè.', 'Cạnh tranh thị trường rất khốc liệt.'],
        ]],
        ['解释', 'jiě shì', 'GIẢI THÍCH', 'giải thích', 'động từ', [
            ['请你', '解释', '一下。', 'Qǐng nǐ jiěshì yíxià.', 'Bạn giải thích một chút đi.'],
        ]],
    ],
];

$wordIds = [];   // "hsk.no" -> [hz => word_id]
$nWords  = 0;
foreach ($wordSets as $key => $words) {
    $lessonId = $L[$key];
    foreach ($words as $i => [$hz, $py, $hv, $vi, $cls, $examples]) {
        $wid = Db::insert('words', [
            'lesson_id'  => $lessonId,
            'hz'         => $hz,
            'pinyin'     => $py,
            'hv'         => $hv,
            'meaning'    => $vi,
            'word_class' => $cls,
            'sort'       => $i + 1,
        ]);
        $wordIds[$key][$hz] = $wid;
        $nWords++;

        foreach ($examples as $j => [$pre, $target, $post, $exPy, $exVi]) {
            Db::insert('word_examples', [
                'word_id' => $wid,
                'pre'     => $pre,
                'target'  => $target,
                'post'    => $post,
                'pinyin'  => $exPy,
                'meaning' => $exVi,
                'sort'    => $j + 1,
            ]);
        }
    }
}
$say("  · $nWords từ vựng kèm câu ví dụ");

// ───────────────── Luyện viết: chữ Hán của HSK 2 · Bài 6 ─────────────────

$hanziRows = [
    ['鸡', 'jī', 'KÊ', 'con gà', 7, 'Trái phải: 又 + 鸟', '鸡蛋', 'trứng gà'],
    ['蛋', 'dàn', 'ĐẢN', 'quả trứng', 11, 'Trên dưới: 疋 + 虫', '鸡蛋', 'trứng gà'],
    ['牛', 'niú', 'NGƯU', 'con bò', 4, 'Chữ đơn, cũng là bộ Ngưu', '牛奶', 'sữa bò'],
    ['奶', 'nǎi', 'NÃI', 'sữa', 5, 'Trái phải: 女 + 乃', '牛奶', 'sữa bò'],
    ['羊', 'yáng', 'DƯƠNG', 'con dê, con cừu', 6, 'Chữ đơn', '羊肉', 'thịt cừu'],
    ['肉', 'ròu', 'NHỤC', 'thịt', 6, 'Chữ đơn, cũng là bộ Nhục', '羊肉', 'thịt cừu'],
    ['西', 'xī', 'TÂY', 'phía tây', 6, 'Chữ đơn', '西瓜', 'dưa hấu'],
    ['瓜', 'guā', 'QUA', 'quả dưa', 5, 'Chữ đơn, cũng là bộ Qua', '西瓜', 'dưa hấu'],
    ['面', 'miàn', 'DIỆN / MIẾN', 'mặt; mì', 9, 'Chữ đơn', '面条', 'mì sợi'],
    ['条', 'tiáo', 'ĐIỀU', 'sợi, dải', 7, 'Trên dưới: 夂 + 朩', '面条', 'mì sợi'],
    ['咖', 'kā', 'CA', 'dùng trong 咖啡', 8, 'Trái phải: 口 + 加', '咖啡', 'cà phê'],
    ['啡', 'fēi', 'PHI', 'dùng trong 咖啡', 11, 'Trái phải: 口 + 非', '咖啡', 'cà phê'],
];

$hanziIds = [];
foreach ($hanziRows as $i => [$hz, $py, $hv, $vi, $strokes, $struct, $wordHz, $wordVi]) {
    $hanziIds[$hz] = Db::insert('hanzi', [
        'lesson_id'    => $L['2.6'],
        'hz'           => $hz,
        'pinyin'       => $py,
        'hv'           => $hv,
        'meaning'      => $vi,
        'strokes'      => $strokes,
        'struct'       => $struct,
        'word_hz'      => $wordHz,
        'word_meaning' => $wordVi,
        'sort'         => $i + 1,
    ]);
}

// Thứ tự nét mẫu cho chữ 牛 (khung 100x100) — đúng bộ path trong thiết kế.
foreach ([
    ['M38 14 Q33 27 21 38', '丿 phẩy'],
    ['M28 34 L76 34', '一 ngang'],
    ['M14 60 L86 60', '一 ngang'],
    ['M50 10 L50 92', '丨 sổ'],
] as $i => [$d, $name]) {
    Db::insert('hanzi_strokes', [
        'hanzi_id' => $hanziIds['牛'],
        'ord'      => $i + 1,
        'path_d'   => $d,
        'name'     => $name,
    ]);
}
$say('  · 12 chữ luyện viết (chữ 牛 có thứ tự nét)');

// ───────────────── Bài tập (bt) của HSK 2 · Bài 6 ─────────────────

$bt = [
    ['meaning', ['hz' => '牛奶', 'ask' => 'Từ này nghĩa là gì?', 'opts' => ['trứng gà', 'sữa bò', 'dưa hấu', 'mì sợi'], 'hzOpts' => false],
        ['ans' => 1], '牛奶 (niúnǎi) là sữa bò: 牛 là con bò, 奶 là sữa.'],
    ['blank', ['pre' => '我每天喝一杯', 'post' => '。', 'vi' => 'Mỗi ngày tôi uống một cốc ___.',
        'opts' => ['鸡蛋', '羊肉', '牛奶', '西瓜'], 'hzOpts' => true], ['ans' => 2], '喝 (uống) đi với đồ uống: 喝牛奶 là uống sữa.'],
    ['audio', ['audioText' => '西瓜', 'opts' => ['鸡蛋', '西瓜', '面条', '羊肉'], 'hzOpts' => true],
        ['ans' => 1], 'Bạn vừa nghe xīguā, tức là 西瓜 (dưa hấu).'],
    ['pinyin', ['hz' => '面条', 'ask' => 'Đọc từ này thế nào?', 'opts' => ['miàntiáo', 'miàntiào', 'miǎntiáo', 'miāntiáo'], 'hzOpts' => false],
        ['ans' => 0], '面 đọc thanh 4 (miàn), 条 đọc thanh 2 (tiáo).'],
    ['order', ['tiles' => ['面条', '想', '我', '吃'], 'vi' => 'Tôi muốn ăn mì.'],
        ['text' => '我想吃面条'], 'Trật tự câu: Chủ ngữ + 想 + Động từ + Tân ngữ.'],
    ['trans', ['zh' => '我要羊肉和米饭。', 'pinyin' => 'Wǒ yào yángròu hé mǐfàn.', 'sample' => 'Tôi gọi thịt cừu và cơm.'],
        ['keywords' => ['thịt cừu', 'cơm']], '羊肉 là thịt cừu, 米饭 là cơm, 要 ở đây là gọi món.'],
    ['tf', ['passage' => '小李说：“我要羊肉和米饭。”', 'stmt' => '小李想吃面条。'],
        ['tf' => false], '小李 gọi thịt cừu và cơm, không phải mì.'],
    ['match', ['pairs' => [['鸡蛋', 'trứng gà'], ['西瓜', 'dưa hấu'], ['咖啡', 'cà phê'], ['米饭', 'cơm']]],
        [], '鸡蛋 trứng gà · 西瓜 dưa hấu · 咖啡 cà phê · 米饭 cơm.'],
    ['meaning', ['hz' => '羊肉', 'ask' => 'Từ này nghĩa là gì?', 'opts' => ['thịt cừu', 'thịt bò', 'cá', 'trứng'], 'hzOpts' => false],
        ['ans' => 0], '羊 là con dê / cừu, 肉 là thịt.'],
    ['blank', ['pre' => '这碗', 'post' => '太辣了。', 'vi' => 'Bát ___ này cay quá.',
        'opts' => ['面条', '牛奶', '西瓜', '咖啡'], 'hzOpts' => true], ['ans' => 0], 'Đựng trong bát (碗) và có vị cay thì là mì.'],
];

// ───────────────── Kiểm tra (kt) của HSK 2 · Bài 6 ─────────────────

$kt = [
    ['fill', ['pre' => '这家饭馆的', 'post' => '很好吃。', 'vi' => 'Món ___ ở quán này rất ngon.',
        'opts' => ['羊肉', '牛奶', '考试', '手机'], 'hzOpts' => true], ['ans' => 0], 'Món ăn thì dùng 好吃.'],
    ['listen', ['audioText' => '我想喝牛奶。',
        'opts' => ['Anh ấy muốn uống sữa', 'Anh ấy muốn ăn mì', 'Anh ấy muốn ăn dưa hấu', 'Anh ấy muốn ăn trứng'], 'hzOpts' => false],
        ['ans' => 0], 'Câu nghe được là 我想喝牛奶 — muốn uống sữa.'],
    ['meaning', ['hz' => '面条', 'ask' => 'Từ này nghĩa là gì?', 'opts' => ['mì sợi', 'thịt cừu', 'sữa bò', 'cà phê'], 'hzOpts' => false],
        ['ans' => 0], '面条 (miàntiáo) là mì sợi.'],
    ['pick', ['ask' => 'Câu nào viết đúng?',
        'opts' => ['我喝一杯牛奶。', '我吃一杯牛奶。', '我喝一个牛奶。', '我一杯喝牛奶。'], 'hzOpts' => true],
        ['ans' => 0], '牛奶 là đồ uống nên dùng 喝, lượng từ là 杯.'],
    ['order', ['tiles' => ['面条', '想', '我', '吃'], 'vi' => 'Tôi muốn ăn mì.'],
        ['text' => '我想吃面条'], 'Trật tự: Chủ ngữ + 想 + Động từ + Tân ngữ.'],
    ['trans', ['zh' => '这家饭馆的羊肉很好吃。', 'pinyin' => 'Zhè jiā fànguǎn de yángròu hěn hǎochī.',
        'sample' => 'Thịt cừu của quán ăn này rất ngon.'],
        ['keywords' => ['thịt cừu', 'ngon']], '好吃 nói về món ăn ngon.'],
    ['tf', ['passage' => '小王说：“两杯牛奶，谢谢。”', 'stmt' => '小王想喝茶。'],
        ['tf' => false], '小王 gọi sữa, không phải trà.'],
    ['match', ['pairs' => [['鸡蛋', 'trứng gà'], ['西瓜', 'dưa hấu'], ['咖啡', 'cà phê'], ['米饭', 'cơm']]],
        [], 'Bốn từ đồ ăn thức uống cơ bản của bài.'],
    ['tone', ['hz' => '面', 'ask' => 'Chọn thanh điệu đúng', 'opts' => ['miān', 'mián', 'miǎn', 'miàn'], 'hzOpts' => false],
        ['ans' => 3], '面 đọc thanh 4: miàn.'],
    ['toZh', ['ask' => 'Tôi muốn uống một cốc cà phê.',
        'opts' => ['我想喝一杯咖啡。', '我想吃一杯咖啡。', '我喝想一杯咖啡。', '我想喝一个咖啡。'], 'hzOpts' => true],
        ['ans' => 0], '想 + 喝 + 一杯 + 咖啡 mới đúng trật tự.'],
];

$promptOf = static function (string $kind, array $d): string {
    return match ($kind) {
        'meaning' => ($d['hz'] ?? '') . ' → nghĩa?',
        'pinyin'  => ($d['hz'] ?? '') . ' → pinyin?',
        'tone'    => ($d['hz'] ?? '') . ' → thanh điệu?',
        'blank', 'fill'   => ($d['pre'] ?? '') . '___' . ($d['post'] ?? ''),
        'audio', 'listen' => 'Nghe: ' . ($d['audioText'] ?? ''),
        'order'   => implode(' / ', $d['tiles'] ?? []),
        'trans'   => (string) ($d['zh'] ?? ''),
        'tf'      => '★ ' . ($d['stmt'] ?? ''),
        'match'   => implode(' · ', array_map(static fn($p) => $p[0], $d['pairs'] ?? [])),
        'pick', 'toZh' => (string) ($d['ask'] ?? ''),
        default   => '',
    };
};

foreach (['bt' => $bt, 'kt' => $kt] as $bank => $set) {
    foreach ($set as $i => [$kind, $data, $answer, $explain]) {
        Db::insert('questions', [
            'lesson_id' => $L['2.6'],
            'bank'      => $bank,
            'kind'      => $kind,
            'prompt'    => $promptOf($kind, $data),
            'data'      => Util::jsonEncode($data),
            'answer'    => Util::jsonEncode($answer),
            'explain'   => $explain,
            'score'     => 1,
            'sort'      => $i + 1,
        ]);
    }
}
$say('  · 10 câu bài tập + 10 câu kiểm tra');

// ───────────────── Hội thoại của HSK 2 · Bài 6 ─────────────────

$dlgId = Db::insert('dialogues', [
    'lesson_id' => $L['2.6'],
    'title'     => 'Ở quán ăn',
    'quiz_q'    => '小王 gọi món gì?',
    'quiz_opts' => Util::jsonEncode(['面条', '羊肉', '米饭']),
    'quiz_ans'  => 0,
]);

$speakers = [];
foreach ([
    ['服务员', '服', '#FFF0B3', 'phục vụ'],
    ['小王', '王', '#DCEBFF', 'khách'],
    ['小李', '李', '#CFF5E1', 'khách'],
] as $i => [$name, $ava, $color, $role]) {
    $speakers[$name] = Db::insert('dialogue_speakers', [
        'dialogue_id' => $dlgId,
        'name'        => $name,
        'ava'         => $ava,
        'color'       => $color,
        'role'        => $role,
        'sort'        => $i + 1,
    ]);
}

foreach ([
    ['服务员', '你好，你们想吃什么？', '', '', 'Nǐ hǎo, nǐmen xiǎng chī shénme?', 'Xin chào, các bạn muốn ăn gì?'],
    ['小王', '我想吃', '面条', '。你呢？', 'Wǒ xiǎng chī miàntiáo. Nǐ ne?', 'Mình muốn ăn mì. Còn bạn?'],
    ['小李', '我要', '羊肉', '和米饭。', 'Wǒ yào yángròu hé mǐfàn.', 'Mình gọi thịt cừu và cơm.'],
    ['服务员', '喝点儿什么？', '', '', 'Hē diǎnr shénme?', 'Các bạn uống gì?'],
    ['小王', '两杯', '牛奶', '，谢谢。', 'Liǎng bēi niúnǎi, xièxie.', 'Hai cốc sữa, cảm ơn.'],
    ['服务员', '好的，请等一下。', '', '', 'Hǎo de, qǐng děng yíxià.', 'Vâng, xin đợi một chút.'],
] as $i => [$who, $pre, $target, $post, $py, $vi]) {
    Db::insert('dialogue_lines', [
        'dialogue_id' => $dlgId,
        'speaker_id'  => $speakers[$who],
        'pre'         => $pre,
        'target'      => $target,
        'post'        => $post,
        'pinyin'      => $py,
        'meaning'     => $vi,
        'sort'        => $i + 1,
    ]);
}
$say('  · 1 hội thoại 6 câu');

// ───────────────── Ngữ pháp của HSK 2 · Bài 6 ─────────────────

$grammar = [
    [
        'V + 过', 'Đã từng làm gì',
        '过 đặt ngay sau động từ, dùng để nói về việc mình đã từng trải qua ít nhất một lần trong quá khứ.',
        'Phủ định dùng 没, không dùng 不: nói 我没吃过, không nói 我不吃过.',
        '我没吃过羊肉', ['我', '没', '吃', '过', '羊肉'], 'Tôi chưa từng ăn thịt cừu.',
        [['Chủ ngữ', '我', '#DCEBFF'], ['Động từ', '吃', '#CFF5E1'], ['Trợ từ', '过', '#FFB3AA'], ['Tân ngữ', '羊肉', '#FFF0B3']],
        [
            ['Khẳng định', 'S + V + 过 + O', '我吃', '过', '羊肉。', 'Wǒ chīguo yángròu.', 'Tôi từng ăn thịt cừu.'],
            ['Phủ định', 'S + 没 + V + 过 + O', '我没吃', '过', '羊肉。', 'Wǒ méi chīguo yángròu.', 'Tôi chưa từng ăn thịt cừu.'],
            ['Câu hỏi', 'S + V + 过 + O + 吗？', '你吃', '过', '羊肉吗？', 'Nǐ chīguo yángròu ma?', 'Bạn ăn thịt cừu bao giờ chưa?'],
        ],
    ],
    [
        'Adj + 一点儿', 'Hơn một chút, một chút',
        '一点儿 đứng sau tính từ để nói “hơn một chút” khi đề nghị hoặc so sánh. Đứng trước danh từ thì nghĩa là “một ít”.',
        'Không đặt 一点儿 trước tính từ: nói 便宜一点儿, không nói 一点儿便宜.',
        '便宜一点儿吧', ['便宜', '一点儿', '吧'], 'Rẻ hơn một chút đi.',
        [['Tính từ', '便宜', '#CFF5E1'], ['Bổ ngữ', '一点儿', '#FFB3AA']],
        [
            ['Đề nghị', 'Adj + 一点儿 + 吧', '便宜', '一点儿', '吧。', 'Piányi yìdiǎnr ba.', 'Rẻ hơn một chút đi.'],
            ['Nhờ ai làm', '请 + V + Adj + 一点儿', '请说慢', '一点儿', '。', 'Qǐng shuō màn yìdiǎnr.', 'Xin nói chậm một chút.'],
            ['Một ít', 'V + 一点儿 + N', '我想喝', '一点儿', '牛奶。', 'Wǒ xiǎng hē yìdiǎnr niúnǎi.', 'Tôi muốn uống một chút sữa.'],
        ],
    ],
    [
        '要 + N', 'Muốn, gọi (món)',
        '要 đứng trước danh từ để nói mình muốn có cái gì, hay dùng khi gọi món. Đứng trước động từ thì nghĩa là “muốn làm, sắp làm”.',
        'Phủ định của “muốn” thường dùng 不想, ví dụ 我不想喝牛奶.',
        '我要两杯牛奶', ['我', '要', '两杯', '牛奶'], 'Tôi gọi hai cốc sữa.',
        [['Chủ ngữ', '我', '#DCEBFF'], ['Động từ', '要', '#FFB3AA'], ['Tân ngữ', '两杯牛奶', '#FFF0B3']],
        [
            ['Gọi món', 'S + 要 + N', '我', '要', '两杯牛奶。', 'Wǒ yào liǎng bēi niúnǎi.', 'Tôi gọi hai cốc sữa.'],
            ['Muốn làm', 'S + 要 + V', '我', '要', '去北京。', 'Wǒ yào qù Běijīng.', 'Tôi muốn đi Bắc Kinh.'],
            ['Câu hỏi', 'S + 要 + 什么？', '你', '要', '什么？', 'Nǐ yào shénme?', 'Bạn muốn gì?'],
        ],
    ],
];

foreach ($grammar as $i => [$title, $sub, $explain, $note, $answer, $tiles, $practiceVi, $blocks, $forms]) {
    $pid = Db::insert('grammar_points', [
        'lesson_id'   => $L['2.6'],
        'title'       => $title,
        'sub'         => $sub,
        'explain'     => $explain,
        'note'        => $note,
        'answer'      => $answer,
        'tiles'       => Util::jsonEncode($tiles),
        'practice_vi' => $practiceVi,
        'sort'        => $i + 1,
    ]);
    foreach ($blocks as $k => [$label, $hz, $color]) {
        Db::insert('grammar_blocks', [
            'point_id' => $pid, 'label' => $label, 'hz' => $hz, 'color' => $color, 'sort' => $k + 1,
        ]);
    }
    foreach ($forms as $k => [$kind, $formula, $pre, $target, $post, $py, $vi]) {
        Db::insert('grammar_forms', [
            'point_id' => $pid, 'kind' => $kind, 'formula' => $formula,
            'pre' => $pre, 'target' => $target, 'post' => $post,
            'pinyin' => $py, 'meaning' => $vi, 'sort' => $k + 1,
        ]);
    }
}
$say('  · 3 điểm ngữ pháp');

// ───────────────── Đề thi thử cuối cấp ─────────────────

foreach ([1, 2] as $hsk) {
    Db::insert('mock_exams', [
        'hsk'         => $hsk,
        'title'       => 'Thi thử HSK ' . $hsk,
        'q_count'     => 60,
        'minutes'     => 55,
        'pass_score'  => 120,
        'total_score' => 200,
        'variants'    => 2,
        'status'      => 'published',
    ]);
}

// Tính lại trạng thái 6 phần cho mọi bài
foreach ($L as $lessonId) {
    AdminContentCtrl::refreshParts($lessonId);
}

// ───────────────────────── Tài khoản ─────────────────────────

$demoPass = 'zika12345';

$people = [
    ['Quản trị viên', 'admin@zika.local', 'admin', 'email', 'hsk34'],
    ['Biên tập nội dung', 'editor@zika.local', 'editor', 'email', 'hsk12'],
    ['Nguyễn Minh Anh', 'minhanh@example.com', 'user', 'google', 'hsk12'],
    ['Trần Quốc Bảo', 'bao.tran@example.com', 'user', 'email', 'new'],
    ['Lê Thu Hà', 'thuha@example.com', 'user', 'google', 'new'],
    ['Phạm Đức Long', 'duclong@example.com', 'user', 'google', 'hsk34'],
    ['Võ Ngọc Trâm', 'ngoctram@example.com', 'user', 'email', 'hsk12'],
    ['Đặng Hoàng Nam', 'hoangnam@example.com', 'user', 'google', 'hsk12'],
    ['Bùi Thanh Tâm', 'thanhtam@example.com', 'user', 'google', 'new'],
    ['Hoàng Mai Linh', 'mailinh@example.com', 'user', 'email', 'hsk34'],
    ['Ngô Văn Khoa', 'vankhoa@example.com', 'user', 'google', 'new'],
    ['Đỗ Kim Chi', 'kimchi@example.com', 'user', 'email', 'new'],
];

$U    = [];
$hash = Auth::hash($demoPass);
foreach ($people as $i => [$name, $email, $role, $provider, $levelHint]) {
    $id = Db::insert('users', [
        'name'       => $name,
        'email'      => $email,
        // Tài khoản Google thật thì không có mật khẩu, nhưng để thử được trên
        // máy dev thì vẫn đặt mật khẩu cho mọi tài khoản mẫu.
        'pass_hash'  => $hash,
        'provider'   => $provider,
        'google_sub' => $provider === 'google' ? 'demo-sub-' . $i : null,
        'role'       => $role,
        'status'     => $email === 'kimchi@example.com' ? 'locked' : 'active',
        'level_hint' => $levelHint,
        'created_at' => date('Y-m-d H:i:s', strtotime('-' . (200 - $i * 15) . ' day')),
        'last_seen_at' => date('Y-m-d H:i:s', strtotime('-' . ($i * 3) . ' hour')),
    ]);
    Db::insert('user_settings', ['user_id' => $id]);
    $U[$email] = $id;
}
$say('  · ' . count($U) . ' tài khoản (mật khẩu: ' . $demoPass . ')');

// ───────────────── Tiến độ & phiên truy cập mẫu ─────────────────

/** Ghi một dòng tiến độ. */
$progress = static function (int $userId, int $lessonId, string $part, int $pos, int $known, int $total, ?int $score, bool $done): void {
    Db::insert('user_part_progress', [
        'user_id'   => $userId,
        'lesson_id' => $lessonId,
        'part'      => $part,
        'position'  => $pos,
        'known'     => $known,
        'total'     => $total,
        'last_score' => $score,
        'best_score' => $score,
        'done_at'   => $done ? date('Y-m-d H:i:s', strtotime('-' . random_int(1, 20) . ' day')) : null,
        'updated_at' => date('Y-m-d H:i:s', strtotime('-' . random_int(0, 10) . ' day')),
    ]);
};

// Minh Anh: xong 12 bài HSK1, đang ở bài 6 HSK2 (khớp thẻ "Học tiếp" trong thiết kế)
$ma = $U['minhanh@example.com'];
for ($no = 1; $no <= 12; $no++) {
    $progress($ma, $L["1.$no"], 'study', 10, 10, 10, null, true);
}
for ($no = 1; $no <= 5; $no++) {
    $progress($ma, $L["2.$no"], 'study', 10, 10, 10, null, true);
    $progress($ma, $L["2.$no"], 'bt', 10, random_int(7, 10), 10, random_int(7, 10), true);
    $progress($ma, $L["2.$no"], 'kt', 10, random_int(7, 10), 10, random_int(7, 10), true);
}
// Bài 6 đang học dở: ở thẻ 5/10
Db::insert('user_part_progress', [
    'user_id' => $ma, 'lesson_id' => $L['2.6'], 'part' => 'study',
    'position' => 5, 'known' => 4, 'total' => 10, 'updated_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
]);
foreach (array_slice(array_values($wordIds['2.6']), 0, 4) as $wid) {
    Db::insert('user_word_progress', ['user_id' => $ma, 'word_id' => $wid, 'state' => 'known', 'reps' => 2]);
}

// Các bạn khác: rải tiến độ cho trang Quản trị có số liệu
$spread = [
    'duclong@example.com'  => [1 => 15, 2 => 15, 3 => 15, 4 => 5],
    'hoangnam@example.com' => [1 => 15, 2 => 15, 3 => 3],
    'mailinh@example.com'  => [1 => 15, 2 => 13],
    'ngoctram@example.com' => [1 => 15, 2 => 1],
    'bao.tran@example.com' => [1 => 6],
    'vankhoa@example.com'  => [1 => 4],
    'thuha@example.com'    => [1 => 1],
    'kimchi@example.com'   => [1 => 2],
];
foreach ($spread as $email => $byLevel) {
    $uid = $U[$email];
    foreach ($byLevel as $hsk => $count) {
        for ($no = 1; $no <= $count; $no++) {
            if (!isset($L["$hsk.$no"])) {
                continue;
            }
            $progress($uid, $L["$hsk.$no"], 'study', 10, 10, 10, null, true);
        }
    }
}

// Phiên truy cập: 6 người đang online + lịch sử 30 ngày
$sessions = [
    ['thuha@example.com',    '-3 minute',  null,          180,  1, 'Android', 'study'],
    ['bao.tran@example.com', '-7 minute',  null,          420,  0, 'Windows', 'bt'],
    ['minhanh@example.com',  '-24 minute', null,          1440, 1, 'Android', 'study'],
    ['duclong@example.com',  '-45 minute', null,          2700, 0, 'macOS',   'kt'],
    ['hoangnam@example.com', '-53 minute', null,          3180, 1, 'Android', 'np'],
    ['mailinh@example.com',  '-28 minute', null,          1680, 1, 'iPhone',  'ht'],
    ['ngoctram@example.com', '-5 hour',    '-4 hour',     2580, 1, 'iPhone',  'study'],
    ['vankhoa@example.com',  '-9 hour',    '-8 hour',     660,  0, 'Windows', 'lv'],
    ['thanhtam@example.com', '-1 day',     '-1 day',      1140, 1, 'Android', 'study'],
];
foreach ($sessions as [$email, $startRel, $endRel, $secs, $isPhone, $platform, $part]) {
    Db::insert('study_sessions', [
        'user_id'      => $U[$email],
        'started_at'   => date('Y-m-d H:i:s', strtotime($startRel)),
        'last_ping_at' => $endRel === null ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', strtotime($endRel)),
        'ended_at'     => $endRel === null ? null : date('Y-m-d H:i:s', strtotime($endRel)),
        'seconds'      => $secs,
        'is_phone'     => $isPhone,
        'platform'     => $platform,
        'parts'        => $part,
        'last_part'    => $part,
        'last_lesson_id' => $L['2.6'],
    ]);
}

// Lịch sử rải 30 ngày để nhiệt đồ và cột "tài khoản mới" có dáng
$emails = array_keys($U);
for ($d = 1; $d <= 30; $d++) {
    for ($k = 0; $k < random_int(2, 8); $k++) {
        $email = $emails[random_int(2, count($emails) - 1)];
        $hour  = [7, 8, 12, 19, 20, 21, 22][random_int(0, 6)];
        $start = date('Y-m-d H:i:s', strtotime("-$d day " . $hour . ':' . random_int(0, 59) . ':00'));
        $secs  = random_int(240, 3000);
        Db::insert('study_sessions', [
            'user_id'      => $U[$email],
            'started_at'   => $start,
            'last_ping_at' => date('Y-m-d H:i:s', strtotime($start) + $secs),
            'ended_at'     => date('Y-m-d H:i:s', strtotime($start) + $secs),
            'seconds'      => $secs,
            'is_phone'     => random_int(0, 1),
            'platform'     => ['Android', 'iPhone', 'Windows', 'macOS'][random_int(0, 3)],
            'parts'        => ['study', 'bt', 'kt', 'lv', 'ht', 'np'][random_int(0, 5)],
            'last_part'    => 'study',
        ]);
    }
}

Db::run(
    'UPDATE users u SET u.total_seconds =
        (SELECT COALESCE(SUM(s.seconds), 0) FROM study_sessions s WHERE s.user_id = u.id)'
);
$say('  · tiến độ học và ' . (int) Db::val('SELECT COUNT(*) FROM study_sessions') . ' phiên truy cập');

// ───────────────────────── Thông báo & góp ý ─────────────────────────

Db::insert('news', [
    'title'     => 'Ra mắt phần Kiểm tra và Thi thử HSK 2',
    'body'      => 'Mỗi bài HSK 2 giờ có bài kiểm tra 10 câu, hết giờ tự nộp. Cuối cấp có đề thi thử 60 câu.',
    'display'   => 'banner',
    'audience'  => 'all',
    'start_at'  => date('Y-m-d H:i:s', strtotime('-2 day')),
    'cta_label' => 'Thử ngay',
    'cta_url'   => '/kt',
    'status'    => 'published',
]);
Db::insert('news', [
    'title'    => 'Sắp có HSK 3',
    'body'     => 'Admin đang soạn nốt phần hội thoại cho HSK 3.',
    'display'  => 'modal',
    'audience' => 'hsk2',
    'status'   => 'draft',
]);

foreach ([
    ['phanthao@example.com', 'noi_dung', 'Câu 7 bài kiểm tra Bài 4 hình như đáp án sai, mình chọn "đồng nghiệp" mà báo sai.', 'kt', 4, 7, 'new'],
    ['mailinh@example.com', 'tinh_nang', 'Mong có giao diện tối để học buổi đêm.', '', null, null, 'new'],
    ['vankhoa@example.com', 'phat_am', 'Từ 便宜 đọc thành pián yí, đúng phải là piányi.', 'study', 1, null, 'doing'],
    ['thuha@example.com', 'noi_dung', 'Thêm bài HSK 3 sớm nhé, mình học xong HSK 2 rồi.', '', null, null, 'new'],
    ['vankhoa@example.com', 'hien_thi', 'Trên iPhone nút Lật thẻ bị thanh dưới che mất.', 'study', 6, null, 'doing'],
    ['hoangnam@example.com', 'noi_dung', 'Ví dụ của từ 已经 thiếu dấu câu.', 'study', 1, null, 'done'],
] as $i => [$email, $kind, $body, $part, $lessonNo, $q, $status]) {
    Db::insert('feedback', [
        'user_id'       => $U[$email] ?? null,
        'kind'          => $kind,
        'body'          => $body,
        'ctx_part'      => $part,
        'ctx_lesson_id' => $lessonNo === null ? null : ($L["2.$lessonNo"] ?? null),
        'ctx_question'  => $q,
        'device'        => $i % 2 === 0 ? 'iPhone · Safari' : 'Windows · Chrome',
        'status'        => $status,
        'created_at'    => date('Y-m-d H:i:s', strtotime('-' . ($i * 7 + 1) . ' hour')),
    ]);
}
$say('  · 2 thông báo, 6 góp ý');

// ───────────────────────── Link ủng hộ ─────────────────────────

foreach ([
    ['Bộ thẻ từ vựng HSK 1-2 (giấy)', 'https://s.shopee.vn/zika-demo-1', 'home,summary', 1, 1820, 64],
    ['Vở ô vuông luyện viết chữ Hán', 'https://s.shopee.vn/zika-demo-2', 'summary', 1, 1540, 51],
    ['Bút lông viết thư pháp', 'https://s.shopee.vn/zika-demo-3', 'home', 1, 1320, 38],
    ['Giáo trình HSK Standard Course 2', 'https://s.shopee.vn/zika-demo-4', 'home,account', 1, 2910, 128],
    ['Sticker 214 bộ thủ', 'https://s.shopee.vn/zika-demo-5', 'summary', 1, 2230, 61],
    ['Loa mini để nghe phát âm', 'https://s.shopee.vn/zika-demo-6', '', 0, 0, 0],
    ['Đèn bàn chống mỏi mắt', 'https://s.shopee.vn/zika-demo-7', '', 0, 0, 0],
] as $i => [$name, $url, $places, $active, $views, $clicks]) {
    Db::insert('support_links', [
        'name'       => $name,
        'url'        => $url,
        'placements' => $places,
        'active'     => $active,
        'views'      => $views,
        'clicks'     => $clicks,
        'sort'       => $i + 1,
    ]);
}

// ───────────────────────── Cài đặt chung ─────────────────────────

foreach ([
    'site_name'     => 'Zìkǎ',
    'tagline'       => 'Chọn bộ thẻ, mỗi ngày lật một ít. Miễn phí.',
    'contact_email' => 'hello@zika.local',
    'site_url'      => 'http://localhost:5173',
    'login_google'  => '1',
    'login_email'   => '1',
    'verify_email'  => '0',
    'allow_signup'  => '1',
    'show_feedback' => '1',
    'show_support'  => '0',
    'mask_email'    => '0',
    'default_voice' => 'xiaoxiao',
    'log_keep_days' => '90',
    'backup_daily'  => '1',
] as $k => $v) {
    Settings::set($k, $v);
}
Settings::set('donate', [
    'enabled' => true,
    'bank'    => 'Techcombank',
    'owner'   => 'NGUYEN VAN A',
    'number'  => '19001234567890',
    'memo'    => 'Ung ho Zika',
    'qr'      => '',
]);

$say('');
$say('Xong. Đăng nhập thử:');
$say('  admin@zika.local    / ' . $demoPass . '  (toàn quyền)');
$say('  editor@zika.local   / ' . $demoPass . '  (chỉ soạn nội dung)');
$say('  minhanh@example.com / ' . $demoPass . '  (người học, đang ở HSK 2 · Bài 6)');
