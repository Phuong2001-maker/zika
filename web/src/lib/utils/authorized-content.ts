/** Có mạng phải hỏi lại quyền; chỉ dùng bài đã lưu khi không liên lạc được máy chủ. */
export async function authorizedContent(req: Request, cache: Pick<Cache, 'match' | 'put' | 'delete'>, ttl: number, fetcher: typeof fetch = fetch): Promise<Response> {
  let res: Response;
  try { res = await fetcher(req); }
  catch {
    const hit = await cache.match(req);
    const at = Number(hit?.headers.get('x-zika-cached-at') ?? 0);
    return hit && at > 0 && Date.now() - at < ttl ? hit : Response.error();
  }
  try {
    if (res.status === 200) {
      const copy = res.clone();
      const headers = new Headers(copy.headers);
      headers.set('x-zika-cached-at', String(Date.now()));
      await cache.put(req, new Response(copy.body, { status: copy.status, headers }));
    } else if ([401, 403, 404].includes(res.status)) await cache.delete(req);
  } catch { /* Kho đầy hoặc xoá thất bại không được làm mất phản hồi quyền từ server. */ }
  return res;
}
