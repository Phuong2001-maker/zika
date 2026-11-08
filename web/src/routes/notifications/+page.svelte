<script lang="ts">
  import MTopbar from '$lib/components/MTopbar.svelte';
  import { onMount, tick } from 'svelte';
  import { page } from '$app/state';
  import { media } from '$lib/stores/media.svelte';
  import { notifications } from '$lib/stores/notifications.svelte';
  import { ago, date, time } from '$lib/utils/format';
  import Icon from '$lib/components/Icon.svelte';
  import Topbar from '$lib/components/Topbar.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';

  // Toàn bộ thông báo, đọc đủ nội dung (trong chuông chỉ hiện 2 dòng). Mở từ chuông thì cuộn tới đúng thông báo.
  onMount(async () => {
    await notifications.tai();
    const id = Number(page.url.hash.replace('#tb-', ''));
    if (id) {
      notifications.docMot(id);
      await tick();
      document.getElementById(`tb-${id}`)?.scrollIntoView({ block: 'center' });
    }
  });
</script>

<svelte:head><title>Thông báo · Zìkǎ</title></svelte:head>

{#snippet body(m: boolean)}
  <div class="tb-dau">
    <div style="flex: 1; min-width: 0">
      {#if !m}<h1 style="font-size: 28px">Thông báo</h1>{/if}
      {#if notifications.total > 0}
        <p class="muted num" style="font-size: 14px">
          {notifications.total} thông báo{#if notifications.unread > 0}
            · <b style="color: var(--son-text)">{notifications.unread} chưa đọc</b>{/if}
        </p>
      {/if}
    </div>
    {#if notifications.unread > 0}
      <button type="button" class="btn btn-sm" onclick={() => notifications.docHet()}><Icon name="check" size={15} /> Đọc hết</button>
    {:else if notifications.total > 0}
      <span class="muted tb-xong"><Icon name="check" size={15} /> Đã đọc hết</span>
    {/if}
  </div>

  {#if !notifications.daTai && notifications.items.length === 0}
    <Skeleton h="160px" r={18} />
  {:else if notifications.items.length === 0}
    <EmptyState title="Chưa có thông báo nào." desc="Khi có tin mới từ Zìkǎ, chuông ở góc trên sẽ báo cho bạn." />
  {:else}
    <div class="block tb-ds">
      {#each notifications.items as x (x.id)}
        <article id="tb-{x.id}" class="tb" class:moi={!x.read}>
          <span class="tb-o"><Icon name="bell" size={20} /></span>
          <div class="tb-noi">
            <div class="tb-hang">
              <h2 class="tb-tieude">{x.title}</h2>
              {#if !x.read}<span class="tb-cham" aria-label="Chưa đọc"></span>{/if}
            </div>
            <span class="tb-gio num" title={ago(x.at)}>{date(x.at)} · {time(x.at)}</span>
            <p class="tb-than">{x.body}</p>
            {#if (x.ctaRoute && x.ctaRoute.startsWith('/')) || !x.read}
              <div class="tb-nut">
                {#if x.ctaRoute && x.ctaRoute.startsWith('/')}
                  <a href={x.ctaRoute} class="btn btn-son btn-sm" onclick={() => notifications.docMot(x.id)}
                    >{x.ctaText || 'Xem ngay'} </a
                  >
                {/if}
                {#if !x.read}
                  <button type="button" class="tb-lk" onclick={() => notifications.docMot(x.id)}
                    ><Icon name="check" size={14} /> Đánh dấu đã đọc</button
                  >
                {/if}
              </div>
            {/if}
          </div>
        </article>
      {/each}
    </div>
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <MTopbar onback={() => history.back()} title="Thông báo" />
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 14px">{@render body(true)}</main>
  </div>
{:else}
  <Topbar />
  <main class="page" style="max-width: 760px; display: flex; flex-direction: column; gap: 18px">{@render body(false)}</main>
{/if}

<style>
  .tb-dau {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
  }
  .tb-xong {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 14px;
    font-weight: 600;
  }
  .tb-ds {
    padding: 6px;
  }
  .tb {
    display: flex;
    gap: 14px;
    padding: 16px 14px;
    border-radius: 16px;
    scroll-margin: 80px;
  }
  .tb + .tb {
    border-top: 1px solid var(--line);
    border-top-left-radius: 0;
    border-top-right-radius: 0;
  }
  /* Chưa đọc: ô biểu tượng màu son, tiêu đề đậm, chấm đỏ. Đã đọc: xám. */
  .tb-o {
    display: grid;
    flex: none;
    place-items: center;
    width: 42px;
    height: 42px;
    border-radius: 50%;
    background: var(--surface-3);
    color: var(--text-3);
  }
  .tb.moi .tb-o {
    background: var(--son-soft);
    color: var(--son-text);
  }
  .tb-noi {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
  }
  .tb-hang {
    display: flex;
    align-items: flex-start;
    gap: 10px;
  }
  .tb-tieude {
    flex: 1;
    color: var(--text-2);
    font-size: 16.5px;
    font-weight: 600;
    line-height: 1.35;
  }
  .tb.moi .tb-tieude {
    color: var(--text);
    font-weight: 800;
  }
  .tb-cham {
    flex: none;
    width: 9px;
    height: 9px;
    margin-top: 7px;
    border-radius: 50%;
    background: var(--son);
  }
  .tb-gio {
    color: var(--text-3);
    font-size: 13px;
  }
  .tb-than {
    margin-top: 4px;
    font-size: 14.5px;
    line-height: 1.6;
    white-space: pre-line;
  }
  .tb-nut {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 14px;
    margin-top: 8px;
  }
  .tb-lk {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 0;
    border: 0;
    background: none;
    color: var(--text-2);
    font: 600 13.5px/1.2 var(--font-ui);
    cursor: pointer;
  }
  .tb-lk:hover {
    color: var(--text);
    text-decoration: underline;
  }
  @media (max-width: 767px) {
    .tb {
      gap: 12px;
      padding: 14px 10px;
    }
    .tb-o {
      width: 36px;
      height: 36px;
    }
  }
</style>
