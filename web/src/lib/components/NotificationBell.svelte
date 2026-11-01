<script lang="ts">
  import { goto } from '$app/navigation';
  import { notifications } from '$lib/stores/notifications.svelte';
  import { date, time } from '$lib/utils/format';
  import type { InboxItem } from '$lib/api/types';
  import Icon from './Icon.svelte';

  // Chuông thông báo cạnh ảnh đại diện: thông báo admin đăng ở /admin/news hiện ở đây.
  // Máy tính: bảng thả xuống dưới chuông. Điện thoại: bảng trải gần hết bề ngang màn hình.
  let { size = 44 }: { size?: number } = $props();

  let mo = $state(false);
  let goc = $state<HTMLDivElement | null>(null);
  /** Tab đang xem: tất cả hay chỉ thông báo chưa đọc. */
  let loc = $state<'tat' | 'chua'>('tat');
  const ds = $derived(loc === 'chua' ? notifications.items.filter((x) => !x.read) : notifications.items);

  function bat(): void {
    mo = !mo;
    if (mo && !notifications.daTai) void notifications.tai();
  }

  function chon(x: InboxItem): void {
    notifications.docMot(x.id);
    mo = false;
    void goto(x.ctaRoute && x.ctaRoute.startsWith('/') ? x.ctaRoute : `/notifications#tb-${x.id}`);
  }

  function ngoai(e: MouseEvent): void {
    // composedPath thay cho contains(target): nút vừa bấm có thể đã bị thay ra khỏi DOM
    // (Đánh dấu đã xem tất cả → Đã xem tất cả), contains sẽ tưởng bấm ra ngoài và đóng bảng.
    if (mo && goc && !e.composedPath().includes(goc)) mo = false;
  }
</script>

<svelte:window onclick={ngoai} onkeydown={(e) => e.key === 'Escape' && (mo = false)} />

<div class="nb" bind:this={goc}>
  <button
    type="button"
    class="nb-nut"
    style="width: {size}px; height: {size}px"
    aria-label={notifications.unread > 0 ? `Thông báo, ${notifications.unread} chưa đọc` : 'Thông báo'}
    aria-expanded={mo}
    onclick={bat}
  >
    <Icon name="bell" size={size > 40 ? 22 : 20} />
    {#if notifications.unread > 0}
      <span class="nb-so num">{notifications.unread > 9 ? '9+' : notifications.unread}</span>
    {/if}
  </button>

  {#if mo}
    <div class="nb-bang" role="dialog" aria-label="Thông báo">
      <div class="nb-dau">
        <div class="nb-tab" role="tablist">
          <button type="button" role="tab" aria-selected={loc === 'tat'} class:on={loc === 'tat'} onclick={() => (loc = 'tat')}>Tất cả ({notifications.total})</button>
          <button type="button" role="tab" aria-selected={loc === 'chua'} class:on={loc === 'chua'} onclick={() => (loc = 'chua')}>Chưa đọc ({notifications.unread})</button>
        </div>
        <!-- Luôn hiện để người học biết trạng thái: còn chưa đọc thì bấm được, đọc hết thì báo đã xem tất cả. -->
        {#if notifications.unread > 0}
          <button type="button" class="nb-lk" onclick={() => notifications.docHet()}><Icon name="check" size={14} /> Đánh dấu đã xem tất cả</button>
        {:else}
          <span class="nb-lk xong"><Icon name="check" size={14} /> Đã xem tất cả</span>
        {/if}
      </div>

      <div class="nb-ds">
        {#if notifications.dangTai && notifications.items.length === 0}
          <p class="muted nb-trong">Đang tải…</p>
        {:else if ds.length === 0}
          <p class="muted nb-trong">{loc === 'chua' && notifications.total > 0 ? 'Bạn đã đọc hết thông báo.' : 'Chưa có thông báo nào.'}</p>
        {:else}
          {#each ds as x (x.id)}
            <button type="button" class="nb-muc" class:moi={!x.read} onclick={() => chon(x)}>
              <span class="nb-hang">
                <span class="muted num nb-gio"><Icon name="clock" size={13} /> {date(x.at)} {time(x.at)}</span>
                {#if x.read}
                  <span class="muted nb-doc"><Icon name="check" size={13} /> Đã đọc</span>
                {:else}
                  <span class="nb-tag">Mới</span>
                {/if}
              </span>
              <span class="nb-tieude">{#if !x.read}<span class="nb-cham" aria-label="Chưa đọc"></span>{/if}{x.title}</span>
              <span class="nb-than">{x.body}</span>
            </button>
          {/each}
        {/if}
      </div>

      <a href="/notifications" class="nb-cuoi" onclick={() => (mo = false)}>Xem tất cả</a>
    </div>
  {/if}
</div>

<style>
  .nb {
    position: relative;
    flex: none;
  }
  .nb-nut {
    position: relative;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 50%;
    background: var(--surface-3);
    color: var(--text);
    cursor: pointer;
  }
  .nb-nut:hover {
    background: var(--surface-2);
  }
  .nb-nut:focus-visible {
    outline: 2px solid var(--focus);
    outline-offset: 2px;
  }
  .nb-so {
    position: absolute;
    top: -3px;
    right: -3px;
    min-width: 19px;
    height: 19px;
    padding: 0 5px;
    border: 2px solid var(--surface);
    border-radius: 10px;
    background: var(--son);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    line-height: 15px;
    text-align: center;
  }

  .nb-bang {
    position: absolute;
    z-index: 60;
    top: calc(100% + 10px);
    right: 0;
    display: flex;
    flex-direction: column;
    width: 380px;
    max-height: min(560px, 75vh);
    border: 1px solid var(--line);
    border-radius: 16px;
    background: var(--surface);
    box-shadow: 0 18px 44px -12px rgb(16 24 40 / 30%);
    overflow: hidden;
  }
  /* Điện thoại: chuông nằm sát mép phải, bảng 380px sẽ tràn trái — trải theo màn hình. */
  @media (max-width: 767px) {
    .nb-bang {
      position: fixed;
      top: 64px;
      left: 10px;
      right: 10px;
      width: auto;
      max-height: calc(100dvh - 84px);
    }
  }
  .nb-dau {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 8px 10px;
    padding: 12px 14px;
    border-bottom: 1px solid var(--line);
  }
  .nb-tab {
    display: inline-flex;
    gap: 4px;
  }
  .nb-tab button {
    padding: 6px 10px;
    border: 0;
    border-radius: 999px;
    background: none;
    color: var(--text-2);
    font: 600 13.5px/1.2 var(--font-ui);
    cursor: pointer;
  }
  .nb-tab button.on {
    background: var(--son-soft);
    color: var(--son-text);
  }
  .nb-lk {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    border: 0;
    background: none;
    color: var(--son-text);
    font: 600 13px/1.2 var(--font-ui);
    cursor: pointer;
  }
  .nb-lk.xong {
    color: var(--text-2);
    cursor: default;
  }
  .nb-ds {
    flex: 1 1 auto;
    overflow-y: auto;
    overscroll-behavior: contain;
  }
  .nb-trong {
    padding: 28px 16px;
    text-align: center;
    font-size: 14px;
  }
  .nb-muc {
    display: flex;
    flex-direction: column;
    gap: 4px;
    width: 100%;
    padding: 12px 16px 12px 13px;
    border: 0;
    border-left: 3px solid transparent;
    border-bottom: 1px solid var(--line);
    background: none;
    color: var(--text);
    text-align: left;
    cursor: pointer;
  }
  .nb-muc:hover {
    background: var(--surface-2);
  }
  .nb-muc.moi {
    border-left-color: var(--son);
    background: var(--son-soft);
  }
  .nb-hang {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
  }
  .nb-gio,
  .nb-doc {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 12.5px;
  }
  .nb-tag {
    padding: 2px 8px;
    border-radius: 999px;
    background: var(--son);
    color: #fff;
    font-size: 11.5px;
    font-weight: 700;
  }
  .nb-tieude {
    font-size: 14.5px;
    font-weight: 800;
    line-height: 1.35;
  }
  /* Đã đọc: chữ thường, xám — chưa đọc: đậm, có chấm đỏ, nền hồng. */
  .nb-muc:not(.moi) .nb-tieude {
    font-weight: 500;
    color: var(--text-2);
  }
  .nb-cham {
    display: inline-block;
    width: 8px;
    height: 8px;
    margin: 0 7px 2px 0;
    border-radius: 50%;
    background: var(--son);
    vertical-align: middle;
  }
  .nb-than {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    color: var(--text-2);
    font-size: 13.5px;
    line-height: 1.45;
  }
  .nb-cuoi {
    padding: 13px;
    border-top: 1px solid var(--line);
    color: var(--son-text);
    font-weight: 700;
    font-size: 14px;
    text-align: center;
    text-decoration: none;
  }
  .nb-cuoi:hover {
    background: var(--surface-2);
  }
</style>
