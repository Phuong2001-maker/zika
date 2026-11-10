<script lang="ts">
  import Modal from '$lib/components/Modal.svelte';
  import Select from '$lib/components/Select.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { api } from '$lib/api/client';
  import { toast } from '$lib/stores/toast.svelte';

  let { nguoi, revision = 0 }: { nguoi: { id: number; ten: string }[]; revision?: number } = $props();
  interface Giai { id: number; nguoiId: number; tenNguoi: string; cuocThi: string; thang: string; ngay: string; hang: number; giai: string; tien: number; trangThai: string; hien: boolean }
  // API admin riêng; không tạo giao dịch hoặc ghi vào lịch sử thi thật.
  let ds = $state<Giai[]>([]);
  let busy = $state(false);
  let loading = $state(true);
  let error = $state(false);
  async function tai() {
    loading = true;
    error = false;
    try { ds = await api.get<Giai[]>('/admin/demo-rewards'); }
    catch { error = true; }
    finally { loading = false; }
  }
  $effect(() => { void revision; void tai(); });
  let sua = $state<Giai | null>(null);
  let xoa = $state<Giai | null>(null);
  let tim = $state('');
  const trangThais = [{ v: 'cho', label: 'Chờ thông tin nhận thưởng' }, { v: 'dang-xu-ly', label: 'Đang xử lý' }, { v: 'da-trao', label: 'Đã trao thưởng' }];
  const dsLoc = $derived(ds.filter((n) => `${n.tenNguoi} ${n.cuocThi} ${n.thang}`.toLocaleLowerCase('vi').includes(tim.toLocaleLowerCase('vi'))));
  const dsXem = $derived(ds.filter((n) => n.hien).toSorted((a, b) => b.thang.localeCompare(a.thang) || a.hang - b.hang));
  const tien = (n: number) => n.toLocaleString('vi-VN') + ' đ';
  const thang = (s: string) => s.split('-').reverse().join('/');
  function mo(n?: Giai) {
    const homNay = new Date();
    const thangNay = `${homNay.getFullYear()}-${String(homNay.getMonth() + 1).padStart(2, '0')}`;
    sua = n ? { ...n } : { id: 0, nguoiId: nguoi[0]?.id ?? 0, tenNguoi: nguoi[0]?.ten ?? '', cuocThi: '', thang: thangNay, ngay: '', hang: 1, giai: 'Giải Nhất', tien: 0, trangThai: 'cho', hien: true };
  }
  async function luu() {
    if (!sua || !sua.nguoiId || !sua.cuocThi.trim()) return;
    const n = { ...sua, tenNguoi: nguoi.find((x) => x.id === sua?.nguoiId)?.ten ?? sua.tenNguoi, cuocThi: sua.cuocThi.trim(), giai: sua.giai.trim() };
    busy = true;
    try {
      if (n.id) await api.put(`/admin/demo-rewards/${n.id}`, n);
      else await api.post('/admin/demo-rewards', n);
      sua = null;
      await tai();
      toast.show('Đã lưu lịch sử thưởng.');
    } catch { toast.show('Chưa lưu được lịch sử thưởng.', 'info'); }
    finally { busy = false; }
  }
  async function anHien(n: Giai) {
    busy = true;
    try { await api.put(`/admin/demo-rewards/${n.id}`, { ...n, hien: !n.hien }); n.hien = !n.hien; }
    catch { toast.show('Chưa đổi được trạng thái.', 'info'); }
    finally { busy = false; }
  }
  async function xoaGiai() {
    if (!xoa) return;
    busy = true;
    try { await api.del(`/admin/demo-rewards/${xoa.id}`); xoa = null; await tai(); }
    catch { toast.show('Chưa xóa được lịch sử thưởng.', 'info'); }
    finally { busy = false; }
  }
</script>

<div class="lsmh-grid">
  <section class="block lsmh-panel">
    <div class="lsmh-head"><div><h2>Lịch sử thi & thưởng</h2><p>Quản lý giải thưởng theo từng người và từng tháng.</p></div><button class="btn btn-son" disabled={!nguoi.length} onclick={() => mo()}><Icon name="plus" size={17} /> Thêm lịch sử</button></div>
    <input class="inp" aria-label="Tìm lịch sử minh họa" placeholder="Tìm người học, cuộc thi hoặc tháng..." bind:value={tim} />
    {#if loading}<p class="lsmh-empty">Đang tải lịch sử...</p>{:else if error}<p class="lsmh-empty">Chưa tải được dữ liệu. <button class="btn btn-sm" onclick={tai}>Thử lại</button></p>{:else}
    {#each dsLoc as n (n.id)}
      <article class="lsmh-item">
        <div class="lsmh-top"><span class="lsmh-medal">{n.hang <= 3 ? ['🥇', '🥈', '🥉'][n.hang - 1] : `#${n.hang}`}</span><div class="lsmh-name"><b>{n.tenNguoi}</b><small>{n.cuocThi} · Tháng {thang(n.thang)}</small></div><strong>{tien(n.tien)}</strong></div>
        <div class="lsmh-meta"><span>{n.giai}</span><span class="lsmh-status" class:lsmh-paid={n.trangThai === 'da-trao'}>{trangThais.find((t) => t.v === n.trangThai)?.label}</span></div>
        <div class="lsmh-actions"><button class="btn btn-sm" disabled={busy} onclick={() => anHien(n)}>{n.hien ? 'Đang hiện cho học viên' : 'Đang ẩn'}</button><button class="btn btn-sm" disabled={busy} onclick={() => mo(n)}><Icon name="edit" size={15} /> Sửa</button><button class="btn btn-icon" disabled={busy} aria-label={`Xóa lịch sử của ${n.tenNguoi}`} onclick={() => xoa = n}><Icon name="trash" size={16} /></button></div>
      </article>
    {:else}<p class="lsmh-empty">Chưa có lịch sử phù hợp. Thêm một giải thưởng để xem trước.</p>{/each}
    {/if}
  </section>
  <aside>
    <div class="lsmh-preview-title"><b>Xem trước lịch sử</b><small>Học viên thấy ở Lịch sử thi & thưởng</small></div>
    <section class="block lsmh-panel">
      <h2>Lịch sử thi & thưởng</h2>
      {#each dsXem as n (n.id)}
        <article class="lsmh-prize"><div class="lsmh-month">GIẢI THƯỞNG THÁNG {thang(n.thang)}</div><h3>🏆 {n.cuocThi}</h3><p>{n.tenNguoi} · {n.giai}</p><div class="lsmh-prize-bottom"><strong>{tien(n.tien)}</strong><span class="lsmh-status" class:lsmh-paid={n.trangThai === 'da-trao'}>{trangThais.find((t) => t.v === n.trangThai)?.label}</span></div><small>{n.ngay ? `Ngày thi ${new Date(n.ngay + 'T00:00:00').toLocaleDateString('vi-VN')}` : 'Chưa nhập ngày thi'}</small></article>
      {:else}<div class="lsmh-empty">🏆<p>Chưa có lịch sử hiển thị</p></div>{/each}
    </section>
  </aside>
</div>

{#if sua}
  <Modal title={sua.id ? 'Sửa lịch sử thi & thưởng' : 'Thêm lịch sử thi & thưởng'} width={600} onclose={() => sua = null}>
    <form id="lsmh-form" class="lsmh-form" onsubmit={(e) => { e.preventDefault(); luu(); }}>
      <label>Người học minh họa<Select ariaLabel="Người đạt giải" bind:value={sua.nguoiId} options={nguoi.map((n) => ({ v: n.id, label: n.ten }))} /></label>
      <label>Tên cuộc thi<input class="inp" required maxlength="120" placeholder="Ví dụ: Thử sức HSK 2" bind:value={sua.cuocThi} /></label>
      <div class="lsmh-fields"><label>Tháng giải thưởng<input class="inp" type="month" required bind:value={sua.thang} /></label><label>Ngày thi<input class="inp" type="date" required bind:value={sua.ngay} /></label></div>
      <div class="lsmh-fields"><label>Thứ hạng<input class="inp" type="number" min="1" max="1000" required bind:value={sua.hang} /></label><label>Tên giải<input class="inp" required maxlength="80" bind:value={sua.giai} /></label></div>
      <label>Tiền thưởng (đồng)<input class="inp" type="number" min="0" max="1000000000" step="1000" required bind:value={sua.tien} /></label>
      <label>Trạng thái thưởng<Select ariaLabel="Trạng thái thưởng" options={trangThais} bind:value={sua.trangThai} /></label>
      <label class="lsmh-check"><input type="checkbox" bind:checked={sua.hien} /> Hiện ở Lịch sử thi & thưởng của học viên</label>
      <small class="muted">Chỉ hiện khi người học này cũng đang bật Hiển thị. Không tạo khoản chi, lượt thi hay giải thưởng thật.</small>
    </form>
    {#snippet actions()}<button class="btn" disabled={busy} onclick={() => sua = null}>Hủy</button><button class="btn btn-son" disabled={busy} type="submit" form="lsmh-form">{busy ? 'Đang lưu...' : 'Lưu lịch sử'}</button>{/snippet}
  </Modal>
{/if}
{#if xoa}<Modal title="Xóa lịch sử minh họa?" onclose={() => xoa = null}><p>Xóa {xoa.giai} của {xoa.tenNguoi} trong cuộc thi “{xoa.cuocThi}”?</p>{#snippet actions()}<button class="btn" disabled={busy} onclick={() => xoa = null}>Giữ lại</button><button class="btn btn-son" disabled={busy} onclick={xoaGiai}>Xóa lịch sử</button>{/snippet}</Modal>{/if}

<style>
  .lsmh-grid { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr); gap: 24px; align-items: start; }
  .lsmh-panel { padding: 22px; } .lsmh-panel h2 { margin: 0; font-size: 18px; } .lsmh-head { display: flex; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap; margin-bottom: 20px; } .lsmh-head p { font-size: 13px; color: var(--muted); margin: 8px 0 0; }
  .lsmh-item { padding: 22px 0; border-bottom: 1px solid var(--line); } .lsmh-item:last-child { border: 0; padding-bottom: 0; } .lsmh-top { display: flex; align-items: center; gap: 12px; } .lsmh-medal { font-size: 25px; width: 40px; text-align: center; } .lsmh-name { flex: 1; min-width: 0; } .lsmh-name small { display: block; font-size: 12px; color: var(--muted); margin-top: 7px; } .lsmh-top strong { color: #b45309; white-space: nowrap; }
  .lsmh-meta, .lsmh-actions { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 16px; } .lsmh-meta { font-size: 13px; } .lsmh-actions .btn:first-child { margin-right: auto; } .lsmh-status { display: inline-block; padding: 5px 9px; border-radius: 8px; background: #fff6e5; color: #996318; font-size: 11px; } .lsmh-paid { background: #edf9f1; color: #167344; }
  .lsmh-preview-title { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px; margin: 4px 0 16px; } .lsmh-preview-title small { color: var(--muted); font-size: 12px; } .lsmh-prize { border: 1px solid #eddda2; background: #fffdf5; border-radius: 14px; margin-top: 18px; padding: 18px; } .lsmh-month { font-size: 10px; font-weight: 800; letter-spacing: .07em; color: #996318; } .lsmh-prize h3 { font-size: 16px; margin: 12px 0 8px; } .lsmh-prize p { font-size: 13px; margin: 0; } .lsmh-prize-bottom { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; margin: 18px 0 12px; padding-top: 16px; border-top: 1px solid #eddda2; } .lsmh-prize-bottom strong { color: #b45309; font-size: 24px; } .lsmh-prize > small { font-size: 11px; color: var(--muted); } .lsmh-empty { padding: 32px 10px; text-align: center; color: var(--muted); font-size: 13px; }
  .lsmh-form { display: flex; flex-direction: column; gap: 18px; } .lsmh-form label { display: flex; flex-direction: column; gap: 8px; font-size: 13px; } .lsmh-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; } .lsmh-form .lsmh-check { flex-direction: row; align-items: center; } .lsmh-check input { accent-color: var(--son); }
  @media (max-width: 1100px) { .lsmh-grid { grid-template-columns: 1fr; } } @media (max-width: 600px) { .lsmh-panel { padding: 16px; } .lsmh-top { flex-wrap: wrap; } .lsmh-top strong { margin-left: 52px; } .lsmh-fields { grid-template-columns: 1fr; } }
</style>
