<script lang="ts">
  import { onMount, tick } from 'svelte';
  import { page } from '$app/state';
  import { media } from '$lib/stores/media.svelte';
  import { notifications } from '$lib/stores/notifications.svelte';
  import { date, time } from '$lib/utils/format';
  import Icon from '$lib/components/Icon.svelte';
  import Topbar from '$lib/components/Topbar.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
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
  <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap">
    {#if !m}<h1 style="font-size: 28px">Thông báo</h1>{/if}
    {#if notifications.unread > 0}
      <button type="button" class="btn btn-sm" onclick={() => notifications.docHet()}><Icon name="check" size={15} /> Đánh dấu đã xem tất cả</button>
    {:else if notifications.total > 0}
      <span class="muted" style="display: inline-flex; align-items: center; gap: 5px; font-size: 14px; font-weight: 600"><Icon name="check" size={15} /> Đã xem tất cả</span>
    {/if}
  </div>
  {#if notifications.total > 0}
    <p class="muted num" style="font-size: 14px; margin-top: -6px">{notifications.total} thông báo · <b style="color: {notifications.unread > 0 ? 'var(--son-text)' : 'inherit'}">{notifications.unread} chưa đọc</b></p>
  {/if}

  {#if !notifications.daTai && notifications.items.length === 0}
    <Skeleton h="160px" r={18} />
  {:else if notifications.items.length === 0}
    <EmptyState title="Chưa có thông báo nào." desc="Khi có tin mới từ Zìkǎ, chuông ở góc trên sẽ báo cho bạn." />
  {:else}
    <div style="display: flex; flex-direction: column; gap: 12px">
      {#each notifications.items as x (x.id)}
        <article id="tb-{x.id}" class="block tb" class:moi={!x.read}>
          <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px">
            <span class="muted num" style="display: inline-flex; align-items: center; gap: 5px; font-size: 13px"><Icon name="clock" size={14} /> {date(x.at)} {time(x.at)}</span>
            {#if x.read}
              <span class="muted" style="display: inline-flex; align-items: center; gap: 4px; font-size: 13px"><Icon name="check" size={14} /> Đã đọc</span>
            {:else}
              <button type="button" class="tb-tag" onclick={() => notifications.docMot(x.id)}>Mới · đánh dấu đã đọc</button>
            {/if}
          </div>
          <h2 style="font-size: 17px; line-height: 1.35; font-weight: {x.read ? 600 : 800}; color: {x.read ? 'var(--text-2)' : 'var(--text)'}">{x.title}</h2>
          <p style="font-size: 14.5px; line-height: 1.6; white-space: pre-line">{x.body}</p>
          {#if x.ctaRoute && x.ctaRoute.startsWith('/')}
            <a href={x.ctaRoute} class="btn btn-son btn-sm" style="align-self: flex-start" onclick={() => notifications.docMot(x.id)}>{x.ctaText || 'Xem ngay'} <Icon name="right" size={15} /></a>
          {/if}
        </article>
      {/each}
    </div>
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop">
      <button type="button" class="btn btn-bare" aria-label="Quay lại" onclick={() => history.back()}><Icon name="left" size={20} /></button>
      <b style="flex: 1; font-size: 15px">Thông báo</b>
      <Avatar size={36} bell={false} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 14px">{@render body(true)}</main>
  </div>
{:else}
  <Topbar />
  <main class="page" style="max-width: 760px; display: flex; flex-direction: column; gap: 18px">{@render body(false)}</main>
{/if}

<style>
  .tb {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 16px 18px;
    border-left: 3px solid transparent;
  }
  .tb.moi {
    border-left-color: var(--son);
    background: var(--son-soft);
  }
  .tb-tag {
    padding: 3px 10px;
    border: 0;
    border-radius: 999px;
    background: var(--son);
    color: #fff;
    font: 700 12px/1.4 var(--font-ui);
    cursor: pointer;
  }
</style>
