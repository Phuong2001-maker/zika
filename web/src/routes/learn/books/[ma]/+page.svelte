<script lang="ts">
  import { page } from '$app/state';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import Icon from '$lib/components/Icon.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import BackLink from '$lib/components/BackLink.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import { nhoTrang, quyenTheoMa, trangDaDoc, urlTrang } from '$lib/learn/sach';

  // Giáo trình · đọc một quyển. Mỗi trang là một ảnh WebP trong uploads, không phải chữ:
  // HSK 2 và HSK 3 vốn là bản scan nên không tách chữ ra được (xem lib/learn/sach.ts).
  const ma = $derived(page.params.ma ?? '');
  const quyen = $derived(quyenTheoMa(ma));

  let trang = $state(1);
  let thieuAnh = $state(false);
  let khung: HTMLDivElement | undefined = $state();

  /** Tờ đang lật: `nen` nằm dưới, `phu` là tờ quay quanh gáy sách. */
  let lat = $state<{ nen: number; phu: number; huong: 'toi' | 'lui' } | null>(null);

  // Đổi quyển là đi trong CÙNG một route nên component không remount và onMount không chạy lại
  // (bẫy số 2 ở CLAUDE.md): bám theo `ma` để mở đúng chỗ đọc dở của quyển vừa chọn.
  $effect(() => {
    const q = quyenTheoMa(ma);
    if (!q) return;
    trang = Math.min(trangDaDoc(q.ma), q.soTrang);
    thieuAnh = false;
    lat = null;
    heartbeat.setActivity({ part: null, screen: 'book', level: q.cap });
  });

  // Tải sẵn trang trước và trang sau để lật không bị chớp trắng.
  $effect(() => {
    const q = quyen;
    if (!q) return;
    for (const n of [trang + 1, trang - 1]) {
      if (n >= 1 && n <= q.soTrang) new Image().src = urlTrang(q.ma, n);
    }
  });

  // Vuốt ngang để lật, như lật sách trên điện thoại. Gắn tay bằng addEventListener thay vì
  // ontouchstart trên <div>: tránh biến thẻ bố cục thành thẻ tương tác (cảnh báo a11y).
  $effect(() => {
    const el = khung;
    if (!el) return;
    let x0 = 0;
    let y0 = 0;
    const batDau = (e: TouchEvent): void => {
      x0 = e.changedTouches[0].clientX;
      y0 = e.changedTouches[0].clientY;
    };
    const ketThuc = (e: TouchEvent): void => {
      const dx = e.changedTouches[0].clientX - x0;
      const dy = e.changedTouches[0].clientY - y0;
      // Vuốt dọc là đang cuộn trang sách, không phải lật.
      if (Math.abs(dx) < 50 || Math.abs(dx) <= Math.abs(dy)) return;
      di(trang + (dx < 0 ? 1 : -1));
    };
    el.addEventListener('touchstart', batDau, { passive: true });
    el.addEventListener('touchend', ketThuc, { passive: true });
    return () => {
      el.removeEventListener('touchstart', batDau);
      el.removeEventListener('touchend', ketThuc);
    };
  });

  function di(n: number): void {
    const q = quyen;
    if (!q) return;
    const dich = Math.min(Math.max(1, Math.round(n) || 1), q.soTrang);
    if (dich === trang) return;
    // Tờ rời đi vẽ đè lên tờ đích rồi quay quanh gáy sách (mép trái), giống lật một tờ giấy thật.
    // Lật lùi thì ngược lại: tờ đích quay từ bên trái trở về.
    if (!media.reducedMotion) {
      lat = dich > trang ? { nen: dich, phu: trang, huong: 'toi' } : { nen: trang, phu: dich, huong: 'lui' };
    }
    trang = dich;
    nhoTrang(q.ma, dich);
    // Trang sách cao hơn màn hình: đang đọc nửa dưới mà lật thì đưa về đầu trang mới, đọc tiếp từ trên xuống.
    const tren = khung?.getBoundingClientRect().top ?? 0;
    if (tren < 0) {
      const dau = document.querySelector<HTMLElement>('.topbar, .mtop')?.offsetHeight ?? 0;
      window.scrollTo({ top: window.scrollY + tren - dau - 12, behavior: media.reducedMotion ? 'auto' : 'smooth' });
    }
  }

  function phim(e: KeyboardEvent): void {
    // Đang gõ số trang thì mũi tên là của ô nhập, không phải của sách.
    const t = e.target as HTMLElement | null;
    if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA')) return;
    if (e.key === 'ArrowRight') di(trang + 1);
    else if (e.key === 'ArrowLeft') di(trang - 1);
    else return;
    e.preventDefault();
  }
</script>

<svelte:head><title>{quyen?.ten ?? 'Giáo trình'} · Zìkǎ</title></svelte:head>
<svelte:window onkeydown={phim} />

{#snippet than()}
  {#if !quyen}
    <EmptyState title="Không có quyển giáo trình này.">
      {#snippet action()}<a href="/learn/books" class="btn btn-sm">Về kệ sách</a>{/snippet}
    </EmptyState>
  {:else if thieuAnh}
    <EmptyState title="Chưa có ảnh trang sách.">
      {#snippet action()}<a href="/learn/books" class="btn btn-sm">Về kệ sách</a>{/snippet}
    </EmptyState>
  {:else}
    <div class="sach" bind:this={khung}>
      <img
        class="to"
        src={urlTrang(quyen.ma, lat ? lat.nen : trang)}
        alt="{quyen.ten} — trang {trang}"
        decoding="async"
        onerror={() => (thieuAnh = true)}
      />
      {#if lat}
        {#key `${lat.phu}-${lat.huong}`}
          <img class="to phu {lat.huong}" src={urlTrang(quyen.ma, lat.phu)} alt="" onanimationend={() => (lat = null)} />
        {/key}
      {/if}
      <button type="button" class="vung trai" disabled={trang <= 1} aria-label="Trang trước" onclick={() => di(trang - 1)}>
        <span class="mui"><Icon name="left" size={20} /></span>
      </button>
      <button type="button" class="vung phai" disabled={trang >= quyen.soTrang} aria-label="Trang sau" onclick={() => di(trang + 1)}>
        <span class="mui"><Icon name="right" size={20} /></span>
      </button>
    </div>
    <div class="dem">
      <input
        class="inp sotrang"
        type="number"
        min="1"
        max={quyen.soTrang}
        value={trang}
        aria-label="Nhảy tới trang"
        onchange={(e) => di(Number(e.currentTarget.value))}
      />
      <span class="muted num">/ {quyen.soTrang}</span>
    </div>
  {/if}
{/snippet}

{#if media.isMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/books" class="btn btn-bare" aria-label="Về kệ sách"><Icon name="left" size={20} /></a>
      <b style="flex: 1; font-size: 15px">{quyen?.ten ?? 'Giáo trình'}</b>
      <Avatar size={36} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 12px">{@render than()}</main>
  </div>
{:else}
  <!-- ============ Máy tính ============ -->
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <nav class="crumb" style="margin-left: 8px"><a href="/learn/books">Giáo trình</a> / <b>{quyen?.ten ?? ''}</b></nav>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 12px"><Switcher current="lt" /><Avatar size={44} /></div>
  </header>
  <main class="page" style="max-width: 900px; display: flex; flex-direction: column; gap: 14px">
    <BackLink href="/learn/books" label="Kệ sách" />
    {@render than()}
  </main>
{/if}

<style>
  /* perspective trên khung mới làm tờ giấy quay có chiều sâu; thiếu nó thì lật ra thành bẹt. */
  .sach {
    position: relative;
    perspective: 1800px;
  }
  .to {
    width: 100%;
    height: auto;
    display: block;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: var(--surface);
  }
  /* Tờ đang lật nằm đè đúng khít tờ dưới — trong cùng một quyển mọi trang cùng khổ nên inset: 0 là vừa. */
  .phu {
    position: absolute;
    inset: 0;
    height: 100%;
    transform-origin: left center;
    backface-visibility: hidden;
    box-shadow: 0 0 26px rgba(0, 0, 0, 0.2);
    z-index: 2;
  }
  .phu.toi {
    animation: lat-toi 0.5s ease-in forwards;
  }
  .phu.lui {
    animation: lat-lui 0.5s ease-out forwards;
  }
  /* Dừng ở 170° chứ không 180°: quay đủ 180 thì tờ giấy nằm đúng mặt phẳng màn hình và loé lên một nhịp. */
  @keyframes lat-toi {
    from {
      transform: rotateY(0deg);
    }
    to {
      transform: rotateY(-170deg);
    }
  }
  @keyframes lat-lui {
    from {
      transform: rotateY(-170deg);
    }
    to {
      transform: rotateY(0deg);
    }
  }
  /* Hai nửa trang bấm được như lật sách giấy; mũi tên chỉ hiện khi rê chuột tới cho đỡ rối mắt. */
  .vung {
    position: absolute;
    top: 0;
    bottom: 0;
    width: 35%;
    display: flex;
    align-items: center;
    padding: 0 12px;
    border: 0;
    background: none;
    cursor: pointer;
    opacity: 0;
    transition: opacity 0.15s ease;
    z-index: 3;
  }
  .vung:hover:not(:disabled),
  .vung:focus-visible {
    opacity: 1;
  }
  .vung:disabled {
    cursor: default;
  }
  .trai {
    left: 0;
    justify-content: flex-start;
  }
  .phai {
    right: 0;
    justify-content: flex-end;
  }
  .mui {
    display: grid;
    place-items: center;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: var(--surface);
    border: 1px solid var(--line);
    box-shadow: var(--sh-sm);
    color: var(--text);
  }
  .dem {
    display: flex;
    align-items: baseline;
    justify-content: center;
    gap: 6px;
    font-size: 14px;
  }
  .sotrang {
    width: 68px;
    text-align: center;
  }
</style>
