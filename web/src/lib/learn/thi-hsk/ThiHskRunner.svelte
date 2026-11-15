<script lang="ts">
  import { onDestroy, onMount, tick } from 'svelte';
  import { media } from '$lib/stores/media.svelte';
  import { session } from '$lib/stores/session.svelte';
  import { homePath } from '$lib/auth/routes';
  import Icon from '$lib/components/Icon.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';
  import DauBai from '$lib/components/DauBai.svelte';
  import { clock } from '$lib/utils/format';
  import { chiaDe, TEN_PHAN, HAN_PHAN, type Phan } from './cau-truc';
  import CauGiay from './CauGiay.svelte';
  import KetQuaHsk from './KetQuaHsk.svelte';
  import { ThiHsk, type CheDo, type CauHoi } from './state.svelte';

  // Màn làm đề Thi thử HSK 3.0 kiểu tờ đề giấy (người dùng chốt 15/11/2026, mẫu ở release/mau-giao-dien/thi-thu-hsk/):
  // cả đề một trang cuộn, chia 一、听力 / 二、阅读 / 三、书写 → 第N部分 như đề giấy, kéo xuống làm từng phần. Đồng hồ
  // và phiếu trả lời luôn đứng yên: máy tính là cột phải dính, điện thoại là thanh trên/dưới + phiếu trượt lên.
  // Luyện tập: mỗi nhóm câu có "Kiểm tra phần này". Thanh riêng như màn Kiểm tra: đang thi thì không có lối sang màn khác.
  // cuocThi: đề cuộc thi (tab Đề thi) — không có danh sách đề để về, không làm lại, tên là tên cuộc thi.
  let { mockId, mode, cuocThi = false }: { mockId: number; mode: CheDo; cuocThi?: boolean } = $props();

  // svelte-ignore state_referenced_locally
  const st = new ThiHsk(mockId, mode);
  onMount(() => void st.tai());
  onDestroy(() => {
    st.huy();
    quan?.disconnect();
  });

  const capDe = $derived(st.at?.mock?.levelId ?? null);
  const veDanhSach = $derived(
    cuocThi ? homePath(session.user?.role, session.features) : capDe ? `/learn/hsk30?cap=${capDe}` : '/learn/hsk30',
  );
  const tenVe = $derived(cuocThi ? 'Về bàn học' : 'Về danh sách đề');
  const tenDe = $derived(
    cuocThi ? (st.at?.contestTitle ?? st.ketQua?.contestTitle ?? 'Cuộc thi') : (st.at?.mock?.title ?? 'Thi thử HSK 3.0'),
  );
  const tenCheDo = $derived(cuocThi ? 'Cuộc thi' : mode === 'practice' ? 'Luyện tập' : 'Thi thật');
  const kicker = $derived(
    !st.at
      ? cuocThi ? 'Cuộc thi' : 'Thi thử HSK 3.0'
      : `${cuocThi ? 'Cuộc thi' : `HSK ${capDe} · ${tenCheDo}`} · ${st.total} câu${st.coGio ? ` · ${Math.round(st.at.durationSec / 60)} phút` : ''}`,
  );
  const dangThi = $derived(st.coGio && st.ketQua === null);
  const conLai = $derived(st.total - st.daLam);
  const de = $derived(chiaDe(st.at?.questions ?? []));
  const phanDangXem = $derived<Phan | null>(st.at?.questions[st.cur]?.section ?? de[0]?.phan ?? null);
  const tienDo = $derived(st.total ? Math.round((st.daLam / st.total) * 100) : 0);

  let moPhieu = $state(false);

  /** Theo dõi câu đang ở giữa màn hình để tô "đang xem" trên phiếu và sáng nút phần tương ứng. */
  let quan: IntersectionObserver | null = null;
  $effect(() => {
    // Gắn lại khi đề hiện xong (đang tải thì còn khung xương, chưa có câu) và khi đổi bố cục máy tính ↔ điện thoại.
    void st.at;
    void st.dangTai;
    void st.ketQua;
    void media.isMobile;
    quan?.disconnect();
    if (!st.at || st.dangTai || st.ketQua || typeof IntersectionObserver === 'undefined') return;
    quan = new IntersectionObserver(
      (es) => {
        for (const e of es) if (e.isIntersecting) st.cur = Number((e.target as HTMLElement).dataset.cau) - 1;
      },
      { rootMargin: '-35% 0px -55% 0px' },
    );
    void tick().then(() => document.querySelectorAll('[data-cau]').forEach((el) => quan?.observe(el)));
  });

  function cuonToi(k: number): void {
    moPhieu = false;
    st.cur = k;
    document.getElementById(`cau-${k + 1}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
  function cuonPhan(phan: Phan): void {
    document.getElementById(`phan-${phan}`)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  /** Tổng kết một nhóm câu sau khi bấm Kiểm tra (luyện tập). */
  function ketQuaBo(ds: CauHoi[]): { kiem: number; dung: number; chuaLam: number } {
    let kiem = 0;
    let dung = 0;
    for (const q of ds) {
      const k = st.kiemTra[q.id];
      if (k?.graded) {
        kiem++;
        if (k.correct) dung++;
      }
    }
    return { kiem, dung, chuaLam: ds.filter((q) => !st.daLamCau(q)).length };
  }
</script>

{#snippet dongHo(m: boolean)}
  <span class="th-dongho" class:th-dongho-gap={st.sapHet} class:th-dongho-m={m} role="timer" aria-label="Thời gian còn lại {clock(st.left * 1000)}">
    <Icon name="clock" size={m ? 16 : 18} />
    <b class="num th-dongho-so">{clock(st.left * 1000)}</b>
    {#if !m || st.sapHet}<span class="th-dongho-chu">{st.sapHet ? 'Sắp hết giờ!' : 'còn lại'}</span>{/if}
  </span>
{/snippet}

<!-- Phiếu trả lời: số câu chia theo phần như phiếu thật; bấm số nào cuộn tới câu đó. 10 ô một hàng cho gọn. -->
{#snippet phieu()}
  {#each st.nhom as g, gi (gi)}
    <div class="ps-nhom" class:on={g.phan === phanDangXem}>
      <p>{TEN_PHAN[g.phan]} · {g.cau[0].k + 1}–{g.cau[g.cau.length - 1].k + 1}</p>
      <div class="ps-luoi" role="group" aria-label="Câu phần {TEN_PHAN[g.phan]}">
        {#each g.cau as { q: x, k } (x.id)}
          <button
            type="button"
            class="ps"
            class:da={st.daLamCau(x)}
            class:dang={k === st.cur}
            class:ps-dung={st.kiemTra[x.id]?.graded && st.kiemTra[x.id]?.correct}
            class:ps-sai={st.kiemTra[x.id]?.graded && !st.kiemTra[x.id]?.correct}
            onclick={() => cuonToi(k)}
            aria-label="Câu {k + 1}{st.daLamCau(x) ? ', đã làm' : ''}"
            aria-current={k === st.cur ? 'true' : undefined}
          >{k + 1}</button>
        {/each}
      </div>
    </div>
  {/each}
  <p class="ps-chu"><span class="ps-mau da"></span>đã làm <span class="ps-mau"></span>chưa làm <span class="ps-mau dang"></span>đang xem</p>
{/snippet}

{#snippet nutPhan(m: boolean)}
  <nav class="th-chip" class:th-chip-m={m} aria-label="Các phần của đề">
    {#each de as ph (ph.phan)}
      <button type="button" class:on={ph.phan === phanDangXem} onclick={() => cuonPhan(ph.phan)}>
        <span class="hz" lang="zh">{ph.han}</span> {ph.ten}
        <i>{ph.bo[0].cau[0].k + 1}–{ph.bo[ph.bo.length - 1].cau[ph.bo[ph.bo.length - 1].cau.length - 1].k + 1}</i>
      </button>
    {/each}
  </nav>
{/snippet}

<!-- Tờ đề: bìa, các phần, nhóm câu, câu. -->
{#snippet giay(m: boolean)}
  {#if st.at}
    {@const at = st.at}
    <div class="th-bia">
      <p class="th-bia-nho">{cuocThi ? 'Cuộc thi' : `HSK 3.0 · ${tenCheDo}`}</p>
      <h2 class="th-bia-ten">{tenDe}</h2>
      <p class="th-bia-de">{st.total} câu{st.coGio ? ` · ${Math.round(at.durationSec / 60)} phút` : ''}</p>
      <div class="th-bia-bang">
        {#each de as ph (ph.phan)}
          <div><b class="hz" lang="zh">{HAN_PHAN[ph.phan]}</b><span>{ph.ten}</span><em>{ph.bo.reduce((s, b) => s + b.cau.length, 0)} câu</em></div>
        {/each}
      </div>
    </div>
    {@render nutPhan(m)}

    {#each de as ph, pi (ph.phan)}
      {#if pi > 0}
        <div class="th-ngan"><p class="hz" lang="zh">下面是{HAN_PHAN[ph.phan]}部分</p><p>Hết phần {de[pi - 1].ten} · Tiếp theo là phần {ph.ten}</p></div>
      {/if}
      <section class="th-phan" id="phan-{ph.phan}" aria-label="Phần {ph.ten}">
        <header class="th-phan-dau">
          <p class="th-phan-han hz" lang="zh">{ph.han}</p>
          <p class="th-phan-ten">{ph.ten} · {ph.bo.reduce((s, b) => s + b.cau.length, 0)} câu</p>
          {#if ph.phan === 'listening' && st.coGio}<p class="th-phan-mo">Mỗi câu nghe tối đa {at.listenLimit} lần.</p>{/if}
        </header>
        {#each ph.bo as bo (bo.so)}
          {@const ds = bo.cau.map((c) => c.q)}
          <div class="th-bo">
            <p class="th-bo-han"><span class="hz" lang="zh">{bo.han}</span> · Phần {bo.so}</p>
            <p class="th-bo-cau"><b>Câu {bo.cau[0].k + 1}{bo.cau.length > 1 ? `–${bo.cau[bo.cau.length - 1].k + 1}` : ''}</b><span>{bo.cachLam}</span></p>
            {#each bo.cau as { q } (q.id)}
              <CauGiay
                {q}
                answer={st.answers[q.id] ?? null}
                onanswer={(v) => st.traLoi(q, v)}
                locked={!!st.kiemTra[q.id] && q.type !== 'speak'}
                kt={st.kiemTra[q.id] ?? null}
                listenLeft={st.conNghe(q)}
                onlisten={() => st.ngheCau(q)}
                py={st.pinyin}
                mobile={m}
                attemptId={at.attemptId}
              />
            {/each}
            {#if st.luyenTap}
              {@const kq = ketQuaBo(ds)}
              <div class="th-kiem">
                {#if kq.kiem > 0}
                  <p class="th-kiem-kq" class:het={kq.dung === kq.kiem}>
                    <b>Phần {bo.so}: đúng {kq.dung}/{kq.kiem} câu đã kiểm</b>{#if kq.chuaLam > 0}<span> · còn {kq.chuaLam} câu chưa làm</span>{/if}
                  </p>
                {/if}
                {#if ds.some((q) => !st.kiemTra[q.id] && st.daLamCau(q))}
                  <button type="button" class="zk-nut zk-nut-chinh" disabled={st.dangKiem} onclick={() => void st.kiemTraPhan(ds)}>
                    <Icon name="check" size={16} /> Kiểm tra phần {bo.so}
                  </button>
                {/if}
              </div>
            {/if}
          </div>
        {/each}
      </section>
    {/each}

    <div class="th-het">
      <p class="hz" lang="zh">全部结束</p>
      <p class="muted">Hết bài · Đã làm {st.daLam}/{st.total} câu</p>
      <button type="button" class="btn btn-son" disabled={st.dangNop} onclick={() => (st.hoiNop = true)}>Nộp bài</button>
    </div>
  {/if}
{/snippet}

{#snippet body(m: boolean)}
  {#if st.loi}
    <EmptyState title="Không mở được đề thi." desc={st.loi}>
      {#snippet action()}<a href={veDanhSach} class="btn btn-sm">{tenVe}</a>{/snippet}
    </EmptyState>
  {:else if st.dangTai || (!st.ketQua && !st.at)}
    <Skeleton h={m ? '420px' : '520px'} r={22} />
  {:else if st.ketQua}
    {#if !m}<DauBai href={veDanhSach} label={cuocThi ? 'Bàn học' : 'Chọn đề'} {kicker} title={tenDe} />{/if}
    <section class="block zk-khung" class:zk-khung-m={m}>
      <KetQuaHsk kq={st.ketQua} mobile={m} onlamlai={cuocThi ? null : () => void st.tai()} ve={veDanhSach} {tenVe} />
    </section>
  {:else if m}
    <main class="block th-giay th-giay-m">{@render giay(true)}</main>
  {:else}
    <div class="th-wrap">
      <main class="block th-giay">{@render giay(false)}</main>
      <!-- Cột phải dính cố định suốt lúc cuộn; phiếu cuộn bên trong nếu màn hình thấp hơn cả cột. -->
      <aside class="th-ben" aria-label="Thời gian và phiếu trả lời">
        <div class="th-the">
          {#if st.coGio}
            {@render dongHo(false)}
          {:else}
            <p class="th-the-ten">{tenCheDo}</p>
          {/if}
          <div class="th-tien"><span style="width: {tienDo}%"></span></div>
          <p class="th-tien-chu">Đã làm <b>{st.daLam}</b>/{st.total} câu</p>
        </div>
        <div class="th-the th-the-phieu">
          <p class="th-the-ten">Phiếu trả lời</p>
          {@render phieu()}
        </div>
        <!-- Chỉ hiện khi cửa sổ hẹp (cột phải xuống đáy, phiếu ẩn): mở phiếu dạng trượt lên như điện thoại. -->
        <button type="button" class="zk-nut th-mo-phieu" onclick={() => (moPhieu = true)}>Phiếu trả lời</button>
        <button type="button" class="btn btn-son th-nop" disabled={st.dangNop} onclick={() => (st.hoiNop = true)}>Nộp bài</button>
      </aside>
    </div>
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop th-mtop">
      {#if !dangThi}<a href={veDanhSach} class="btn btn-bare" aria-label={tenVe}><Icon name="chev" size={20} style="transform: rotate(180deg)" /></a>{/if}
      <b style="flex: 1; min-width: 0; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{tenDe}</b>
      {#if st.coGio && !st.ketQua && st.at}{@render dongHo(true)}{:else}<span class="tag">{tenCheDo}</span>{/if}
      {#if st.at && !st.ketQua}<span class="th-mtien" aria-hidden="true"><span style="width: {tienDo}%"></span></span>{/if}
    </header>
    <div class="mcontent th-mcontent">{@render body(true)}</div>
    {#if st.at && !st.ketQua}
      <div class="th-mday">
        <button type="button" class="zk-nut" onclick={() => (moPhieu = true)}>Phiếu trả lời · {st.daLam}/{st.total}</button>
        <button type="button" class="btn btn-son" disabled={st.dangNop} onclick={() => (st.hoiNop = true)}>Nộp bài</button>
      </div>
    {/if}
  </div>
{:else}
  <header class="topbar">
    <nav class="crumb">{#if !dangThi}<a href={veDanhSach}>{cuocThi ? 'Bàn học' : 'Thi thử HSK 3.0'}</a> / {/if}<b>{tenDe}</b></nav>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 10px">
      <span class="tag">{dangThi ? 'Đang thi' : tenCheDo}</span>
      {#if !dangThi}<Avatar size={44} />{/if}
    </div>
  </header>
  <div class="page th-trang">{@render body(false)}</div>
{/if}

<!-- Phiếu trả lời trượt lên từ đáy: điện thoại, và máy tính khi cửa sổ hẹp. -->
{#if moPhieu && st.at && !st.ketQua}
  <button type="button" class="th-man" aria-label="Đóng phiếu trả lời" onclick={() => (moPhieu = false)}></button>
  <div class="th-sheet" role="dialog" aria-label="Phiếu trả lời">
    <span class="th-sheet-tay" aria-hidden="true"></span>
    <div class="th-sheet-dau">
      <b>Phiếu trả lời</b><span>Đã làm {st.daLam}/{st.total}</span>
      <button type="button" class="th-dong" aria-label="Đóng" onclick={() => (moPhieu = false)}><Icon name="x" size={18} /></button>
    </div>
    {@render phieu()}
    <button type="button" class="btn btn-son th-nop" disabled={st.dangNop} onclick={() => { moPhieu = false; st.hoiNop = true; }}>Nộp bài</button>
  </div>
{/if}

{#if st.hoiNop}
  <Modal title="Nộp bài?" onclose={() => (st.hoiNop = false)}>
    <p style="font-size: 14px">
      Bạn đã làm {st.daLam}/{st.total} câu{conLai > 0 ? ` — còn ${conLai} câu chưa trả lời` : ''}.
      {st.luyenTap ? 'Nộp để xem tổng kết từng phần.' : 'Nộp rồi thì không sửa được nữa.'}
    </p>
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (st.hoiNop = false)}>Làm tiếp</button>
      <button type="button" class="btn btn-son btn-sm" disabled={st.dangNop} onclick={() => void st.nop(false)}>Nộp bài</button>
    {/snippet}
  </Modal>
{/if}

{#if st.hetGio}
  <Modal title="Hết giờ" closable={false} onclose={() => {}}>
    <p style="font-size: 14px">Bài của bạn đang được nộp với các câu đã làm, chờ một chút nhé.</p>
  </Modal>
{/if}

<style>
  .th-trang { display: flex; flex-direction: column; gap: 18px; max-width: 1200px; }
  .th-wrap { display: grid; grid-template-columns: minmax(0, 1fr) 290px; gap: 24px; align-items: start; }
  .th-giay { padding: 32px 40px 38px; }
  .th-giay-m { padding: 18px 14px 24px; border-radius: 20px; }

  /* Bìa đề */
  .th-bia { text-align: center; padding-bottom: 20px; border-bottom: 1px solid var(--line); }
  .th-bia-nho { font-size: 12.5px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--text-2); }
  .th-bia-ten { margin: 6px 0 2px; font-size: 28px; font-weight: 800; }
  .th-giay-m .th-bia-ten { font-size: 22px; }
  .th-bia-de { font-size: 14px; color: var(--text-2); }
  .th-bia-bang { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: 8px; max-width: 560px; margin: 16px auto 0; }
  .th-bia-bang div { display: flex; flex-direction: column; align-items: center; gap: 2px; padding: 10px 6px; border: 1px solid var(--line); border-radius: 12px; background: var(--surface-2); }
  .th-bia-bang b { font-size: 18px; }
  .th-bia-bang span { font-size: 13px; font-weight: 700; }
  .th-bia-bang em { font-style: normal; font-size: 12px; color: var(--text-2); }

  /* Dải nút phần: dính trên đầu tờ đề (điện thoại: dính ngay dưới thanh trên 56px). */
  .th-chip { position: sticky; top: 0; z-index: 3; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin: 16px -12px 4px; padding: 10px 12px; background: var(--surface); border-bottom: 1px solid var(--line); }
  .th-chip button { display: inline-flex; align-items: center; gap: 6px; height: 40px; padding: 0 16px; border: 1px solid var(--line-3); border-radius: 999px; background: var(--surface); font: 600 14px/1 var(--font-ui); color: var(--text-2); cursor: pointer; }
  .th-chip button i { font-style: normal; font-size: 12px; color: var(--text-3); }
  .th-chip button.on { border-color: var(--son-line); background: var(--son-soft); color: var(--son-text); }
  .th-chip-m { top: 56px; flex-wrap: nowrap; justify-content: flex-start; overflow-x: auto; margin: 12px -14px 4px; padding: 8px 14px; scrollbar-width: none; }
  .th-chip-m button { flex: none; height: 36px; padding: 0 12px; font-size: 13px; }

  /* Phần và nhóm câu như đề giấy */
  .th-phan { scroll-margin-top: 70px; }
  .th-phan-dau { text-align: center; margin: 28px 0 4px; }
  .th-phan-han { font-size: 26px; font-weight: 700; letter-spacing: .1em; }
  .th-phan-ten { font-size: 13px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: var(--son-text); }
  .th-phan-mo { margin-top: 4px; font-size: 13.5px; color: var(--text-2); }
  .th-bo { margin-top: 22px; }
  .th-bo-han { text-align: center; font-size: 15px; font-weight: 700; color: var(--text-2); }
  .th-bo-han .hz { font-size: 17px; color: var(--text); }
  .th-bo-cau { display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; margin: 10px 0 4px; font-size: 14px; }
  .th-bo-cau b { padding: 2px 10px; border-radius: 999px; background: var(--text); color: var(--surface); font-size: 12.5px; }
  .th-bo-cau span { color: var(--text-2); }
  .th-kiem { display: flex; flex-direction: column; align-items: center; gap: 10px; margin-top: 10px; }
  .th-kiem-kq { align-self: stretch; padding: 10px 14px; border: 1px solid var(--son-line); border-radius: 12px; background: var(--son-soft); font-size: 14px; }
  .th-kiem-kq b { color: var(--son-text); }
  .th-kiem-kq.het { border-color: var(--line); background: var(--ngoc-soft); }
  .th-kiem-kq.het b { color: var(--green-text); }
  .th-ngan { margin: 32px 0 4px; padding: 14px; border-top: 1px dashed var(--ink-strong); border-bottom: 1px dashed var(--ink-strong); text-align: center; }
  .th-ngan .hz { font-size: 18px; letter-spacing: .15em; }
  .th-ngan p + p { margin-top: 2px; font-size: 13px; color: var(--text-2); }
  .th-het { display: flex; flex-direction: column; align-items: center; gap: 8px; margin-top: 28px; padding-top: 20px; border-top: 1px solid var(--line); text-align: center; }
  .th-het .hz { font-size: 18px; letter-spacing: .2em; }
  .th-het .btn { min-height: 48px; padding: 0 28px; }

  /* Cột phải (máy tính) */
  .th-ben { position: sticky; top: 16px; display: flex; flex-direction: column; gap: 12px; max-height: calc(100vh - 32px); }
  .th-the { padding: 16px; border: 1px solid var(--line); border-radius: 18px; background: var(--surface); box-shadow: var(--sh-sm); }
  .th-the-phieu { min-height: 0; overflow-y: auto; }
  .th-the-ten { margin-bottom: 8px; font-size: 13px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--text-2); }
  .th-tien { height: 8px; margin: 12px 0 6px; border: 1px solid var(--line); border-radius: 99px; background: var(--surface-2); overflow: hidden; }
  .th-tien span { display: block; height: 100%; background: var(--son); transition: width .2s ease; }
  .th-tien-chu { font-size: 13px; color: var(--text-2); }
  .th-nop { width: 100%; min-height: 48px; justify-content: center; }
  .th-mo-phieu { display: none; }

  .th-dongho { display: inline-flex; align-items: center; gap: 8px; color: var(--text-2); white-space: nowrap; }
  .th-dongho-so { font-size: 28px; color: var(--text); }
  .th-dongho-chu { font-size: 13px; }
  .th-dongho-gap .th-dongho-so, .th-dongho-gap .th-dongho-chu { color: var(--bad-text); }
  .th-dongho-m { height: 36px; padding: 0 12px; border: 1px solid var(--line); border-radius: 999px; background: var(--surface-2); }
  .th-dongho-m .th-dongho-so { font-size: 17px; }
  .th-dongho-m.th-dongho-gap { border-color: var(--son-line); background: var(--son-soft); }

  /* Phiếu trả lời */
  .ps-nhom { padding: 8px; border-radius: 12px; }
  .ps-nhom.on { background: var(--surface-2); }
  .ps-nhom p { margin-bottom: 6px; font-size: 12.5px; font-weight: 700; color: var(--text-2); }
  .ps-luoi { display: grid; grid-template-columns: repeat(10, minmax(0, 1fr)); gap: 4px; }
  .ps { display: grid; place-items: center; height: 28px; padding: 0; border: 1px solid var(--line-3); border-radius: 6px; background: var(--surface); font: 700 11.5px/1 var(--font-ui); color: var(--text-2); cursor: pointer; }
  .ps.da { border-color: var(--text); background: var(--text); color: var(--surface); }
  .ps.ps-dung { border-color: var(--ngoc); background: var(--ngoc); color: #fff; }
  .ps.ps-sai { border-color: #dc2626; background: #dc2626; color: #fff; }
  .ps.dang { box-shadow: 0 0 0 2px var(--son); }
  .ps-chu { display: flex; align-items: center; flex-wrap: wrap; gap: 4px; margin-top: 8px; font-size: 12px; color: var(--text-2); }
  .ps-mau { display: inline-block; width: 13px; height: 13px; margin-left: 6px; border: 1px solid var(--line-3); border-radius: 4px; background: var(--surface); }
  .ps-mau.da { border-color: var(--text); background: var(--text); }
  .ps-mau.dang { box-shadow: 0 0 0 2px var(--son); }

  /* Điện thoại */
  .th-mtop { position: sticky; }
  .th-mtien { position: absolute; left: 0; right: 0; bottom: -1px; height: 3px; background: var(--line); }
  .th-mtien span { display: block; height: 100%; background: var(--son); }
  .th-mcontent { padding: 12px 12px 16px; }
  .th-mday { position: sticky; bottom: 0; z-index: 20; display: flex; gap: 10px; padding: 10px 14px calc(12px + env(safe-area-inset-bottom)); background: var(--surface); border-top: 1px solid var(--line); box-shadow: 0 -6px 18px rgb(0 0 0 / .06); }
  .th-mday > * { flex: 1; min-height: 48px; justify-content: center; }
  .th-man { position: fixed; inset: 0; z-index: 40; border: 0; background: rgb(15 18 24 / .45); }
  .th-sheet { position: fixed; left: 0; right: 0; bottom: 0; z-index: 41; max-height: 85vh; overflow-y: auto; padding: 8px 14px calc(18px + env(safe-area-inset-bottom)); border-radius: 20px 20px 0 0; background: var(--surface); box-shadow: 0 -10px 30px rgb(0 0 0 / .15); }
  .th-sheet-tay { display: block; width: 40px; height: 4px; margin: 0 auto 10px; border-radius: 99px; background: var(--line-3); }
  .th-sheet-dau { display: flex; align-items: center; gap: 10px; margin-bottom: 6px; }
  .th-sheet-dau b { flex: 1; font-size: 16px; }
  .th-sheet-dau span { font-size: 13px; color: var(--text-2); }
  .th-dong { display: grid; place-items: center; width: 40px; height: 40px; border: 1px solid var(--line); border-radius: 50%; background: var(--surface); color: var(--text-2); cursor: pointer; }
  .th-sheet .ps { height: 34px; }
  .th-sheet .th-nop { margin-top: 12px; }

  /* Máy tính bảng / cửa sổ hẹp: không đủ chỗ cho cột phải thì cột xuống dưới tờ đề, vẫn dính đáy màn hình. */
  @media (max-width: 1080px) {
    .th-wrap { grid-template-columns: 1fr; }
    .th-ben { position: sticky; top: auto; bottom: 0; z-index: 5; flex-direction: row; flex-wrap: wrap; align-items: center; max-height: none; padding: 10px; border: 1px solid var(--line); border-radius: 16px; background: var(--surface); box-shadow: var(--sh); }
    .th-ben .th-the { flex: 1 1 200px; padding: 10px 12px; box-shadow: none; }
    .th-ben .th-the-phieu { display: none; }
    .th-ben .th-mo-phieu { display: inline-flex; }
    .th-ben .th-nop { flex: 0 0 auto; width: auto; padding: 0 24px; }
  }
</style>
