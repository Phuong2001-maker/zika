"""Kiểm các hàm chuẩn bị chữ và đo của doc_edge.py (không gọi mạng, không cần mô hình).

  python test_doc_edge.py
"""
from __future__ import annotations

import unittest

import doc_edge as d


class DocEdge(unittest.TestCase):
    def test_tu_rieng_chon_am(self):
        self.assertEqual(d.van_ban_doc("还", "huán"), "环")
        self.assertEqual(d.van_ban_doc("还", "hái"), "孩")

    def test_ep_chu_da_am_khi_mac_dinh_khac_sach(self):
        # Mặc định đọc đúng thì giữ nguyên; 这只 mặc định zhǐ mà sách zhī → thay chữ thông dụng cùng âm
        self.assertEqual(d.van_ban_doc("我去还书。", "Wǒ qù huán shū."), "我去还书。")
        moi = d.van_ban_doc("这只小猫真漂亮！", "Zhè zhī xiǎo māo zhēn piàoliang!")
        self.assertNotEqual(moi[1], "只")
        self.assertTrue(d._thong_dung(moi[1]))
        self.assertEqual(d.am_sach(moi, "Zhè zhī xiǎo māo zhēn piàoliang!")[1], "zhi1")

    def test_khong_dung_chu_hiem(self):
        for c in "倁倖勡楄":
            self.assertFalse(d._thong_dung(c))
        self.assertTrue(all(d._thong_dung(c) for ds in d.DONG_AM.values() for c in ds))

    def test_khong_ep_bien_dieu_va_thanh_nhe(self):
        # 不/一 biến điệu, thanh nhẹ và 3-3 viết trong sách đều để giọng đọc tự xử lý
        self.assertEqual(d.van_ban_doc("不客气！", "Bú kèqi!"), "不客气！")
        self.assertEqual(d.van_ban_doc("你好！", "Nǐ hǎo!"), "你好！")

    def test_so_va_chu_cai(self):
        self.assertEqual(d.doc_so("0932463321"), "零九三二四六三三二幺")
        self.assertEqual(d.doc_so("HSK"), "汉语水平考试")
        self.assertEqual(d.so_sang_chu("8:40"), "八点四十")
        self.assertEqual(d.so_sang_chu("11月21号"), "十一月二十一号")
        self.assertEqual(d.so_sang_chu("两个"), "二个")

    def test_bo_ngoac(self):
        self.assertEqual(d.bo_ngoac("今天11月21号（日）。"), "今天11月21号。")
        self.assertEqual(d.bo_ngoac("（在办公室，老师给小李讲题）"), "在办公室，老师给小李讲题")

    def test_hai_cach_noi(self):
        self.assertEqual(d.bo_ngoac("星期日/星期天"), "星期日，星期天")
        self.assertEqual(d.bo_ngoac("2026年2月14日/号，星期六"), "2026年2月14日，星期六")
        self.assertEqual(d.am_so_sanh("今天是2026年1月31日"), d.am_so_sanh("今天是二零二六年一月三十一日"))

    def test_so_voi_may_nghe(self):
        # 地/得 trợ từ và 的 đều de; 外边 = 外面; trợ từ cuối câu bỏ qua
        self.assertEqual(d.am_so_sanh("他高兴地笑了。"), d.am_so_sanh("他高兴的笑了"))
        self.assertEqual(d.am_so_sanh("孩子们在外边玩。"), d.am_so_sanh("孩子们在外面玩"))
        self.assertEqual(d.am_so_sanh("我们学校真大啊！"), d.am_so_sanh("我们学校真大"))

    def test_rut_lang(self):
        import numpy as np
        x = np.concatenate([np.ones(2400) * 0.5, np.zeros(int(d.SR * 1.5)), np.ones(2400) * 0.5]).astype(np.float32)
        self.assertLess(len(d.rut_lang(x)) / d.SR, 0.7)

    def test_chia_nguoi_doi_gioi(self):
        self.assertEqual(d.chia_nguoi("A：你好！B：你好！", "nu"), [("你好！", "nu"), ("你好！", "nam")])

    def test_dem_am_tiet(self):
        self.assertEqual(d.so_am_tiet({"text": "那儿", "pinyin": "nàr"}), 1.0)
        self.assertEqual(d.so_am_tiet({"text": "那里", "pinyin": "nàli"}), 1.5)
        self.assertEqual(d.so_am_tiet({"text": "你好", "pinyin": "nǐhǎo"}), 2.0)

    def test_trang_thai(self):
        tu = {"text": "女", "pinyin": "nǚ", "kind": "tu"}
        self.assertEqual(d.trang_thai(tu, {"dat": False, "loi": ["nghe ra «你»"]}), "nghi")
        self.assertEqual(d.trang_thai(tu, {"dat": False, "loi": ["thời lượng 0.90 giây/âm tiết"]}), "loi")
        cau = {"text": "你好吗？", "pinyin": "Nǐ hǎo ma?", "kind": "cau"}
        self.assertEqual(d.trang_thai(cau, {"dat": False, "loi": ["nghe ra «你好马»"]}), "loi")


if __name__ == "__main__":
    unittest.main()
