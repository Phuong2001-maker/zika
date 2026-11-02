"""Kiểm cách đọc chậm giữ cao độ: python -m unittest test_doc_tu_nam.py."""
import unittest

import numpy as np

from doc_tu_nam import doc_cham


class DocChamTest(unittest.TestCase):
    def test_keo_dai_nhung_khong_ha_cao_do(self):
        sr = 24000
        am = (0.2 * np.sin(2 * np.pi * 180 * np.arange(int(sr * 0.3)) / sr)).astype(np.float32)
        ra = doc_cham(am, sr, 1)
        self.assertGreater(len(ra) / sr, 0.43)
        self.assertLess(len(ra) / sr, 0.53)
        pho = np.abs(np.fft.rfft(ra))
        hz = np.fft.rfftfreq(len(ra), 1 / sr)[np.argmax(pho)]
        self.assertAlmostEqual(hz, 180, delta=3)

    def test_khong_lam_nhanh_file_da_doc_cham(self):
        am = np.full(24000, 0.2, dtype=np.float32)
        self.assertIs(doc_cham(am, 24000, 1), am)

    def test_file_cam_bi_tu_choi(self):
        with self.assertRaises(ValueError):
            doc_cham(np.zeros(24000, dtype=np.float32), 24000, 1)


if __name__ == "__main__":
    unittest.main()
