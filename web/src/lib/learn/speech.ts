// Đọc chữ Hán. Thứ tự ưu tiên:
//  1. file mp3 gắn sẵn (bài khoá thu thật, câu thoại);
//  2. file đọc mẫu sinh sẵn (Yunxi cho từ lật thẻ, Kokoro cho các phần khác), băm cả pinyin khi có
//     (/uploads/tts/<giọng>/<2 ký tự đầu>/<sha1>.mp3) — mọi màn gọi speak(text) đều có giọng tự nhiên
//     mà API không phải trả đường dẫn cho từng từ, từng câu ví dụ, từng câu hỏi;
//  3. giọng có sẵn của trình duyệt khi chữ đó chưa có file đọc mẫu (nội dung mới soạn).
import { settings } from '$lib/stores/settings.svelte';
import { ttsKey } from './tts-key';

/** Đổi khi sinh lại toàn bộ file đọc mẫu, để trình duyệt không giữ bản cũ trong cache. */
const TTS_VERSION = 5; // 5: lật thẻ nam Yunxi, chậm lại bằng atempo giữ cao độ, khoá cả pinyin.

let audio: HTMLAudioElement | null = null;
/** Mỗi lần gọi speak() tăng lên; lượt cũ thấy số đổi thì thôi, không phát chồng hay rơi sang giọng khác. */
let luot = 0;
/** File đọc mẫu đã biết là chưa có, khỏi hỏi lại server mỗi lần bấm. */
const thieu = new Set<string>();

export type Giong = 'nu' | 'nam';

/** Đường dẫn file đọc mẫu của một câu chữ. */
export function ttsUrl(text: string, giong: Giong, reading?: string | null): string {
  const h = ttsKey(text, reading);
  return `/uploads/tts/${giong}/${h.slice(0, 2)}/${h}.mp3?v=${TTS_VERSION}`;
}

/** Dừng mọi thứ đang phát (đổi thẻ, đổi câu). */
export function stopSpeak(): void {
  if (typeof speechSynthesis !== 'undefined') speechSynthesis.cancel();
  if (audio) {
    audio.pause();
    audio = null;
  }
}

/** true = đã phát (hoặc bị lượt mới ngắt ngang: cũng dừng, không thử file khác). */
async function phat(url: string): Promise<boolean> {
  if (thieu.has(url)) return false;
  const a = new Audio(url);
  a.playbackRate = settings.value.rate || 1;
  audio = a;
  try {
    await a.play();
    return true;
  } catch (e) {
    const ten = e instanceof DOMException ? e.name : '';
    // AbortError: người học bấm câu khác khi file này đang tải — file vẫn tốt, chỉ là bị ngắt.
    if (ten === 'AbortError') return true;
    // NotAllowedError là trình duyệt chặn tự phát, file vẫn có: đừng đánh dấu thiếu.
    if (ten !== 'NotAllowedError') thieu.add(url);
    if (audio === a) audio = null;
    return false;
  }
}

/**
 * @param text chữ Hán cần đọc
 * @param url  đường dẫn mp3 có sẵn (nếu có)
 * @param reading pinyin của từ trên thẻ, phân biệt những chữ đa âm
 * @param voice giọng cố định theo màn; lật thẻ dùng giọng nam
 */
export async function speak(text: string, url?: string | null, reading?: string | null, voice?: Giong): Promise<void> {
  if (typeof window === 'undefined') return;
  stopSpeak();
  const toi = ++luot;

  const ds: string[] = url ? [url] : [];
  const chon = settings.value.voice;
  if ((voice || chon !== 'browser') && text.trim() !== '') {
    const dau: Giong = voice ?? (chon === 'yunxi' ? 'nam' : 'nu');
    ds.push(ttsUrl(text, dau, reading));
    if (!voice) ds.push(ttsUrl(text, dau === 'nu' ? 'nam' : 'nu', reading));
  }
  for (const u of ds) {
    if (await phat(u)) return;
    if (toi !== luot) return; // đã có lượt đọc mới
  }

  if (typeof speechSynthesis === 'undefined' || text.trim() === '') return;
  const u = new SpeechSynthesisUtterance(text);
  u.lang = 'zh-CN';
  // Giọng trình duyệt đọc nhanh hơn người thật, hạ xuống một chút cho người học.
  u.rate = (settings.value.rate || 1) * 0.85;

  // Chọn giọng tiếng Trung nếu máy có; không thì để trình duyệt tự quyết.
  const voices = speechSynthesis.getVoices();
  const zh = voices.find((v) => v.lang?.toLowerCase().startsWith('zh'));
  if (zh) u.voice = zh;

  speechSynthesis.speak(u);
}

/** Trình duyệt có đọc được tiếng Trung không (để ẩn nút nghe khi không có giọng). */
export function canSpeak(): boolean {
  return typeof window !== 'undefined' && typeof speechSynthesis !== 'undefined';
}
