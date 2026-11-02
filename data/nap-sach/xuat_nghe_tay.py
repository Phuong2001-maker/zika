"""Xuất danh sách cho người biết tiếng Trung nghe xác nhận → ra/nghe-tay-<ngày>.zip (mở index.html).

  python xuat_nghe_tay.py

Gồm: file doc_edge.py đánh dấu "nghi" (chữ đứng riêng máy nghe nhầm, nghi thanh), câu có chỗ không
ép được cách đọc (NGHE_TAY: 得 děi, 不得了 liǎo), và 3% file đạt chọn ngẫu nhiên theo từng giọng để
kiểm chéo. Người nghe ghi lại số thứ tự chỗ sai rồi gửi lại.
"""
from __future__ import annotations

import html
import json
import random
import time
import zipfile
from collections import defaultdict

from chung import THU_MUC
from doc_edge import NGHE_TAY

TEN_GIONG = {"nu": "nữ Xiaoxiao", "nam": "nam Yunxi", "xiaoxiao": "Xiaoxiao (nữ)", "xiaoyi": "Xiaoyi (nữ trẻ)",
             "yunxi": "Yunxi (nam trẻ)", "yunjian": "Yunjian (nam trưởng thành)", "yunyang": "Yunyang (nam lớn tuổi)",
             "yunxia": "Yunxia (bé trai)"}

if __name__ == "__main__":
    viec = {v["out"]: v for v in json.loads((THU_MUC / "ra" / "doc_edge.json").read_text(encoding="utf-8"))}
    kq = json.loads((THU_MUC / "ra" / "doc_edge_kq.json").read_text(encoding="utf-8"))
    nhom: dict[str, list[tuple[dict, dict]]] = defaultdict(list)
    for o, k in kq.items():
        v = viec.get(o)
        if not v or not (THU_MUC / o).exists():
            continue
        if k.get("trang_thai") == "nghi":
            nhom["1. Máy nghe chưa chắc"].append((v, k))
        elif any(c in v["text"] and (v.get("pinyin") or "").lower().find(p) >= 0 for c, p in NGHE_TAY.items()):
            nhom["2. Cách đọc không ép được (得 děi, 不得了)"].append((v, k))
    random.seed(20261103)
    theo_giong = defaultdict(list)
    for o, k in kq.items():
        if k.get("trang_thai") == "dat" and o in viec:
            theo_giong[viec[o]["voice"]].append((viec[o], k))
    for g, ds in sorted(theo_giong.items()):
        nhom["3. Chọn ngẫu nhiên 3% file đạt"] += random.sample(ds, min(len(ds), max(3, len(ds) * 3 // 100)))
    ten = f"nghe-tay-{time.strftime('%Y%m%d')}"
    zf = THU_MUC / "ra" / f"{ten}.zip"
    e = html.escape
    hang, so = [], 0
    with zipfile.ZipFile(zf, "w", zipfile.ZIP_STORED) as z:
        for tieu_de, ds in sorted(nhom.items()):
            hang.append(f"<h2>{e(tieu_de)} · {len(ds)} file</h2><div class='bang'><table>")
            for v, k in ds:
                so += 1
                ten_file = f"am/{so:04d}.mp3"
                z.write(THU_MUC / v["out"], f"{ten}/{ten_file}")
                ghi = "; ".join(k.get("loi") or []) if k.get("trang_thai") == "nghi" else ""
                hang.append(f"<tr><td class='so'>{so}</td><td><div class='zh'>{e(v['text'])}</div>"
                            f"<div class='py'>{e(v.get('pinyin') or '')}</div><div class='gc'>{e(TEN_GIONG.get(v['voice'], v['voice']))}"
                            f"{' · máy: ' + e(ghi) if ghi else ''}</div></td>"
                            f"<td><audio controls preload='none' src='{ten_file}'></audio></td></tr>")
            hang.append("</table></div>")
        trang = f"""<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nghe xác nhận giọng đọc · Zìkǎ</title><style>
:root {{ --bg:#f6f7f9; --card:#fff; --line:#e6e8ec; --text:#1d2129; --muted:#5f6570; --son:#c62a2f; }}
@media (prefers-color-scheme: dark) {{ :root {{ --bg:#111318; --card:#1a1d25; --line:#2c313d; --text:#f2f5fa; --muted:#9aa3b2; --son:#ff8a7f; }} }}
* {{ box-sizing:border-box; }} body {{ margin:0; background:var(--bg); color:var(--text); font:15px/1.5 system-ui,"Segoe UI",sans-serif; }}
main {{ max-width:960px; margin:0 auto; padding:24px 16px 48px; }} h1 {{ font-size:24px; margin:0 0 6px; }} h2 {{ font-size:18px; margin:28px 0 10px; }}
.note {{ background:var(--card); border:1px solid var(--line); border-radius:12px; padding:12px 16px; color:var(--muted); }}
.bang {{ background:var(--card); border:1px solid var(--line); border-radius:14px; padding:6px 14px; overflow-x:auto; }}
table {{ width:100%; border-collapse:collapse; }} td {{ padding:10px 6px; border-top:1px solid var(--line); vertical-align:middle; }}
tr:first-child td {{ border-top:0; }} .so {{ color:var(--muted); width:44px; }}
.zh {{ font-size:22px; font-family:"Noto Sans SC","Microsoft YaHei",sans-serif; }} .py {{ color:var(--son); }} .gc {{ color:var(--muted); font-size:13px; }}
audio {{ height:36px; max-width:260px; }}
</style></head><body><main><h1>Nghe xác nhận giọng đọc</h1>
<p class="note">Bấm ▶ nghe từng dòng. Dòng nào đọc sai âm, sai thanh điệu, hoặc nghe không tự nhiên thì ghi lại <b>số thứ tự</b>
kèm ghi chú ngắn rồi gửi lại. Phần 1 là chỗ máy nhận dạng chưa chắc (thường do chữ đứng riêng thiếu ngữ cảnh),
phần 2 là chữ đa âm không ép được, phần 3 là mẫu ngẫu nhiên để kiểm chéo.</p>
{''.join(hang)}</main></body></html>"""
        z.writestr(f"{ten}/index.html", trang)
    print(f"{zf.name}: {so} file · " + ", ".join(f"{k.split('.')[0]}: {len(v)}" for k, v in sorted(nhom.items())))
