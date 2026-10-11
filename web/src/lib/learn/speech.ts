// Đọc chữ Hán. Ưu tiên file mp3 do admin tải lên; không có thì dùng giọng của trình duyệt.
import { settings } from '$lib/stores/settings.svelte';

let audio: HTMLAudioElement | null = null;

/** Dừng mọi thứ đang phát (đổi thẻ, đổi câu). */
export function stopSpeak(): void {
  if (typeof speechSynthesis !== 'undefined') speechSynthesis.cancel();
  if (audio) {
    audio.pause();
    audio = null;
  }
}

/**
 * @param text chữ Hán cần đọc
 * @param url  đường dẫn mp3 có sẵn (nếu có)
 */
export async function speak(text: string, url?: string | null): Promise<void> {
  if (typeof window === 'undefined') return;
  stopSpeak();

  if (url) {
    try {
      audio = new Audio(url);
      audio.playbackRate = settings.value.rate || 1;
      await audio.play();
      return;
    } catch {
      // Không phát được file thì rơi xuống giọng trình duyệt.
    }
  }

  if (typeof speechSynthesis === 'undefined' || text.trim() === '') return;
  const u = new SpeechSynthesisUtterance(text);
  u.lang = 'zh-CN';
  u.rate = settings.value.rate || 1;

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
