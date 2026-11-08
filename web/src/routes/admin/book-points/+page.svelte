<script lang="ts">
  import { onMount } from 'svelte';
  import { api, ApiError } from '$lib/api/client';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';

  // Quản trị · Điểm mở bài giáo trình: bảng mọi học viên kèm điểm, cộng/trừ tay ngay trên dòng (hệ thống có sai số
  // thì quản trị sửa được — người dùng yêu cầu 08/11), xem sổ điểm từng người, mở cả quyển không trừ điểm (09/11).
  interface HocVien { id: number; email: string; name: string; curriculum: string; diem: number; baiMo: number; lastClaim: string | null }
  interface SoDiem {
    user: { id: number; email: string; name: string; role: string } | null;
    diem?: number;
    unlocks?: { ma: string; bai: number; at: string }[];
    ledger?: { delta: number; kind: string; khoa: string; note: string | null; at: string }[];
    /** Các quyển thuộc giáo trình của học viên; biên tập/quản trị đọc tự do nên rỗng. */
    books?: { ma: string; ten: string; tong: number; daMo: number }[];
  }

  const ngay = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
  const baoLoi = (e: unknown, macDinh: string) => toast.show(e instanceof ApiError ? e.message : macDinh, 'info', 5000);

  let tenSach = $state<Record<string, string>>({});

  // Bảng học viên
  let ds = $state<HocVien[] | null>(null);
  let tong = $state(0);
  let trang = $state(1);
  let tim = $state('');
  let sapXep = $state<'diem' | 'moi'>('diem');
  let loi = $state(false);
  let dangChinh = $state<number | null>(null);
  const soTrang = $derived(Math.max(1, Math.ceil(tong / 50)));

  // Chi tiết một học viên
  let hv = $state<SoDiem | null>(null);
  let congDiem = $state('1');
  let lyDo = $state('');
  let dangLuu = $state(false);
  /** Mở cả quyển phải bấm hai lần: lần đầu chỉ hỏi lại, tránh lỡ tay tặng nguyên quyển. */
  let hoiMo = $state<string | null>(null);
  let dangMo = $state(false);

  async function taiDs(): Promise<void> {
    loi = false;
    try {
      const r = await api.getFull<HocVien[]>('/admin/book-points/users', { query: { q: tim.trim(), sort: sapXep, page: trang } });
      ds = r.data;
      tong = Number(r.meta?.total ?? 0);
    } catch {
      loi = true;
    }
  }

  onMount(async () => {
    void taiDs();
    try {
      const r = await api.get<{ books: { ma: string; ten: string }[] }>('/admin/book-points');
      tenSach = Object.fromEntries(r.books.map((b) => [b.ma, b.ten]));
    } catch (e) {
      baoLoi(e, 'Chưa tải được cài đặt điểm.');
    }
  });

  function locLai(): void {
    trang = 1;
    void taiDs();
  }

  /** Cộng/trừ nhanh ngay trên dòng: ghi vào sổ điểm như mọi lần chỉnh tay, có lý do mặc định. */
  async function chinhNhanh(h: HocVien, delta: number): Promise<void> {
    dangChinh = h.id;
    try {
      const r = await api.post<{ diem: number }>('/admin/book-points/adjust', { userId: h.id, delta, note: 'Chỉnh nhanh từ bảng học viên' });
      ds = (ds ?? []).map((x) => (x.id === h.id ? { ...x, diem: r.diem } : x));
    } catch (e) {
      baoLoi(e, 'Chưa chỉnh được điểm.');
    } finally {
      dangChinh = null;
    }
  }

  async function moChiTiet(id: number): Promise<void> {
    congDiem = '1';
    lyDo = '';
    hoiMo = null;
    try {
      hv = await api.get<SoDiem>('/admin/book-points/user', { query: { q: String(id) } });
    } catch (e) {
      baoLoi(e, 'Chưa mở được sổ điểm.');
    }
  }

  async function luuChinh(): Promise<void> {
    if (!hv?.user) return;
    dangLuu = true;
    try {
      const r = await api.post<{ diem: number }>('/admin/book-points/adjust', { userId: hv.user.id, delta: Number(congDiem), note: lyDo });
      toast.ok(`Đã cập nhật: ${hv.user.name} còn ${r.diem} điểm.`);
      const id = hv.user.id;
      ds = (ds ?? []).map((x) => (x.id === id ? { ...x, diem: r.diem } : x));
      await moChiTiet(id);
    } catch (e) {
      baoLoi(e, 'Chưa cập nhật được điểm.');
    } finally {
      dangLuu = false;
    }
  }

  async function moQuyen(ma: string): Promise<void> {
    if (!hv?.user) return;
    if (hoiMo !== ma) {
      hoiMo = ma;
      return;
    }
    dangMo = true;
    try {
      const id = hv.user.id;
      const r = await api.post<{ moThem: number }>('/admin/book-points/open-book', { userId: id, ma });
      toast.ok(r.moThem ? `Đã mở ${r.moThem} bài của ${tenSach[ma] ?? ma} cho ${hv.user.name}.` : 'Quyển này đã mở hết từ trước.');
      ds = (ds ?? []).map((x) => (x.id === id ? { ...x, baiMo: x.baiMo + r.moThem } : x));
      await moChiTiet(id);
    } catch (e) {
      baoLoi(e, 'Chưa mở được quyển này.');
    } finally {
      dangMo = false;
      hoiMo = null;
    }
  }

  const TEN_LOAI: Record<string, string> = { first: 'Lần đầu nhận điểm', daily: 'Nhận điểm', unlock: 'Mở bài', admin: 'Quản trị chỉnh tay' };
  function moTaDong(x: { kind: string; khoa: string; note: string | null }): string {
    if (x.kind === 'unlock') {
      const [, ma, bai] = x.khoa.split(':');
      return `Mở Bài ${bai} · ${tenSach[ma] ?? ma}`;
    }
    if (x.kind === 'daily') {
      // Dòng ghi lúc còn chế độ test có khoá "thu:…", không mang ngày.
      return x.khoa.startsWith('daily:') ? `Nhận điểm ngày ${x.khoa.slice(6).split('-').reverse().join('/')}` : 'Nhận điểm';
    }
    if (x.khoa.startsWith('moquyen:')) {
      return `Quản trị mở cả quyển ${tenSach[x.khoa.split(':')[1]] ?? ''}`.trim() + (x.note ? ` · ${x.note}` : '');
    }
    return TEN_LOAI[x.kind] + (x.note ? ` · ${x.note}` : '');
  }
</script>

<svelte:head><title>Điểm mở bài · Quản trị · Zìkǎ</title></svelte:head>

<h1 class="bp-tieu-de">Điểm mở bài giáo trình</h1>

<div class="bp-loc">
  <form class="bp-tim" onsubmit={(e) => { e.preventDefault(); locLai(); }}>
    <input class="inp" bind:value={tim} placeholder="Tìm theo tên hoặc email" aria-label="Tìm học viên" />
    <button type="submit" class="btn btn-sm"><Icon name="search" size={15} />Tìm</button>
  </form>
  <div class="seg" role="group" aria-label="Sắp xếp">
    <button type="button" class:on={sapXep === 'diem'} aria-pressed={sapXep === 'diem'} onclick={() => { sapXep = 'diem'; locLai(); }}>Nhiều điểm nhất</button>
    <button type="button" class:on={sapXep === 'moi'} aria-pressed={sapXep === 'moi'} onclick={() => { sapXep = 'moi'; locLai(); }}>Nhận điểm gần đây</button>
  </div>
</div>

{#if loi}
  <EmptyState title="Không tải được danh sách học viên.">{#snippet action()}<button type="button" class="btn btn-sm" onclick={taiDs}>Thử lại</button>{/snippet}</EmptyState>
{:else if ds === null}
  <Skeleton h="260px" r={16} />
{:else if !ds.length}
  <EmptyState title={tim ? 'Không có học viên nào khớp.' : 'Chưa có học viên nào.'} />
{:else}
  <section class="paper" style="overflow-x: auto">
    <table class="tbl">
      <thead><tr><th>Học viên</th><th>Giáo trình</th><th class="r">Điểm</th><th class="r">Bài đã mở</th><th>Nhận điểm gần nhất</th><th></th></tr></thead>
      <tbody>
        {#each ds as h (h.id)}
          <tr>
            <td><b>{h.name}</b><span class="muted" style="display: block; font-size: 12.5px">{h.email}</span></td>
            <td style="font-size: 13.5px">{h.curriculum}</td>
            <td class="r"><span class="bp-so num">{h.diem}</span></td>
            <td class="r num">{h.baiMo}</td>
            <td class="num" style="font-size: 13px; white-space: nowrap">{h.lastClaim ? ngay(h.lastClaim) : 'Chưa nhận lần nào'}</td>
            <td style="white-space: nowrap; text-align: right">
              <button type="button" class="btn btn-sm bp-nut" aria-label="Trừ 1 điểm của {h.name}" disabled={dangChinh === h.id || h.diem <= 0} onclick={() => chinhNhanh(h, -1)}>−1</button>
              <button type="button" class="btn btn-sm bp-nut" aria-label="Cộng 1 điểm cho {h.name}" disabled={dangChinh === h.id} onclick={() => chinhNhanh(h, 1)}>+1</button>
              <button type="button" class="btn btn-sm btn-text" onclick={() => moChiTiet(h.id)}>Chi tiết</button>
            </td>
          </tr>
        {/each}
      </tbody>
    </table>
  </section>
  <div class="bp-trang">
    <span class="muted num">{tong} học viên{soTrang > 1 ? ` · trang ${trang}/${soTrang}` : ''}</span>
    {#if soTrang > 1}
      <button type="button" class="btn btn-sm" disabled={trang <= 1} onclick={() => { trang--; void taiDs(); }}>Trang trước</button>
      <button type="button" class="btn btn-sm" disabled={trang >= soTrang} onclick={() => { trang++; void taiDs(); }}>Trang sau</button>
    {/if}
  </div>
{/if}

{#if hv?.user}
  {@const u = hv.user}
  <Modal title="Sổ điểm · {u.name}" onclose={() => (hv = null)} width={620}>
    <div class="bp-hv-dau">
      <span class="muted" style="font-size: 13px">{u.email} · đã mở <b class="num">{hv.unlocks?.length ?? 0}</b> bài</span>
      <span class="bp-diem num">{hv.diem} điểm</span>
    </div>
    <div class="bp-cong">
      <div class="field" style="flex: 0 0 120px"><label class="lbl" for="bp-delta">Cộng / trừ</label><input id="bp-delta" class="inp num" type="number" min="-100" max="100" bind:value={congDiem} /></div>
      <div class="field" style="flex: 1 1 220px"><label class="lbl" for="bp-ly-do">Lý do (ghi vào sổ)</label><input id="bp-ly-do" class="inp" bind:value={lyDo} placeholder="Hệ thống cộng thiếu ngày 08/11" /></div>
      <button type="button" class="btn btn-son btn-sm" disabled={dangLuu || !Number(congDiem)} onclick={luuChinh}>Lưu</button>
    </div>
    <p class="hint" style="margin: 0">Số âm là trừ điểm (không trừ quá số đang có). Bài đã mở giữ nguyên dù trừ điểm.</p>
    {#if hv.books?.length}
      <section class="bp-quyen" aria-label="Mở cả quyển">
        <h3>Mở cả quyển</h3>
        <p class="hint" style="margin: 0">Mở mọi bài của quyển cho học viên, không trừ điểm. Chỉ có các quyển thuộc giáo trình đang học.</p>
        <ul>
          {#each hv.books as b (b.ma)}
            {@const du = b.daMo >= b.tong}
            <li>
              <span><b>{b.ten}</b> <span class="muted num">· đã mở {b.daMo}/{b.tong} bài</span></span>
              {#if du}
                <span class="bp-du">Đã mở hết</span>
              {:else}
                <button type="button" class="btn btn-sm" class:btn-son={hoiMo === b.ma} disabled={dangMo} onclick={() => moQuyen(b.ma)}>
                  {hoiMo === b.ma ? 'Bấm lần nữa để mở' : 'Mở cả quyển'}
                </button>
              {/if}
            </li>
          {/each}
        </ul>
      </section>
    {/if}
    {#if hv.ledger?.length}
      <div class="bp-so-diem">
        <table class="tbl">
          <thead><tr><th>Lúc</th><th>Nội dung</th><th class="r">Điểm</th></tr></thead>
          <tbody>
            {#each hv.ledger as x, i (i)}
              <tr><td class="num" style="white-space: nowrap">{ngay(x.at)}</td><td>{moTaDong(x)}</td><td class="r num">{x.delta > 0 ? '+' : ''}{x.delta}</td></tr>
            {/each}
          </tbody>
        </table>
      </div>
    {:else}
      <p class="muted" style="font-size: 14px">Chưa có lần cộng/trừ nào.</p>
    {/if}
  </Modal>
{/if}

<style>
  .bp-tieu-de { font-size: 28px; margin-bottom: 20px; }
  .bp-loc { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; }
  .bp-tim { display: flex; gap: 8px; flex: 0 1 420px; }
  .bp-tim .inp { flex: 1; }
  .bp-so {
    display: inline-grid; place-items: center; min-width: 36px; height: 28px; padding: 0 8px; border-radius: 8px;
    background: var(--son-soft); color: var(--son-text); font-weight: 800;
  }
  .bp-nut { min-width: 40px; padding: 0 8px; font-weight: 800; }
  .bp-trang { display: flex; align-items: center; gap: 8px; margin-top: 10px; font-size: 13px; }
  .bp-trang .muted { margin-right: auto; }
  .field { display: flex; flex-direction: column; gap: 4px; }
  .bp-hv-dau { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
  .bp-diem { padding: 6px 14px; border-radius: 999px; background: var(--son-soft); color: var(--son-text); font-weight: 800; font-size: 18px; }
  .bp-cong { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
  .bp-quyen { display: flex; flex-direction: column; gap: 6px; padding-top: 12px; border-top: 1px solid var(--line); }
  .bp-quyen h3 { font-size: 15px; margin: 0; }
  .bp-quyen ul { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px; }
  .bp-quyen li { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; font-size: 14px; }
  .bp-du { color: var(--muted); font-size: 13px; }
  .bp-so-diem { max-height: 320px; overflow-y: auto; border: 1px solid var(--line); border-radius: 12px; }
</style>
