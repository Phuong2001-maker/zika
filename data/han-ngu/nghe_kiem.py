"""Nghe lại toàn bộ file ghi âm của bộ Hán ngữ bằng FunASR (chạy trên máy, không tốn phí dịch vụ).

  python nghe_kiem.py                nghe mọi file còn thiếu, ghi nghe/q<quyển>/<tên file>.json
  python nghe_kiem.py 4              chỉ quyển 4

Mỗi kết quả có chữ máy nghe được và mốc thời gian từng chữ, để đối chiếu với sách và cắt đoạn. Chạy
lại được: file đã nghe thì bỏ qua.
"""
from __future__ import annotations

import json
import sys
import time

import numpy as np

from chung_hn import SACH_DIR, THU_MUC, ghi_json
from doc_edge import asr  # type: ignore  # data/nap-sach/doc_edge.py

THU_MUC_NGHE = {1: "File nghe Hán ngữ 1", 2: "File nghe Hán 2", 3: "File nghe Hán 3", 4: "File nghe Hán 4",
                5: "File nghe Hán ngữ 5", 6: "File nghe Hán ngữ 6"}


def giai_ben(mp3, sr: int) -> np.ndarray:
    """Giải mã chịu lỗi: file quyển 5–6 có khung hỏng rải rác và đổi tần số giữa file, bộ giải mã thường dừng ngay.
    Bỏ khung hỏng, tạo lại bộ đổi tần số khi định dạng khung thay đổi."""
    import av

    doan: list[np.ndarray] = []
    r, dang = None, None
    with av.open(str(mp3)) as c:
        for goi in c.demux(audio=0):
            try:
                khung = goi.decode()
            except av.error.InvalidDataError:
                continue
            for f in khung:
                k = (f.sample_rate, f.format.name, f.layout.name)
                if k != dang:
                    r, dang = av.AudioResampler(format="flt", layout="mono", rate=sr), k
                for x in r.resample(f):
                    doan.append(x.to_ndarray().reshape(-1))
    return np.concatenate(doan) if doan else np.zeros(0, np.float32)


def nghe(mp3) -> dict:
    am = giai_ben(mp3, 16000)
    kq = asr(True).generate(input=np.asarray(am, np.float32), disable_pbar=True)
    chu, moc = "", []
    for x in kq:
        chu += x.get("text", "").replace(" ", "")
        moc += x.get("timestamp", [])
    return {"giay": round(len(am) / 16000, 1), "chu": chu, "moc": moc}


if __name__ == "__main__":
    chon = [int(sys.argv[1])] if len(sys.argv) > 1 else list(THU_MUC_NGHE)
    for q in chon:
        for mp3 in sorted((SACH_DIR / THU_MUC_NGHE[q]).glob("*.mp3")):
            ra = THU_MUC / "nghe" / f"q{q}" / f"{mp3.stem}.json"
            if ra.exists() and not json.loads(ra.read_text(encoding="utf-8")).get("loi"):
                continue
            t = time.time()
            try:
                kq = nghe(mp3)
            except Exception as e:  # file hỏng thì ghi nhận, không dừng cả lượt
                kq = {"giay": 0, "chu": "", "moc": [], "loi": str(e)[:200]}
            ghi_json(ra, kq)
            print(f"q{q} {mp3.name}: {kq['giay']}s → {len(kq['chu'])} chữ, {time.time() - t:.0f}s xử lý", flush=True)
    print("XONG", flush=True)
