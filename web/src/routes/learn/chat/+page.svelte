<script lang="ts">
  import { onMount } from 'svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { media } from '$lib/stores/media.svelte';
  import Topbar from '$lib/components/Topbar.svelte';
  import MTopbar from '$lib/components/MTopbar.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import { daXem, taiCacBan, type Ban } from '$lib/learn/quan-tra.svelte';

  // Quán trà · danh sách bàn. Mỗi bàn một chủ đề để câu hỏi bài không trôi giữa chuyện làm quen (người dùng góp ý
  // 08/11/2026). Bàn nào có tin chưa đọc (so với tin cuối đã xem, lưu ở máy) thì có chấm đỏ.
  heartbeat.setActivity({ part: null, screen: 'chat' });

  let ban = $state<Ban[] | null>(null);
  let loi = $state(false);
  let dongCua = $state(false);
  // Đọc localStorage sau khi gắn để biết bàn nào có tin mới.
  let daXemTheoBan = $state<Record<string, number>>({});

  async function tai(): Promise<void> {
    loi = false;
    try {
      const r = await taiCacBan();
      ban = r.rooms;
      dongCua = !r.open;
      daXemTheoBan = Object.fromEntries(r.rooms.map((b) => [b.slug, daXem(b.slug)]));
    } catch {
      loi = true;
    }
  }
  onMount(() => {
    void tai();
    // Quay lại tab là thấy bàn nào vừa có tin.
    const hien = () => document.visibilityState === 'visible' && void tai();
    document.addEventListener('visibilitychange', hien);
    return () => document.removeEventListener('visibilitychange', hien);
  });

  const luc = (iso: string) => {
    const d = new Date(iso);
    return d.toDateString() === new Date().toDateString()
      ? d.toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })
      : d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
  };
</script>

<svelte:head><title>Quán trà · Zìkǎ</title></svelte:head>

{#snippet noiDung(mobile: boolean)}
  <div>
    <h1 class="qb-tieu" style="font-size: {mobile ? 24 : 32}px"><span lang="zh" class="qb-cha">茶馆</span>Quán trà</h1>
    <p class="muted qb-mo-ta">Nơi người học Zìkǎ trò chuyện. Mỗi bàn một chủ đề — chọn bàn hợp với điều bạn muốn nói.</p>
  </div>
  {#if dongCua}<p class="qb-dong">Quán trà đang tạm đóng cửa: bạn vẫn đọc được tin cũ, chưa gửi tin được.</p>{/if}

  {#if loi}
    <EmptyState title="Chưa vào được Quán trà." desc="Kiểm tra kết nối mạng rồi thử lại.">
      {#snippet action()}<button type="button" class="btn btn-sm" onclick={tai}>Thử lại</button>{/snippet}
    </EmptyState>
  {:else if ban === null}
    <div class="qb-ds" aria-hidden="true">{#each [1, 2, 3, 4] as i (i)}<Skeleton h="96px" r={18} />{/each}</div>
  {:else}
    <div class="qb-ds">
      {#each ban as b (b.slug)}
        {@const moi = (b.lastId ?? 0) > (daXemTheoBan[b.slug] ?? 0)}
        <a href="/learn/chat/{b.slug}" class="qb-ban" class:qb-dong-ban={!b.open}>
          <span class="qb-icon" aria-hidden="true">{b.icon}</span>
          <span class="qb-giua">
            <span class="qb-ten">
              <b>{b.name}</b>
              {#if !b.open}<span class="tag">Đang đóng</span>{/if}
              {#if moi}<span class="qb-moi">Tin mới</span>{/if}
            </span>
            <span class="muted qb-mt">{b.description}</span>
            {#if b.last}
              <span class="qb-cuoi"><b>{b.last.name}:</b> {b.last.body}<span class="muted num"> · {luc(b.last.at)}</span></span>
            {:else}
              <span class="qb-cuoi muted">Chưa có ai nói gì — mở lời đầu tiên nhé.</span>
            {/if}
          </span>
          {#if b.today}<span class="qb-hom-nay num" title="Số tin 24 giờ qua">{b.today}<small>tin</small></span>{/if}
        </a>
      {/each}
    </div>
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <MTopbar />
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 16px">{@render noiDung(true)}</main>
  </div>
{:else}
  <Topbar />
  <main class="page" style="display: flex; flex-direction: column; gap: 20px; max-width: 820px">{@render noiDung(false)}</main>
{/if}

<style>
  .qb-tieu { display: flex; align-items: center; gap: 10px; line-height: 1.2; }
  .qb-cha {
    display: grid; place-items: center; padding: 4px 8px; border-radius: 10px; background: #DDEFD9; color: #2F5D2A;
    font-family: var(--font-hz), var(--font-ui); font-size: .62em; font-weight: 700;
  }
  .qb-mo-ta { margin-top: 4px; font-size: 14px; }
  .qb-dong { padding: 10px 14px; border-radius: 12px; background: var(--bo-soft); color: var(--warn-text); font-size: 14px; }
  .qb-ds { display: flex; flex-direction: column; gap: 10px; }
  .qb-ban {
    display: flex; align-items: center; gap: 14px; padding: 14px 16px; border: 1px solid var(--line); border-radius: 18px;
    background: var(--surface); color: var(--text); text-decoration: none;
  }
  .qb-ban:hover { border-color: var(--line-3); box-shadow: var(--sh-sm); }
  .qb-ban:focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; }
  .qb-dong-ban { opacity: .7; }
  .qb-icon {
    flex: none; display: grid; place-items: center; width: 48px; height: 48px; border-radius: 14px; background: #DDEFD9;
    color: #2F5D2A; font: 700 22px var(--font-hz), var(--font-ui);
  }
  .qb-giua { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
  .qb-ten { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
  .qb-ten b { font-size: 16px; }
  .qb-moi { padding: 1px 8px; border-radius: 99px; background: var(--son); color: var(--on-accent); font-size: 11px; font-weight: 700; }
  .qb-mt { font-size: 13px; }
  .qb-cuoi { margin-top: 2px; font-size: 13.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .qb-hom-nay {
    flex: none; display: flex; flex-direction: column; align-items: center; min-width: 44px; padding: 4px 6px; border-radius: 12px;
    background: var(--surface-2); font-size: 16px; font-weight: 800; line-height: 1.1;
  }
  .qb-hom-nay small { font-size: 10.5px; font-weight: 600; color: var(--text-2); }
</style>
