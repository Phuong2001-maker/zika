import { sha1 } from '$lib/utils/sha1';

/** Cùng chữ có thể khác âm: 还 hái/huán phải có hai file riêng. */
export function ttsKey(text: string, reading?: string | null): string {
  const py = reading?.normalize('NFC').replace(/\s+/gu, '').toLowerCase();
  return sha1(text.trim() + (py ? `\n${py}` : ''));
}
