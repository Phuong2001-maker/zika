# Sinh giọng đọc mẫu Kokoro cho toàn bộ nội dung đang có trên web local — một lệnh làm hết.
#
#   cd D:\zika-devtools\Project\zika\data\nap-sach
#   powershell -ExecutionPolicy Bypass -File .\sinh-giong-doc.ps1
#
# Tuỳ chọn:
#   -TienTrinh 8      số tiến trình Kokoro chạy song song (máy 16 luồng: 8; máy yếu: 4)
#   -BoQuaLap         không lập lại danh sách, sinh tiếp ra/doc_mau_tat.json đang có
#   -BoQuaQuet        không chạy quet.py ở cuối (quét mất 10–15 phút)
#
# Cần trước: MariaDB + API + web local đang chạy (dev\CHAY-TAT-CA.cmd), vì bước lập danh sách
# và bước tải lên đều đi qua API. Dừng giữa chừng (Ctrl+C, tắt máy) thì chạy lại y lệnh cũ:
# file đã sinh / đã tải được bỏ qua.
param(
    [int]$TienTrinh = 8,
    [switch]$BoQuaLap,
    [switch]$BoQuaQuet
)
$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
$env:PYTHONUTF8 = '1'

$kokoro = Resolve-Path "$PSScriptRoot\..\..\..\kokoro-tts"
# venv trong kokoro-tts trỏ cứng đường dẫn cũ nên hỏng khi chuyển thư mục; dùng thẳng Python đi kèm.
$pyKokoro = Get-ChildItem "$kokoro\python" -Directory | Where-Object { Test-Path "$($_.FullName)\python.exe" } |
    Sort-Object Name -Descending | Select-Object -First 1 | ForEach-Object { "$($_.FullName)\python.exe" }
if (-not $pyKokoro) { throw "Không thấy python.exe trong $kokoro\python" }
$siteKokoro = "$kokoro\.venv\Lib\site-packages"

function Buoc($ten) { Write-Host "`n=== $ten ===" -ForegroundColor Cyan }

if (-not $BoQuaLap) {
    Buoc '1/5 Lập danh sách câu chữ cần đọc (từ, ví dụ, chữ viết, câu thoại, ngữ pháp, câu hỏi nghe)'
    python viec_doc.py tu web
    if ($LASTEXITCODE -ne 0) { throw 'Chưa lập xong danh sách giọng đọc.' }
}

Buoc "2/5 Sinh mp3 bằng Kokoro ($TienTrinh tiến trình) — lâu nhất, khoảng 100 file/phút"
$env:PYTHONPATH = "$siteKokoro;$PSScriptRoot"
$ps = 0..($TienTrinh - 1) | ForEach-Object {
    Start-Process -FilePath $pyKokoro -ArgumentList 'doc_mau.py', 'ra/doc_mau_tat.json', $_, $TienTrinh `
        -NoNewWindow -PassThru -RedirectStandardOutput "ra/doc_mau_$_.log" -RedirectStandardError "ra/doc_mau_$_.err"
}
$ps | Wait-Process
Get-ChildItem ra/doc_mau_*.log | ForEach-Object { Get-Content $_ -Tail 1 }
Remove-Item Env:PYTHONPATH   # Python thường không được thấy numpy của Kokoro (khác phiên bản → lỗi)

Buoc 'Sinh từ lật thẻ giọng nam Yunxi (cần mạng và pip install edge-tts av)'
python doc_tu_nam.py
if ($LASTEXITCODE -ne 0) { throw 'Sinh giọng nam Yunxi chưa xong; chưa tải lên web.' }

Buoc '3/5 Tải file lên web local (POST /admin/media/tts)'
python tai_doc.py
if ($LASTEXITCODE -ne 0) { throw 'Tải âm thanh chưa xong.' }

Buoc '4/5 Gắn âm thanh cho câu thoại, ghép lại bài khoá chưa có file thu thật'
python gan_thoai.py
if ($LASTEXITCODE -ne 0) { throw 'Gắn âm thanh hội thoại chưa xong.' }

if (-not $BoQuaQuet) {
    Buoc '5/5 Quét lỗi toàn bộ → cuối NHAT-KY-CAP-NHAT.md'
    python quet.py
    if ($LASTEXITCODE -ne 0) { throw 'Quét giọng đọc chưa xong.' }
}
Write-Host "`nXong. Đưa lên server: python xuat_sql.py rồi làm theo HUONG-DAN-SERVER.md" -ForegroundColor Green
