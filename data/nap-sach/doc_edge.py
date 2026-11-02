"""Sinh giọng đọc bằng edge-tts (giọng Microsoft) và tự kiểm từng file; file không đạt thì sinh lại.

  python doc_edge.py               # chạy ra/doc_edge.json (viec_doc.py tu web lập); sinh tiếp phần còn thiếu
  python doc_edge.py --kiem-lai    # kiểm lại mọi file đã sinh (đổi ngưỡng kiểm thì chạy lại cái này)

Mỗi việc {text, pinyin, voice, kind: "tu" | "cau", out}. Chạy theo đợt 200 file: sinh → kiểm ngay →
file không đạt thì sinh lại bằng cách khác (tối đa 4 lần) → ghi ra/doc_edge_kq.json sau mỗi đợt,
nên dừng giữa chừng chạy lại được. Cần mạng khi sinh; web chỉ phát mp3 đã có.

Kiểm mỗi file:
  1. kỹ thuật: giải mã được, không câm, độ to -16 LUFS, không có khoảng lặng dài bất thường,
     thời lượng: từ đơn 0,15–0,85 giây/âm tiết (không kéo dài âm), câu 1,8–5,5 âm tiết/giây;
  2. FunASR nghe lại, pinyin không dấu phải khớp sách (từ đơn đặt sau câu dẫn 我说 để mô hình có
     ngữ cảnh, không đoán thành chữ Latin);
  3. từ một chữ thanh 1, 2, 4: đường cao độ không được trái hẳn thanh của sách.
Đọc đúng chữ đa âm: chỗ nào cách đọc mặc định (pypinyin theo ngữ cảnh) khác pinyin sách thì gửi đi
đọc một chữ đồng âm đúng thanh (还 huán → 环); chữ hiển thị trên web không đổi.
"""
from __future__ import annotations

import asyncio
import hashlib
import io
import json
import os
import re
import sys
import time
import unicodedata
from collections import Counter
from difflib import SequenceMatcher
from pathlib import Path

from chung import SACH_DIR, THU_MUC, chu_han, ghi_json, tach_am_tiet

os.environ.setdefault("MODELSCOPE_CACHE", str(SACH_DIR / "kiem-giong-doc" / "models" / "modelscope"))

import av  # noqa: E402
import edge_tts  # noqa: E402
import numpy as np  # noqa: E402
import pyloudnorm as pyln  # noqa: E402
from pypinyin import Style, lazy_pinyin  # noqa: E402
from pypinyin import pinyin as pinyin_all  # noqa: E402
from pypinyin.contrib.tone_convert import to_tone3  # noqa: E402
from pypinyin.pinyin_dict import pinyin_dict  # noqa: E402

REV = 2  # đổi cách sinh/xử lý thì tăng để sinh lại toàn bộ
SR = 24000
LUFS = -16.0
DINH = 0.89  # đỉnh tối đa -1 dBFS
GIONG = {
    "nu": "zh-CN-XiaoxiaoNeural", "nam": "zh-CN-YunxiNeural",
    "xiaoxiao": "zh-CN-XiaoxiaoNeural", "xiaoyi": "zh-CN-XiaoyiNeural",
    "yunxi": "zh-CN-YunxiNeural", "yunjian": "zh-CN-YunjianNeural",
    "yunyang": "zh-CN-YunyangNeural", "yunxia": "zh-CN-YunxiaNeural",
    "xiaoxiao2": "zh-CN-XiaoxiaoNeural",
}
CAO_DO = {"xiaoxiao2": "-14Hz"}  # giọng biến thể: cùng giọng gốc, đổi cao độ
NU_GIONG = {"nu", "xiaoxiao", "xiaoyi", "xiaoxiao2"}
# Ví dụ dạng "A：… B：…": B đọc giọng khác giới với A.
KHAC = {g: ("nam" if g in NU_GIONG else "nu") for g in GIONG}
# Tốc độ gốc (%): từ đơn để giọng tự đọc chậm nhẹ, không kéo dài bằng phần mềm; giọng nam đọc câu
# nhanh hơn giọng nữ khoảng 25% nên chậm thêm cho đều tai người học.
TOC_GOC = {"tu": -10, "cau_nu": -10, "cau_nam": -20}
SO_LAN = 4  # 1 lần sinh + tối đa 3 lần sinh lại
F_VIEC, F_KQ, F_LOG = (THU_MUC / "ra" / t for t in ("doc_edge.json", "doc_edge_kq.json", "doc_edge.log"))
SO = dict(zip("0123456789", "零一二三四五六七八九"))
DAU = "āáǎàēéěèīíǐìōóǒòūúǔùǖǘǚǜ"


def log(msg: str) -> None:
    dong = f"[{time.strftime('%H:%M:%S')}] {msg}"
    print(dong, flush=True)
    with F_LOG.open("a", encoding="utf-8") as f:
        f.write(dong + "\n")


# ------------------------------------------------------------------ chuẩn bị chữ gửi đi đọc

def doc_so(text: str) -> str:
    """Dãy số dài (số điện thoại) đọc từng số, 1 đọc yāo; HSK đọc tên đầy đủ (giọng Trung không đọc chữ cái)."""
    text = re.sub(r"\d{7,}", lambda m: "".join("幺" if c == "1" else SO[c] for c in m.group()), text)
    return text.replace("HSK", "汉语水平考试")


def bo_ngoac(text: str) -> str:
    """（日） sau 号 là cách viết khác in trong sách, không đọc; câu dẫn cả câu trong ngoặc thì đọc phần trong."""
    t = text.strip()
    # Sách in hai cách nói "A/B" (星期日/星期天, 在/正在): đọc cả hai, nghỉ ngắn ở giữa.
    # Riêng "日/号" sau số ngày là hai cách viết của cùng một chữ: chỉ đọc 日.
    t = t.replace("日/号", "日")
    t = re.sub(r"(?<=[\u3400-\u9fff])\s*/\s*(?=[\u3400-\u9fff])", "，", t)
    if re.fullmatch(r"[（(].*[）)]", t):
        return t[1:-1]
    return re.sub(r"[（(][^）)]*[）)]", "", t).strip()


def chia_nguoi(text: str, giong: str) -> list[tuple[str, str]]:
    phan = re.split(r"\b([AB])\s*[:：]\s*", text)
    if len(phan) < 3:
        return [(text, giong)]
    ra = [(phan[0], giong)] if phan[0].strip() else []
    for nhan, doan in zip(phan[1::2], phan[2::2]):
        if doan.strip():
            ra.append((doan, giong if nhan == "A" else KHAC[giong]))
    return ra


def am_sach(text: str, pinyin: str | None) -> list[str] | None:
    """Âm tiết sách gán cho từng chữ Hán (tone3, thanh nhẹ = 5); None khi không ghép chắc được.

    Không ghép khi có chữ số (7点 trong chữ, "7" trong pinyin): đếm lệch ở chỗ số có thể bù với lệch ở
    chỗ nhi hoá, số âm tiết vẫn khớp mà âm bị gán trượt cả câu (đã gặp: 聊天儿 + 7点 → đọc vô nghĩa).
    Mỗi âm tiết còn phải là một cách đọc của đúng chữ đó, lệch một chỗ là bỏ."""
    if not pinyin or re.search(r"\d", text) or re.search(r"\d", pinyin):
        return None
    chu = chu_han(text)
    am = tach_am_tiet(re.sub(r"\b[AB]\s*[:：]\s*", "", pinyin))
    if "儿" in chu and len(am) == len(chu) - chu.count("儿"):
        moi, k = [], 0
        for i, c in enumerate(chu):
            if c == "儿" and i > 0:
                moi.append("er")
                continue
            a = am[k]
            k += 1
            if i + 1 < len(chu) and chu[i + 1] == "儿" and a.lower().endswith("r") and a.lower() != "er":
                a = a[:-1]
            moi.append(a)
        am = moi
    if len(am) != len(chu):
        return None
    try:
        ra = [to_tone3(unicodedata.normalize("NFC", a.lower()).replace("'", ""), neutral_tone_with_five=True) for a in am]
    except Exception:
        return None
    for c, a in zip(chu, ra):
        goc = re.sub(r"\d", "", a)
        if c == "儿" or not goc:
            continue
        doc_duoc = {x for x in pinyin_all(c, style=Style.NORMAL, heteronym=True)[0]}
        if goc not in doc_duoc and goc.rstrip("r") not in doc_duoc:
            return None
    return ra


def _thong_dung(c: str) -> bool:
    """3.755 chữ cấp 1 của GB2312 (byte đầu 0xB0–0xD7). Chữ hiếm (倁, 勡…) giọng đọc có thể không biết."""
    try:
        b = c.encode("gb2312")
    except UnicodeEncodeError:
        return False
    return len(b) == 2 and 0xB0 <= b[0] <= 0xD7


def _bang_dong_am() -> dict[str, list[str]]:
    """'huan2' → các chữ thông dụng chỉ có đúng một cách đọc đó; ưu tiên chữ có trong giáo trình."""
    quen: Counter = Counter()
    for f in (THU_MUC / "ra").glob("tu_hsk*.json"):
        quen.update(c for w in json.loads(f.read_text(encoding="utf-8")) for c in w["hanzi"])
    bang: dict[str, list[tuple[int, str]]] = {}
    for cp, doc in pinyin_dict.items():
        if not 0x4E00 <= cp <= 0x9FA5 or not _thong_dung(chr(cp)):
            continue
        # Chữ chỉ có một cách đọc đứng trước; chữ nhiều cách đọc chỉ dùng khi cách đọc mặc định trùng âm cần
        # (đứng riêng thì giọng đọc theo âm mặc định), vd 票 piào cho 漂 piào.
        mot = "," not in doc
        bang.setdefault(to_tone3(doc.split(",")[0], neutral_tone_with_five=True), []).append((0 if mot else 1, chr(cp)))
    return {k: [c for _, c in sorted(ds, key=lambda x: (x[0], -quen[x[1]], ord(x[1])))] for k, ds in bang.items()}


DONG_AM = _bang_dong_am()
# Từ đứng riêng thiếu ngữ cảnh: các chỗ đã nghe kiểm từ bộ Yunxi cũ.
TU_RIENG = {("还", "huán"): "环", ("还", "hái"): "孩", ("长", "cháng"): "常",
            ("只", "zhī"): "枝", ("地", "de"): "的", ("粘人", "niánrén"): "黏人",
            ("便", "pián"): "骈"}  # 便 của 便宜: đứng riêng giọng đọc theo âm mặc định biàn
# Chỗ sách đọc khác mặc định mà không có chữ đồng âm để ép — đưa vào danh sách người nghe.
NGHE_TAY = {"得": "děi", "了": "liǎo"}


def van_ban_doc(text: str, pinyin: str | None, ep_them: set[int] | None = None) -> str:
    """Chữ gửi đi đọc: thay chữ đa âm bằng chữ đồng âm ở chỗ cách đọc mặc định khác sách.
    ep_them: vị trí chữ Hán (trong câu) máy nghe sai ở lần trước — ép luôn nếu có chữ đồng âm."""
    py = "".join((pinyin or "").lower().split())
    if (text, py) in TU_RIENG:
        return TU_RIENG[(text, py)]
    sach = am_sach(text, pinyin)
    if not sach:
        return text
    mac = lazy_pinyin(text, style=Style.TONE3, neutral_tone_with_five=True, errors="ignore")
    if len(mac) != len(sach):
        return text
    thay: dict[int, str] = {}
    for i, (b, p) in enumerate(zip(sach, mac)):
        c = chu_han(text)[i]
        if c in "一不儿" or b[-1] == "5" or not b[-1].isdigit():
            continue
        sai = b[:-1] != p[:-1] or (p[-1] != "5" and b[-1] != p[-1]
                                     and not (b[-1] == "2" and p[-1] == "3" and i + 1 < len(mac) and mac[i + 1][-1] == "3"))
        # Ép thêm ở chỗ máy nghe sai chỉ áp cho chữ đa âm: chữ một cách đọc (妮) thay đi chỉ thêm rủi ro.
        da_am = "," in pinyin_dict.get(ord(c), "")
        if sai or (ep_them and i in ep_them and da_am):
            ung = [x for x in DONG_AM.get(b, []) if x != c]
            if ung:
                thay[i] = ung[0]
    if not thay:
        return text
    ra, k = [], 0
    for ch in text:
        if re.fullmatch(r"[㐀-鿿]", ch):
            ra.append(thay.get(k, ch))
            k += 1
        else:
            ra.append(ch)
    return "".join(ra)


# ------------------------------------------------------------------ âm thanh

def giai(mp3: bytes | Path, sr: int = SR) -> np.ndarray:
    with av.open(io.BytesIO(mp3) if isinstance(mp3, bytes) else str(mp3)) as f:
        rs = av.AudioResampler(format="flt", layout="mono", rate=sr)
        ph = [o.to_ndarray().reshape(-1) for fr in f.decode(audio=0) for o in rs.resample(fr)]
        ph += [o.to_ndarray().reshape(-1) for o in rs.resample(None)]
    return np.concatenate(ph) if ph else np.zeros(0, np.float32)


def ghi_mp3(am: np.ndarray, p: Path, sr: int = SR, bit_rate: int = 64000) -> None:
    p.parent.mkdir(parents=True, exist_ok=True)
    tam = p.with_suffix(".tmp.mp3")
    with av.open(str(tam), "w", format="mp3") as f:
        s = f.add_stream("mp3", rate=sr, layout="mono")
        s.bit_rate = bit_rate
        fr = av.AudioFrame.from_ndarray(np.ascontiguousarray(am, np.float32).reshape(1, -1), format="flt", layout="mono")
        fr.sample_rate = sr
        for pk in s.encode(fr):
            f.mux(pk)
        for pk in s.encode(None):
            f.mux(pk)
    tam.replace(p)


_DO = {}


def do_to(am: np.ndarray, sr: int = SR) -> float:
    m = _DO.setdefault(sr, pyln.Meter(sr))
    x = am if len(am) >= sr // 2 else np.concatenate([am, np.zeros(sr // 2 - len(am), np.float32)])
    return float(m.integrated_loudness(x.astype(np.float64)))


def chan_dinh(am: np.ndarray, sr: int = SR) -> np.ndarray:
    """Bộ chặn đỉnh nhìn trước 5 ms: chỉ hạ đúng chỗ đỉnh vượt -1 dBFS, nhả dần 60 ms, không cắt méo."""
    from scipy.ndimage import maximum_filter1d, uniform_filter1d
    w = max(1, int(sr * 0.005))
    bao = maximum_filter1d(np.abs(am), size=2 * w + 1)
    gain = np.minimum(1.0, DINH / np.maximum(bao, 1e-9))
    gain = np.minimum(gain, uniform_filter1d(gain, size=int(sr * 0.06)))
    return np.clip(am * gain, -DINH, DINH)


def can_to(am: np.ndarray, sr: int = SR) -> np.ndarray:
    """Về -16 LUFS cho to rõ; đỉnh vượt -1 dBFS thì chặn cục bộ thay vì hạ nhỏ cả file."""
    for _ in range(2):  # chặn đỉnh làm nhỏ đi chút ít → đo lại, bù một lần
        l = do_to(am, sr)
        if not np.isfinite(l):
            break
        am = chan_dinh(am * (10 ** ((LUFS - l) / 20)), sr)
    return am.astype(np.float32)


def rut_lang(am: np.ndarray, sr: int = SR, toi_da: float = 0.6, con: float = 0.45) -> np.ndarray:
    """Khoảng lặng giữa câu dài hơn 0,6 giây rút còn 0,45 giây (giọng đọc đôi khi ngắt 1,5 giây ở dấu phẩy)."""
    if not len(am):
        return am
    nguong = float(np.abs(am).max()) * 0.02
    khung = 240
    n = len(am) // khung
    lang = np.abs(am[: n * khung]).reshape(n, khung).max(axis=1) < nguong
    giu, i = [], 0
    while i < n:
        j = i
        while j < n and lang[j] == lang[i]:
            j += 1
        a, b = i * khung, j * khung
        if lang[i] and (b - a) > toi_da * sr and 0 < i and j < n:
            giua = (a + b) // 2
            nua = int(con * sr / 2)
            giu.append(am[a:a + nua])
            giu.append(am[b - nua:b])
        else:
            giu.append(am[a:b])
        i = j
    giu.append(am[n * khung:])
    return np.concatenate(giu)


def gon(am: np.ndarray, sr: int = SR) -> np.ndarray:
    """Cắt khoảng lặng hai đầu, chừa 0,08 giây trước và 0,25 giây sau để không nghe cụt."""
    nguong = max(0.01, float(np.abs(am).max()) * 0.02) if len(am) else 0.01
    co = np.flatnonzero(np.abs(am) > nguong)
    if not len(co):
        return am
    return am[max(0, co[0] - int(sr * 0.08)): co[-1] + int(sr * 0.25)]


async def tts(text: str, voice: str, rate: str) -> np.ndarray:
    for lan in range(7):
        try:
            b = bytearray()
            async for c in edge_tts.Communicate(text, GIONG[voice], rate=rate, pitch=CAO_DO.get(voice, "+0Hz")).stream():
                if c["type"] == "audio":
                    b += c["data"]
            if not b:
                raise RuntimeError("không nhận được âm thanh")
            return giai(bytes(b))
        except Exception as e:  # mạng chập chờn / dịch vụ chặn tạm: chờ lâu dần rồi thử lại
            cho = [2, 5, 15, 30, 60, 120, 180][lan]
            log(f"  edge-tts lỗi ({type(e).__name__}: {str(e)[:80]}), thử lại sau {cho}s")
            await asyncio.sleep(cho)
    raise RuntimeError(f"edge-tts không đọc được: {text[:30]}")


def toc_goc(v: dict, giong: str) -> int:
    if v["kind"] == "tu":
        return TOC_GOC["tu"]
    return TOC_GOC["cau_nu" if giong in NU_GIONG else "cau_nam"]


def cach_tiep(v: dict, cu: dict) -> dict:
    """Cách sinh lần sau dựa vào lỗi lần trước: quá nhanh → chậm lại; kéo dài → nhanh lên;
    máy nghe sai âm → ép chữ đồng âm đúng ở chỗ sai, rồi đổi tốc độ."""
    loi = " ".join(cu.get("loi", []))
    lech = cu.get("lech", 0)
    ep = bool(cu.get("ep"))
    if "tốc độ" in loi:
        tdo = float(re.search(r"tốc độ ([\d.]+)", loi).group(1))
        lech += -10 if tdo > 3.6 else 10
    elif "thời lượng" in loi:
        tg = float(re.search(r"thời lượng ([\d.]+)", loi).group(1))
        lech += 10 if tg > 0.5 else -10
    elif "nghe ra" in loi or "thanh" in loi:
        if not ep:
            ep = True
        else:
            lech += -10 if lech >= 0 else 15
    return {"lech": lech, "ep": ep}


async def sinh(v: dict, lech: int = 0, ep: set[int] | None = None) -> dict:
    doan, gui = [], None
    luot = chia_nguoi(bo_ngoac(doc_so(v["text"])), v["voice"])
    for chu, giong in luot:
        gui = van_ban_doc(chu, v.get("pinyin") if len(luot) == 1 else None, ep)
        toc = max(-50, min(20, toc_goc(v, giong) + lech))
        doan += [gon(await tts(gui, giong, f"{toc:+d}%")), np.zeros(int(SR * 0.3), np.float32)]
    am = can_to(rut_lang(np.concatenate(doan[:-1])))
    ghi_mp3(am, THU_MUC / v["out"])
    return {"gui": gui if len(luot) == 1 else None, "toc": toc_goc(v, v["voice"]) + lech}


# ------------------------------------------------------------------ kiểm

_ASR: dict[bool, object] = {}


def asr(dai: bool = False):
    """Paraformer-zh; đoạn dài (bài đọc) thêm VAD tách câu, đoạn ngắn thì không (VAD hay cắt mất từ đơn)."""
    if dai not in _ASR:
        from funasr import AutoModel
        _ASR[dai] = AutoModel(model="paraformer-zh", vad_model="fsmn-vad" if dai else None,
                              disable_update=True, log_level="ERROR")
    return _ASR[dai]


def so_sang_chu(s: str) -> str:
    def nguyen(n: int) -> str:
        if n == 0:
            return "零"
        dv = [(1000, "千"), (100, "百"), (10, "十")]
        ra, con, co_so = "", n, False
        for g, t in dv:
            q, con = divmod(con, g)
            if q:
                ra += ("" if g == 10 and q == 1 and not ra else SO[str(q)]) + t
                co_so = True
            elif co_so and con:
                ra += "零" if not ra.endswith("零") else ""
        return ra + (SO[str(con)] if con else "")

    s = re.sub(r"(\d{4})(?=年)", lambda m: "".join(SO[c] for c in m.group(1)), s)  # năm đọc từng số
    s = re.sub(r"(\d+)[.:：](\d+)", lambda m: f"{m.group(1)}点{m.group(2)}", s)
    s = re.sub(r"\d{5,}", lambda m: "".join(SO[c] for c in m.group()), s)
    s = re.sub(r"\d+", lambda m: nguyen(int(m.group())) if int(m.group()) < 10000 else "".join(SO[c] for c in m.group()), s)
    return s.replace("两", "二").replace("幺", "一")


def am_khong_dau(s: str) -> list[str]:
    return lazy_pinyin("".join(chu_han(so_sang_chu(bo_ngoac(doc_so(s))))))


def am_so_sanh(s: str) -> list[str]:
    """Âm không dấu để so với máy nghe: bỏ 儿 nhi hoá (máy hay ghi 这儿 thành 这) và coi 您 = 你
    (máy hay lẫn nín/nǐ, còn giọng đọc luôn đọc đúng 您)."""
    t = so_sang_chu(bo_ngoac(doc_so(s)))
    # Trợ từ cuối câu (啊 呀 呢 吧 嘛 哦) máy hay nghe sót hoặc đổi qua lại: bỏ trước khi so.
    t = re.sub(r"[啊呀呢吧嘛哦](?=[\s，。！？!?,.；;：:]*($|[，。！？!?,.]))", "", t)
    chu = chu_han(t)
    chu = [c for i, c in enumerate(chu) if not (c == "儿" and i > 0)]
    # 外边/外面, 前边/前面…: máy luôn ghi dạng 面 quen hơn.
    chu = ["边" if c == "面" and i > 0 and chu[i - 1] in "外前后里上下左右东西南北" else c for i, c in enumerate(chu)]
    doi = {"nin": "ni", "di": "de", "dei": "de"}  # 您/你; 的/地/得 trợ từ đọc de, máy luôn ghi 的
    # 的 cũng phải đổi: máy ghi "打的" thì pypinyin đọc dǎdī (đi taxi)
    return [doi.get(a, a) if not (a in ("di", "dei") and c not in "地得的") else a
            for c, a in zip(chu, lazy_pinyin("".join(chu)))]


def so_am_tiet(v: dict) -> float:
    """Số âm tiết để đo tốc độ: 儿 nhi hoá không thành âm tiết riêng (那儿 = nàr), âm tiết thanh nhẹ
    (那里 nà li, 东西 dōngxi) vốn ngắn nên tính nửa — không thì từ có thanh nhẹ bị báo đọc quá nhanh."""
    chu = chu_han(v["text"])
    n = len(am_khong_dau(v["text"])) - sum(1 for i, c in enumerate(chu) if c == "儿" and i > 0)
    sach = am_sach(v["text"], v.get("pinyin"))
    if sach:
        n -= 0.5 * sum(1 for c, a in zip(chu, sach) if a.endswith("5") and c != "儿")
    return max(1.0, n)


_DAN: dict[str, np.ndarray] = {}


async def cau_dan(voice: str) -> np.ndarray:
    if voice not in _DAN:
        _DAN[voice] = giai_16k(gon(await tts("我说", "nu" if voice in NU_GIONG else "nam", "-10%")))
    return _DAN[voice]


def giai_16k(am24: np.ndarray) -> np.ndarray:
    return np.interp(np.arange(0, len(am24), 1.5), np.arange(len(am24)), am24).astype(np.float32)


def cao_do(am: np.ndarray) -> np.ndarray:
    import parselmouth
    p = parselmouth.Sound(am.astype(np.float64), SR).to_pitch_ac(
        time_step=0.01, pitch_floor=70, pitch_ceiling=400, octave_jump_cost=0.8, voicing_threshold=0.5)
    f0 = p.selected_array["frequency"]
    v = f0[f0 > 0]
    if len(v) < 8:
        return np.zeros(0)
    st = 12 * np.log2(v / np.median(v))
    st = st[np.abs(st) < 7]  # bỏ khung nhảy quãng tám
    if len(st) >= 5:
        st = np.convolve(st, np.ones(3) / 3, mode="valid")
    return st


def xet_thanh(am: np.ndarray, thanh: str) -> str | None:
    """Chỉ xét 60% giữa phần có tiếng: đầu âm (phụ âm) và đuôi giọng hạ tự nhiên cuối từ làm sai hướng."""
    st = cao_do(am)
    if len(st) < 10:
        return None
    st = st[len(st) // 5: len(st) - len(st) // 5]
    q = max(2, len(st) // 3)
    dau, cuoi = float(st[:q].mean()), float(st[-q:].mean())
    if thanh == "4" and cuoi - dau > 1.0:
        return f"thanh 4 mà giọng đi lên ({dau:+.1f}→{cuoi:+.1f})"
    if thanh == "2" and cuoi - dau < -1.0:
        return f"thanh 2 mà giọng đi xuống ({dau:+.1f}→{cuoi:+.1f})"
    if thanh == "1" and abs(cuoi - dau) > 4.0:
        return f"thanh 1 mà giọng không ngang ({dau:+.1f}→{cuoi:+.1f})"
    return None


async def kiem(v: dict) -> dict:
    p = THU_MUC / v["out"]
    loi: list[str] = []
    try:
        am = giai(p)
    except Exception as e:
        return {"dat": False, "loi": [f"không giải mã được: {e}"], "sai_vi_tri": []}
    if len(am) < SR * 0.15 or float(np.abs(am).max()) < 0.05:
        return {"dat": False, "loi": ["file câm hoặc quá ngắn"], "sai_vi_tri": []}
    l = do_to(am)
    # Hạ đỉnh để không vỡ tiếng có thể làm file nhỏ hơn -16 LUFS một chút; chỉ báo khi lệch hẳn.
    if not LUFS - 3 <= l <= LUFS + 1.5:
        loi.append(f"độ to {l:.1f} LUFS")
    # Thời lượng phần có tiếng
    nguong = float(np.abs(am).max()) * 0.05
    co = np.flatnonzero(np.abs(am) > nguong)
    dai = (co[-1] - co[0]) / SR if len(co) else 0
    n = so_am_tiet(v)
    if v["kind"] == "tu":
        if not 0.15 <= dai / n <= 0.8:
            loi.append(f"thời lượng {dai / n:.2f} giây/âm tiết")
    elif not 1.8 <= n / max(dai, 0.1) <= 5.5:
        loi.append(f"tốc độ {n / max(dai, 0.1):.1f} âm tiết/giây")
    # Khoảng lặng dài giữa chừng (lỗi dịch vụ) — bỏ qua chỗ nghỉ 0,3 giây giữa hai người nói
    khung = np.abs(am[: len(am) // 240 * 240]).reshape(-1, 240).max(axis=1) < nguong
    dai_lang, cur = 0, 0
    for x in khung[int(0.1 * SR / 240): -int(0.3 * SR / 240) or None]:
        cur = cur + 1 if x else 0
        dai_lang = max(dai_lang, cur)
    if dai_lang * 240 / SR > 1.2:
        loi.append(f"khoảng lặng {dai_lang * 240 / SR:.1f} giây")
    # Nghe lại
    am16 = giai_16k(am)
    dem = np.zeros(4800, np.float32)
    mong = am_so_sanh(v["text"])
    cho_phep = 0 if len(mong) <= 10 else 1 if len(mong) <= 40 else int(len(mong) * 0.15)

    def nghe_thu(vao: np.ndarray, cat_dan: bool) -> tuple[str, list[int], bool]:
        kq_asr = asr(len(vao) > 16000 * 20).generate(input=vao, disable_pbar=True)
        nghe = "".join(x.get("text", "") for x in kq_asr).replace(" ", "")
        duoc = am_so_sanh(nghe)
        if cat_dan:
            duoc = duoc[-len(mong):] if len(duoc) >= len(mong) else duoc
        sm = SequenceMatcher(None, mong, duoc, autojunk=False)
        trung = sum(b.size for b in sm.get_matching_blocks())
        sai = sorted({i for tag, i1, i2, _, _ in sm.get_opcodes() if tag != "equal" for i in range(i1, i2)})
        return nghe, sai, len(mong) - trung <= cho_phep and len(duoc) - trung <= cho_phep + 1

    if v["kind"] == "tu":
        # Từ đơn: nghe sau câu dẫn 我说 (đỡ đoán thành chữ Latin), rồi nghe riêng — câu dẫn đôi khi
        # kéo mô hình ngôn ngữ sang cụm quen (我说行). Khớp một trong hai là đạt.
        dan = await cau_dan(v["voice"])
        nghe, sai_vi_tri, khop = nghe_thu(np.concatenate([dem, dan, np.zeros(2400, np.float32), am16, dem]), True)
        if not khop:
            nghe2, sai2, khop = nghe_thu(np.concatenate([dem, am16, dem]), False)
            if khop:
                nghe, sai_vi_tri = nghe2, sai2
            else:
                nghe = f"{nghe} / {nghe2}"
    else:
        nghe, sai_vi_tri, khop = nghe_thu(np.concatenate([dem, am16, dem]), False)
    if not khop:
        loi.append(f"nghe ra «{nghe}»")
    # Thanh điệu từ một chữ
    sach = am_sach(v["text"], v.get("pinyin"))
    if v["kind"] == "tu" and sach and len(sach) == 1 and sach[0][-1] in "124":
        e = xet_thanh(am, sach[0][-1])
        if e:
            loi.append(e)
    return {"dat": not loi, "loi": loi, "nghe": nghe, "sai_vi_tri": sai_vi_tri,
            "lufs": round(l, 1), "giay": round(len(am) / SR, 2)}


# ------------------------------------------------------------------ chạy

def chu_ky(v: dict) -> str:
    return hashlib.sha1(json.dumps([REV, v["text"], v.get("pinyin"), v["voice"], v["kind"]],
                                   ensure_ascii=False).encode()).hexdigest()[:12]


def sha(p: Path) -> str:
    return hashlib.sha256(p.read_bytes()).hexdigest()


KY_THUAT = ("độ to", "thời lượng", "tốc độ", "khoảng lặng", "file câm", "không giải mã")


def trang_thai(v: dict, k: dict) -> str:
    """dat · loi (sinh lại: lỗi kỹ thuật, hoặc câu/từ ≥ 2 âm tiết máy nghe sai) ·
    nghi (chữ một âm tiết máy nghe nhầm / nghi thanh: nhận dạng thiếu ngữ cảnh, sinh lại không đổi
    được kết quả — để người nghe xác nhận)."""
    if k["dat"]:
        return "dat"
    if any(m.startswith(KY_THUAT) for m in k["loi"]):
        return "loi"
    # Từ đứng riêng máy hay nghe sang từ quen (变化 → 电话); chỉ câu nghe sai mới sinh lại.
    if any(m.startswith("nghe ra") for m in k["loi"]) and v["kind"] == "cau":
        return "loi"
    return "nghi"


async def lam_dot(dot: list[dict], kq: dict, kiem_lai: bool) -> None:
    sem = asyncio.Semaphore(6)

    async def mot(v: dict, lech: int, ep) -> None:
        async with sem:
            kq[v["out"]] = {**kq.get(v["out"], {}), **(await sinh(v, lech, ep)), "ck": chu_ky(v)}

    can = [v for v in dot if not kiem_lai or not (THU_MUC / v["out"]).exists()]
    for v in can:
        kq[v["out"]] = {"ck": chu_ky(v), "lech": 0, "ep": False}
    await asyncio.gather(*(mot(v, 0, None) for v in can))
    for v in dot:
        kq.setdefault(v["out"], {"ck": chu_ky(v), "lech": 0, "ep": False})
    chua = list(dot)
    for lan in range(SO_LAN):
        hong = []
        for v in chua:
            k = await kiem(v)
            k["lan"] = lan + 1
            k["trang_thai"] = trang_thai(v, k)
            kq[v["out"]].update(k)
            if k["trang_thai"] == "loi":
                hong.append(v)
        if not hong or lan + 1 >= SO_LAN:
            for v in hong:
                if not any(m.startswith(KY_THUAT) for m in kq[v["out"]]["loi"]):
                    kq[v["out"]]["trang_thai"] = "nghi"
            break
        for v in hong:
            kq[v["out"]].update(cach_tiep(v, kq[v["out"]]))
            log(f"  sinh lại ({lan + 2}/{SO_LAN}) {v['text'][:24]} [{v['voice']}]: {'; '.join(kq[v['out']]['loi'])}"
                f" → tốc độ {kq[v['out']]['lech']:+d}%{', ép chữ đồng âm' if kq[v['out']]['ep'] else ''}")
        await asyncio.gather(*(mot(v, kq[v["out"]]["lech"],
                                   set(kq[v["out"]].get("sai_vi_tri", [])) if kq[v["out"]]["ep"] else None) for v in hong))
        chua = hong
    for v in dot:
        kq[v["out"]]["sha"] = sha(THU_MUC / v["out"])


async def main() -> None:
    viec = json.loads(F_VIEC.read_text(encoding="utf-8"))
    kq = json.loads(F_KQ.read_text(encoding="utf-8")) if F_KQ.exists() else {}
    kiem_lai = "--kiem-lai" in sys.argv or "--kiem-chua-dat" in sys.argv
    if "--kiem-chua-dat" in sys.argv:  # đổi luật so: kiểm lại file chưa đạt, chỉ sinh lại file còn lỗi
        con = [v for v in viec if (THU_MUC / v["out"]).exists() and kq.get(v["out"], {}).get("trang_thai") != "dat"]
    elif kiem_lai:
        con = [v for v in viec if (THU_MUC / v["out"]).exists()]
    elif "--xet-lai" in sys.argv or "--xet-lai-het" in sys.argv:
        def doi_chu(v: dict) -> bool:
            k = kq.get(v["out"], {})
            luot = chia_nguoi(bo_ngoac(doc_so(v["text"])), v["voice"])
            return (len(luot) == 1 and not k.get("ep") and k.get("gui") is not None
                    and van_ban_doc(luot[0][0], v.get("pinyin")) != k["gui"])
        bo_qua = ("dat",) if "--xet-lai-het" in sys.argv else ("dat", "nghi")
        con = [v for v in viec if kq.get(v["out"], {}).get("trang_thai") not in bo_qua or doi_chu(v)]
        for v in con:  # sinh lại từ đầu bằng cách gốc
            (THU_MUC / v["out"]).unlink(missing_ok=True)
        kiem_lai = False
    else:
        con = [v for v in viec if not (THU_MUC / v["out"]).exists() or kq.get(v["out"], {}).get("ck") != chu_ky(v)
               or "trang_thai" not in kq.get(v["out"], {})]
    log(f"{len(viec)} việc, {len(con)} cần {'kiểm lại' if kiem_lai else 'sinh'}")
    t0 = time.time()
    for i in range(0, len(con), 200):
        dot = con[i:i + 200]
        await lam_dot(dot, kq, kiem_lai)
        ghi_json(F_KQ, kq)
        xong = i + len(dot)
        d = Counter(kq[v["out"]]["trang_thai"] for v in dot)
        toc = xong / max(1, time.time() - t0)
        log(f"đợt {i // 200 + 1}: {d['dat']} đạt, {d['nghi']} cần người nghe, {d['loi']} lỗi · xong {xong}/{len(con)}"
            f" · còn ~{(len(con) - xong) / toc / 60:.0f} phút")
    tong = Counter(kq.get(v["out"], {}).get("trang_thai", "chua") for v in viec)
    log(f"Xong: {tong['dat']} đạt, {tong['nghi']} cần người nghe, {tong['loi']} còn lỗi sau {SO_LAN} lần sinh"
        f" (ra/doc_edge_kq.json)")


if __name__ == "__main__":
    asyncio.run(main())
