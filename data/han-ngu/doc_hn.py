"""Sinh và tự kiểm giọng đọc còn thiếu cho giáo trình Hán ngữ, bằng đúng quy trình của data/nap-sach/doc_edge.py.

  python doc_hn.py

Dùng chuẩn và sổ kết quả chung với bộ HSK. Dừng giữa chừng thì chạy lại: file đã sinh và đã kiểm được bỏ qua.
"""
import asyncio

from chung_hn import THU_MUC
import doc_edge  # data/nap-sach/doc_edge.py

doc_edge.F_VIEC = THU_MUC / "ra" / "doc_edge_hn.json"
asyncio.run(doc_edge.main())
