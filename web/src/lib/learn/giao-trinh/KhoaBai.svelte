<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { session } from '$lib/stores/session.svelte';
  import { diemDoc } from '$lib/learn/diem-doc.svelte';
  import type { DocSach, MucBai } from './doc-sach.svelte';

  // Thẻ đứng thay cho các trang của một bài chưa mở. Server không gửi ảnh, đáp án, file nghe của bài này, nên ở
  // đây chỉ còn lối mở: dùng điểm, nhận điểm hôm nay, hoặc mở bài trước.
  let { st, bai, soTrang, ondi }: { st: DocSach; bai: MucBai; soTrang: number; ondi: (trang: number) => void } = $props();

  let hoi = $state(false);
  const ke = $derived(st.quyen?.ke ?? null);
  const laKe = $derived(st.laBaiKe(bai.so));
  const diem = $derived(st.quyen?.diem ?? 0);
  const soNghe = $derived(st.ngheTheoBai[bai.so]?.length ?? 0);
  const trangKe = $derived(ke && ke.ma === st.sach?.ma ? (st.sach?.bai.find((b) => b.so === ke.bai)?.trang ?? 1) : 1);

  async function nhan(): Promise<void> {
    await diemDoc.nhan();
    await st.lamMoiQuyen();
  }
</script>

<div class="gt-khoa" role="group" aria-label="{st.dapAn ? 'Đáp án bài' : 'Bài'} {bai.so} chưa mở">
  <span class="gt-khoa-o" aria-hidden="true"><Icon name="lock" size={24} /></span>
  <p class="gt-khoa-tieu">{st.dapAn ? 'Đáp án bài' : 'Bài'} {bai.so} chưa mở</p>
  {#if bai.ten || bai.tenVi}
    <p class="gt-khoa-ten">
      {#if bai.ten}<b lang="zh">{bai.ten}</b>{/if}
      {#if bai.tenVi}<span>{bai.tenVi}</span>{/if}
    </p>
  {/if}
  <p class="muted gt-khoa-phu num">{soTrang} trang{soNghe && !st.dapAn ? ` · ${soNghe} file nghe` : ''}</p>

  {#if st.dapAn}
    <p class="gt-khoa-loi-giai">Mở bài trong sách là xem được đáp án của bài đó.</p>
    <button type="button" class="btn btn-son" onclick={() => st.veSach(bai.so)}><Icon name="book" size={16} />Về Bài {bai.so} trong sách</button>
  {:else if laKe}
    {#if !session.can('books.points')}
      <p class="gt-khoa-loi-giai">Liên hệ quản trị viên để được mở bài.</p>
    {:else if diem >= 1}
      {#if hoi}
        <p class="gt-khoa-loi-giai">Dùng 1 trong <b class="num">{diem}</b> điểm để mở Bài {bai.so}? Mở rồi là đọc mãi.</p>
        <div class="gt-khoa-nut">
          <button type="button" class="btn btn-son" disabled={st.dangMo !== null} onclick={() => st.moBai(bai.so)}>
            {st.dangMo === bai.so ? 'Đang mở…' : 'Mở bài'}
          </button>
          <button type="button" class="btn" disabled={st.dangMo !== null} onclick={() => (hoi = false)}>Thôi</button>
        </div>
      {:else}
        <button type="button" class="btn btn-son" onclick={() => (hoi = true)}>Mở bài này · 1 điểm</button>
        <p class="muted gt-khoa-phu">Bạn đang có <b class="num">{diem}</b> điểm mở bài.</p>
      {/if}
    {:else if !diemDoc.homNay}
      <p class="gt-khoa-loi-giai">
        Bạn chưa có điểm mở bài. Bấm nhận để lấy điểm hôm nay.
      </p>
      <button type="button" class="btn btn-son" onclick={nhan}>Nhận {diemDoc.lanToi} điểm hôm nay</button>
    {:else}
      <p class="gt-khoa-loi-giai">Bạn đã dùng hết điểm. Mai quay lại bấm Nhận điểm nhé.</p>
    {/if}
  {:else if ke}
    <p class="gt-khoa-loi-giai">Bài mở lần lượt theo thứ tự. Mở Bài {ke.bai}{ke.ma !== st.sach?.ma ? ` của ${ke.tenSach}` : ''} trước nhé.</p>
    {#if ke.ma === st.sach?.ma}
      <button type="button" class="btn" onclick={() => ondi(trangKe)}>Đến Bài {ke.bai}</button>
    {:else}
      <a class="btn" href="/learn/books/{ke.ma}">Đến {ke.tenSach}</a>
    {/if}
  {/if}

  {#if st.loiMo && laKe}<p class="gt-khoa-sai" role="alert">{st.loiMo}</p>{/if}
</div>

<style>
  .gt-khoa {
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    padding: 36px 20px 28px; text-align: center;
    background: repeating-linear-gradient(135deg, var(--surface-2) 0 12px, var(--surface) 12px 24px);
  }
  .gt-khoa-o {
    display: grid; place-items: center; width: 56px; height: 56px; margin-bottom: 4px; border-radius: 50%;
    background: var(--surface); border: 1px solid var(--line); color: var(--son-text); box-shadow: var(--sh-sm);
  }
  .gt-khoa-tieu { font-size: 18px; font-weight: 700; }
  .gt-khoa-ten { display: flex; flex-direction: column; gap: 2px; line-height: 1.35; }
  .gt-khoa-ten b { font-family: var(--font-hz), var(--font-ui); font-size: 17px; font-weight: 600; }
  .gt-khoa-ten span { font-size: 13px; color: var(--text-2); }
  .gt-khoa-phu { font-size: 13px; }
  .gt-khoa-loi-giai { max-width: 420px; font-size: 14px; line-height: 1.5; }
  .gt-khoa-nut { display: flex; gap: 8px; }
  .gt-khoa-sai { color: var(--bad-text); font-size: 13px; }
</style>
