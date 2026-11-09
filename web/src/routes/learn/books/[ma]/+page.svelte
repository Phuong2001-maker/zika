<script lang="ts">
  import MTopbar from '$lib/components/MTopbar.svelte';
  import Topbar from '$lib/components/Topbar.svelte';
  import { session } from '$lib/stores/session.svelte';
  import { SvelteSet } from 'svelte/reactivity';
  import { onDestroy, onMount, untrack } from 'svelte';
  import { page } from '$app/state';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import Icon from '$lib/components/Icon.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import { baiCuaTrang, dinhDangPhut, nhoTrang, urlBia, urlTrang } from '$lib/learn/sach';
  import { diemDoc } from '$lib/learn/diem-doc.svelte';
  import { DocSach } from '$lib/learn/giao-trinh/doc-sach.svelte';
  import NganKeo from '$lib/learn/giao-trinh/NganKeo.svelte';
  import MucLuc from '$lib/learn/giao-trinh/MucLuc.svelte';
  import DanhSachNghe from '$lib/learn/giao-trinh/DanhSachNghe.svelte';
  import TrinhPhat from '$lib/learn/giao-trinh/TrinhPhat.svelte';
  import KhoaBai from '$lib/learn/giao-trinh/KhoaBai.svelte';

  // Đọc một quyển giáo trình: trang sách cuộn dọc, kèm mục lục, file nghe và đáp án (nút nào sách không có thì ẩn).
  const st = new DocSach();
  $effect(() => {
    if (!session.can('books.audio')) { st.may.tamDung(); if (st.ngan === 'nghe') st.ngan = ''; }
    if (!session.can('books.answers') && st.dapAn) st.veSach(st.baiHienTai?.so);
  });
  const ma = $derived(page.params.ma ?? '');
  const sach = $derived(st.sach);
  const cacTrang = $derived(Array.from({ length: st.soTrang }, (_, i) => i + 1));
  const thieuAnh = new SvelteSet<number>();
  let khung: HTMLDivElement | undefined = $state();
  /** Chiều cao thanh đầu trang (máy tính): thanh công cụ sách dính ngay dưới nó, trang sách cuộn tới chừa đúng chỗ. */
  let caoThanh = $state(61);
  /** Điện thoại: thanh chung có thể hai dòng (nút lùi + tên sách ở dòng 2). */
  let caoThanhM = $state(57);

  const tongPhut = $derived(Math.round((sach?.nghe ?? []).reduce((t, n) => t + n.giay, 0) / 60));
  const ngheBaiNay = $derived(st.dapAn || !st.baiHienTai || !st.baiMo(st.baiHienTai.so) ? [] : (st.ngheTheoBai[st.baiHienTai.so] ?? []));

  /** Số trang của bài bắt đầu ở trang `b.trang` trong phần đang xem — để thẻ khoá ghi "14 trang". */
  function soTrangBai(b: { so: number; trang: number }): number {
    const i = st.mucLuc.findIndex((x) => x.so === b.so);
    return (st.mucLuc[i + 1]?.trang ?? st.soTrang + 1) - b.trang;
  }

  // Vừa nhận điểm ở màn khác thì quay lại là thấy số điểm mới; mã ký đường dẫn ảnh
  // sống 6 giờ, đi quá nửa thì lấy mã mới trước khi hết hạn giữa lúc đọc.
  onMount(() => {
    void diemDoc.refresh();
    const lamMoi = () => {
      if (document.visibilityState !== 'visible') return;
      void st.lamMoiQuyen();
      void diemDoc.refresh();
    };
    document.addEventListener('visibilitychange', lamMoi);
    window.addEventListener('focus', lamMoi);
    const dongHo = setInterval(() => {
      if (st.sach && st.veSapHet()) void st.lamMoiQuyen();
    }, 10 * 60 * 1000);
    return () => {
      document.removeEventListener('visibilitychange', lamMoi);
      window.removeEventListener('focus', lamMoi);
      clearInterval(dongHo);
    };
  });

  /**
   * Không cho lưu trang sách bằng các lối thông thường: chuột phải "Lưu hình ảnh", giữ ngón tay trên điện thoại,
   * kéo ảnh ra màn hình, Ctrl+S / Ctrl+P. Chặn được người dùng bình thường; ai chụp màn hình thì vẫn chụp được —
   * phần chặn thật là server không gửi ảnh của bài chưa mở.
   */
  function chanPhim(e: KeyboardEvent): void {
    if ((e.ctrlKey || e.metaKey) && ['s', 'p'].includes(e.key.toLowerCase())) e.preventDefault();
  }
  onMount(() => {
    // Bắt ở document thay vì gắn lên khung trang: khung là vùng đọc, không phải nút bấm (luật a11y của Svelte).
    const chan = (e: Event) => {
      if (e.target instanceof Element && e.target.closest('.book-pages, .gt-ca-khoa')) e.preventDefault();
    };
    document.addEventListener('contextmenu', chan);
    document.addEventListener('dragstart', chan);
    return () => {
      document.removeEventListener('contextmenu', chan);
      document.removeEventListener('dragstart', chan);
    };
  });

  /** Ảnh lỗi (mất mạng, hay mã ký hết hạn khi máy ngủ lâu): lấy mã mới rồi tải lại trang đó. */
  async function thuLai(n: number): Promise<void> {
    await st.lamMoiQuyen(true);
    thieuAnh.delete(n);
  }

  // Đổi quyển (/learn/books/hn1 → hn2) là cùng route, component không dựng lại (bẫy số 2): bám theo mã.
  // untrack: phần thân đọc cả thieuAnh và trạng thái sách, không được để ảnh lỗi làm tải lại cả quyển.
  $effect(() => {
    const m = ma;
    untrack(() => {
      thieuAnh.clear();
      void st.tai(m).then(() => {
        if (st.sach) heartbeat.setActivity({ part: null, screen: 'book', level: st.sach.cap, book: st.sach.ma });
      });
    });
  });
  onDestroy(() => st.may.dong());

  /** Theo dõi trang đi vào vùng đọc, không duyệt cả quyển mỗi lần cuộn. Dựng lại khi đổi quyển hoặc Sách ↔ Đáp án. */
  function theoDoi(node: HTMLDivElement, khoa: string) {
    const [maSach, phan] = khoa.split(':');
    const dapAn = phan === 'dap-an';
    const thay = new SvelteSet<HTMLElement>();
    let observer: IntersectionObserver | undefined;
    let neo: ResizeObserver | undefined;
    const tim = (n: number) => node.querySelector<HTMLElement>(`[data-trang="${n}"]`);
    // Chỗ đang đọc tính theo tỉ lệ trong trang, vì đổi chiều rộng thì chiều cao trang đổi theo.
    let neoTrang = 1;
    let lech = 0;
    const ghiNeo = () => {
      const el = tim(st.trang);
      if (!el) return;
      neoTrang = st.trang;
      lech = (parseFloat(getComputedStyle(el).scrollMarginTop) - el.getBoundingClientRect().top) / el.offsetHeight;
    };
    const frame = requestAnimationFrame(() => {
      const dich = st.dich;
      st.trang = dich;
      if (dich > 1) tim(dich)?.scrollIntoView({ block: 'start', behavior: 'instant' });
      else window.scrollTo({ top: 0, behavior: 'instant' });
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
        if (!thay.size || st.sach?.ma !== maSach || st.dapAn !== dapAn) return;
        const n = Math.min(...Array.from(thay, (el) => Number(el.dataset.trang)));
        st.trang = n;
        nhoTrang(maSach, n, dapAn);
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
    if (!sach || !Number.isFinite(n)) return;
    const dich = Math.min(Math.max(1, Math.round(n)), st.soTrang);
    st.ngan = '';
    st.trang = dich;
    nhoTrang(sach.ma, dich, st.dapAn);
    // Trang thuộc bài chưa mở không có trên màn (cả bài gom thành một thẻ khoá): đến thẻ khoá của bài đó.
    const el = khung?.querySelector<HTMLElement>(`[data-trang="${dich}"]`)
      ?? khung?.querySelector<HTMLElement>(`[data-trang="${baiCuaTrang(st.mucLuc, dich)?.trang ?? 1}"]`);
    el?.scrollIntoView({ block: 'start', behavior: 'instant' });
  }

  /** Nút "Mở bài trong sách" ở đầu trang đáp án, và nút ở thanh phát khi đang xem đáp án. */
  function diTrangSach(trang: number): void {
    if (st.dapAn) {
      st.ngan = '';
      st.veSach();
      st.dich = trang;
      st.trang = trang;
    } else di(trang);
  }
</script>

<svelte:window onkeydown={chanPhim} />

<svelte:head><title>{sach ? `${st.dapAn ? 'Đáp án · ' : ''}${sach.ten}` : 'Giáo trình'} · Zìkǎ</title></svelte:head>

{#snippet nutPhan(mobile: boolean)}
  {#if sach?.dapAn && session.can('books.answers')}
    {#if mobile}
      <button type="button" class="gt-cong" class:on={st.dapAn} aria-pressed={st.dapAn} aria-label={st.dapAn ? 'Về trang sách' : 'Xem đáp án'}
        onclick={() => (st.dapAn ? st.veSach(st.baiHienTai?.so) : st.moDapAn(st.baiHienTai?.so))}>
        <Icon name="answer" size={20} />
      </button>
    {:else}
      <div class="seg" role="group" aria-label="Phần đang xem">
        <button type="button" class:on={!st.dapAn} aria-pressed={!st.dapAn} onclick={() => st.dapAn && st.veSach(st.baiHienTai?.so)}>Sách</button>
        <button type="button" class:on={st.dapAn} aria-pressed={st.dapAn} onclick={() => !st.dapAn && st.moDapAn(st.baiHienTai?.so)}>Đáp án</button>
      </div>
    {/if}
  {/if}
{/snippet}

<div class:mscreen={media.isMobile}>
  {#if media.isMobile}
    <MTopbar back="/learn/books" backLabel="Về kệ sách" bind:cao={caoThanhM}>
      <div class="gt-mten">
        <b>{sach?.ten ?? 'Giáo trình'}</b>
        {#if st.dapAn}<span class="gt-nhan-da">Đáp án</span>{:else if sach}<span class="muted">{sach.phu}</span>{/if}
      </div>
    </MTopbar>
  {:else}
    <!-- Thanh đầu trang dính trên cùng khi cuộn sách, như .mtop ở điện thoại: đang đọc vẫn sang được phần học khác. -->
    <div class="gt-thanh-dinh" bind:clientHeight={caoThanh}>
      <Topbar current="lt">
        <nav class="crumb"><a href="/learn/books">Giáo trình</a> / <b>{sach?.ten ?? ''}</b>{#if st.dapAn} / <b>Đáp án</b>{/if}</nav>
      </Topbar>
    </div>
  {/if}

  <!-- Dùng cùng khung đọc để đổi bố cục không dựng lại danh sách trang. -->
  <main class={media.isMobile ? 'mcontent book-main book-mobile' : 'page book-main'} class:gt-co-may={st.may.dang !== null}
    style:--book-tools-top="{media.isMobile ? caoThanhM : caoThanh}px">
    {#if st.dangTai && !sach}
      <div class="book-tools" aria-hidden="true"><span></span><Skeleton w="180px" h="42px" r={999} /><span></span></div>
      <Skeleton h="70vh" r={4} />
    {:else if !sach}
      <EmptyState title={st.loi === 'khong-co' ? 'Không có quyển giáo trình này.' : 'Chưa tải được giáo trình.'}
        desc={st.loi === 'khong-co' ? 'Quyển này không thuộc giáo trình bạn đang học.' : 'Kiểm tra kết nối mạng rồi thử lại.'}>
        {#snippet action()}
          {#if st.loi !== 'khong-co'}<button type="button" class="btn btn-sm" onclick={() => st.tai(ma)}>Thử lại</button>{/if}
          <a href="/learn/books" class="btn btn-sm">Về kệ sách</a>
        {/snippet}
      </EmptyState>
    {:else if st.quyen?.khoa}
      <!-- Chưa học tới quyển này: server khoá cả trang đầu sách, chỉ còn bìa. -->
      {@const ke = st.quyen.ke}
      <section class="gt-ca-khoa">
        <img class="gt-ca-khoa-bia" src={urlBia(sach)} alt="" draggable="false" />
        <div class="gt-ca-khoa-chu">
          <span class="gt-ca-khoa-nhan"><Icon name="lock" size={15} />Quyển này chưa mở</span>
          <h1>{sach.ten}</h1>
          <p class="muted">{sach.bai.length} bài · {sach.soTrang} trang{sach.nghe.length ? ` · ${sach.nghe.length} file nghe` : ''}</p>
          <p>
            {#if ke}Bài mở lần lượt theo thứ tự. Mở hết <b>{ke.tenSach}</b> (bài tiếp theo: Bài {ke.bai}) là đọc được quyển này.{/if}
          </p>
          <div class="gt-ca-khoa-nut">
            {#if ke}<a class="btn btn-son" href="/learn/books/{ke.ma}">Đến {ke.tenSach}</a>{/if}
          </div>
        </div>
      </section>
    {:else}
      <div class="book-tools" class:gt-tools-m={media.isMobile}>
        <div class="gt-trai">
          <button type="button" class="gt-nut-ml" aria-haspopup="dialog" aria-label="Mục lục{st.baiHienTai ? `, đang ở bài ${st.baiHienTai.so}` : ''}" onclick={() => (st.ngan = 'muc-luc')}>
            <Icon name="list" size={18} />
            {#if !media.isMobile}
              {#if st.baiHienTai}
                <span class="gt-ml-nhan"><b>Bài {st.baiHienTai.so}</b><span lang="zh">{st.baiHienTai.ten}</span></span>
              {:else}
                <span class="gt-ml-nhan"><b>Mục lục</b></span>
              {/if}
            {/if}
          </button>
        </div>

        <form class="book-pager" aria-label="Điều hướng trang {st.dapAn ? 'đáp án' : 'sách'}" onsubmit={(e) => { e.preventDefault(); const data = new FormData(e.currentTarget); di(Number(data.get('trang'))); }}>
          <button type="button" class="book-arrow" disabled={st.trang <= 1} aria-label="Trang trước" title="Trang trước" onclick={() => di(st.trang - 1)}><Icon name="chev" size={16} style="transform: rotate(180deg)" /></button>
          <label class="book-jump">
            <span class="sr-only">Trang</span>
            <input class="book-number" name="trang" type="number" inputmode="numeric" min="1" max={st.soTrang} value={st.trang} required aria-label="Số trang cần đến"
              onfocus={(e) => e.currentTarget.select()}
              onchange={(e) => { di(Number(e.currentTarget.value)); e.currentTarget.value = String(st.trang); }} />
            <span class="book-total">/ {st.soTrang}</span>
          </label>
          <button type="button" class="book-arrow" disabled={st.trang >= st.soTrang} aria-label="Trang sau" title="Trang sau" onclick={() => di(st.trang + 1)}><Icon name="chev" size={16} /></button>
        </form>

        <div class="gt-phai">
          {#if sach.nghe.length && session.can('books.audio')}
            <button type="button" class="gt-cong" class:gt-cong-chu={!media.isMobile} class:on={st.may.dang !== null} aria-haspopup="dialog"
              aria-label="File nghe{ngheBaiNay.length ? `, bài này có ${ngheBaiNay.length} file` : ''}" onclick={() => (st.ngan = 'nghe')}>
              <Icon name="headphones" size={19} />
              {#if !media.isMobile}<span>Nghe</span>{/if}
              {#if ngheBaiNay.length}<span class="gt-dem num">{ngheBaiNay.length}</span>{/if}
            </button>
          {/if}
          {@render nutPhan(media.isMobile)}
        </div>
      </div>

      {#key `${sach.ma}:${st.dapAn ? 'dap-an' : 'sach'}`}
        <div class="book-pages" bind:this={khung} use:theoDoi={`${sach.ma}:${st.dapAn ? 'dap-an' : 'sach'}`} aria-label="{st.dapAn ? 'Đáp án' : 'Nội dung'} {sach.ten}">
          {#each cacTrang as n (n)}
            {@const cao = st.cao[n - 1] ?? 1600}
            {@const bai = st.dauBai[n]}
            {#if !st.trangMo(n)}
              <!-- Bài chưa mở: cả bài gom thành một thẻ ở trang đầu bài, các trang còn lại không dựng. -->
              {#if bai}
                <figure class="book-sheet" data-trang={n}><KhoaBai {st} {bai} soTrang={soTrangBai(bai)} ondi={di} /></figure>
              {/if}
            {:else}
            <figure class="book-sheet" data-trang={n}>
              {#if bai}
                {@const soNghe = st.ngheTheoBai[bai.so]?.length ?? 0}
                <div class="gt-dai" class:gt-dai-da={st.dapAn}>
                  <span class="gt-dai-so">{st.dapAn ? 'Đáp án bài' : 'Bài'} {bai.so}</span>
                  <span class="gt-dai-ten">
                    {#if bai.ten}<b lang="zh">{bai.ten}</b>{/if}
                    {#if bai.tenVi}<span>{bai.tenVi}</span>{/if}
                  </span>
                  <span class="gt-dai-nut">
                    {#if st.dapAn}
                      <button type="button" class="btn btn-sm" onclick={() => st.veSach(bai.so)}><Icon name="book" size={16} />Mở bài trong sách</button>
                    {:else}
                      {#if soNghe && session.can('books.audio')}
                        <button type="button" class="btn btn-sm gt-nghe-bai" onclick={() => st.ngheBai(bai)}>
                          <Icon name="headphones" size={16} />Nghe bài này<span class="gt-phu num">{soNghe} file</span>
                        </button>
                      {/if}
                      {#if bai.so in st.dapAnTheoBai && session.can('books.answers')}
                        <button type="button" class="btn btn-sm" onclick={() => st.moDapAn(bai.so)}><Icon name="answer" size={16} />Đáp án</button>
                      {/if}
                    {/if}
                  </span>
                </div>
              {/if}
              {#if thieuAnh.has(n)}
                <div class="book-error" style="aspect-ratio: {sach.rong} / {cao}">
                  <p>Không tải được trang {n}.</p>
                  <button type="button" class="btn btn-sm" onclick={() => thuLai(n)}>Thử lại</button>
                </div>
              {:else}
                <img class="book-image" src={urlTrang(sach, st.ve, n, st.dapAn)} alt="{st.dapAn ? 'Đáp án ' : ''}{sach.ten} — trang {n}" width={sach.rong} height={cao}
                  loading="lazy" decoding="async" draggable="false" onerror={() => thieuAnh.add(n)} />
              {/if}
              <figcaption class="book-caption">{st.dapAn ? 'Đáp án · ' : ''}Trang {n}</figcaption>
            </figure>
            {/if}
          {/each}
        </div>
      {/key}
    {/if}
  </main>
</div>

{#if sach && st.ngan === 'muc-luc'}
  <NganKeo title={st.dapAn ? 'Mục lục đáp án' : 'Mục lục'} sub="{sach.ten} · {st.mucLuc.length} bài" mobile={media.isMobile} onclose={() => (st.ngan = '')}>
    <MucLuc {st} ondi={di} />
  </NganKeo>
{/if}
{#if sach && st.ngan === 'nghe' && session.can('books.audio')}
  <NganKeo title="File nghe" sub="{sach.nghe.length} file · {dinhDangPhut(tongPhut)} · ghi âm đi kèm sách" mobile={media.isMobile} onclose={() => (st.ngan = '')}>
    <DanhSachNghe {st} ondi={diTrangSach} />
  </NganKeo>
{/if}
{#if sach && session.can('books.audio')}<TrinhPhat {st} mobile={media.isMobile} ondi={diTrangSach} />{/if}

<style>
  .book-main { max-width: 900px; display: flex; flex-direction: column; gap: 12px; }
  /* Trên thanh công cụ sách (10) và bé túi (19), dưới ngăn mục lục/hộp thoại (80). */
  .gt-thanh-dinh { position: sticky; top: 0; z-index: 31; }
  .book-mobile { padding: 12px 8px 24px; }
  /* Chừa chỗ cho thanh phát dính đáy để trang cuối không bị che. */
  .gt-co-may { padding-bottom: 150px; }
  .book-tools {
    position: sticky; top: var(--book-tools-top, 0px); z-index: 10;
    display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 8px;
    padding: 6px 0; background: var(--ground);
  }
  .gt-trai { justify-self: start; min-width: 0; max-width: 100%; }
  .gt-phai { justify-self: end; display: flex; align-items: center; gap: 6px; }
  .gt-nut-ml {
    display: inline-flex; align-items: center; gap: 8px; max-width: 100%; min-height: 42px; padding: 0 14px 0 12px;
    border: 1px solid var(--line); border-radius: 999px; background: var(--surface); color: var(--text-2); font: inherit; cursor: pointer;
  }
  .gt-tools-m .gt-nut-ml { width: 42px; padding: 0; justify-content: center; }
  .gt-nut-ml:hover { color: var(--text); border-color: var(--line-3); }
  .gt-ml-nhan { display: flex; align-items: baseline; gap: 6px; min-width: 0; font-size: 13px; }
  .gt-ml-nhan b { color: var(--text); white-space: nowrap; }
  .gt-ml-nhan span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: var(--font-hz), var(--font-ui); }
  .gt-cong {
    position: relative; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    width: 42px; height: 42px; padding: 0; border: 1px solid var(--line); border-radius: 999px;
    background: var(--surface); color: var(--text-2); font: 600 13px var(--font-ui); cursor: pointer;
  }
  .gt-cong-chu { width: auto; padding: 0 14px 0 12px; }
  .gt-cong:hover { color: var(--text); border-color: var(--line-3); }
  .gt-cong.on { background: var(--son-soft); border-color: var(--son-line); color: var(--son-text); }
  .gt-nut-ml:focus-visible, .gt-cong:focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; }
  /* Số file nghe của bài đang đọc: thấy ngay trang này có ghi âm. */
  .gt-dem {
    display: grid; place-items: center; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 99px;
    background: var(--son); color: var(--on-accent); font-size: 11px; font-weight: 700;
  }
  .gt-tools-m .gt-dem { position: absolute; top: -4px; right: -4px; }
  .gt-mten { flex: 1; min-width: 0; display: flex; flex-direction: column; line-height: 1.25; }
  .gt-mten b { font-size: 15px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .gt-mten .muted { font-size: 12px; }
  .gt-nhan-da { font-size: 12px; font-weight: 700; color: var(--green-text); }

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
  .book-sheet { scroll-margin-top: calc(var(--book-tools-top, 0px) + 62px); border: 1px solid var(--line); background: var(--surface); }
  /* Không bôi chọn, không menu "Lưu hình ảnh" khi giữ ngón tay (iOS), không kéo ảnh ra ngoài. */
  .book-image {
    display: block; width: 100%; height: auto;
    -webkit-touch-callout: none; -webkit-user-select: none; user-select: none; -webkit-user-drag: none;
  }
  /* In trang web ra giấy/PDF thì không in trang sách. */
  @media print { .book-pages, .gt-ca-khoa { display: none; } }

  .gt-ca-khoa {
    display: flex; gap: 24px; align-items: flex-start; padding: 24px;
    background: var(--surface); border: 1px solid var(--line); border-radius: 20px;
  }
  .gt-ca-khoa-bia {
    flex: none; width: 180px; aspect-ratio: 1 / 1.38; object-fit: cover; object-position: top;
    border-radius: 12px; border: 1px solid var(--line); filter: grayscale(.6); opacity: .85;
    -webkit-touch-callout: none; user-select: none;
  }
  .gt-ca-khoa-chu { display: flex; flex-direction: column; gap: 8px; min-width: 0; }
  .gt-ca-khoa-chu h1 { font-size: 24px; line-height: 1.25; }
  .gt-ca-khoa-chu p { font-size: 14px; line-height: 1.55; }
  .gt-ca-khoa-nhan {
    display: inline-flex; align-items: center; gap: 6px; align-self: flex-start; padding: 4px 10px; border-radius: 999px;
    background: var(--surface-3); color: var(--text-2); font-size: 12px; font-weight: 700;
  }
  .gt-ca-khoa-nut { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 4px; }
  .book-mobile .gt-ca-khoa { flex-direction: column; align-items: center; text-align: center; padding: 18px 14px; }
  .book-mobile .gt-ca-khoa-nhan { align-self: center; }
  .book-mobile .gt-ca-khoa-nut { justify-content: center; }
  .book-mobile .gt-ca-khoa-bia { width: 140px; }
  .book-caption { padding: 6px; text-align: center; color: var(--text-2); font-size: 12px; }
  .book-error { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; }

  /* Dải tên bài trên trang đầu mỗi bài: biết bài này có ghi âm, có đáp án mà không cần mở menu. */
  .gt-dai {
    display: flex; align-items: center; flex-wrap: wrap; gap: 8px 14px;
    padding: 12px 14px; border-bottom: 1px solid var(--line); background: var(--surface-2);
  }
  .gt-dai-da { background: var(--ngoc-soft); }
  .gt-dai-so {
    flex: none; padding: 4px 10px; border-radius: 999px; background: var(--son); color: var(--on-accent);
    font-size: 12px; font-weight: 700; white-space: nowrap;
  }
  .gt-dai-da .gt-dai-so { background: var(--ngoc); }
  .gt-dai-ten { flex: 1 1 220px; display: flex; flex-direction: column; min-width: 0; line-height: 1.35; }
  .gt-dai-ten b { font-family: var(--font-hz), var(--font-ui); font-size: 17px; font-weight: 600; }
  .gt-dai-ten span { font-size: 13px; color: var(--text-2); }
  .gt-dai-nut { display: flex; flex-wrap: wrap; gap: 8px; }
  .gt-nghe-bai { background: var(--son-soft); border-color: var(--son-line); color: var(--son-text); }
  .gt-nghe-bai:hover { background: var(--son-soft); border-color: var(--son); color: var(--son-text); }
  .gt-phu { font-weight: 500; opacity: .8; }
  .book-mobile .gt-dai { padding: 10px; }
  .book-mobile .gt-dai-ten b { font-size: 16px; }
</style>
