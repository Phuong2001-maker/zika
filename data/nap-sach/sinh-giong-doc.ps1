# Sinh giọng đọc mẫu (giọng Microsoft qua edge-tts) cho toàn bộ nội dung đang có trên web local — một lệnh làm hết.
#
#   cd D:\zika-devtools\Project\zika\data\nap-sach
#   powershell -ExecutionPolicy Bypass -File .\sinh-giong-doc.ps1
#
# Tuỳ chọn:
#   -BoQuaLap         không lập lại danh sách, sinh tiếp ra/doc_edge.json đang có
#   -BoQuaQuet        không chạy quet.py ở cuối (quét mất 10–15 phút)
#
# Cần trước: MariaDB + API + web local đang chạy (dev\CHAY-TAT-CA.cmd), mạng Internet (edge-tts), và
#   python -m pip install edge-tts av pyloudnorm praat-parselmouth funasr modelscope pypinyin scipy
# Dừng giữa chừng (Ctrl+C, tắt máy) thì chạy lại y lệnh cũ: file đã sinh và đã kiểm được bỏ qua.
param(
    [switch]$BoQuaLap,
    [switch]$BoQuaQuet
)
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
$env:PYTHONUTF8 = '1'

function Buoc($ten) { Write-Host "`n=== $ten ===" -ForegroundColor Cyan }

if (-not $BoQuaLap) {
    Buoc '1/5 Lập danh sách câu chữ cần đọc (từ, ví dụ, chữ viết, câu thoại, ngữ pháp, câu hỏi nghe)'
    python viec_doc.py tu web
    if ($LASTEXITCODE -ne 0) { throw 'Chưa lập xong danh sách giọng đọc.' }
}

Buoc '2/5 Sinh + kiểm từng đợt 200 file (FunASR nghe lại, đo độ to, tốc độ, thanh) — tự sinh lại file lỗi'
python doc_edge.py
if ($LASTEXITCODE -ne 0) { throw 'Sinh giọng đọc chưa xong.' }

Buoc '3/5 Tải file đã kiểm lên web local (POST /admin/media/tts)'
python tai_doc.py
if ($LASTEXITCODE -ne 0) { throw 'Tải âm thanh chưa xong.' }

Buoc '4/5 Gắn âm thanh cho câu thoại; bài khoá: giữ băng thật khớp sách, còn lại ghép giọng đọc mẫu'
python gan_thoai.py
if ($LASTEXITCODE -ne 0) { throw 'Gắn âm thanh hội thoại chưa xong.' }

if (-not $BoQuaQuet) {
    Buoc '5/5 Quét lỗi toàn bộ qua web (đúng bản đã kiểm) → cuối NHAT-KY-CAP-NHAT.md'
    python quet.py
    if ($LASTEXITCODE -ne 0) { throw 'Quét giọng đọc chưa xong.' }
}
Write-Host "`nXong. Đóng gói lên server: cd ..\..\web; npm run build; cd ..\data\nap-sach; python dong_goi_giong.py" -ForegroundColor Green
