<script lang="ts">
  import { goto } from '$app/navigation';
  import { notifications } from '$lib/stores/notifications.svelte';
  import { ago, date, time } from '$lib/utils/format';
  import type { InboxItem } from '$lib/api/types';
  import { neo } from '$lib/utils/neo';
  import Icon from './Icon.svelte';

  // Chuông thông báo cạnh ảnh đại diện: thông báo admin đăng ở /admin/news hiện ở đây.
  // Bảng luôn thả xuống ngay dưới chuông; điện thoại thì trải gần hết bề ngang (xem utils/neo.ts).
  let { size = 44 }: { size?: number } = $props();

  let mo = $state(false);
  let goc = $state<HTMLDivElement | null>(null);
  let nut = $state<HTMLButtonElement | null>(null);
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
    // (Đọc hết → Đã đọc hết), contains sẽ tưởng bấm ra ngoài và đóng bảng.
    if (mo && goc && !e.composedPath().includes(goc)) mo = false;
  }
</script>

<svelte:window onclick={ngoai} onkeydown={(e) => e.key === 'Escape' && (mo = false)} />

<div class="nb" bind:this={goc}>
  <button
    type="button"
    bind:this={nut}
    class="nb-nut"
    class:dang-mo={mo}
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
    <div style="display: contents" use:neo={nut}>
      <!-- Nền mờ chỉ hiện trên điện thoại; nằm trong .nb nên phải tự đóng, ngoai() không bắt được. -->
      <button type="button" class="nb-nen" aria-label="Đóng thông báo" tabindex="-1" onclick={() => (mo = false)}></button>
      <div class="nb-bang" role="dialog" aria-label="Thông báo">
        <div class="nb-dau">
          <b class="nb-ten">Thông báo</b>
          {#if notifications.unread > 0}
            <button type="button" class="nb-lk" onclick={() => notifications.docHet()}><Icon name="check" size={15} /> Đọc hết</button>
          {:else if notifications.total > 0}
            <span class="nb-lk xong"><Icon name="check" size={15} /> Đã đọc hết</span>
          {/if}
          <button type="button" class="nb-dong" aria-label="Đóng" onclick={() => (mo = false)}><Icon name="x" size={18} /></button>
        </div>

        <div class="nb-tab" role="tablist">
          <button type="button" role="tab" aria-selected={loc === 'tat'} class:on={loc === 'tat'} onclick={() => (loc = 'tat')}>
            Tất cả <span class="num">{notifications.total}</span>
          </button>
          <button type="button" role="tab" aria-selected={loc === 'chua'} class:on={loc === 'chua'} onclick={() => (loc = 'chua')}>
            Chưa đọc {#if notifications.unread > 0}<span class="num nb-dem">{notifications.unread}</span>{/if}
          </button>
        </div>

        <div class="nb-ds">
          {#if notifications.dangTai && notifications.items.length === 0}
            <p class="nb-trong">Đang tải…</p>
          {:else if ds.length === 0}
            <div class="nb-trong">
              <span class="nb-o"><Icon name={loc === 'chua' && notifications.total > 0 ? 'check' : 'bell'} size={22} /></span>
              {loc === 'chua' && notifications.total > 0 ? 'Bạn đã đọc hết thông báo.' : 'Chưa có thông báo nào.'}
            </div>
          {:else}
            {#each ds as x (x.id)}
              <button type="button" class="nb-muc" class:moi={!x.read} onclick={() => chon(x)}>
                <span class="nb-o"><Icon name="bell" size={18} /></span>
                <span class="nb-noi">
                  <span class="nb-tieude">{x.title}</span>
                  <span class="nb-than">{x.body}</span>
                  <span class="nb-gio num" title="{date(x.at)} {time(x.at)}">{ago(x.at)}</span>
                </span>
                {#if !x.read}<span class="nb-cham" aria-label="Chưa đọc"></span>{/if}
              </button>
            {/each}
          {/if}
        </div>

        <a href="/notifications" class="nb-cuoi" onclick={() => (mo = false)}>Xem tất cả thông báo </a>
      </div>
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
  .nb-nut:hover,
  .nb-nut.dang-mo {
    background: var(--line);
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

  .nb-nen,
  .nb-dong {
    display: none;
  }
  .nb-bang {
    position: absolute;
    z-index: 60;
    top: calc(100% + 10px);
    right: 0;
    display: flex;
    flex-direction: column;
    width: 380px;
    max-height: min(560px, calc(100dvh - 90px));
    border: 1px solid var(--line);
    border-radius: 16px;
    background: var(--surface);
    box-shadow: 0 18px 44px -12px rgb(16 24 40 / 28%);
    overflow: hidden;
    transform-origin: top right;
    animation: nb-ha 0.16s ease-out;
  }
  @keyframes nb-ha {
    from {
      opacity: 0;
      transform: translateY(-6px) scale(0.98);
    }
  }

  /* Điện thoại: chuông không sát mép phải nên bảng 380px neo theo chuông sẽ tràn trái.
     Ghim ngay dưới chuông, cách hai mép 8px; nền mờ chỉ phủ phần dưới thanh trên. */
  @media (max-width: 767px) {
    .nb-nen {
      display: block;
      position: fixed;
      z-index: 59;
      top: var(--neo-nen, 56px);
      right: 0;
      bottom: 0;
      left: 0;
      border: 0;
      background: rgb(16 18 24 / 32%);
      animation: nb-mo 0.16s ease-out;
    }
    .nb-bang {
      position: fixed;
      top: var(--neo-top, 68px);
      right: 12px;
      left: 12px;
      width: auto;
      max-height: calc(100dvh - var(--neo-top, 68px) - 16px);
      box-shadow: 0 16px 40px -8px rgb(16 24 40 / 30%);
    }
    .nb-dong {
      display: grid;
    }
  }
  @keyframes nb-mo {
    from {
      opacity: 0;
    }
  }
  @media (prefers-reduced-motion: reduce) {
    .nb-bang,
    .nb-nen {
      animation: none;
    }
  }

  .nb-dau {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 16px 10px;
  }
  .nb-ten {
    flex: 1;
    font-size: 17px;
    font-weight: 800;
  }
  .nb-lk {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 10px;
    border: 0;
    border-radius: 8px;
    background: none;
    color: var(--text-2);
    font: 600 13px/1.2 var(--font-ui);
    white-space: nowrap;
    cursor: pointer;
  }
  .nb-lk:hover {
    background: var(--surface-3);
    color: var(--text);
  }
  .nb-lk.xong {
    color: var(--text-3);
    cursor: default;
  }
  .nb-lk.xong:hover {
    background: none;
  }
  .nb-dong {
    place-items: center;
    width: 34px;
    height: 34px;
    margin-right: -6px;
    border: 0;
    border-radius: 50%;
    background: var(--surface-3);
    color: var(--text-2);
    cursor: pointer;
  }

  .nb-tab {
    display: flex;
    gap: 2px;
    margin: 0 16px 8px;
    padding: 3px;
    border-radius: 10px;
    background: var(--surface-3);
  }
  .nb-tab button {
    display: inline-flex;
    flex: 1;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 7px 10px;
    border: 0;
    border-radius: 8px;
    background: none;
    color: var(--text-2);
    font: 600 13.5px/1.2 var(--font-ui);
    cursor: pointer;
  }
  .nb-tab button.on {
    background: var(--surface);
    color: var(--text);
    box-shadow: 0 1px 3px rgb(16 24 40 / 12%);
  }
  .nb-dem {
    min-width: 18px;
    padding: 1px 5px;
    border-radius: 9px;
    background: var(--son);
    color: #fff;
    font-size: 11px;
    font-weight: 700;
  }

  .nb-ds {
    flex: 1 1 auto;
    padding: 0 8px 8px;
    overflow-y: auto;
    overscroll-behavior: contain;
  }
  .nb-trong {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 30px 16px;
    color: var(--text-2);
    font-size: 14px;
    text-align: center;
  }
  .nb-muc {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    width: 100%;
    padding: 12px 10px;
    border: 0;
    border-radius: 12px;
    background: none;
    color: var(--text);
    text-align: left;
    cursor: pointer;
  }
  .nb-muc + .nb-muc {
    margin-top: 2px;
  }
  .nb-muc:hover {
    background: var(--surface-2);
  }
  .nb-muc:focus-visible {
    outline: 2px solid var(--focus);
    outline-offset: -2px;
  }
  /* Ô biểu tượng: chưa đọc tô màu son, đã đọc xám — cùng chấm bên phải và tiêu đề đậm là đủ phân biệt. */
  .nb-o {
    display: grid;
    flex: none;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: var(--surface-3);
    color: var(--text-3);
  }
  .nb-muc.moi .nb-o {
    background: var(--son-soft);
    color: var(--son-text);
  }
  .nb-noi {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 3px;
    min-width: 0;
  }
  .nb-tieude {
    color: var(--text-2);
    font-size: 14.5px;
    font-weight: 600;
    line-height: 1.35;
  }
  .nb-muc.moi .nb-tieude {
    color: var(--text);
    font-weight: 800;
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
  .nb-gio {
    margin-top: 2px;
    color: var(--text-3);
    font-size: 12.5px;
  }
  .nb-muc.moi .nb-gio {
    color: var(--son-text);
    font-weight: 600;
  }
  .nb-cham {
    flex: none;
    width: 9px;
    height: 9px;
    margin-top: 6px;
    border-radius: 50%;
    background: var(--son);
  }

  .nb-cuoi {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 13px;
    border-top: 1px solid var(--line);
    color: var(--text);
    font-weight: 700;
    font-size: 14px;
    text-decoration: none;
  }
  .nb-cuoi:hover {
    background: var(--surface-2);
  }
</style>
