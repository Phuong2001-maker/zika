"""Bảng tiến độ: bài nào đã chép, đã soát, đã nạp, đã đối chiếu khớp.

  python tien_do.py
"""
import json

from chung import THU_MUC, file_bai, so_bai

nap = json.loads((THU_MUC / "ra" / "nap.json").read_text(encoding="utf-8")) if (THU_MUC / "ra" / "nap.json").exists() else {}
dc = json.loads((THU_MUC / "ra" / "doi_chieu.json").read_text(encoding="utf-8")) if (THU_MUC / "ra" / "doi_chieu.json").exists() else {}
for cap in (1, 2, 3):
    dong = []
    for b in range(1, so_bai(cap) + 1):
        ky = "·"
        if file_bai(cap, b).exists():
            ky = "c"
            if (THU_MUC / "ra" / "soat" / f"hsk{cap}-bai{b:02d}.md").exists():
                ky = "s"
            if f"hsk{cap}-bai{b:02d}" in nap:
                ky = "n" if ky == "c" else "N"
            k = dc.get(f"HSK {cap} · Bài {b}")
            if k == []:
                ky = "✓" if ky in ("N", "s") else "v"
        dong.append(f"{b}{ky}")
    print(f"HSK {cap}: " + " ".join(dong))
print("· chưa chép · c đã chép · s đã soát · n/N đã nạp (chưa/đã soát) · v khớp web (chưa soát) · ✓ soát + khớp web")
