<?php
declare(strict_types=1);

namespace Zika\Services;

/**
 * 214 bộ thủ Khang Hy: số → [chữ gốc, tên Hán Việt, nghĩa ngắn]. Dùng ở màn Luyện viết để nói
 * "氵 là bộ Thủy (水) – nước". Dạng biến thể trong chữ (氵, 亻, 扌…) lưu ở characters.radical.
 */
final class Radicals
{
    private const BANG = [
        1 => ['一', 'Nhất', 'số một'], 2 => ['丨', 'Cổn', 'nét sổ'], 3 => ['丶', 'Chủ', 'nét chấm'],
        4 => ['丿', 'Phiệt', 'nét phẩy'], 5 => ['乙', 'Ất', 'thứ hai (can Ất)'], 6 => ['亅', 'Quyết', 'nét móc'],
        7 => ['二', 'Nhị', 'số hai'], 8 => ['亠', 'Đầu', 'đầu, nắp'], 9 => ['人', 'Nhân', 'người'],
        10 => ['儿', 'Nhân', 'người (chân người)'], 11 => ['入', 'Nhập', 'vào'], 12 => ['八', 'Bát', 'số tám, chia ra'],
        13 => ['冂', 'Quynh', 'vùng biên, khung'], 14 => ['冖', 'Mịch', 'trùm, phủ'], 15 => ['冫', 'Băng', 'băng, lạnh'],
        16 => ['几', 'Kỷ', 'ghế nhỏ'], 17 => ['凵', 'Khảm', 'há miệng, hố'], 18 => ['刀', 'Đao', 'con dao'],
        19 => ['力', 'Lực', 'sức lực'], 20 => ['勹', 'Bao', 'bọc lại'], 21 => ['匕', 'Chuỷ', 'cái thìa'],
        22 => ['匚', 'Phương', 'cái hộp'], 23 => ['匸', 'Hệ', 'che giấu'], 24 => ['十', 'Thập', 'số mười'],
        25 => ['卜', 'Bốc', 'xem bói'], 26 => ['卩', 'Tiết', 'đốt tre, ấn tín'], 27 => ['厂', 'Hán', 'sườn núi'],
        28 => ['厶', 'Khư', 'riêng tư'], 29 => ['又', 'Hựu', 'lại, bàn tay'], 30 => ['口', 'Khẩu', 'miệng'],
        31 => ['囗', 'Vi', 'vây quanh'], 32 => ['土', 'Thổ', 'đất'], 33 => ['士', 'Sĩ', 'kẻ sĩ'],
        34 => ['夂', 'Truy', 'đến sau'], 35 => ['夊', 'Tuy', 'đi chậm'], 36 => ['夕', 'Tịch', 'buổi tối'],
        37 => ['大', 'Đại', 'to lớn'], 38 => ['女', 'Nữ', 'phụ nữ'], 39 => ['子', 'Tử', 'con'],
        40 => ['宀', 'Miên', 'mái nhà'], 41 => ['寸', 'Thốn', 'tấc, bàn tay'], 42 => ['小', 'Tiểu', 'nhỏ'],
        43 => ['尢', 'Uông', 'yếu đuối'], 44 => ['尸', 'Thi', 'thân thể, xác'], 45 => ['屮', 'Triệt', 'mầm cây'],
        46 => ['山', 'Sơn', 'núi'], 47 => ['巛', 'Xuyên', 'sông'], 48 => ['工', 'Công', 'công việc'],
        49 => ['己', 'Kỷ', 'bản thân'], 50 => ['巾', 'Cân', 'khăn'], 51 => ['干', 'Can', 'can, thiên can'],
        52 => ['幺', 'Yêu', 'nhỏ bé'], 53 => ['广', 'Nghiễm', 'mái nhà rộng'], 54 => ['廴', 'Dẫn', 'bước dài'],
        55 => ['廾', 'Củng', 'chắp tay'], 56 => ['弋', 'Dặc', 'bắn, cọc'], 57 => ['弓', 'Cung', 'cái cung'],
        58 => ['彐', 'Kệ', 'đầu con nhím'], 59 => ['彡', 'Sam', 'lông, hoa văn'], 60 => ['彳', 'Xích', 'bước chân'],
        61 => ['心', 'Tâm', 'tim, lòng'], 62 => ['戈', 'Qua', 'cây giáo'], 63 => ['戶', 'Hộ', 'cửa một cánh'],
        64 => ['手', 'Thủ', 'tay'], 65 => ['支', 'Chi', 'cành'], 66 => ['攴', 'Phộc', 'đánh khẽ'],
        67 => ['文', 'Văn', 'văn chương'], 68 => ['斗', 'Đẩu', 'cái đấu'], 69 => ['斤', 'Cân', 'cái rìu'],
        70 => ['方', 'Phương', 'vuông, hướng'], 71 => ['无', 'Vô', 'không'], 72 => ['日', 'Nhật', 'mặt trời, ngày'],
        73 => ['曰', 'Viết', 'nói rằng'], 74 => ['月', 'Nguyệt', 'mặt trăng, tháng'], 75 => ['木', 'Mộc', 'cây, gỗ'],
        76 => ['欠', 'Khiếm', 'thiếu, há miệng'], 77 => ['止', 'Chỉ', 'dừng lại'], 78 => ['歹', 'Đãi', 'xấu, chết'],
        79 => ['殳', 'Thù', 'binh khí'], 80 => ['毋', 'Vô', 'chớ, đừng'], 81 => ['比', 'Tỷ', 'so sánh'],
        82 => ['毛', 'Mao', 'lông'], 83 => ['氏', 'Thị', 'họ'], 84 => ['气', 'Khí', 'hơi, khí'],
        85 => ['水', 'Thuỷ', 'nước'], 86 => ['火', 'Hoả', 'lửa'], 87 => ['爪', 'Trảo', 'móng vuốt'],
        88 => ['父', 'Phụ', 'cha'], 89 => ['爻', 'Hào', 'hào (Kinh Dịch)'], 90 => ['爿', 'Tường', 'mảnh gỗ'],
        91 => ['片', 'Phiến', 'mảnh, tấm'], 92 => ['牙', 'Nha', 'răng'], 93 => ['牛', 'Ngưu', 'con trâu, bò'],
        94 => ['犬', 'Khuyển', 'con chó'], 95 => ['玄', 'Huyền', 'màu đen, huyền bí'], 96 => ['玉', 'Ngọc', 'ngọc'],
        97 => ['瓜', 'Qua', 'quả dưa'], 98 => ['瓦', 'Ngoã', 'ngói'], 99 => ['甘', 'Cam', 'ngọt'],
        100 => ['生', 'Sinh', 'sống, sinh ra'], 101 => ['用', 'Dụng', 'dùng'], 102 => ['田', 'Điền', 'ruộng'],
        103 => ['疋', 'Sơ', 'đơn vị đo vải'], 104 => ['疒', 'Nạch', 'bệnh tật'], 105 => ['癶', 'Bát', 'gạt ra'],
        106 => ['白', 'Bạch', 'màu trắng'], 107 => ['皮', 'Bì', 'da'], 108 => ['皿', 'Mãnh', 'bát đĩa'],
        109 => ['目', 'Mục', 'mắt'], 110 => ['矛', 'Mâu', 'cây mâu'], 111 => ['矢', 'Thỉ', 'mũi tên'],
        112 => ['石', 'Thạch', 'đá'], 113 => ['示', 'Thị', 'thần, cúng tế'], 114 => ['禸', 'Nhựu', 'vết chân thú'],
        115 => ['禾', 'Hoà', 'lúa'], 116 => ['穴', 'Huyệt', 'hang lỗ'], 117 => ['立', 'Lập', 'đứng'],
        118 => ['竹', 'Trúc', 'tre trúc'], 119 => ['米', 'Mễ', 'gạo'], 120 => ['糸', 'Mịch', 'sợi tơ'],
        121 => ['缶', 'Phẫu', 'đồ sành'], 122 => ['网', 'Võng', 'cái lưới'], 123 => ['羊', 'Dương', 'con dê'],
        124 => ['羽', 'Vũ', 'lông vũ'], 125 => ['老', 'Lão', 'già'], 126 => ['而', 'Nhi', 'mà, và'],
        127 => ['耒', 'Lỗi', 'cái cày'], 128 => ['耳', 'Nhĩ', 'tai'], 129 => ['聿', 'Duật', 'cây bút'],
        130 => ['肉', 'Nhục', 'thịt, cơ thể'], 131 => ['臣', 'Thần', 'bề tôi'], 132 => ['自', 'Tự', 'tự mình'],
        133 => ['至', 'Chí', 'đến'], 134 => ['臼', 'Cữu', 'cái cối'], 135 => ['舌', 'Thiệt', 'cái lưỡi'],
        136 => ['舛', 'Suyễn', 'trái ngược'], 137 => ['舟', 'Chu', 'con thuyền'], 138 => ['艮', 'Cấn', 'dừng, quẻ Cấn'],
        139 => ['色', 'Sắc', 'màu sắc'], 140 => ['艸', 'Thảo', 'cỏ'], 141 => ['虍', 'Hô', 'vằn hổ'],
        142 => ['虫', 'Trùng', 'sâu bọ'], 143 => ['血', 'Huyết', 'máu'], 144 => ['行', 'Hành', 'đi, làm'],
        145 => ['衣', 'Y', 'áo'], 146 => ['襾', 'Á', 'che đậy'], 147 => ['見', 'Kiến', 'nhìn thấy'],
        148 => ['角', 'Giác', 'cái sừng'], 149 => ['言', 'Ngôn', 'lời nói'], 150 => ['谷', 'Cốc', 'khe núi'],
        151 => ['豆', 'Đậu', 'hạt đậu'], 152 => ['豕', 'Thỉ', 'con lợn'], 153 => ['豸', 'Trãi', 'loài thú'],
        154 => ['貝', 'Bối', 'vỏ sò, tiền'], 155 => ['赤', 'Xích', 'màu đỏ'], 156 => ['走', 'Tẩu', 'chạy, đi'],
        157 => ['足', 'Túc', 'chân'], 158 => ['身', 'Thân', 'thân mình'], 159 => ['車', 'Xa', 'xe'],
        160 => ['辛', 'Tân', 'cay, vất vả'], 161 => ['辰', 'Thần', 'thìn, thời gian'], 162 => ['辵', 'Sước', 'bước đi'],
        163 => ['邑', 'Ấp', 'làng, vùng đất'], 164 => ['酉', 'Dậu', 'bình rượu'], 165 => ['釆', 'Biện', 'phân biệt'],
        166 => ['里', 'Lý', 'làng, dặm'], 167 => ['金', 'Kim', 'kim loại, vàng'], 168 => ['長', 'Trường', 'dài'],
        169 => ['門', 'Môn', 'cửa hai cánh'], 170 => ['阜', 'Phụ', 'gò đất'], 171 => ['隶', 'Đãi', 'kịp, bắt'],
        172 => ['隹', 'Chuy', 'chim đuôi ngắn'], 173 => ['雨', 'Vũ', 'mưa'], 174 => ['青', 'Thanh', 'màu xanh'],
        175 => ['非', 'Phi', 'không phải, sai'], 176 => ['面', 'Diện', 'mặt'], 177 => ['革', 'Cách', 'da thuộc'],
        178 => ['韋', 'Vi', 'da mềm'], 179 => ['韭', 'Cửu', 'rau hẹ'], 180 => ['音', 'Âm', 'âm thanh'],
        181 => ['頁', 'Hiệt', 'đầu, trang'], 182 => ['風', 'Phong', 'gió'], 183 => ['飛', 'Phi', 'bay'],
        184 => ['食', 'Thực', 'ăn'], 185 => ['首', 'Thủ', 'đầu'], 186 => ['香', 'Hương', 'mùi thơm'],
        187 => ['馬', 'Mã', 'con ngựa'], 188 => ['骨', 'Cốt', 'xương'], 189 => ['高', 'Cao', 'cao'],
        190 => ['髟', 'Bưu', 'tóc dài'], 191 => ['鬥', 'Đấu', 'đánh nhau'], 192 => ['鬯', 'Sưởng', 'rượu nếp'],
        193 => ['鬲', 'Cách', 'cái nồi'], 194 => ['鬼', 'Quỷ', 'ma quỷ'], 195 => ['魚', 'Ngư', 'con cá'],
        196 => ['鳥', 'Điểu', 'con chim'], 197 => ['鹵', 'Lỗ', 'đất mặn'], 198 => ['鹿', 'Lộc', 'con hươu'],
        199 => ['麥', 'Mạch', 'lúa mạch'], 200 => ['麻', 'Ma', 'cây gai'], 201 => ['黃', 'Hoàng', 'màu vàng'],
        202 => ['黍', 'Thử', 'lúa nếp'], 203 => ['黑', 'Hắc', 'màu đen'], 204 => ['黹', 'Chỉ', 'may vá'],
        205 => ['黽', 'Mãnh', 'con ếch'], 206 => ['鼎', 'Đỉnh', 'cái vạc'], 207 => ['鼓', 'Cổ', 'cái trống'],
        208 => ['鼠', 'Thử', 'con chuột'], 209 => ['鼻', 'Tỵ', 'cái mũi'], 210 => ['齊', 'Tề', 'ngang bằng'],
        211 => ['齒', 'Xỉ', 'răng'], 212 => ['龍', 'Long', 'con rồng'], 213 => ['龜', 'Quy', 'con rùa'],
        214 => ['龠', 'Dược', 'sáo trúc'],
    ];

    /**
     * @param string|null $form dạng viết trong chữ (氵); trống thì lấy chữ gốc
     * @return array{no:int,form:string,root:string,name:string,meaning:string}|null
     */
    public static function info(?int $no, ?string $form): ?array
    {
        if ($no === null || !isset(self::BANG[$no])) {
            return null;
        }
        [$root, $name, $meaning] = self::BANG[$no];
        return [
            'no' => $no,
            'form' => $form !== null && $form !== '' ? $form : $root,
            'root' => $root,
            'name' => $name,
            'meaning' => $meaning,
        ];
    }
}
