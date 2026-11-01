"""Xác định mỗi file nghe (mp3 người dùng đưa) đọc phần nào của bài: nhận dạng giọng nói zh-CN có
sẵn của Windows rồi so chữ nghe được với từng bài khoá / bảng từ / bài tập của bài đó.

  python nghe_gan.py 1        HSK 1 (thư mục ../../../hsk1/<bài>-<số>.mp3)
  python nghe_gan.py 2 3      HSK 2 bài 3 (thư mục ../../../hsk2/Bài <bài>/Bài <bài>-<số>.mp3)

Kết quả: ra/nghe.json — {"hsk1-bai06": [{"file": "6-4.mp3", "phan": "bai_khoa:2", "diem": …, "nghe": "…"}]}.
Nhận dạng sai nhiều chữ cũng không sao: chỉ cần đủ chữ trùng để biết đoạn nào.
"""
from __future__ import annotations

import json
import re
import subprocess
import sys
from pathlib import Path

from chung import SACH_DIR, THU_MUC, chon_bai, chu_han, doc_bai, file_bai, ghi_json

sys.path.append(str(SACH_DIR / "kokoro-tts" / ".venv" / "Lib" / "site-packages"))
import numpy as np  # noqa: E402
import soundfile as sf  # noqa: E402

TAM = THU_MUC / "anh" / "nghe_tam.wav"


def danh_sach(cap: int, bai: int) -> list[Path]:
    th = SACH_DIR / "hsk1" if cap == 1 else SACH_DIR / f"hsk{cap}" / f"Bài {bai}"
    mau = f"{bai}-*.mp3" if cap == 1 else f"Bài {bai}-*.mp3"
    return sorted(th.glob(mau), key=lambda p: int(re.search(r"-(\d+)\.mp3$", p.name).group(1)))


def nghe(mp3: Path) -> str:
    d, sr = sf.read(str(mp3))
    if d.ndim > 1:
        d = d.mean(axis=1)
    n = int(len(d) * 16000 / sr)
    d = np.interp(np.linspace(0, len(d) - 1, n), np.arange(len(d)), d)
    TAM.parent.mkdir(parents=True, exist_ok=True)
    sf.write(str(TAM), d, 16000, subtype="PCM_16")
    r = subprocess.run(["powershell", "-ExecutionPolicy", "Bypass", "-File", str(THU_MUC / "nghe_chu.ps1"), str(TAM)],
                       capture_output=True, timeout=300)
    return r.stdout.decode("utf-8", "replace").strip()


def phan_cua_bai(d: dict) -> dict[str, str]:
    """Chữ Hán của từng phần có thể được đọc trong file nghe."""
    ph = {f"bai_khoa:{k['so']}": "".join(c["zh"] for c in k["cau"]) for k in d["bai_khoa"]}
    # Bảng từ đọc riêng sau từng bài khoá (bài khoá 1 → từ mới 1 → bài khoá 2 …), nên tách theo bài khoá
    for w in d["tu_moi"]:
        k = f"tu_moi:{w.get('bai_khoa', 0)}"
        ph[k] = ph.get(k, "") + w["hanzi"]
    ph["tu_moi:rieng"] = "".join(w["hanzi"] for w in d["ten_rieng"])
    bt = [q.get("cau", "") + q.get("hoi", "") + "".join(q.get("phuong_an", [])) for k in d["bai_khoa"] for q in k.get("cau_hoi", [])]
    bt += [json.dumps(b, ensure_ascii=False) for b in d["luyen_tap"]]
    ph["bai_tap"] = "".join(bt)
    return {k: "".join(chu_han(v)) for k, v in ph.items() if chu_han(v)}


def diem(nghe_duoc: str, van_ban: str) -> float:
    """Tỉ lệ cặp chữ (bigram) nghe được có trong văn bản, cộng nửa điểm cho chữ đơn trùng."""
    a = "".join(chu_han(nghe_duoc))
    if len(a) < 2:
        return 0.0
    cap = {van_ban[i:i + 2] for i in range(len(van_ban) - 1)}
    b2 = sum(1 for i in range(len(a) - 1) if a[i:i + 2] in cap) / (len(a) - 1)
    b1 = sum(1 for c in a if c in van_ban) / len(a)
    return round(b2 + 0.5 * b1, 3)


if __name__ == "__main__":
    f = THU_MUC / "ra" / "nghe.json"
    kq = json.loads(f.read_text(encoding="utf-8")) if f.exists() else {}
    for cap, bai in chon_bai(sys.argv[1:]):
        if cap == 3 or not file_bai(cap, bai).exists():
            continue
        ph = phan_cua_bai(doc_bai(cap, bai))
        dong = []
        for mp3 in danh_sach(cap, bai):
            nd = nghe(mp3)
            bang = sorted(((diem(nd, v), k) for k, v in ph.items()), reverse=True)
            dong.append({"file": mp3.name, "phan": bang[0][1], "diem": bang[0][0],
                         "thu_hai": bang[1] if len(bang) > 1 else None, "nghe": nd[:120]})
            print(f"HSK {cap} · Bài {bai} · {mp3.name}: {bang[0][1]} ({bang[0][0]}) — {nd[:40]}", flush=True)
        kq[f"hsk{cap}-bai{bai:02d}"] = dong
        ghi_json(f, kq)
