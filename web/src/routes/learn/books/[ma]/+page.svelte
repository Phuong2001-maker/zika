<script lang="ts">
  import { SvelteSet } from 'svelte/reactivity';
  import { page } from '$app/state';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import Icon from '$lib/components/Icon.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import { nhoTrang, quyenTheoMa, trangDaDoc, urlTrang } from '$lib/learn/sach';

  const ma = $derived(page.params.ma ?? '');
  const quyen = $derived(quyenTheoMa(ma));
  const cacTrang = $derived(Array.from({ length: quyen?.soTrang ?? 0 }, (_, i) => i + 1));
  let trang = $state(1);
  const thieuAnh = new SvelteSet<number>();
  let khung: HTMLDivElement | undefined = $state();

  $effect(() => {
    const q = quyenTheoMa(ma);
    if (!q) return;
    thieuAnh.clear();
    heartbeat.setActivity({ part: null, screen: 'book', level: q.cap });
  });

  /** Theo dõi các trang đi vào vùng đọc, không duyệt cả quyển mỗi lần cuộn. */
  function theoDoi(node: HTMLDivElement, maSach: string) {
    const q = quyenTheoMa(maSach);
    if (!q) return;
    const thay = new SvelteSet<HTMLElement>();
    let observer: IntersectionObserver | undefined;
    let neo: ResizeObserver | undefined;
    const tim = (n: number) => node.querySelector<HTMLElement>(`[data-trang="${n}"]`);
    // Chỗ đang đọc tính theo tỉ lệ trong trang, vì đổi chiều rộng thì chiều cao trang đổi theo.
    let neoTrang = 1;
    let lech = 0;
    const ghiNeo = () => {
      const el = tim(trang);
      if (!el) return;
      neoTrang = trang;
      lech = (parseFloat(getComputedStyle(el).scrollMarginTop) - el.getBoundingClientRect().top) / el.offsetHeight;
    };
    const frame = requestAnimationFrame(() => {
      const dich = Math.min(trangDaDoc(q.ma), q.soTrang);
      trang = dich;
      if (dich > 1) tim(dich)?.scrollIntoView({ block: 'start', behavior: 'instant' });
      ghiNeo();
      window.addEventListener('scroll', ghiNeo, { passive: true });
      // Xoay máy hay kéo qua 768px: scrollY cũ rơi vào trang khác hẳn và observer sẽ ghi đè dấu
      // trang. ResizeObserver chạy trước IntersectionObserver trong cùng khung hình nên kịp neo lại.
      let rong = node.clientWidth;
      neo = new ResizeObserver(() => {
        if (node.clientWidth === rong) return;
        rong = node.clientWidth;
        const el = tim(neoTrang);
        if (!el) return;
        el.scrollIntoView({ block: 'start', behavior: 'instant' });
        window.scrollBy({ top: lech * el.offsetHeight, behavior: 'instant' });
      });
      neo.observe(node);
      // Khôi phục trước khi quan sát để trang đầu không ghi đè dấu trang đang đọc dở.
      observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
          const el = entry.target as HTMLElement;
          if (entry.isIntersecting) thay.add(el);
          else thay.delete(el);
        }
        if (!thay.size || ma !== q.ma) return;
        const n = Math.min(...Array.from(thay, (el) => Number(el.dataset.trang)));
        trang = n;
        nhoTrang(q.ma, n);
      }, { rootMargin: '-120px 0px -20% 0px', threshold: 0 });
      node.querySelectorAll<HTMLElement>('[data-trang]').forEach((el) => observer?.observe(el));
    });
    return {
      destroy() {
        cancelAnimationFrame(frame);
        observer?.disconnect();
        neo?.disconnect();
        window.removeEventListener('scroll', ghiNeo);
      },
    };
  }

  function di(n: number): void {
    if (!quyen || !Number.isFinite(n)) return;
    const dich = Math.min(Math.max(1, Math.round(n)), quyen.soTrang);
    trang = dich;
    nhoTrang(quyen.ma, dich);
    khung?.querySelector<HTMLElement>(`[data-trang="${dich}"]`)?.scrollIntoView({ block: 'start', behavior: 'instant' });
  }

  function thuLai(n: number): void {
    thieuAnh.delete(n);
  }
</script>

<svelte:head><title>{quyen?.ten ?? 'Giáo trình'} · Zìkǎ</title></svelte:head>

<div class:mscreen={media.isMobile}>
  {#if media.isMobile}
    <header class="mtop">
      <a href="/learn/books" class="btn btn-bare" aria-label="Về kệ sách"><Icon name="left" size={20} /></a>
      <b style="flex: 1; font-size: 15px">{quyen?.ten ?? 'Giáo trình'}</b>
      <Avatar size={36} />
    </header>
  {:else}
    <header class="topbar">
      <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
      <nav class="crumb" style="margin-left: 8px"><a href="/learn/books">Giáo trình</a> / <b>{quyen?.ten ?? ''}</b></nav>
      <div style="margin-left: auto; display: flex; align-items: center; gap: 12px"><Switcher current="lt" /><Avatar size={44} /></div>
    </header>
  {/if}

  <!-- Dùng cùng khung đọc để đổi bố cục không dựng lại danh sách trang. -->
  <main class={media.isMobile ? 'mcontent book-main book-mobile' : 'page book-main'}>
    {#if !quyen}
      <EmptyState title="Không có quyển giáo trình này.">
        {#snippet action()}<a href="/learn/books" class="btn btn-sm">Về kệ sách</a>{/snippet}
      </EmptyState>
    {:else}
      <div class="book-tools">
        {#if !media.isMobile}
          <a class="book-back" href="/learn/books"><Icon name="left" size={16} /> Kệ sách</a>
        {/if}
        <form class="book-pager" aria-label="Điều hướng trang sách" onsubmit={(e) => { e.preventDefault(); const data = new FormData(e.currentTarget); di(Number(data.get('trang'))); }}>
          <button type="button" class="book-arrow" disabled={trang <= 1} aria-label="Trang trước" title="Trang trước" onclick={() => di(trang - 1)}><Icon name="left" size={16} /></button>
          <label class="book-jump">
            <span class="sr-only">Trang</span>
            <input class="book-number" name="trang" type="number" inputmode="numeric" min="1" max={quyen.soTrang} value={trang} required aria-label="Số trang cần đến"
              onfocus={(e) => e.currentTarget.select()}
              onchange={(e) => { di(Number(e.currentTarget.value)); e.currentTarget.value = String(trang); }} />
            <span class="book-total">/ {quyen.soTrang}</span>
          </label>
          <button type="button" class="book-arrow" disabled={trang >= quyen.soTrang} aria-label="Trang sau" title="Trang sau" onclick={() => di(trang + 1)}><Icon name="right" size={16} /></button>
        </form>
      </div>

      {#key quyen.ma}
        <div class="book-pages" bind:this={khung} use:theoDoi={quyen.ma} aria-label="Nội dung {quyen.ten}">
          {#each cacTrang as n (n)}
            {@const cao = quyen.caoTrangDau[n - 1] ?? quyen.caoTrang}
            <figure class="book-sheet" data-trang={n}>
              {#if thieuAnh.has(n)}
                <div class="book-error" style="aspect-ratio: 1200 / {cao}">
                  <p>Không tải được trang {n}.</p>
                  <button type="button" class="btn btn-sm" onclick={() => thuLai(n)}>Thử lại</button>
                </div>
              {:else}
                <img class="book-image" src={urlTrang(quyen.ma, n)} alt="{quyen.ten} — trang {n}" width="1200" height={cao}
                  loading="lazy" decoding="async" onerror={() => thieuAnh.add(n)} />
              {/if}
              <figcaption class="book-caption">Trang {n}</figcaption>
            </figure>
          {/each}
        </div>
      {/key}
    {/if}
  </main>
</div>

<style>
  .book-main { max-width: 900px; display: flex; flex-direction: column; gap: 12px; }
  .book-mobile { --book-tools-top: 56px; padding: 12px 8px 24px; }
  .book-tools {
    position: sticky; top: var(--book-tools-top, 0px); z-index: 10;
    display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 8px;
    padding: 4px 0; background: var(--ground);
  }
  .book-back { grid-column: 1; display: inline-flex; align-items: center; gap: 6px; width: fit-content; padding: 8px 0; font-size: 13px; color: var(--text-2); }
  .book-back:hover { color: var(--text); }
  .book-pager { grid-column: 2; display: flex; align-items: center; gap: 2px; padding: 3px; background: var(--surface); border: 1px solid var(--line); border-radius: 999px; }
  .book-arrow { display: grid; place-items: center; width: 36px; height: 36px; padding: 0; border: 0; border-radius: 50%; background: transparent; color: var(--text-2); cursor: pointer; }
  .book-arrow:hover:not(:disabled) { background: var(--surface-3); color: var(--text); }
  .book-arrow:disabled { opacity: .3; cursor: default; }
  .book-jump { display: flex; align-items: center; gap: 2px; font-size: 13px; font-variant-numeric: tabular-nums; }
  .book-number { appearance: textfield; width: 40px; height: 30px; padding: 2px; border: 0; border-radius: 5px; background: transparent; color: var(--text); font: inherit; font-weight: 600; text-align: center; }
  .book-number::-webkit-inner-spin-button, .book-number::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
  .book-number:focus { background: var(--surface-3); }
  .book-total { padding-right: 6px; color: var(--text-2); white-space: nowrap; }
  .book-mobile .book-number { font-size: 16px; }
  .book-pages { display: flex; flex-direction: column; gap: 16px; }
  /* Chừa đúng phần thanh dính phía trên; chừa dư thì lộ chú thích "Trang n−1" ngay trên trang vừa đi tới. */
  .book-sheet { scroll-margin-top: calc(var(--book-tools-top, 0px) + 60px); border: 1px solid var(--line); background: var(--surface); }
  .book-image { display: block; width: 100%; height: auto; }
  .book-caption { padding: 6px; text-align: center; color: var(--text-2); font-size: 12px; }
  .book-error { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; }
</style>
