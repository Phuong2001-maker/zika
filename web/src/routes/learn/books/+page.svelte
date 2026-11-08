<script lang="ts">
  import { onMount } from 'svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { media } from '$lib/stores/media.svelte';
  import Topbar from '$lib/components/Topbar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import { dinhDangPhut, quyenGan, taiKeSach, trangDaDoc, urlBia, type TomTatSach } from '$lib/learn/sach';

  // Giáo trình · kệ sách. Học viên chỉ thấy bộ sách của giáo trình mình học, quản trị thấy cả hai bộ.
  heartbeat.setActivity({ part: null, screen: 'book' });

  let ds = $state<TomTatSach[] | null>(null);
  let loi = $state(false);
  // Chỗ đọc dở nằm ở localStorage nên đọc sau khi gắn, rồi mới hiện "Đang đọc trang N" và thẻ Đọc tiếp.
  let dangDoc = $state<Record<string, number>>({});
  let gan = $state('');

  async function tai() {
    loi = false;
    try {
      ds = await taiKeSach();
      dangDoc = Object.fromEntries(ds.map((q) => [q.ma, trangDaDoc(q.ma)]));
      gan = quyenGan();
    } catch {
      loi = true;
    }
  }
  onMount(() => void tai());

  // API đã xếp sẵn theo bộ rồi theo cấp, chỉ cần gom các quyển liền nhau cùng bộ.
  const nhom = $derived.by(() => {
    const ra: { bo: string; ten: string; ds: TomTatSach[] }[] = [];
    for (const q of ds ?? []) {
      const cuoi = ra.at(-1);
      if (cuoi?.bo === q.bo) cuoi.ds.push(q);
      else ra.push({ bo: q.bo, ten: q.tenBo, ds: [q] });
    }
    return ra;
  });
  const docTiep = $derived(ds?.find((q) => q.ma === gan && (dangDoc[q.ma] ?? 1) > 1));
</script>

<svelte:head><title>Giáo trình · Zìkǎ</title></svelte:head>

{#snippet the(q: TomTatSach, mobile: boolean)}
  {@const trang = dangDoc[q.ma] ?? 1}
  <a href="/learn/books/{q.ma}" class="gt-quyen">
    <span class="gt-bia-khung">
      <img class="gt-bia" src={urlBia(q)} alt="" loading="lazy" />
      {#if q.soNghe}<span class="gt-co-nghe" title="Có file nghe"><Icon name="headphones" size={15} /></span>{/if}
    </span>
    <b class="gt-ten" style="font-size: {mobile ? 14 : 16}px">{q.ten}</b>
    <span class="muted" style="font-size: 13px">{q.phu}</span>
    <span class="gt-chips">
      <span class="gt-chip"><Icon name="book" size={14} />{q.soBai} bài</span>
      {#if q.soNghe}
        <span class="gt-chip gt-chip-nghe" title="{q.soNghe} file nghe · {dinhDangPhut(q.phutNghe)}"><Icon name="headphones" size={14} />{q.soNghe} file nghe</span>
      {/if}
      {#if q.coDapAn}<span class="gt-chip gt-chip-da"><Icon name="answer" size={14} />Đáp án</span>{/if}
    </span>
    <span class="gt-chan">
      {#if trang > 1}
        <span class="gt-tien" aria-hidden="true"><i style="width: {Math.min(100, (trang / q.soTrang) * 100)}%"></i></span>
        <span class="num">Đang đọc trang {trang}/{q.soTrang}</span>
      {:else}
        <span class="muted num">{q.soTrang} trang</span>
      {/if}
    </span>
  </a>
{/snippet}

{#snippet ke(mobile: boolean)}
  <div>
    <h1 style="font-size: {mobile ? 22 : 34}px; line-height: 1.2">Giáo trình</h1>
    {#if nhom.length === 1}
      <p class="muted gt-mo-ta">Sách của <b>{nhom[0].ten}</b> — đọc từng trang, nghe file ghi âm và so đáp án ngay trên web.</p>
    {:else if nhom.length > 1}
      <p class="muted gt-mo-ta">Bạn thấy mọi bộ sách; học viên chỉ thấy bộ sách của giáo trình mình đang học.</p>
    {/if}
  </div>

  {#if loi}
    <EmptyState title="Chưa tải được kệ sách." desc="Kiểm tra kết nối mạng rồi thử lại.">
      {#snippet action()}<button type="button" class="btn btn-sm" onclick={tai}>Thử lại</button>{/snippet}
    </EmptyState>
  {:else if ds === null}
    <div class="gt-ke" class:m={mobile} aria-hidden="true">
      {#each Array.from({ length: mobile ? 4 : 6 }, (_, i) => i) as i (i)}
        <div class="gt-quyen"><Skeleton h="auto" r={12} style="aspect-ratio: 1 / 1.38" /><Skeleton w="70%" /><Skeleton w="45%" h="12px" /></div>
      {/each}
    </div>
  {:else if !ds.length}
    <EmptyState title="Giáo trình bạn đang học chưa có sách đọc kèm." desc="Khi có sách, kệ này sẽ tự hiện." />
  {:else}
    {#if docTiep}
      {@const trang = dangDoc[docTiep.ma] ?? 1}
      <a class="gt-doc-tiep" href="/learn/books/{docTiep.ma}">
        <img src={urlBia(docTiep)} alt="" />
        <span class="gt-dt-giua">
          <span class="gt-dt-nhan">Đọc tiếp</span>
          <b>{docTiep.ten}</b>
          <span class="gt-tien" aria-hidden="true"><i style="width: {Math.min(100, (trang / docTiep.soTrang) * 100)}%"></i></span>
          <span class="muted num" style="font-size: 13px">Trang {trang} / {docTiep.soTrang}</span>
        </span>
        <span class="btn btn-son btn-sm gt-dt-nut">{mobile ? 'Đọc' : 'Đọc tiếp'}<Icon name="chev" size={16} /></span>
      </a>
    {/if}

    {#each nhom as g (g.bo)}
      <section class="gt-nhom" aria-label={g.ten}>
        {#if nhom.length > 1}
          <h2 class="gt-nhom-ten">{g.ten}<span class="muted">{g.ds.length} quyển</span></h2>
        {/if}
        <div class="gt-ke" class:m={mobile}>
          {#each g.ds as q (q.ma)}{@render the(q, mobile)}{/each}
        </div>
      </section>
    {/each}
  {/if}
{/snippet}

{#if media.isMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 30px; height: 30px; font-size: 17px">字</span>Zìkǎ</a>
      <div style="margin-left: auto; display: flex; align-items: center; gap: 10px">
        <Switcher current="lt" />
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
  .gt-mo-ta { margin-top: 6px; font-size: 14px; }
  .gt-mo-ta b { color: var(--text); }
  .gt-nhom { display: flex; flex-direction: column; gap: 14px; }
  .gt-nhom-ten { display: flex; align-items: baseline; gap: 10px; font-size: 20px; font-weight: 700; }
  .gt-nhom-ten .muted { font-size: 13px; font-weight: 500; }
  .gt-ke {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(210px, 100%), 1fr));
    gap: 18px;
    align-items: stretch;
  }
  .gt-ke.m { grid-template-columns: 1fr 1fr; gap: 12px; }
  .gt-quyen {
    display: flex; flex-direction: column; gap: 3px;
    padding: 10px 10px 12px;
    background: var(--surface); border: 1px solid var(--line); border-radius: 20px;
    text-decoration: none; color: var(--text);
    transition: border-color .15s ease, box-shadow .15s ease;
  }
  .gt-quyen:hover { border-color: var(--line-3); box-shadow: var(--sh-md); }
  .gt-quyen:hover .gt-bia { transform: translateY(-3px); }
  .gt-quyen:focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; }
  .gt-bia-khung { position: relative; display: block; margin-bottom: 8px; }
  /* Bìa các quyển không cùng tỉ lệ (bản scan khác khổ), ép về một khung cho kệ sách thẳng hàng.
     Cắt từ mép dưới vì phần tên sách nằm ở nửa trên. */
  .gt-bia {
    display: block; width: 100%; aspect-ratio: 1 / 1.38; object-fit: cover; object-position: top;
    border-radius: 12px; border: 1px solid var(--line); background: var(--surface-3);
    transition: transform .15s ease;
  }
  /* Dấu tai nghe trên bìa: lướt kệ là biết quyển nào nghe được, khỏi đọc chữ nhỏ bên dưới. */
  .gt-co-nghe {
    position: absolute; right: 8px; bottom: 8px;
    display: grid; place-items: center; width: 30px; height: 30px; border-radius: 50%;
    background: var(--surface); color: var(--son-text); box-shadow: var(--sh-md);
  }
  .gt-ten { line-height: 1.3; }
  .gt-chips { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px; }
  .gt-chip {
    display: inline-flex; align-items: center; gap: 4px; height: 24px; padding: 0 8px; border-radius: 999px;
    background: var(--surface-3); color: var(--text-2); font-size: 12px; font-weight: 600; white-space: nowrap;
  }
  .gt-chip-nghe { background: var(--son-soft); color: var(--son-text); }
  .gt-chip-da { background: var(--ngoc-soft); color: var(--green-text); }
  /* Đẩy dòng số trang xuống đáy thẻ để cả hàng thẳng dù dòng nhãn dài ngắn khác nhau. */
  .gt-chan { display: flex; flex-direction: column; gap: 5px; margin-top: auto; padding-top: 10px; font-size: 12.5px; }
  .gt-tien { display: block; height: 4px; border-radius: 99px; background: var(--track); overflow: hidden; }
  .gt-tien i { display: block; height: 100%; border-radius: inherit; background: var(--son); }

  .gt-doc-tiep {
    display: flex; align-items: center; gap: 16px;
    padding: 12px 16px 12px 12px;
    background: var(--surface); border: 1px solid var(--line); border-radius: 20px; box-shadow: var(--sh-sm);
    text-decoration: none; color: var(--text);
  }
  .gt-doc-tiep:hover { border-color: var(--line-3); box-shadow: var(--sh-md); }
  .gt-doc-tiep img { flex: none; width: 60px; aspect-ratio: 1 / 1.38; object-fit: cover; object-position: top; border-radius: 8px; border: 1px solid var(--line); }
  .gt-dt-giua { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
  .gt-dt-giua b { display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.3; }
  .gt-dt-giua .gt-tien { max-width: 320px; }
  .gt-dt-nhan { font-size: 12px; font-weight: 700; color: var(--son-text); text-transform: uppercase; letter-spacing: .04em; }
  .gt-dt-nut { flex: none; gap: 4px; }
</style>
