<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api, ApiError } from '$lib/api/client';
  import { feedback, type FeedbackContext } from '$lib/stores/feedback.svelte';
  import { toast } from '$lib/stores/toast.svelte';
  import { cap } from '$lib/stores/cap.svelte';
  import { partBySlug, PARTS } from '$lib/parts';
  import { trangDaDoc } from '$lib/learn/sach';
  import Modal from './Modal.svelte';

  // Hộp Góp ý · báo lỗi (07 §1.3): mở từ bảng Chuyển nhanh ở mọi màn người học, tự kèm màn đang mở (phần, cấp, bài,
  // trang sách) để biên tập bấm "Mở để sửa" là tới đúng chỗ. Gắn một lần ở layout; Switcher chỉ hiện dòng Góp ý khi
  // `feedback.available`. Khu quản trị (/admin/feedback) có từ trước, đến 08/11/2026 người học mới gửi được.
  type Loai = 'content_error' | 'pronunciation' | 'display' | 'feature' | 'content_suggestion' | 'other';
  const LOAI: { k: Loai; ten: string; goiY: string }[] = [
    { k: 'content_error', ten: 'Lỗi nội dung', goiY: 'Ví dụ: từ 马 ở bài này dịch sai nghĩa, phải là "con ngựa".' },
    { k: 'pronunciation', ten: 'Lỗi phát âm', goiY: 'Ví dụ: câu thứ 3 đọc sai thanh điệu chữ 好.' },
    { k: 'display', ten: 'Lỗi hiển thị', goiY: 'Ví dụ: trên điện thoại nút Tiếp bị che mất.' },
    { k: 'feature', ten: 'Ý tưởng tính năng', goiY: 'Bạn muốn Zìkǎ có thêm gì?' },
    { k: 'content_suggestion', ten: 'Góp ý nội dung', goiY: 'Ví dụ: nên thêm câu ví dụ cho từ này.' },
    { k: 'other', ten: 'Khác', goiY: 'Bạn muốn nói gì với Zìkǎ?' },
  ];
  const TRANG_THAI: Record<string, string> = { new: 'Đã gửi', doing: 'Đang xử lý', done: 'Đã xong' };

  interface DaGui { id: number; category: Loai; message: string; status: string; reply: string | null; createdAt: string; repliedAt: string | null }

  let loai = $state<Loai>('content_error');
  let noiDung = $state('');
  let dangGui = $state(false);
  let daGui = $state<DaGui[] | null>(null);
  let xemDaGui = $state(false);

  /** Ngữ cảnh lấy từ đường dẫn màn đang mở; màn nào tự đặt (feedback.setContext / open) thì đè lên. */
  function tuDuongDan(path: string): FeedbackContext & { book?: string; trang?: number } {
    const m = path.match(/^\/learn\/([a-z]+)\/hsk\/(\d+)(?:\/lesson\/(\d+))?/);
    if (m) {
      return { part: partBySlug(m[1])?.code ?? null, levelId: Number(m[2]), lessonNo: m[3] ? Number(m[3]) : null };
    }
    const b = path.match(/^\/learn\/books\/((?:hsk|hn)[1-6])/);
    if (b) return { book: b[1], trang: trangDaDoc(b[1]) };
    const p = path.match(/^\/learn\/([a-z]+)/);
    return p ? { part: partBySlug(p[1])?.code ?? null } : {};
  }

  const ctx = $derived({ ...tuDuongDan(page.url.pathname), ...feedback.ctx, ...(feedback.override ?? {}) });
  const nhanNguCanh = $derived.by(() => {
    const c = ctx;
    const phan = c.part ? PARTS.find((p) => p.code === c.part)?.short : null;
    const ra = [
      c.book ? `Giáo trình · ${c.book.toUpperCase()}${c.trang ? ` · trang ${c.trang}` : ''}` : phan,
      c.levelId ? cap.ten(c.levelId) : null,
      c.lessonNo ? `Bài ${c.lessonNo}` : null,
      c.questionNo ? `Câu ${c.questionNo}` : null,
    ].filter(Boolean);
    return ra.join(' · ');
  });
  const goiY = $derived(LOAI.find((l) => l.k === loai)?.goiY ?? '');

  onMount(() => {
    feedback.available = true;
    return () => {
      feedback.available = false;
    };
  });

  // Mở hộp: đang ở màn làm bài thì mặc định "Lỗi nội dung", màn khác thì "Ý tưởng tính năng"; tải góp ý đã gửi.
  $effect(() => {
    if (!feedback.isOpen) return;
    const c = ctx;
    loai = c.doing || c.lessonNo || c.book ? 'content_error' : 'feature';
    void taiDaGui();
  });

  async function taiDaGui(): Promise<void> {
    try {
      daGui = await api.get<DaGui[]>('/feedback/mine', { quiet: true });
    } catch {
      daGui = [];
    }
  }

  async function gui(): Promise<void> {
    if (noiDung.trim().length < 5) {
      toast.show('Viết thêm chút nữa nhé (ít nhất 5 ký tự).', 'info');
      return;
    }
    dangGui = true;
    const c = ctx;
    try {
      await api.post('/feedback', {
        category: loai,
        message: noiDung.trim(),
        ctx: {
          part: c.part ?? undefined, levelId: c.levelId ?? undefined, lessonNo: c.lessonNo ?? undefined,
          questionNo: c.questionNo ?? undefined, book: c.book, trang: c.trang,
          capTen: c.levelId ? cap.ten(c.levelId) : undefined, route: page.url.pathname,
        },
      });
      noiDung = '';
      feedback.close();
      toast.ok('Cảm ơn bạn! Góp ý đã gửi tới đội Zìkǎ.', 4500);
    } catch (e) {
      toast.show(e instanceof ApiError ? e.message : 'Chưa gửi được, thử lại nhé.', 'info', 5000);
    } finally {
      dangGui = false;
    }
  }

  const ngay = (iso: string) => new Date(iso).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
</script>

{#if feedback.isOpen}
  <!-- Mở từ nút Chuyển nhanh trên thanh đầu trang: điện thoại treo ngay dưới thanh như bảng xếp hạng, không trượt từ đáy. -->
  <Modal title="Góp ý · báo lỗi" onclose={() => feedback.close()} width={520} anchor="top">
    <div class="fb-loai" role="radiogroup" aria-label="Loại góp ý">
      {#each LOAI as l (l.k)}
        <button type="button" role="radio" aria-checked={loai === l.k} class="fb-chip" class:on={loai === l.k} onclick={() => (loai = l.k)}>{l.ten}</button>
      {/each}
    </div>
    {#if nhanNguCanh}
      <p class="fb-kem">Gửi kèm màn đang mở: <b>{nhanNguCanh}</b></p>
    {/if}
    <label class="sr-only" for="fb-noi-dung">Nội dung góp ý</label>
    <textarea id="fb-noi-dung" class="inp fb-o" rows="5" maxlength="2000" placeholder={goiY} bind:value={noiDung}></textarea>
    <p class="fb-dem num">{noiDung.length}/2000</p>

    {#if daGui?.length}
      <button type="button" class="btn btn-text btn-sm fb-xem" aria-expanded={xemDaGui} onclick={() => (xemDaGui = !xemDaGui)}>
        Góp ý bạn đã gửi ({daGui.length}){daGui.some((d) => d.reply) ? ' · có phản hồi' : ''}
      </button>
      {#if xemDaGui}
        <ul class="fb-ds">
          {#each daGui as d (d.id)}
            <li>
              <span class="fb-ds-dau">
                <b>{LOAI.find((l) => l.k === d.category)?.ten ?? 'Góp ý'}</b>
                <span class="muted num">{ngay(d.createdAt)}</span>
                <span class="tag" class:tag-ok={d.status === 'done'} class:tag-blue={d.status === 'doing'}>{TRANG_THAI[d.status] ?? d.status}</span>
              </span>
              <span class="fb-ds-nd">{d.message}</span>
              {#if d.reply}<span class="fb-tl"><b>Zìkǎ trả lời:</b> {d.reply}</span>{/if}
            </li>
          {/each}
        </ul>
      {/if}
    {/if}

    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => feedback.close()}>Huỷ</button>
      <button type="button" class="btn btn-son btn-sm" disabled={dangGui} onclick={gui}>{dangGui ? 'Đang gửi…' : 'Gửi góp ý'}</button>
    {/snippet}
  </Modal>
{/if}

<style>
  .fb-loai { display: flex; flex-wrap: wrap; gap: 6px; }
  .fb-chip {
    height: 32px; padding: 0 12px; border: 1px solid var(--line); border-radius: 999px; background: var(--surface);
    color: var(--text-2); font: 600 13px var(--font-ui); cursor: pointer;
  }
  .fb-chip:hover { border-color: var(--line-3); color: var(--text); }
  .fb-chip.on { background: var(--son-soft); border-color: var(--son-line); color: var(--son-text); }
  .fb-chip:focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; }
  .fb-kem { font-size: 13px; color: var(--text-2); }
  .fb-kem b { color: var(--text); }
  .fb-o { width: 100%; resize: vertical; min-height: 110px; font: inherit; line-height: 1.5; }
  .fb-dem { margin-top: -6px; text-align: right; font-size: 12px; color: var(--text-3); }
  .fb-xem { align-self: flex-start; padding-left: 0; }
  .fb-ds { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; max-height: 240px; overflow-y: auto; }
  .fb-ds li { display: flex; flex-direction: column; gap: 4px; padding: 10px 12px; border: 1px solid var(--line); border-radius: 12px; font-size: 13.5px; }
  .fb-ds-dau { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
  .fb-ds-dau .tag { margin-left: auto; height: 22px; font-size: 11px; }
  .fb-ds-nd { line-height: 1.45; white-space: pre-wrap; }
  .fb-tl { padding: 8px 10px; border-radius: 9px; background: var(--ngoc-soft); color: var(--green-text); line-height: 1.45; white-space: pre-wrap; }
</style>
