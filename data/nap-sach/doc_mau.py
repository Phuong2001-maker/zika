"""Sinh file đọc mẫu bằng Kokoro (giọng người tự nhiên, chạy trên máy, không gọi dịch vụ ngoài).

Chạy bằng Python của kokoro-tts (venv trong thư mục đó đã hỏng đường dẫn khi chuyển máy):
  set PYTHONPATH=<Project>\\kokoro-tts\\.venv\\Lib\\site-packages
  <Project>\\kokoro-tts\\python\\cpython-3.12.15-windows-x86_64-none\\python.exe doc_mau.py ra/doc_mau.json 0 4

Đầu vào: danh sách {text, pinyin, voice, speed, out}. Chia việc theo (số thứ tự % N == i) để chạy
nhiều tiến trình song song. File đã có thì bỏ qua, nên dừng giữa chừng chạy lại được.

Kokoro tự đoán cách đọc bằng pypinyin nên đọc sai chữ đa âm (还 hái/huán, 得 de/děi, 地 de/dì…).
Có pinyin của sách thì ép đọc đúng từng âm tiết theo pinyin đó, rồi mới để Kokoro biến điệu
(3-3, 一, 不) như người thật. Số âm tiết không khớp số chữ (câu có chữ số…) thì để Kokoro tự đọc.
"""
from __future__ import annotations

import json
import os
import re
import sys
from pathlib import Path

THU_MUC = Path(__file__).resolve().parent
KOKORO = THU_MUC.parents[2] / "kokoro-tts"
os.environ.setdefault("HF_HOME", str(KOKORO / "models"))
os.environ.setdefault("HF_HUB_OFFLINE", "1")

import numpy as np  # noqa: E402
import soundfile as sf  # noqa: E402
import torch  # noqa: E402
from kokoro import KModel, KPipeline  # noqa: E402
from pypinyin.contrib.tone_convert import to_finals_tone3, to_initials, to_tone3  # noqa: E402

sys.path.insert(0, str(THU_MUC))
from chung import chu_han, tach_am_tiet  # noqa: E402

REPO_ID = "hexgrad/Kokoro-82M-v1.1-zh"
SR = 24000
GIONG = {"nu": "zf_001", "nam": "zm_010", "nu2": "zf_003", "nam2": "zm_009"}

ep: list[tuple[str, str]] = []  # (âm đầu, vần+thanh) còn phải dùng cho câu đang đọc


def am_tiet(py: str) -> tuple[str, str]:
    """'xiǎo' → ('x', 'iao3'); thanh nhẹ → 5. Dạng strict như pypinyin trả cho Kokoro."""
    t3 = to_tone3(py.lower().replace("’", "").replace("'", ""), neutral_tone_with_five=True)
    return to_initials(t3, strict=True), to_finals_tone3(t3, strict=True, neutral_tone_with_five=True)


def chuan_bi_ep(text: str, pinyin: str | None) -> bool:
    ep.clear()
    if not pinyin or re.search(r"\d", text):
        return False
    chu = chu_han(text)
    am = tach_am_tiet(pinyin)
    # Nhi hoá: 一点儿 = yìdiǎnr → âm tiết trước bỏ r, 儿 đọc er5 để Kokoro tự gộp.
    if "儿" in chu and len(am) == len(chu) - chu.count("儿"):
        moi, k = [], 0
        for i, c in enumerate(chu):
            if c == "儿" and i > 0:
                moi.append("er")
            else:
                a = am[k]
                k += 1
                if i + 1 < len(chu) and chu[i + 1] == "儿" and a.lower().endswith("r") and a.lower() != "er":
                    a = a[:-1]
                moi.append(a)
        am = moi
    if len(am) != len(chu):
        return False
    try:
        ep.extend(am_tiet(a) for a in am)
    except Exception:
        ep.clear()
        return False
    return True


def vao_kokoro(pipe: KPipeline) -> None:
    fe = pipe.g2p.frontend
    goc = fe._get_initials_finals
    goc_bien = fe.tone_modifier.modified_tone
    vua_ep: dict[int, str] = {}  # vị trí trong từ vừa ép → vần+thanh của sách

    def bien(word: str, pos: str, finals: list[str]) -> list[str]:
        # Biến điệu 3-3, 一, 不 vẫn để Kokoro làm; nhưng nó còn ép 得/地/的 cuối từ thành thanh nhẹ,
        # trong khi sách ghi 得 děi — thanh sách ghi rõ (1–4) thì giữ.
        ra = goc_bien(word, pos, finals)
        for i, v in vua_ep.items():
            if i < len(ra) and ra[i].endswith("5") and not v.endswith("5"):
                ra[i] = v
        vua_ep.clear()
        return ra

    fe.tone_modifier.modified_tone = bien

    def moi(word: str):
        ini, fin = goc(word)
        vua_ep.clear()
        if ep:
            for i, c in enumerate(word):
                if "㐀" <= c <= "鿿" and ep:
                    a, v = ep.pop(0)
                    # Như ZHFrontend: zi/ci/si → ii, zhi/chi/shi/ri → iii
                    if re.match(r"i\d", v):
                        v = v.replace("i", "ii" if a in ("z", "c", "s") else "iii" if a in ("zh", "ch", "sh", "r") else "i", 1)
                    ini[i], fin[i] = a, v
                    vua_ep[i] = v
        return ini, fin

    fe._get_initials_finals = moi


SO = dict(zip("0123456789", "零幺二三四五六七八九"))


def doc_so(text: str) -> str:
    """Những chỗ Kokoro (qua cn2an) đọc sai: số điện thoại thành số lớn (0932… → 九亿…), chữ cái Latin."""
    # Số điện thoại/dãy số dài: đọc từng số, 1 đọc yāo như khi đọc số điện thoại.
    text = re.sub(r"\d{7,}", lambda m: "".join(SO[c] for c in m.group()), text)
    # Giọng tiếng Trung không đọc được chữ cái; HSK đọc bằng tên đầy đủ của kỳ thi.
    return text.replace("HSK", "汉语水平考试")


def chia_nguoi(text: str, giong: str) -> list[tuple[str, str]]:
    """Ví dụ dạng hội thoại "A：… B：…": bỏ nhãn, A và B đọc hai giọng khác nhau (nữ/nam đảo theo giọng chính)."""
    phan = re.split(r"\b([AB])\s*[:：]\s*", text)
    if len(phan) < 3:
        return [(text, giong)]
    khac = {"nu": "nam", "nam": "nu", "nu2": "nam2", "nam2": "nu2"}[giong]
    ra = [(phan[0], giong)] if phan[0].strip() else []
    for nhan, doan_chu in zip(phan[1::2], phan[2::2]):
        if doan_chu.strip():
            ra.append((doan_chu, giong if nhan == "A" else khac))
    return ra


def main() -> None:
    viec = json.loads(Path(sys.argv[1]).read_text(encoding="utf-8"))
    i, n = (int(sys.argv[2]), int(sys.argv[3])) if len(sys.argv) > 3 else (0, 1)
    torch.set_num_threads(max(1, (os.cpu_count() or 4) // n))
    model = KModel(repo_id=REPO_ID).to("cpu").eval()
    pipe = KPipeline(lang_code="z", repo_id=REPO_ID, model=model)
    vao_kokoro(pipe)
    lam = khong_ep = 0
    for k, v in enumerate(viec):
        if k % n != i:
            continue
        out = THU_MUC / v["out"]
        if out.exists():
            continue
        out.parent.mkdir(parents=True, exist_ok=True)
        luot = chia_nguoi(doc_so(v["text"]), v["voice"])
        py = re.sub(r"\b[AB]\s*[:：]\s*", "", v.get("pinyin") or "")
        if not chuan_bi_ep("".join(t for t, _ in luot), py or None):
            khong_ep += 1
        # Kokoro cắt cụt đoạn quá ~510 âm vị (đoạn văn, câu thoại dài) → đọc từng câu rồi nối,
        # nghỉ 0,3 giây giữa hai câu. Pinyin ép vẫn đi theo thứ tự chữ nên tách câu không lệch.
        doan = []
        for doan_chu, giong in luot:
            for cau in [c for c in re.split(r"(?<=[。！？；!?;\n])", doan_chu) if c.strip()]:
                doan += [r.audio.numpy() for r in pipe(cau, voice=GIONG[giong], speed=v.get("speed", 1.0)) if r.audio is not None]
                doan.append(np.zeros(int(SR * 0.3), dtype=np.float32))
        doan = doan[:-1]
        ep.clear()
        if not doan:
            print(f"[rỗng] {v['text']}", flush=True)
            continue
        tmp = out.with_suffix(".tmp.mp3")
        sf.write(tmp, np.concatenate(doan), SR, format="MP3")
        tmp.replace(out)
        lam += 1
        if lam % 100 == 0:
            print(f"[{i}/{n}] {lam} file", flush=True)
    print(f"[{i}/{n}] xong {lam} file, {khong_ep} câu để Kokoro tự đọc", flush=True)


if __name__ == "__main__":
    main()
