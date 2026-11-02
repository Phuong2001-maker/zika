"""Sinh từ lật thẻ bằng Yunxi; cần mạng và `pip install edge-tts`.

  python doc_tu_nam.py  # pip install edge-tts av

Ghép 10 từ mỗi lượt yêu cầu, tách bằng mốc WordBoundary do dịch vụ trả về.
Không đoán chỗ cắt bằng khoảng lặng, không kéo tốc độ Kokoro xuống ngoài vùng đọc rõ.
"""
from __future__ import annotations

import asyncio
import hashlib
import io
import json
import sys
from fractions import Fraction

from chung import SACH_DIR, THU_MUC, ghi_json

sys.path.append(str(SACH_DIR / "kokoro-tts" / ".venv" / "Lib" / "site-packages"))
import edge_tts  # noqa: E402
import av  # noqa: E402
import numpy as np  # noqa: E402
import soundfile as sf  # noqa: E402


def chu_doc(v: dict) -> str:
    # Từ đứng riêng thiếu ngữ cảnh chọn âm. Dùng chữ đồng âm cùng thanh cho các âm dễ bị đoán sai;
    # nhãn thẻ và khoá file vẫn là chữ + pinyin gốc. Không đổi các từ ghép có ngữ cảnh đầy đủ.
    py = "".join(v["reading"].lower().split())
    return {("还", "huán"): "环", ("还", "hái"): "孩", ("长", "cháng"): "常",
            ("只", "zhī"): "枝", ("地", "de"): "的", ("粘人", "niánrén"): "黏人"}.get(
                (v["text"], py), v["text"])


def doc_cham(am: np.ndarray, sr: int, so_chu: int) -> np.ndarray:
    """Kéo thời lượng bằng atempo (giữ cao độ), không ép mô hình phát âm ở tốc độ quá thấp."""
    co_tieng = np.flatnonzero(np.abs(am) > 0.02)
    if len(co_tieng) == 0:
        raise ValueError("File câm")
    dai = (co_tieng[-1] - co_tieng[0] + 1) / sr
    dich = (0.48 if so_chu <= 2 else 0.43) * so_chu
    toc = min(1.0, dai / dich)
    if toc >= 0.97:
        return am
    graph = av.filter.Graph()
    src = graph.add_abuffer(sample_rate=sr, format="fltp", layout="mono", time_base=Fraction(1, sr))
    truoc = src
    # atempo giữ chất lượng tốt hơn khi mỗi tầng nằm trong [0,5; 1].
    while toc < 0.5:
        sau = graph.add("atempo", "0.5")
        truoc.link_to(sau)
        truoc = sau
        toc /= 0.5
    sau = graph.add("atempo", str(toc))
    sink = graph.add("abuffersink")
    truoc.link_to(sau)
    sau.link_to(sink)
    graph.configure()
    frame = av.AudioFrame.from_ndarray(am.reshape(1, -1), format="fltp", layout="mono")
    frame.sample_rate = sr
    frame.time_base = Fraction(1, sr)
    src.push(frame)
    src.push(None)
    ra = []
    while True:
        try:
            ra.append(sink.pull().to_ndarray().ravel())
        except (av.error.BlockingIOError, av.error.EOFError):
            break
    return np.concatenate(ra)


def cat_tu(viec: list[dict], raw: bytes, moc: list[dict]) -> None:
    audio, sr = sf.read(io.BytesIO(raw), dtype="float32")
    if audio.ndim != 1:
        raise ValueError("Giọng mẫu phải là mono")
    k = 0
    for v in viec:
        text = chu_doc(v)
        start = k
        nhan = ""
        while k < len(moc) and len(nhan) < len(text):
            nhan += moc[k]["text"]
            k += 1
        if nhan != text:
            raise ValueError(f"Mốc đọc không khớp {text}: {nhan}")
        dau = max(0, int((moc[start]["offset"] / 10_000_000 - 0.05) * sr))
        cuoi = min(len(audio), int(((moc[k - 1]["offset"] + moc[k - 1]["duration"]) / 10_000_000 + 0.08) * sr))
        am = audio[dau:cuoi]
        if len(am) < sr * 0.1 or np.abs(am).max() < 0.02:
            raise ValueError(f"Âm thanh rỗng/câm: {v['text']}")
        am = doc_cham(am, sr, len(v["text"]))
        out = THU_MUC / v["out"]
        out.parent.mkdir(parents=True, exist_ok=True)
        tam = out.with_suffix(".tmp.mp3")
        sf.write(tam, am, sr, format="MP3")
        tam.replace(out)
    if k != len(moc):
        raise ValueError("Còn mốc đọc thừa sau từ cuối")


async def main() -> None:
    ds = json.loads((THU_MUC / "ra/doc_mau_tat.json").read_text(encoding="utf-8"))
    viec = [v for v in ds if v.get("engine") == "yunxi" and not (THU_MUC / v["out"]).exists()]
    gioi_han = asyncio.Semaphore(2)
    dem = 0

    async def mot_nhom(nhom: list[dict]) -> None:
        nonlocal dem
        text = "。".join(chu_doc(v) for v in nhom) + "。"
        key = hashlib.sha1(("Yunxi:-20%:v1:" + text).encode("utf-8")).hexdigest()
        cache = THU_MUC / "ra/yunxi-batch"
        cache.mkdir(parents=True, exist_ok=True)
        f_am, f_moc = cache / (key + ".mp3"), cache / (key + ".json")
        async with gioi_han:
            for lan in range(3):
                try:
                    if f_am.exists() and f_moc.exists():
                        raw, moc = f_am.read_bytes(), json.loads(f_moc.read_text(encoding="utf-8"))
                    else:
                        chunks, moc = [], []
                        c = edge_tts.Communicate(text, "zh-CN-YunxiNeural", rate="-20%", boundary="WordBoundary",
                                                 connect_timeout=15, receive_timeout=45)
                        async for chunk in c.stream():
                            if chunk["type"] == "audio":
                                chunks.append(chunk["data"])
                            elif chunk["type"] == "WordBoundary":
                                moc.append(chunk)
                        raw = b"".join(chunks)
                        f_am.write_bytes(raw)
                        ghi_json(f_moc, moc)
                    cat_tu(nhom, raw, moc)
                    break
                except Exception:
                    if lan == 2:
                        raise
                    await asyncio.sleep(2 * (lan + 1))
            dem += len(nhom)
            print(f"Yunxi: {dem}/{len(viec)} từ", flush=True)

    await asyncio.gather(*(mot_nhom(viec[i:i + 10]) for i in range(0, len(viec), 10)))
    print(f"Xong {len(viec)} file giọng nam Yunxi", flush=True)


if __name__ == "__main__":
    asyncio.run(main())
