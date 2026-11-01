import { describe, expect, it } from 'vitest';
import { sha1 } from './sha1';

describe('sha1', () => {
  it('khớp giá trị chuẩn', () => {
    expect(sha1('')).toBe('da39a3ee5e6b4b0d3255bfef95601890afd80709');
    expect(sha1('abc')).toBe('a9993e364706816aba3e25717850c26c9cd0d89d');
    expect(sha1('a'.repeat(1000))).toBe('291e9a6c66994949b57ba5e650361e98fc36b1ba');
  });

  it('tính trên UTF-8 như PHP sha1() và Python hashlib', () => {
    expect(sha1('你好')).toBe('440ee0853ad1e99f962b63e459ef992d7c211722');
  });
});
