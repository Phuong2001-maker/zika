<script lang="ts">
  import { onMount } from 'svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { media } from '$lib/stores/media.svelte';
  import Topbar from '$lib/components/Topbar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { SACH, urlBia, trangDaDoc } from '$lib/learn/sach';

  // Giáo trình · chọn quyển (3 quyển HSK 1–3).
  heartbeat.setActivity({ part: null, screen: 'book' });

  // Chỗ đọc dở nằm ở localStorage nên đọc sau khi gắn, rồi mới hiện nhãn “Đang đọc trang N”.
  let dangDoc = $state<Record<string, number>>({});
  onMount(() => {
    dangDoc = Object.fromEntries(SACH.map((q) => [q.ma, trangDaDoc(q.ma)]));
  });
</script>

<svelte:head><title>Giáo trình · Zìkǎ</title></svelte:head>

{#snippet ke(mobile: boolean)}
  <h1 style="font-size: {mobile ? 22 : 34}px; line-height: 1.2">Giáo trình</h1>
  <section aria-label="Các quyển giáo trình" class="ke" class:m={mobile}>
    {#each SACH as q (q.ma)}
      <a href="/learn/books/{q.ma}" class="quyen">
        <img class="bia" src={urlBia(q.ma)} alt="Bìa {q.ten}" loading="lazy" />
        <b style="font-size: {mobile ? 14 : 16}px">{q.ten}</b>
        <span class="muted" style="font-size: 13px">{q.phu}</span>
        <span class="chan">
          {#if (dangDoc[q.ma] ?? 1) > 1}
            <span class="tag tag-ok">Đang đọc trang {dangDoc[q.ma]}</span>
          {:else}
            <span class="muted num">{q.soTrang} trang</span>
          {/if}
          <Icon name="right" size={16} />
        </span>
      </a>
    {/each}
  </section>
{/snippet}

{#if media.isMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 30px; height: 30px; font-size: 17px">字</span>Zìkǎ</a>
      <div style="margin-left: auto; display: flex; align-items: center; gap: 10px">
        <Switcher current="lt" mobile />
        <Avatar size={44} />
      </div>
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 18px">{@render ke(true)}</main>
  </div>
{:else}
  <!-- ============ Máy tính ============ -->
  <Topbar current="lt" />
  <main class="page" style="display: flex; flex-direction: column; gap: 28px">{@render ke(false)}</main>
{/if}

<style>
  .ke {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(240px, 100%), 1fr));
    gap: 18px;
    align-items: start;
  }
  .ke.m {
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }
  .quyen {
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding: 10px 10px 12px;
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: 20px;
    text-decoration: none;
    color: var(--text);
  }
  .quyen:hover .bia {
    transform: translateY(-3px);
  }
  /* Bìa ba quyển không cùng tỉ lệ (HSK 2 scan ở khổ khác), ép về một khung cho kệ sách thẳng hàng.
     Cắt từ mép dưới vì phần tên sách nằm ở nửa trên. */
  .bia {
    width: 100%;
    aspect-ratio: 1 / 1.38;
    object-fit: cover;
    object-position: top;
    border-radius: 12px;
    border: 1px solid var(--line);
    margin-bottom: 8px;
    transition: transform 0.15s ease;
  }
  .chan {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    margin-top: 8px;
    font-size: 13px;
  }
</style>
