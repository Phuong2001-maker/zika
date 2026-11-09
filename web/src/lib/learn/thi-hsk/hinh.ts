// Kho hình minh hoạ cho đề Thi thử HSK 3.0 (câu chọn tranh, nhìn tranh trả lời/nói). Hình SVG tự vẽ bằng
// data/hsk30/hinh/ve.py, nằm ở web/static/hinh-hsk/<mã>.svg; thêm hình thì thêm vào cả hai chỗ.
// Mã chỉ gồm chữ thường, số, gạch nối (QuestionPayload::MA_HINH).

export interface Hinh {
  ma: string;
  zh: string;
  vi: string;
  nhom: string;
}

const N = (nhom: string, ds: [string, string, string][]): Hinh[] => ds.map(([ma, zh, vi]) => ({ ma, zh, vi, nhom }));

export const HINH: Hinh[] = [
  ...N('Đồ ăn, đồ uống', [
    ['tao', '苹果', 'quả táo'],
    ['cha', '茶', 'trà'],
    ['ca-phe', '咖啡', 'cà phê'],
    ['com', '米饭', 'cơm'],
    ['mi', '面条', 'mì'],
    ['nuoc', '水', 'nước'],
    ['sua', '牛奶', 'sữa'],
    ['trung', '鸡蛋', 'trứng'],
    ['ca', '鱼', 'cá'],
    ['dua-hau', '西瓜', 'dưa hấu'],
    ['banh-ngot', '蛋糕', 'bánh ngọt'],
  ]),
  ...N('Đồ vật', [
    ['sach', '书', 'sách'],
    ['but', '笔', 'bút'],
    ['ban', '桌子', 'cái bàn'],
    ['ghe', '椅子', 'cái ghế'],
    ['dien-thoai', '手机', 'điện thoại'],
    ['may-tinh', '电脑', 'máy tính'],
    ['ti-vi', '电视', 'ti vi'],
    ['o', '伞', 'cái ô'],
    ['coc', '杯子', 'cái cốc'],
    ['ao', '衣服', 'quần áo'],
    ['giay', '鞋', 'giày'],
    ['mu', '帽子', 'mũ'],
    ['tui', '包', 'cái túi'],
    ['ve', '票', 'vé'],
    ['tien', '钱', 'tiền'],
  ]),
  ...N('Xe cộ', [
    ['xe-buyt', '公共汽车', 'xe buýt'],
    ['tau-hoa', '火车', 'tàu hoả'],
    ['may-bay', '飞机', 'máy bay'],
    ['taxi', '出租车', 'taxi'],
    ['xe-dap', '自行车', 'xe đạp'],
  ]),
  ...N('Nơi chốn', [
    ['truong-hoc', '学校', 'trường học'],
    ['benh-vien', '医院', 'bệnh viện'],
    ['cua-hang', '商店', 'cửa hàng'],
    ['nha-hang', '饭店', 'nhà hàng'],
    ['nha', '家', 'nhà'],
    ['san-bay', '机场', 'sân bay'],
  ]),
  ...N('Thời tiết', [
    ['nang', '晴天', 'trời nắng'],
    ['mua', '下雨', 'trời mưa'],
    ['tuyet', '下雪', 'tuyết rơi'],
    ['am-u', '阴天', 'trời âm u'],
    ['gio', '刮风', 'trời gió'],
  ]),
  ...N('Con vật', [
    ['meo', '猫', 'con mèo'],
    ['cho', '狗', 'con chó'],
    ['chim', '鸟', 'con chim'],
  ]),
  ...N('Người', [
    ['bac-si', '医生', 'bác sĩ'],
    ['giao-vien', '老师', 'giáo viên'],
    ['hoc-sinh', '学生', 'học sinh'],
    ['em-be', '孩子', 'đứa trẻ'],
  ]),
  ...N('Hoạt động', [
    ['an-com', '吃饭', 'ăn cơm'],
    ['uong-tra', '喝茶', 'uống trà'],
    ['doc-sach', '看书', 'đọc sách'],
    ['xem-tivi', '看电视', 'xem ti vi'],
    ['ngu', '睡觉', 'ngủ'],
    ['goi-dien', '打电话', 'gọi điện thoại'],
    ['viet-chu', '写字', 'viết chữ'],
    ['chay-bo', '跑步', 'chạy bộ'],
    ['boi', '游泳', 'bơi'],
    ['da-bong', '踢足球', 'đá bóng'],
    ['hat', '唱歌', 'hát'],
    ['nau-an', '做饭', 'nấu ăn'],
    ['mua-do', '买东西', 'mua đồ'],
    ['lam-viec', '工作', 'làm việc'],
    ['nghe-nhac', '听音乐', 'nghe nhạc'],
    ['chup-anh', '照相', 'chụp ảnh'],
    ['di-bo', '走路', 'đi bộ'],
    ['lai-xe', '开车', 'lái xe'],
    ['om', '生病', 'bị ốm'],
    ['cuoi', '笑', 'cười'],
  ]),
  ...N('Giờ', [
    ['gio-7', '七点', '7 giờ'],
    ['gio-8', '八点', '8 giờ'],
    ['gio-9-30', '九点半', '9 giờ rưỡi'],
    ['gio-12', '十二点', '12 giờ'],
    ['gio-3', '三点', '3 giờ'],
    ['gio-6-30', '六点半', '6 giờ rưỡi'],
  ]),
];

export const HINH_THEO_MA: Record<string, Hinh> = Object.fromEntries(HINH.map((h) => [h.ma, h]));

export const urlHinh = (ma: string): string => `/hinh-hsk/${ma}.svg`;

/** Mã hình từ đường dẫn /hinh-hsk/<mã>.svg (ô hình minh hoạ lưu cả đường dẫn). */
export function maTuUrl(url: string | null | undefined): string | null {
  const m = String(url ?? '').match(/^\/hinh-hsk\/([a-z0-9-]+)\.svg$/);
  return m ? m[1] : null;
}
