"""Tổng hợp số liệu + kết quả kiểm tra của cả 3 quyển → ra/BAO-CAO.md.

  python bao_cao.py

Đọc bai/*.json, ra/*.json, ra/doi_chieu.json (web ↔ bản chép), ra/doi_chieu_sach.json (bản chép ↔ lớp chữ PDF).
Chạy sau doi_chieu.py all và doi_chieu_sach.py all.
"""
import json

from chung import THU_MUC, file_bai, so_bai


def doc(p):
    f = THU_MUC / p
    return json.loads(f.read_text(encoding="utf-8")) if f.exists() else {}


dc_web = doc("ra/doi_chieu.json")
dc_sach = doc("ra/doi_chieu_sach.json")
dong = ["# Báo cáo nạp giáo trình Hanu HSK 1–3 vào Zìkǎ", ""]
tong = {k: 0 for k in ("bai", "tu", "rieng", "bk", "cau", "np", "vd", "tudich", "bt", "kt", "lv", "loai", "loi")}
loi_sach, chua_khop_web, can_soat = [], [], []
for cap in (1, 2, 3):
    dong += [f"## HSK {cap}", "", "| Bài | Tên | Từ | Bài khoá (câu) | Ngữ pháp (ví dụ) | Bài tập | Kiểm tra | Chữ viết | Web khớp | Khớp chữ sách |",
             "|---|---|---|---|---|---|---|---|---|---|"]
    for b in range(1, so_bai(cap) + 1):
        if not file_bai(cap, b).exists():
            dong.append(f"| {b} | (chưa chép) |  |  |  |  |  |  |  |  |")
            continue
        d = json.loads(file_bai(cap, b).read_text(encoding="utf-8"))
        p = doc(f"ra/hsk{cap}/bai{b:02d}.json")
        ten = f"HSK {cap} · Bài {b}"
        n_cau = sum(len(k["cau"]) for k in d["bai_khoa"])
        n_vd = sum(len(g.get("vi_du", [])) for g in d["ngu_phap"])
        n_tudich = sum(1 for g in d["ngu_phap"] for v in g.get("vi_du", []) if v.get("tu_dich"))
        w = dc_web.get(ten)
        s = dc_sach.get(ten, {})
        web = "✓" if w == [] else ("—" if w is None else f"{len(w)} lệch")
        sach = f"{s['khop']}/{s['tong']}" if s else "—"
        dong.append(f"| {b} | {d['ten_vi']} | {len(d['tu_moi'])}+{len(d['ten_rieng'])} | {len(d['bai_khoa'])} ({n_cau}) | "
                    f"{len(d['ngu_phap'])} ({n_vd}) | {len(p.get('bt', []))} | {len(p.get('kt', []))} | {len(p.get('writing', []))} | {web} | {sach} |")
        tong["bai"] += 1
        tong["tu"] += len(d["tu_moi"])
        tong["rieng"] += len(d["ten_rieng"])
        tong["bk"] += len(d["bai_khoa"])
        tong["cau"] += n_cau
        tong["np"] += len(d["ngu_phap"])
        tong["vd"] += n_vd
        tong["tudich"] += n_tudich
        tong["bt"] += len(p.get("bt", []))
        tong["kt"] += len(p.get("kt", []))
        tong["lv"] += len(p.get("writing", []))
        tong["loai"] += len(d.get("loai_tru", []))
        tong["loi"] += len(d["loi_sach"])
        loi_sach += [f"- **{ten}** · {x['o_dau']}: sách in “{x['sach_in']}” → “{x['sua_thanh']}”" + (f" — {x['ly_do']}" if x.get("ly_do") else "")
                     for x in d["loi_sach"]]
        if w:
            chua_khop_web += [f"- {ten}: {x}" for x in w]
        can_soat += [f"- {ten} · {x['vi_tri']}: “{x['chuoi'][:60]}” (phủ {x['do_phu']})" for x in s.get("can_soat", [])]
    dong.append("")

dong[1:1] = ["", f"Tổng: **{tong['bai']} bài**, {tong['tu']} từ mới + {tong['rieng']} tên riêng, {tong['bk']} bài khoá "
             f"({tong['cau']} câu/đoạn), {tong['np']} điểm ngữ pháp ({tong['vd']} ví dụ, trong đó {tong['tudich']} câu sách không in "
             f"nghĩa nên đã tự dịch), {tong['bt']} câu Bài tập, {tong['kt']} câu Kiểm tra, {tong['lv']} lượt chữ Luyện viết. "
             f"{tong['loai']} câu hỏi bị loại vì có hơn một đáp án đúng; {tong['loi']} chỗ sách in sai đã sửa.", ""]
dong += ["## Chỗ sách in sai đã sửa khi chép", ""] + (loi_sach or ["(không có)"]) + [""]
dong += ["## Web ↔ bản chép: chỗ còn lệch", ""] + (chua_khop_web or ["Không có — mọi bài đã nạp đọc lại từ web đều khớp bản chép."]) + [""]
dong += ["## Bản chép ↔ chữ trong PDF: chuỗi cần nhìn lại ảnh", "",
         "HSK 1 so khớp tuyệt đối với chữ thật trong PDF; HSK 2–3 so với lớp chữ OCR nên đây là danh sách nghi vấn, không phải lỗi.", ""]
dong += can_soat or ["(không có)"]
(THU_MUC / "ra" / "BAO-CAO.md").write_text("\n".join(dong) + "\n", encoding="utf-8")
print(dong[2])
print(f"→ {THU_MUC / 'ra' / 'BAO-CAO.md'}")
