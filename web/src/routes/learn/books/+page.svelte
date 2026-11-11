<script lang="ts">
  import MTopbar from '$lib/components/MTopbar.svelte';
  import { onMount } from 'svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { media } from '$lib/stores/media.svelte';
  import Topbar from '$lib/components/Topbar.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import { dinhDangPhut, quyenGan, taiKeSach, trangDaDoc, urlBia, type TomTatSach } from '$lib/learn/sach';
  import { diemDoc } from '$lib/learn/diem-doc.svelte';
  import { session } from '$lib/stores/session.svelte';
  import { loginUrl } from '$lib/auth/routes';

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
  onMount(() => {
    void tai();
    void diemDoc.refresh();
    // Nhận điểm xong ở tab Shopee rồi quay lại: số điểm và quyển vừa mở khoá phải hiện đúng ngay.
    const lamMoi = () => {
      if (document.visibilityState !== 'visible') return;
      void diemDoc.refresh();
      void tai();
    };
    document.addEventListener('visibilitychange', lamMoi);
    return () => document.removeEventListener('visibilitychange', lamMoi);
  });

  let dangNhan = $state(false);
  async function nhanDiem(): Promise<void> {
    dangNhan = true;
    try {
      await diemDoc.nhan();
    } finally {
      dangNhan = false;
    }
  }

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
  const docTiep = $derived(ds?.find((q) => q.ma === gan && !q.khoa && (dangDoc[q.ma] ?? 1) > 1));
  const tongBai = $derived((ds ?? []).reduce((t, q) => t + q.soBai, 0));
  // Chỉ kể những gì vai trò này đang được dùng: tắt File nghe/Đáp án thì đừng hứa "nghe", "so đáp án".
  const viec = $derived(['đọc sách', session.can('books.audio') && 'nghe file ghi âm', session.can('books.answers') && 'so đáp án'].filter(Boolean).join(', '));
  const viecMo = $derived(['đọc', session.can('books.audio') && 'nghe', session.can('books.answers') && 'xem đáp án'].filter(Boolean).join(', '));
  const baiDaMo = $derived((ds ?? []).reduce((t, q) => t + q.baiMo, 0));
  /** Quyển đứng trước trong cùng bộ: thẻ quyển khoá ghi "Mở hết <quyển trước>". */
  const truoc = $derived<Record<string, string>>(
    Object.fromEntries(nhom.flatMap((g) => g.ds.map((q, i) => [q.ma, i > 0 ? g.ds[i - 1].ten : '']))),
  );
</script>

<svelte:head><title>Giáo trình · Zìkǎ</title></svelte:head>

{#snippet the(q: TomTatSach, mobile: boolean)}
  {@const trang = dangDoc[q.ma] ?? 1}
  <!-- Khách học thử đọc quyển đầu; quyển khoá dẫn thẳng sang đăng nhập rồi quay lại đúng quyển đó. -->
  <a href={q.khoa && !session.user ? loginUrl(`/learn/books/${q.ma}`) : `/learn/books/${q.ma}`} class="gt-quyen" class:gt-quyen-khoa={q.khoa}>
    <span class="gt-bia-khung">
      <img class="gt-bia" src={urlBia(q)} alt="" loading="lazy" draggable="false" />
      {#if q.khoa}
        <span class="gt-khoa-bia"><Icon name="lock" size={16} />{!session.user ? 'Cần đăng nhập' : truoc[q.ma] ? `Mở hết ${truoc[q.ma]}` : 'Chưa mở'}</span>
      {:else if q.soNghe && session.can('books.audio')}<span class="gt-co-nghe" title="Có file nghe"><Icon name="headphones" size={15} /></span>{/if}
    </span>
    <b class="gt-ten" style="font-size: {mobile ? 14 : 16}px">{q.ten}</b>
    <span class="muted" style="font-size: 13px">{q.phu}</span>
    <span class="gt-chips">
      <span class="gt-chip"><Icon name="book" size={14} />{q.soBai} bài</span>
      {#if q.soNghe && session.can('books.audio')}
        <span class="gt-chip gt-chip-nghe" title="{q.soNghe} file nghe · {dinhDangPhut(q.phutNghe)}"><Icon name="headphones" size={14} />{q.soNghe} file nghe</span>
      {/if}
      {#if q.coDapAn && session.can('books.answers')}<span class="gt-chip gt-chip-da"><Icon name="answer" size={14} />Đáp án</span>{/if}
    </span>
    {#if !session.isStaff}
      <span class="gt-mo num">
        <Icon name={q.baiMo ? 'check' : 'lock'} size={14} />Đã mở {q.baiMo}/{q.soBai} bài
      </span>
    {/if}
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

{#snippet viDiem()}
  <!-- Điểm mở bài nhận tại đây, độc lập với bé túi Shopee; mỗi điểm mở một bài. -->
  <section class="gt-vi" aria-label="Điểm mở bài">
    <span class="gt-vi-so" aria-hidden="true"><b class="num">{diemDoc.diem}</b><small>điểm</small></span>
    <span class="gt-vi-giua">
      <span class="gt-vi-nhan">Điểm mở bài<span class="sr-only">: {diemDoc.diem}</span></span>
      <span class="gt-vi-tien" aria-hidden="true"><i style="width: {tongBai ? (baiDaMo / tongBai) * 100 : 0}%"></i></span>
      <span class="gt-vi-phu num">Đã mở {baiDaMo}/{tongBai} bài</span>
    </span>
    <span class="gt-vi-nut">
      {#if !diemDoc.daTai}
        <Skeleton w="120px" h="34px" r={999} />
      {:else if !diemDoc.homNay}
        <button type="button" class="btn btn-son btn-sm" disabled={dangNhan} onclick={nhanDiem}>Nhận +{diemDoc.lanToi} điểm</button>
      {:else}
        <span class="gt-vi-da"><Icon name="check" size={15} />Hôm nay đã nhận</span>
      {/if}
    </span>
  </section>
{/snippet}

{#snippet ke(mobile: boolean)}
  <header class="gt-dau" class:m={mobile}>
    <div class="gt-dau-chu">
      <h1 style="font-size: {mobile ? 24 : 34}px">Giáo trình</h1>
      {#if nhom.length === 1}
        <p class="muted gt-mo-ta"><b>{nhom[0].ten}</b> · {nhom[0].ds.length} quyển · {tongBai} bài — {viec}.</p>
      {:else if nhom.length > 1}
        <p class="muted gt-mo-ta">Bạn thấy mọi bộ sách; học viên chỉ thấy bộ sách của giáo trình mình đang học.</p>
      {/if}
    </div>
    {#if !session.isStaff && ds?.length && session.can('books.points')}{@render viDiem()}{/if}
  </header>
  {#if !session.isStaff && ds?.length && diemDoc.lanDau && diemDoc.daTai && session.can('books.points')}
    <!-- Chỉ người chưa nhận điểm lần nào mới cần đọc luật; sau đó dòng này tự ẩn cho gọn. -->
    <ol class="gt-luat" aria-label="Cách mở bài">
      <li><span class="num">1</span>Bấm <b>Nhận điểm</b> mỗi ngày — lần đầu được 3 điểm</li>
      <li><span class="num">2</span>Mỗi điểm mở một bài, mở lần lượt theo sách</li>
      <li><span class="num">3</span>Bài đã mở là {viecMo} mãi</li>
    </ol>
  {/if}

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
    <MTopbar />
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 18px">{@render ke(true)}</main>
  </div>
{:else}
  <!-- ============ Máy tính ============ -->
  <Topbar current="lt" />
  <main class="page" style="display: flex; flex-direction: column; gap: 28px">{@render ke(false)}</main>
{/if}

<style>
  .gt-mo-ta { margin-top: 6px; font-size: 14px; line-height: 1.5; }
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
  /* Quyển chưa học tới: vẫn bấm vào được (màn đọc báo cách mở), nhưng nhìn là biết còn khoá. */
  .gt-quyen-khoa .gt-bia { filter: grayscale(.85); opacity: .6; }
  .gt-quyen-khoa .gt-ten, .gt-quyen-khoa .gt-chips { opacity: .7; }
  .gt-khoa-bia {
    position: absolute; left: 8px; right: 8px; bottom: 8px;
    display: flex; align-items: center; justify-content: center; gap: 5px; padding: 6px 8px; border-radius: 10px;
    background: var(--surface); color: var(--text); box-shadow: var(--sh-md); font-size: 12px; font-weight: 700; text-align: center;
  }
  .gt-mo { display: inline-flex; align-items: center; gap: 4px; margin-top: 6px; font-size: 12.5px; font-weight: 600; color: var(--text-2); }

  /* Đầu kệ: tên bên trái, ví điểm bên phải cùng một hàng để kệ sách lên cao, không chồng hai khối chữ. */
  .gt-dau { display: flex; align-items: flex-end; justify-content: space-between; gap: 16px 24px; flex-wrap: wrap; }
  .gt-dau h1 { line-height: 1.15; }
  .gt-dau-chu { min-width: 0; flex: 1 1 320px; }
  .gt-dau.m { align-items: stretch; }

  .gt-vi {
    display: flex; align-items: center; gap: 14px; flex: 0 1 auto;
    padding: 10px 12px 10px 10px; border: 1px solid var(--son-line); border-radius: 18px;
    background: linear-gradient(120deg, var(--son-soft), var(--surface) 70%);
  }
  .gt-dau.m .gt-vi { flex: 1 1 100%; }
  /* Ô số như con dấu son: nhìn là biết "điểm" là thứ để tiêu, không lẫn với số trang. */
  .gt-vi-so {
    flex: none; display: flex; flex-direction: column; align-items: center; justify-content: center;
    width: 54px; height: 54px; border-radius: 14px; background: var(--son); color: var(--on-accent);
    box-shadow: inset 0 0 0 2px rgba(255, 255, 255, .25);
  }
  .gt-vi-so b { font-size: 24px; line-height: 1; font-weight: 800; }
  .gt-vi-so small { font-size: 10.5px; font-weight: 700; opacity: .9; letter-spacing: .02em; }
  .gt-vi-giua { display: flex; flex-direction: column; gap: 5px; min-width: 130px; flex: 1 1 auto; }
  .gt-vi-nhan { font-size: 14px; font-weight: 700; }
  .gt-vi-tien { display: block; width: 100%; max-width: 180px; height: 5px; border-radius: 99px; background: var(--track); overflow: hidden; }
  .gt-vi-tien i { display: block; height: 100%; border-radius: inherit; background: var(--son); }
  .gt-vi-phu { font-size: 12.5px; color: var(--text-2); }
  .gt-vi-nut { display: flex; align-items: center; gap: 10px; flex: none; }
  .gt-vi-da {
    display: inline-flex; align-items: center; gap: 5px; height: 32px; padding: 0 12px; border-radius: 999px;
    background: var(--ngoc-soft); color: var(--green-text); font-size: 13px; font-weight: 700; white-space: nowrap;
  }
  .gt-dau.m .gt-vi { flex-wrap: wrap; }
  .gt-dau.m .gt-vi-nut { width: 100%; justify-content: space-between; padding-top: 2px; }

  .gt-luat {
    list-style: none; margin: -6px 0 0; padding: 0; display: flex; flex-wrap: wrap; gap: 8px 18px;
    font-size: 13px; color: var(--text-2);
  }
  .gt-luat li { display: flex; align-items: center; gap: 7px; }
  .gt-luat span {
    display: grid; place-items: center; flex: none; width: 20px; height: 20px; border-radius: 50%;
    background: var(--son-soft); color: var(--son-text); font-size: 11.5px; font-weight: 800;
  }
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
