<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { media } from '$lib/stores/media.svelte';
  import { toast } from '$lib/stores/toast.svelte';
  import Topbar from '$lib/components/Topbar.svelte';
  import MTopbar from '$lib/components/MTopbar.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';
  import { daXem, QuanTra, taiCacBan, type Ban, type TinNhan } from '$lib/learn/quan-tra.svelte';

  // Quán trà · một bàn: trò chuyện theo chủ đề của bàn (Hỏi bài, Mẹo học…). Tin mới đến mỗi 4 giây.
  heartbeat.setActivity({ part: null, screen: 'chat' });

  const slug = $derived(page.params.ban ?? 'chung');
  let st = $state(new QuanTra(page.params.ban ?? 'chung'));
  let cacBan = $state<Ban[]>([]);
  let khung: HTMLDivElement | undefined = $state();
  let o: HTMLTextAreaElement | undefined = $state();
  let moTin = $state<number | null>(null);
  let baoCao = $state<TinNhan | null>(null);
  let lyDo = $state('Nói tục, xúc phạm');
  let xemNoiQuy = $state(false);
  const LY_DO = ['Nói tục, xúc phạm', 'Spam, quảng cáo', 'Lừa đảo', 'Nội dung không phù hợp', 'Khác'];

  // Đổi bàn (/learn/chat/chung → /hoi-bai) là cùng route, component không dựng lại (bẫy số 2): mỗi lần đổi mã bàn thì
  // dựng trạng thái bàn mới, dừng hỏi tin của bàn cũ.
  $effect(() => {
    const s = new QuanTra(slug);
    // Cuộn theo trang (không phải khung riêng): ô gõ dính đáy màn hình, danh sách tin là nội dung trang.
    s.oCuoi = () => window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 160;
    s.cuonXuong = () => {
      window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'instant' });
      s.tinMoiChuaXem = 0;
    };
    st = s;
    moTin = null;
    void s.batDau();
    void taiCacBan().then((r) => (cacBan = r.rooms)).catch(() => {});
    return () => s.dung();
  });

  // Tin cuối đang hiện coi như đã xem: danh sách bàn thôi báo "tin mới" của bàn này.
  $effect(() => {
    void st.tin.length;
    st.ghiDaXem();
  });

  onMount(() => {
    const hien = () => {
      if (document.visibilityState === 'visible') void st.hoiMoi();
    };
    const cuon = () => {
      if (st.tinMoiChuaXem && st.oCuoi()) st.tinMoiChuaXem = 0;
    };
    document.addEventListener('visibilitychange', hien);
    window.addEventListener('scroll', cuon, { passive: true });
    return () => {
      document.removeEventListener('visibilitychange', hien);
      window.removeEventListener('scroll', cuon);
    };
  });

  /** Máy tính: Enter gửi, Shift+Enter xuống dòng. Điện thoại: Enter xuống dòng (bàn phím ảo khó bấm Shift). */
  function phim(e: KeyboardEvent): void {
    if (e.key === 'Enter' && !e.shiftKey && !media.isMobile && !e.isComposing) {
      e.preventDefault();
      void st.gui();
    }
  }

  function traLoi(t: TinNhan): void {
    st.traLoi = t;
    moTin = null;
    o?.focus();
  }

  async function guiBaoCao(): Promise<void> {
    if (!baoCao) return;
    if (await st.baoCao(baoCao, lyDo)) toast.ok('Đã gửi báo cáo. Cảm ơn bạn giữ Quán trà sạch đẹp!');
    baoCao = null;
  }

  const gio = (iso: string) => new Date(iso).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
  const ngay = (iso: string) => {
    const d = new Date(iso);
    const homNay = new Date();
    const homQua = new Date(Date.now() - 86400000);
    if (d.toDateString() === homNay.toDateString()) return 'Hôm nay';
    if (d.toDateString() === homQua.toDateString()) return 'Hôm qua';
    return d.toLocaleDateString('vi-VN', { weekday: 'long', day: '2-digit', month: '2-digit', year: 'numeric' });
  };
  /** Tin liền nhau của cùng người trong 5 phút thì gộp, không lặp tên và ảnh. */
  const lienTiep = (a: TinNhan | undefined, b: TinNhan) =>
    !!a && a.user.id === b.user.id && new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime() < 300000
    && new Date(a.createdAt).toDateString() === new Date(b.createdAt).toDateString();
  const khacNgay = (a: TinNhan | undefined, b: TinNhan) => !a || new Date(a.createdAt).toDateString() !== new Date(b.createdAt).toDateString();
  const camDen = $derived(st.biCam ? new Date(st.biCam).toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' }) : '');
</script>

<svelte:head><title>{st.ban?.name ?? 'Quán trà'} · Quán trà · Zìkǎ</title></svelte:head>

{#snippet noiDungTrang(mobile: boolean)}
  <!-- Chuyển bàn nhanh, không phải quay về danh sách. -->
  {#if cacBan.length}
    <nav class="qt-ban-ds" aria-label="Các bàn ở Quán trà">
      {#each cacBan as b (b.slug)}
        <a href="/learn/chat/{b.slug}" class="qt-ban-chip" class:on={b.slug === slug} aria-current={b.slug === slug ? 'page' : undefined}>
          <span aria-hidden="true">{b.icon}</span>{b.name}
          {#if b.slug !== slug && (b.lastId ?? 0) > daXem(b.slug)}<i class="qt-cham" title="Có tin mới"></i>{/if}
        </a>
      {/each}
    </nav>
  {/if}
  <div class="qt-dau">
    <div>
      {#if !mobile}<a href="/learn/chat" class="qt-ve"><Icon name="left" size={14} />Quán trà</a>{/if}
      <h1 style="font-size: {mobile ? 22 : 30}px"><span class="qt-icon" aria-hidden="true">{st.ban?.icon ?? '🍵'}</span>{st.ban?.name ?? 'Quán trà'}</h1>
      {#if st.ban?.description}<p class="muted">{st.ban.description}</p>{/if}
    </div>
    <button type="button" class="btn btn-sm" aria-expanded={xemNoiQuy} onclick={() => (xemNoiQuy = !xemNoiQuy)}><Icon name="list" size={15} />Nội quy</button>
  </div>
  {#if xemNoiQuy}
    <ul class="qt-noi-quy">
      <li>Lịch sự, tôn trọng nhau. Không nói tục, xúc phạm, phân biệt.</li>
      <li>Không quảng cáo, rao vặt, chia sẻ link lạ hay thông tin cá nhân (số điện thoại, tài khoản ngân hàng).</li>
      <li>Không chia sẻ tài liệu có bản quyền.</li>
      <li>Thấy tin xấu thì bấm <b>Báo cáo</b>; quản trị sẽ xoá tin và có thể cấm trò chuyện.</li>
    </ul>
  {/if}

  {#if !st.daTai && !st.loi}
    <div class="qt-ds" aria-hidden="true">
      {#each [1, 2, 3, 4] as i (i)}<div class="qt-tin"><Skeleton w="36px" h="36px" r={999} /><Skeleton w="60%" h="44px" r={14} /></div>{/each}
    </div>
  {:else if !st.daTai}
    <EmptyState title={st.loi}>{#snippet action()}<button type="button" class="btn btn-sm" onclick={() => st.taiDau()}>Thử lại</button>{/snippet}</EmptyState>
  {:else}
    <div class="qt-ds" bind:this={khung} role="log" aria-live="polite" aria-label="Tin nhắn Quán trà">
      {#if st.conCu}
        <button type="button" class="btn btn-sm qt-cu" disabled={st.dangTaiCu} onclick={() => st.taiCu()}>{st.dangTaiCu ? 'Đang tải…' : 'Xem tin cũ hơn'}</button>
      {/if}
      {#if !st.tin.length}
        <p class="qt-trong">Bàn này còn vắng. Hãy là người mở lời đầu tiên nhé! 🍵</p>
      {/if}
      {#each st.tin as t, i (t.id)}
        {@const truoc = st.tin[i - 1]}
        {#if khacNgay(truoc, t)}<p class="qt-ngay"><span>{ngay(t.createdAt)}</span></p>{/if}
        {@const gop = lienTiep(truoc, t)}
        <div class="qt-tin" class:qt-cua-minh={t.mine} class:qt-gop={gop}>
          {#if !t.mine}
            <span class="qt-anh" aria-hidden="true">
              {#if !gop}{#if t.user.avatarUrl}<img src={t.user.avatarUrl} alt="" referrerpolicy="no-referrer" />{:else}{t.user.initials}{/if}{/if}
            </span>
          {/if}
          <div class="qt-than">
            {#if !gop && !t.mine}
              <span class="qt-ten">{t.user.name}{#if t.user.staff}<span class="qt-qt">Quản trị</span>{/if}</span>
            {/if}
            <div class="qt-bong" class:qt-xoa={t.deleted}>
              {#if t.replyTo && !t.deleted}
                <span class="qt-trich"><b>{t.replyTo.name}</b>{t.replyTo.deleted ? 'Tin đã bị xoá' : t.replyTo.body}</span>
              {/if}
              {#if t.deleted}<i>Tin đã bị xoá</i>{:else}<span class="qt-chu">{t.body}</span>{/if}
              <span class="qt-gio num">{gio(t.createdAt)}{#if st.quanTri && t.reports}<span class="qt-bao" title="Số lần bị báo cáo"> · ⚑ {t.reports}</span>{/if}</span>
            </div>
          </div>
          {#if !t.deleted}
            <div class="qt-menu">
              <button type="button" class="qt-nut" aria-label="Thao tác với tin của {t.user.name}" aria-expanded={moTin === t.id} onclick={() => (moTin = moTin === t.id ? null : t.id)}><Icon name="dots" size={16} /></button>
              {#if moTin === t.id}
                <div class="qt-chon paper">
                  <button type="button" onclick={() => traLoi(t)}>Trả lời</button>
                  {#if t.mine || st.quanTri}<button type="button" onclick={() => { moTin = null; void st.xoa(t); }}>Xoá tin</button>{/if}
                  <!-- Tin của quản trị không báo cáo được (server cũng chặn): có ý kiến thì gửi Góp ý. -->
                  {#if !t.mine && !t.user.staff}<button type="button" onclick={() => { moTin = null; baoCao = t; }}>Báo cáo</button>{/if}
                </div>
              {/if}
            </div>
          {/if}
        </div>
      {/each}
    </div>

    {#if st.tinMoiChuaXem}
      <button type="button" class="qt-moi" onclick={() => st.cuonXuong()}><Icon name="down" size={16} />{st.tinMoiChuaXem} tin mới</button>
    {/if}

    <!-- Ô gõ dính đáy màn hình. -->
    <form class="qt-go" onsubmit={(e) => { e.preventDefault(); void st.gui(); }}>
      {#if !st.mo}
        <p class="qt-bao-dong">Quán trà đang tạm đóng cửa. Bạn vẫn đọc được tin cũ.</p>
      {:else if st.ban && !st.ban.open}
        <p class="qt-bao-dong">Bàn này đang đóng, chỉ đọc được tin cũ. Mời bạn sang bàn khác nhé.</p>
      {:else if st.biCam !== null}
        <p class="qt-bao-dong">{st.biCam === '' ? 'Bạn đã bị cấm trò chuyện ở Quán trà.' : `Bạn đang bị tạm cấm trò chuyện đến ${camDen}.`}</p>
      {:else}
        {#if st.traLoi}
          <div class="qt-dang-tl">
            <span>Trả lời <b>{st.traLoi.user.name}</b>: {st.traLoi.body.slice(0, 80)}</span>
            <button type="button" class="btn btn-bare btn-sm" aria-label="Bỏ trả lời" onclick={() => (st.traLoi = null)}><Icon name="x" size={15} /></button>
          </div>
        {/if}
        {#if st.loi}<p class="qt-loi" role="alert">{st.loi}</p>{/if}
        <div class="qt-hang">
          <label class="sr-only" for="qt-o">Tin nhắn</label>
          <textarea id="qt-o" class="inp qt-o" rows="1" maxlength="1000" placeholder={media.isMobile ? 'Nhắn gì đó…' : 'Nhắn gì đó… (Enter để gửi, Shift+Enter xuống dòng)'}
            bind:this={o} bind:value={st.noiDung} onkeydown={phim}></textarea>
          <button type="submit" class="btn btn-son qt-gui" disabled={st.dangGui || !st.noiDung.trim()} aria-label="Gửi"><Icon name="right" size={18} /></button>
        </div>
        {#if st.noiDung.length > 800}<p class="qt-dem num">{st.noiDung.length}/1000</p>{/if}
      {/if}
    </form>
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <MTopbar back="/learn/chat" backLabel="Về Quán trà" title={st.ban?.name ?? 'Quán trà'} sub="Quán trà" />
    <main class="mcontent qt-trang">{@render noiDungTrang(true)}</main>
  </div>
{:else}
  <Topbar />
  <main class="page qt-trang" style="max-width: 820px">{@render noiDungTrang(false)}</main>
{/if}

{#if baoCao}
  <Modal title="Báo cáo tin nhắn" onclose={() => (baoCao = null)} width={440} anchor="top">
    <p class="qt-bc-tin"><b>{baoCao.user.name}:</b> {baoCao.body.slice(0, 160)}</p>
    <div class="qt-ly-do" role="radiogroup" aria-label="Lý do">
      {#each LY_DO as l (l)}
        <button type="button" role="radio" aria-checked={lyDo === l} class:on={lyDo === l} onclick={() => (lyDo = l)}>{l}</button>
      {/each}
    </div>
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (baoCao = null)}>Thôi</button>
      <button type="button" class="btn btn-son btn-sm" onclick={guiBaoCao}>Gửi báo cáo</button>
    {/snippet}
  </Modal>
{/if}

<style>
  /* Trang cao ít nhất bằng màn hình, danh sách tin giãn ra: ô gõ luôn sát đáy như ứng dụng chat, kể cả khi ít tin. */
  .qt-trang { display: flex; flex-direction: column; gap: 14px; padding-bottom: 0; min-height: calc(100dvh - 61px); }
  .mscreen .qt-trang { min-height: calc(100dvh - 57px); }
  .qt-dau { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
  .qt-dau h1 { display: flex; align-items: center; gap: 10px; line-height: 1.2; }
  .qt-dau .muted { margin-top: 4px; font-size: 14px; }
  .qt-icon {
    display: grid; place-items: center; width: 1.5em; height: 1.5em; border-radius: 12px; background: #DDEFD9; color: #2F5D2A;
    font-family: var(--font-mix); font-size: .8em; font-weight: 700;
  }
  .qt-ve { display: inline-flex; align-items: center; gap: 4px; margin-bottom: 4px; font-size: 13px; font-weight: 600; color: var(--son-text); text-decoration: none; }
  .qt-ban-ds { display: flex; gap: 6px; overflow-x: auto; scrollbar-width: none; margin: 0 -4px; padding: 2px 4px; }
  .qt-ban-ds::-webkit-scrollbar { display: none; }
  .qt-ban-chip {
    position: relative; flex: none; display: inline-flex; align-items: center; gap: 6px; height: 34px; padding: 0 12px;
    border: 1px solid var(--line); border-radius: 999px; background: var(--surface); color: var(--text-2);
    font-size: 13px; font-weight: 600; text-decoration: none; white-space: nowrap;
  }
  .qt-ban-chip:hover { border-color: var(--line-3); color: var(--text); }
  .qt-ban-chip.on { background: #DDEFD9; border-color: #B9DAB2; color: #2F5D2A; }
  .qt-cham { width: 8px; height: 8px; border-radius: 50%; background: var(--son); }
  .qt-noi-quy {
    margin: 0; padding: 12px 16px 12px 32px; border: 1px solid var(--line); border-radius: 14px; background: var(--surface);
    font-size: 13.5px; line-height: 1.6; color: var(--text-2);
  }

  .qt-ds { flex: 1; display: flex; flex-direction: column; justify-content: flex-end; gap: 2px; padding-bottom: 12px; }
  .qt-cu { align-self: center; margin-bottom: 8px; }
  .qt-trong { align-self: center; padding: 28px 12px; color: var(--text-2); font-size: 14px; text-align: center; }
  .qt-ngay { display: flex; justify-content: center; margin: 12px 0 6px; }
  .qt-ngay span { padding: 3px 12px; border-radius: 999px; background: var(--surface-3); color: var(--text-2); font-size: 12px; font-weight: 600; }

  .qt-tin { position: relative; display: flex; align-items: flex-end; gap: 8px; margin-top: 8px; }
  .qt-gop { margin-top: 2px; }
  .qt-cua-minh { justify-content: flex-end; }
  .qt-anh {
    flex: none; display: grid; place-items: center; width: 34px; height: 34px; border-radius: 50%; overflow: hidden;
    background: var(--surface-3); color: var(--text-2); font-size: 12px; font-weight: 700;
  }
  .qt-gop .qt-anh { background: none; }
  .qt-anh img { width: 100%; height: 100%; object-fit: cover; }
  .qt-than { display: flex; flex-direction: column; gap: 3px; max-width: min(78%, 560px); min-width: 0; }
  .qt-cua-minh .qt-than { align-items: flex-end; }
  .qt-ten { display: flex; align-items: center; gap: 6px; padding-left: 4px; font-size: 12.5px; font-weight: 700; color: var(--text-2); }
  .qt-qt { padding: 1px 7px; border-radius: 99px; background: var(--son-soft); color: var(--son-text); font-size: 10.5px; }
  .qt-bong {
    display: flex; flex-direction: column; gap: 4px; padding: 8px 12px 6px; border-radius: 16px 16px 16px 6px;
    background: var(--surface); border: 1px solid var(--line); font-size: 14.5px; line-height: 1.5;
  }
  .qt-cua-minh .qt-bong { border-radius: 16px 16px 6px 16px; background: var(--son-soft); border-color: var(--son-line); }
  .qt-xoa { background: transparent !important; border-style: dashed; color: var(--text-3); }
  .qt-chu { white-space: pre-wrap; overflow-wrap: anywhere; }
  .qt-trich {
    display: flex; flex-direction: column; padding: 4px 8px; border-left: 3px solid var(--son); border-radius: 6px;
    background: var(--surface-2); font-size: 12.5px; color: var(--text-2); overflow: hidden; max-height: 3.4em;
  }
  .qt-trich b { color: var(--text); font-size: 12px; }
  .qt-gio { align-self: flex-end; font-size: 11px; color: var(--text-3); }
  .qt-bao { color: var(--bad-text); font-weight: 700; }

  .qt-menu { position: relative; align-self: center; }
  .qt-nut {
    display: grid; place-items: center; width: 28px; height: 28px; padding: 0; border: 0; border-radius: 50%;
    background: none; color: var(--text-3); cursor: pointer; opacity: .55;
  }
  .qt-tin:hover .qt-nut, .qt-nut[aria-expanded='true'], .qt-nut:focus-visible { opacity: 1; background: var(--surface-3); }
  .qt-cua-minh .qt-menu { order: -1; }
  .qt-chon {
    position: absolute; z-index: 5; bottom: calc(100% + 4px); right: 0; display: flex; flex-direction: column; min-width: 130px;
    padding: 4px; border-radius: 12px; box-shadow: var(--sh-md);
  }
  .qt-cua-minh .qt-chon { right: auto; left: 0; }
  .qt-chon button { padding: 8px 10px; border: 0; border-radius: 8px; background: none; text-align: left; font: inherit; font-size: 13.5px; cursor: pointer; color: var(--text); }
  .qt-chon button:hover { background: var(--surface-2); }

  .qt-moi {
    position: fixed; z-index: 15; left: 50%; bottom: 96px; transform: translateX(-50%);
    display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border: 0; border-radius: 999px;
    background: var(--son); color: var(--on-accent); font: 700 13px var(--font-ui); box-shadow: var(--sh-md); cursor: pointer;
  }

  .qt-go {
    position: sticky; bottom: 0; z-index: 12; display: flex; flex-direction: column; gap: 6px;
    margin: 0 -4px; padding: 10px 4px calc(12px + env(safe-area-inset-bottom)); background: var(--ground);
    border-top: 1px solid var(--line);
  }
  .qt-hang { display: flex; align-items: flex-end; gap: 8px; }
  .qt-o { flex: 1; min-height: 44px; max-height: 140px; resize: none; field-sizing: content; font: inherit; line-height: 1.45; }
  .qt-gui { flex: none; width: 44px; height: 44px; padding: 0; border-radius: 50%; }
  .qt-dang-tl {
    display: flex; align-items: center; gap: 8px; padding: 6px 6px 6px 12px; border-left: 3px solid var(--son); border-radius: 8px;
    background: var(--surface); font-size: 13px; color: var(--text-2);
  }
  .qt-dang-tl span { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .qt-bao-dong { padding: 12px; border-radius: 12px; background: var(--bo-soft); color: var(--warn-text); font-size: 14px; text-align: center; }
  .qt-loi { font-size: 13px; color: var(--bad-text); }
  .qt-dem { text-align: right; font-size: 12px; color: var(--text-3); }

  .qt-bc-tin { padding: 10px 12px; border-radius: 10px; background: var(--surface-2); font-size: 13.5px; line-height: 1.5; }
  .qt-ly-do { display: flex; flex-wrap: wrap; gap: 6px; }
  .qt-ly-do button {
    height: 32px; padding: 0 12px; border: 1px solid var(--line); border-radius: 999px; background: var(--surface);
    color: var(--text-2); font: 600 13px var(--font-ui); cursor: pointer;
  }
  .qt-ly-do button.on { background: var(--son-soft); border-color: var(--son-line); color: var(--son-text); }
</style>
