"""Nạp hàm so pinyin từ data/nap-sach/kiem_tra.py. Hai file cùng tên kiem_tra nên phải nạp theo đường dẫn."""
import importlib.util

from chung_hn import NAP_SACH

_spec = importlib.util.spec_from_file_location("kiem_tra_nap_sach", NAP_SACH / "kiem_tra.py")
_m = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(_m)
so_pinyin = _m.so_pinyin
