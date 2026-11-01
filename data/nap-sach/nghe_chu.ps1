# Nhận dạng tiếng Trung trong một file WAV bằng bộ nhận dạng zh-CN có sẵn của Windows (SAPI).
# Dùng để biết file nghe nói đoạn nào của bài — không cần chính xác từng chữ.
#   powershell -ExecutionPolicy Bypass -File nghe_chu.ps1 <file.wav>
param([Parameter(Mandatory = $true)][string]$Wav)
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
Add-Type -AssemblyName System.Speech
$ci = New-Object System.Globalization.CultureInfo("zh-CN")
$r = New-Object System.Speech.Recognition.SpeechRecognitionEngine($ci)
$r.LoadGrammar((New-Object System.Speech.Recognition.DictationGrammar))
$r.SetInputToWaveFile($Wav)
$ra = New-Object System.Collections.Generic.List[string]
while ($true) {
    try { $kq = $r.Recognize() } catch { break }
    if ($null -eq $kq) { break }
    $ra.Add($kq.Text)
}
$r.Dispose()
($ra -join " ")
