import { afterEach, describe, expect, it, vi } from 'vitest';

vi.mock('$lib/stores/settings.svelte', () => ({ settings: { value: { voice: 'xiaoxiao', rate: 1 } } }));
import { speak, ttsUrl } from './speech';

afterEach(() => vi.unstubAllGlobals());

describe('giọng đọc lật thẻ', () => {
  it('dùng giọng nam và đúng pinyin dù cài đặt chung là giọng nữ', async () => {
    const urls: string[] = [];
    vi.stubGlobal('window', {});
    vi.stubGlobal('speechSynthesis', undefined);
    vi.stubGlobal('Audio', class {
      playbackRate = 1;
      constructor(url: string) { urls.push(url); }
      play() { return Promise.resolve(); }
      pause() {}
    });
    await speak('还', null, 'huán', 'nam');
    expect(urls).toEqual([ttsUrl('还', 'nam', 'huán')]);
    expect(urls[0]).not.toBe(ttsUrl('还', 'nam', 'hái'));
  });

  it('không tự chuyển sang file nữ khi đã yêu cầu giọng nam', async () => {
    const urls: string[] = [];
    vi.stubGlobal('window', {});
    vi.stubGlobal('speechSynthesis', undefined);
    vi.stubGlobal('Audio', class {
      playbackRate = 1;
      constructor(url: string) { urls.push(url); }
      play() { return Promise.reject(new DOMException('Thiếu file', 'NotSupportedError')); }
      pause() {}
    });
    await speak('道', null, 'dào', 'nam');
    expect(urls).toEqual([ttsUrl('道', 'nam', 'dào')]);
  });
});
