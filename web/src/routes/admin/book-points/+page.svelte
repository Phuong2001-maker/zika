<script lang="ts">
  import { onMount } from 'svelte';
  import { api, ApiError } from '$lib/api/client';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';

  // Quản trị · Điểm mở bài giáo trình: chế độ test, tra sổ điểm từng học viên, cộng/trừ tay.
  // Không có phần bán sách (người dùng bỏ 08/11/2026): học viên chỉ mở bài bằng điểm từ bé túi / link Shopee.
  interface SoDiem {
    user: { id: number; email: string; name: string; role: string } | null;
    diem?: number;
    unlocks?: { ma: string; bai: number; at: string }[];
    ledger?: { delta: number; kind: string; khoa: string; note: string | null; at: string }[];
  }

  const ngay = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '';
  const baoLoi = (e: unknown, macDinh: string) => toast.show(e instanceof ApiError ? e.message : macDinh, 'info', 5000);

  /** Chế độ test điểm: mỗi lần bấm bé túi đều +1. */
  let cheDoThu = $state(false);
  let tenSach = $state<Record<string, string>>({});
  let timHv = $state('');
  let hv = $state<SoDiem | null>(null);
  let dangTim = $state(false);
  let dangLam = $state(false);
  let congDiem = $state('1');
  let lyDo = $state('');

  onMount(async () => {
    try {
      const r = await api.get<{ testMode: boolean; books: { ma: string; ten: string }[] }>('/admin/book-points');
      cheDoThu = r.testMode;
      tenSach = Object.fromEntries(r.books.map((b) => [b.ma, b.ten]));
    } catch (e) {
      baoLoi(e, 'Chưa tải được cài đặt điểm.');
    }
  });

  async function doiCheDoThu(bat: boolean): Promise<void> {
    try {
      cheDoThu = (await api.put<{ testMode: boolean }>('/admin/book-points/test-mode', { enabled: bat })).testMode;
      toast.ok(cheDoThu ? 'Đã bật chế độ test: mỗi lần bấm bé túi đều +1 điểm.' : 'Đã tắt chế độ test: mỗi ngày chỉ +1 điểm.');
    } catch (e) {
      cheDoThu = !bat;
      baoLoi(e, 'Chưa đổi được chế độ test.');
    }
  }

  async function taiHv(): Promise<void> {
    if (!timHv.trim()) return;
    dangTim = true;
    try {
      hv = await api.get<SoDiem>('/admin/book-points/user', { query: { q: timHv.trim() } });
    } catch (e) {
      baoLoi(e, 'Chưa tìm được.');
    } finally {
      dangTim = false;
    }
  }

  async function cong(): Promise<void> {
    if (!hv?.user) return;
    dangLam = true;
    try {
      await api.post('/admin/book-points/adjust', { userId: hv.user.id, delta: Number(congDiem), note: lyDo });
      toast.ok('Đã cập nhật điểm.');
      lyDo = '';
      await taiHv();
    } catch (e) {
      baoLoi(e, 'Chưa cập nhật được điểm.');
    } finally {
      dangLam = false;
    }
  }

  const TEN_LOAI: Record<string, string> = { first: 'Lần đầu bấm link', daily: 'Bấm link', unlock: 'Mở bài', admin: 'Quản trị' };
  function moTaDong(x: { kind: string; khoa: string; note: string | null }): string {
    if (x.kind === 'unlock') {
      const [, ma, bai] = x.khoa.split(':');
      return `Mở Bài ${bai} · ${tenSach[ma] ?? ma}`;
    }
    if (x.kind === 'daily') {
      return x.khoa.startsWith('thu:') ? 'Bấm link (chế độ test)' : `Bấm link ngày ${x.khoa.slice(6).split('-').reverse().join('/')}`;
    }
    return TEN_LOAI[x.kind] + (x.note ? ` · ${x.note}` : '');
  }
</script>

<svelte:head><title>Điểm mở bài · Quản trị · Zìkǎ</title></svelte:head>

<div class="bp-dau">
  <h1>Điểm mở bài giáo trình</h1>
  <p class="muted" style="font-size: 14px">
    Lần đầu bấm bé túi hoặc link Shopee được 3 điểm, từ hôm sau mỗi ngày bấm được 1 điểm; điểm chưa dùng giữ lại, ngày không
    bấm không bù. Mỗi điểm mở một bài, lần lượt theo thứ tự.
  </p>
</div>

{#if cheDoThu}
  <!-- Bật quên là ai cũng nhận điểm vô hạn: nhắc cho tới khi tắt. -->
  <div class="bp-canh-bao" role="status">
    <b>Đang bật chế độ test điểm</b> — mỗi lần bấm bé túi đều +1 điểm, không giới hạn mỗi ngày. Nhớ tắt trước khi chạy thật.
    <button type="button" class="btn btn-sm" onclick={() => doiCheDoThu(false)}>Tắt ngay</button>
  </div>
{/if}

<section class="block bp-khoi">
  <label class="sw"><input type="checkbox" checked={cheDoThu} onchange={(e) => doiCheDoThu(e.currentTarget.checked)} /><span></span> Chế độ test: mỗi lần bấm bé túi đều +1 điểm (không chờ sang ngày)</label>
</section>

<section class="block bp-khoi">
  <h2 class="bp-h2">Tra điểm học viên</h2>
  <form class="bp-tim" onsubmit={(e) => { e.preventDefault(); void taiHv(); }}>
    <input class="inp" bind:value={timHv} placeholder="Email hoặc mã tài khoản" aria-label="Tìm học viên" />
    <button type="submit" class="btn btn-sm" disabled={dangTim}><Icon name="search" size={15} />Tìm</button>
  </form>
</section>

{#if hv && !hv.user}
  <EmptyState title="Không tìm thấy tài khoản này." />
{:else if hv?.user}
  {@const u = hv.user}
  <section class="block bp-khoi">
    <div class="bp-hv-dau">
      <div><b style="font-size: 17px">{u.name}</b><span class="muted" style="display: block; font-size: 13px">{u.email}</span></div>
      <span class="bp-diem num">{hv.diem} điểm</span>
    </div>
    <p style="font-size: 14px">Đã mở <b class="num">{hv.unlocks?.length ?? 0}</b> bài.</p>
    <div class="bp-cong">
      <div class="field"><label class="lbl" for="bp-delta">Cộng / trừ điểm</label><input id="bp-delta" class="inp num" type="number" min="-100" max="100" bind:value={congDiem} /></div>
      <div class="field" style="flex: 1 1 240px"><label class="lbl" for="bp-ly-do">Lý do</label><input id="bp-ly-do" class="inp" bind:value={lyDo} placeholder="Tặng thêm vì báo lỗi sách" /></div>
      <button type="button" class="btn btn-son btn-sm" disabled={dangLam || !Number(congDiem)} onclick={cong}>Lưu</button>
    </div>
    {#if hv.ledger?.length}
      <table class="tbl">
        <thead><tr><th>Lúc</th><th>Nội dung</th><th class="r">Điểm</th></tr></thead>
        <tbody>
          {#each hv.ledger as x, i (i)}
            <tr><td class="num" style="white-space: nowrap">{ngay(x.at)}</td><td>{moTaDong(x)}</td><td class="r num">{x.delta > 0 ? '+' : ''}{x.delta}</td></tr>
          {/each}
        </tbody>
      </table>
    {:else}
      <p class="muted" style="font-size: 14px">Chưa có lần cộng/trừ nào.</p>
    {/if}
  </section>
{/if}

<style>
  .bp-dau { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; max-width: 820px; }
  .bp-dau h1 { font-size: 28px; }
  .bp-canh-bao {
    display: flex; align-items: center; gap: 10px 14px; flex-wrap: wrap; margin-bottom: 14px; padding: 10px 14px;
    border: 1px solid var(--bo-dot); border-radius: 12px; background: var(--bo-soft); color: var(--warn-text); font-size: 14px;
  }
  .bp-khoi { padding: 16px 20px; display: flex; flex-direction: column; gap: 12px; margin-bottom: 12px; }
  .bp-h2 { font-size: 17px; }
  .bp-tim { display: flex; gap: 8px; max-width: 460px; }
  .bp-tim .inp { flex: 1; }
  .field { display: flex; flex-direction: column; gap: 4px; }
  .bp-hv-dau { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
  .bp-diem { padding: 6px 14px; border-radius: 999px; background: var(--son-soft); color: var(--son-text); font-weight: 800; font-size: 18px; }
  .bp-cong { display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap; }
  .bp-cong .field:first-child { flex: 0 0 140px; }
</style>
