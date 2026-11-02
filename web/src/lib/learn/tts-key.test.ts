import { describe, expect, it } from 'vitest';
import { ttsKey } from './tts-key';

describe('file giọng đọc theo pinyin', () => {
  it('giữ đường dẫn cũ khi không chỉ định cách đọc', () => {
    expect(ttsKey(' 你好 ')).toBe('440ee0853ad1e99f962b63e459ef992d7c211722');
  });

  it('tách hai cách đọc của cùng chữ và khớp PHP/Python', () => {
    expect(ttsKey('还', 'hái')).toBe('298879273cfe12881e3a503a1f60cc51eb2e20e7');
    expect(ttsKey('还', 'huán')).toBe('3a544e1d1352203cd4d51b9d8f8914950e33635d');
    expect(ttsKey('头', 'tou')).not.toBe(ttsKey('头', 'tóu'));
    expect(ttsKey('道', 'dao')).not.toBe(ttsKey('道', 'dào'));
  });

  it('không phân biệt chữ hoa và khoảng trắng trong pinyin', () => {
    expect(ttsKey('你好', 'Nǐ Hǎo')).toBe('9e3be89eae672e91077bc98791367617fadeec06');
    expect(ttsKey('你好', ' nǐ\thǎo\n')).toBe(ttsKey('你好', 'nǐhǎo'));
    expect(ttsKey('女', 'nu\u0308\u030c')).toBe(ttsKey('女', 'nǚ'));
  });
});
