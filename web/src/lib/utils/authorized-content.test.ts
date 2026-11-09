import { describe, expect, it, vi } from 'vitest';
import { authorizedContent } from './authorized-content';

describe('Bài đã lưu vẫn phải hỏi lại quyền khi có mạng', () => {
  const req = new Request('https://zika.test/api/learn/lessons/1/flashcards');
  function cache(age = 0) {
    return { match: vi.fn(async () => new Response('bài cũ', { headers: { 'x-zika-cached-at': String(Date.now() - age) } })), put: vi.fn(async () => {}), delete: vi.fn(async () => true) };
  }
  it.each([401, 403, 404])('không trả bài cũ khi server từ chối %s', async (status) => {
    const c = cache();
    c.delete.mockRejectedValueOnce(new Error('Không xoá được'));
    const res = await authorizedContent(req, c, 10000, vi.fn(async () => new Response('ẩn', { status })));
    expect(res.status).toBe(status);
    expect(c.delete).toHaveBeenCalledWith(req);
    expect(c.match).not.toHaveBeenCalled();
  });
  it('có mạng dùng bản mới, kể cả khi kho đầy', async () => {
    const c = cache();
    c.put.mockRejectedValueOnce(new Error('Kho đầy'));
    expect(await (await authorizedContent(req, c, 10000, vi.fn(async () => new Response('bài mới')))).text()).toBe('bài mới');
  });
  it('mất mạng chỉ dùng bản còn hạn', async () => {
    const offline = vi.fn(async () => { throw new Error('Mất mạng'); });
    expect(await (await authorizedContent(req, cache(), 10000, offline)).text()).toBe('bài cũ');
    expect((await authorizedContent(req, cache(20000), 10000, offline)).type).toBe('error');
  });
  it('lỗi server không được mở lại nội dung trong cache', async () => {
    expect((await authorizedContent(req, cache(), 10000, vi.fn(async () => new Response('', { status: 500 })))).status).toBe(500);
  });
});
