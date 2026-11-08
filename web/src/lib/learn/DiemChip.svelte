<script lang="ts">
  import { onMount } from 'svelte';
  import { session } from '$lib/stores/session.svelte';
  import { diemDoc } from './diem-doc.svelte';

  // Số điểm mở bài trên thanh đầu trang ở mọi màn học: người học luôn biết mình còn bao nhiêu điểm và hôm nay đã nhận
  // chưa; bấm là về kệ Giáo trình (ở đó có nút Nhận điểm, Mua gói). Đứng đầu cụm nút bên phải ở cả Topbar (máy tính)
  // lẫn MTopbar (điện thoại, bản `gon`). Biên tập/quản trị đọc sách tự do nên không có điểm để hiện.
  let { gon = false }: { gon?: boolean } = $props();

  onMount(() => void diemDoc.refresh());

  const chuaNhan = $derived(!diemDoc.homNay);
  const giaiThich = $derived(
    `Bạn có ${diemDoc.diem} điểm mở bài giáo trình. Mỗi ngày chạm bé túi được ${chuaNhan ? diemDoc.lanToi : 1} điểm, ` +
      `mỗi điểm mở một bài.${chuaNhan ? ' Hôm nay bạn chưa nhận.' : ''} Bấm để vào Giáo trình.`,
  );
</script>

{#if !session.isStaff && diemDoc.daTai}
  <a class="dc" class:dc-gon={gon} href="/learn/books" title={gon ? undefined : giaiThich} aria-label={giaiThich}>
    <span class="dc-o num" aria-hidden="true">
      <b>{diemDoc.diem}</b>
      {#if gon}<small>điểm</small>{/if}
      {#if chuaNhan}<i class="dc-cham">+{diemDoc.lanToi}</i>{/if}
    </span>
    {#if !gon}
      <span class="dc-chu" aria-hidden="true">
        <b>điểm mở bài</b>
        {#if chuaNhan}<small class="dc-nhan">Nhận +{diemDoc.lanToi} hôm nay</small>{:else}<small>Mở bài giáo trình</small>{/if}
      </span>
    {/if}
  </a>
{/if}

<style>
  .dc {
    position: relative; display: inline-flex; align-items: center; gap: 9px; height: 44px; padding: 0 12px 0 5px;
    border: 1px solid var(--son-line); border-radius: 12px; background: var(--surface);
    color: var(--text); text-decoration: none; white-space: nowrap; flex: none;
  }
  .dc:hover { background: var(--son-soft); }
  .dc:focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; }
  /* Ô số đỏ như con dấu son — cùng kiểu ví điểm ở kệ Giáo trình, nhìn là nhận ra cùng một thứ. */
  .dc-o {
    position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center;
    min-width: 34px; height: 34px; padding: 0 6px; border-radius: 9px;
    background: var(--son); color: var(--on-accent);
  }
  .dc-o b { font-size: 17px; line-height: 1; font-weight: 800; }
  .dc-o small { font-size: 8.5px; font-weight: 700; line-height: 1.1; opacity: .9; }
  /* Chấm "+1" khi hôm nay chưa nhận: lý do duy nhất để ô này gọi người học bấm vào. */
  .dc-cham {
    position: absolute; top: -7px; right: -9px; padding: 1px 5px; border-radius: 99px;
    background: #FFD84D; color: #5A3B00; font: 800 10.5px/1.3 var(--font-ui); font-style: normal;
    box-shadow: 0 0 0 2px var(--surface); animation: dc-nhip 1.6s ease-in-out infinite;
  }
  .dc-chu { display: flex; flex-direction: column; line-height: 1.2; }
  .dc-chu b { font-size: 13.5px; font-weight: 700; }
  .dc-chu small { font-size: 11.5px; color: var(--text-2); }
  .dc-chu .dc-nhan { color: var(--son-text); font-weight: 700; }

  /* Điện thoại: chỉ ô số kèm chữ "điểm", cao bằng nút chuông. */
  .dc-gon { height: auto; padding: 0; border: 0; background: none; }
  .dc-gon:hover { background: none; }
  .dc-gon .dc-o { min-width: 40px; height: 40px; border-radius: 11px; }
  .dc-gon .dc-o b { font-size: 16px; }

  /* Máy tính màn hẹp: thanh có cả đường dẫn bài học nên chỉ giữ ô số (chữ vẫn có ở tooltip). */
  @media (max-width: 1180px) {
    .dc:not(.dc-gon) { padding: 0 5px; }
    .dc-chu { display: none; }
  }
  @keyframes dc-nhip { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.12); } }
  @media (prefers-reduced-motion: reduce) { .dc-cham { animation: none; } }
</style>
