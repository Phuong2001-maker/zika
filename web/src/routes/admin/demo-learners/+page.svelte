<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { onMount, onDestroy } from 'svelte';
  import { api, ApiError } from '$lib/api/client';
  import Modal from '$lib/components/Modal.svelte';
  import Select from '$lib/components/Select.svelte';
  import { toast } from '$lib/stores/toast.svelte';
  import LichSuMinhHoa from '$lib/admin/LichSuMinhHoa.svelte';
  import NhipHocMinhHoa from '$lib/admin/NhipHocMinhHoa.svelte';

  // Dữ liệu riêng trong DB; bản xem trước không ghi vào tiến độ hay kết quả thật.
  interface Nguoi { id: number; ten: string; avatarUrl: string | null; muc: string; phut: number; ngay: number; bai: number; hien: boolean; dangKy: boolean; thi: boolean; tuDong: boolean; thang?: string }
  let ds = $state<Nguoi[]>([]);
  let busy = $state(false);
  let loading = $state(true);
  let error = $state(false);
  let revision = $state(0);
  let anh = $state<File | null>(null);
  let anhTam = $state('');
  function boAnhTam() {
    if (anhTam) URL.revokeObjectURL(anhTam);
    anhTam = '';
    anh = null;
  }
  onDestroy(boAnhTam);
  function chonAnh(e: Event) {
    const input = e.currentTarget as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
      toast.show('Chọn ảnh JPG, PNG hoặc WebP tối đa 5 MB.', 'info');
      return;
    }
    boAnhTam();
    anh = file;
    anhTam = URL.createObjectURL(file);
  }
  async function tai(silent = false) {
    if (!silent) loading = true;
    error = false;
    try { ds = await api.get<Nguoi[]>('/admin/demo-learners'); }
    catch { error = true; }
    finally { loading = false; }
  }
  onMount(() => {
    void tai();
    const interval = setInterval(() => { if (!sua && !busy) { void tai(true); revision++; } }, 60_000);
    return () => clearInterval(interval);
  });
  let tim = $state('');
  let khu = $state('nguoi');
  let tab = $state('cham');
  let sua = $state<Nguoi | null>(null);
  let xoa = $state<Nguoi | null>(null);
  let goc = $state({ phut: 0, ngay: 0, bai: 0 });
  const mucs = [{ v: 'it', label: 'Nhẹ nhàng · 5–15 phút/ngày' }, { v: 'vua', label: 'Đều đặn · 15–35 phút/ngày' }, { v: 'cham', label: 'Chăm chỉ · 30–60 phút/ngày' }];
  const loc = $derived(ds.filter((n) => n.ten.toLocaleLowerCase('vi').includes(tim.toLocaleLowerCase('vi'))));
  const xem = $derived(ds.filter((n) => n.hien && (tab === 'cham' || (tab === 'dk' ? n.dangKy : n.thi))).toSorted((a, b) => b.phut - a.phut));
  const vietTat = (ten: string) => ten.trim().split(/\s+/).slice(-2).map((s) => s[0]).join('');
  function mo(n?: Nguoi) {
    boAnhTam();
    goc = { phut: n?.phut ?? 0, ngay: n?.ngay ?? 0, bai: n?.bai ?? 0 };
    sua = n ? { ...n } : { id: 0, ten: '', avatarUrl: null, muc: 'vua', phut: 0, ngay: 0, bai: 0, hien: true, dangKy: false, thi: false, tuDong: true };
  }
  async function luu() {
    if (!sua || !sua.ten.trim()) return;
    const n = { ...sua, ten: sua.ten.trim(), goc };
    busy = true;
    try {
      if (anh) {
        const form = new FormData();
        form.append('file', anh);
        const uploaded = await api.upload<{ avatarUrl: string }>('/admin/demo-learners/avatar', form);
        n.avatarUrl = uploaded.avatarUrl;
      }
      if (n.id) await api.put(`/admin/demo-learners/${n.id}`, n);
      else await api.post('/admin/demo-learners', n);
      sua = null;
      boAnhTam();
      revision++;
      await tai();
      toast.show('Đã lưu người học.');
    } catch (e) { toast.show(e instanceof ApiError ? e.message : 'Chưa lưu được người học. Hãy thử lại.', 'info'); }
    finally { busy = false; }
  }
  async function anHien(n: Nguoi) {
    busy = true;
    try { await api.put(`/admin/demo-learners/${n.id}`, { ...n, hien: !n.hien, goc: { phut: n.phut, ngay: n.ngay, bai: n.bai } }); await tai(true); }
    catch { toast.show('Chưa đổi được trạng thái.', 'info'); }
    finally { busy = false; }
  }
  async function xoaNguoi() {
    if (!xoa) return;
    busy = true;
    try { await api.del(`/admin/demo-learners/${xoa.id}`); xoa = null; revision++; await tai(); }
    catch { toast.show('Chưa xóa được người học.', 'info'); }
    finally { busy = false; }
  }
</script>

<svelte:head><title>Người học minh họa · Quản trị · Zìkǎ</title></svelte:head>

<div class="mh-dau">
  <div><span class="mh-nhan">QUẢN LÝ HIỂN THỊ</span><h1>Người học minh họa</h1><p class="muted">Chuẩn bị danh sách và nhịp học cho bảng xếp hạng.</p></div>
  <button class="btn btn-son" onclick={() => mo()}><Icon name="plus" size={18} /> Thêm người học</button>
</div>
<div class="mh-tabs" style="max-width: 440px; margin-bottom: 24px"><button class:mh-chon={khu === 'nguoi'} onclick={() => khu = 'nguoi'}>Người học</button><button class:mh-chon={khu === 'thuong'} onclick={() => khu = 'thuong'}>Lịch sử thi & thưởng</button></div>

<div hidden={khu !== 'nguoi'}>
<div class="mh-bo-cuc">
  <section class="block mh-quan-ly">
    <div class="mh-thanh"><div><h2>Danh sách người học</h2><span class="muted">{ds.length} người · {ds.filter((n) => n.hien).length} đang hiển thị</span></div><input class="inp" placeholder="Tìm tên người học..." aria-label="Tìm người học minh họa" bind:value={tim} /></div>
    {#if loading}<p class="mh-trong">Đang tải người học...</p>{:else if error}<p class="mh-trong">Chưa tải được dữ liệu. <button class="btn btn-sm" onclick={() => tai()}>Thử lại</button></p>{:else}
    {#each loc as n (n.id)}
      <div class="mh-nguoi">
        <span class="mh-avatar">{#if n.avatarUrl}<img src={n.avatarUrl} alt="" />{:else}{vietTat(n.ten)}{/if}</span>
        <div class="mh-thong-tin"><b>{n.ten}</b><small>{mucs.find((m) => m.v === n.muc)?.label} · {n.tuDong ? 'Tự động bật' : 'Tự động tắt'}</small><span>{n.phut} phút · {n.ngay} ngày học · {n.bai} phần hoàn thành</span></div>
        <div class="mh-thao-tac"><button class="btn btn-sm" disabled={busy} class:mh-dang-hien={n.hien} onclick={() => anHien(n)}>{n.hien ? 'Đang hiện' : 'Đang ẩn'}</button><button class="btn btn-icon" disabled={busy} aria-label={`Sửa ${n.ten}`} onclick={() => mo(n)}><Icon name="edit" size={16} /></button><button class="btn btn-icon" disabled={busy} aria-label={`Xóa ${n.ten}`} onclick={() => xoa = n}><Icon name="trash" size={16} /></button></div>
      </div>
    {:else}<p class="mh-trong">Không tìm thấy người học phù hợp.</p>{/each}
    {/if}
  </section>

  <aside class="mh-xem">
    <div class="mh-xem-dau"><h2>Xem trước hiển thị</h2><span class="muted">Bảng dành cho học viên</span></div>
    <section class="block mh-bang">
      <h3>Bảng xếp hạng tháng {new Date().toLocaleDateString('vi-VN', { month: '2-digit', year: 'numeric' })}</h3>
      <div class="mh-tabs">{#each [{ v: 'cham', ten: '🌱 Chăm học' }, { v: 'thi', ten: '🏆 Cuộc thi' }, { v: 'dk', ten: '📋 Đăng ký' }] as t}<button class:mh-chon={tab === t.v} onclick={() => tab = t.v}>{t.ten}</button>{/each}</div>
      {#each xem as n, i (n.id)}
        <div class="mh-hang"><span>{i < 3 ? ['🥇', '🥈', '🥉'][i] : i + 1}</span><span class="mh-avatar mh-nho">{#if n.avatarUrl}<img src={n.avatarUrl} alt="" />{:else}{vietTat(n.ten)}{/if}</span><div><b>{n.ten}</b><small>{tab === 'dk' ? 'Đã đăng ký tham gia' : tab === 'thi' ? 'Chờ cập nhật kết quả' : `${n.ngay} ngày học · ${n.bai} phần bài học xong`}</small></div>{#if tab === 'cham'}<strong>{n.phut}<small>phút học</small></strong>{/if}</div>
      {:else}<div class="mh-trong">{tab === 'thi' ? 'Chưa có kết quả cuộc thi.' : 'Chưa có người học hiển thị.'}</div>{/each}
    </section>
    <NhipHocMinhHoa {revision} nguoi={ds.map((n) => ({ id: n.id, ten: n.ten }))} />
  </aside>
</div>
</div>
<div hidden={khu !== 'thuong'}><LichSuMinhHoa {revision} nguoi={ds.map((n) => ({ id: n.id, ten: n.ten }))} /></div>

{#if sua}
  <Modal title={sua.id ? 'Chỉnh sửa người học' : 'Thêm người học minh họa'} closable={!busy} onclose={() => { boAnhTam(); sua = null; }} width={560}>
    <form id="mh-form" class="mh-form" onsubmit={(e) => { e.preventDefault(); luu(); }}>
      <div class="mh-chon-anh">
        <span class="mh-avatar mh-avatar-lon">{#if anhTam || sua.avatarUrl}<img src={anhTam || sua.avatarUrl || ''} alt="Ảnh đại diện đang chọn" />{:else}{vietTat(sua.ten) || 'Ảnh'}{/if}</span>
        <div><label class="btn btn-sm">{anhTam || sua.avatarUrl ? 'Đổi ảnh' : 'Chọn ảnh'}<input type="file" accept="image/jpeg,image/png,image/webp" disabled={busy} onchange={chonAnh} /></label><small>JPG, PNG, WebP · Tối đa 5 MB</small></div>
        {#if anhTam || sua.avatarUrl}<button type="button" class="btn btn-sm" disabled={busy} onclick={() => { boAnhTam(); if (sua) sua.avatarUrl = null; }}>Xóa ảnh</button>{/if}
      </div>
      <label>Tên hiển thị<input class="inp" bind:value={sua.ten} required maxlength="80" placeholder="Ví dụ: Nguyễn Thảo Linh" /></label>
      <label>Nhịp học<Select options={mucs} bind:value={sua.muc} ariaLabel="Nhịp học" /></label>
      <div class="mh-lua-chon"><label><input type="checkbox" bind:checked={sua.tuDong} /> Tự động tăng phút theo nhịp học</label><small class="muted">Có ngày nghỉ và hai lượt học mỗi ngày. Tắt để giữ số liệu; thời gian tắt không được cộng bù.</small></div>
      <div class="mh-so"><label>Phút học hiện tại<input class="inp" type="number" min="0" max="50000" required bind:value={sua.phut} /></label><label>Số ngày học<input class="inp" type="number" min="0" max="31" required bind:value={sua.ngay} /></label><label>Phần hoàn thành<input class="inp" type="number" min="0" max="1000" required bind:value={sua.bai} /></label></div>
      <div class="mh-lua-chon"><b>Hiển thị ở đâu?</b><label><input type="checkbox" bind:checked={sua.hien} /> Hiển thị người học</label><label><input type="checkbox" bind:checked={sua.dangKy} /> Danh sách đăng ký cuộc thi</label><label><input type="checkbox" bind:checked={sua.thi} /> Bảng cuộc thi</label><small class="muted">Người học minh họa không tham gia xét thưởng thật.</small></div>
    </form>
    {#snippet actions()}<button class="btn" disabled={busy} onclick={() => { boAnhTam(); sua = null; }}>Hủy</button><button class="btn btn-son" disabled={busy} type="submit" form="mh-form">{busy ? 'Đang lưu...' : 'Lưu người học'}</button>{/snippet}
  </Modal>
{/if}
{#if xoa}<Modal title="Xóa người học minh họa?" onclose={() => xoa = null}><p>Xóa {xoa.ten} và toàn bộ lịch sử thưởng minh họa của người này? Thao tác không thể hoàn tác.</p>{#snippet actions()}<button class="btn" disabled={busy} onclick={() => xoa = null}>Giữ lại</button><button class="btn btn-son" disabled={busy} onclick={xoaNguoi}>Xóa người học</button>{/snippet}</Modal>{/if}

<style>
  .mh-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
  .mh-chon-anh { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; padding-bottom: 18px; border-bottom: 1px solid var(--line); }
  .mh-avatar-lon { width: 72px; height: 72px; font-size: 20px; }
  .mh-chon-anh > div { flex: 1; }
  .mh-chon-anh input { position: absolute; width: 1px; height: 1px; opacity: 0; }
  .mh-chon-anh small { display: block; margin-top: 8px; font-size: 11px; color: var(--muted); }
  .mh-dau, .mh-thanh, .mh-xem-dau { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
  .mh-dau { margin-bottom: 20px; } .mh-dau h1 { margin: 6px 0 8px; font-size: 28px; } .mh-dau p { margin: 0; }
  .mh-nhan { font-size: 11px; letter-spacing: .09em; font-weight: 800; color: var(--muted); }
  .mh-bo-cuc { display: grid; grid-template-columns: minmax(0, 1.3fr) minmax(0, 1fr); gap: 24px; align-items: start; }
  .mh-quan-ly { padding: 20px; } .mh-thanh { flex-wrap: wrap; margin-bottom: 12px; } .mh-thanh .inp { max-width: 250px; } .mh-thanh h2, .mh-xem h2 { font-size: 17px; margin: 0 0 5px; } .mh-thanh .muted, .mh-xem-dau .muted { font-size: 12px; }
  .mh-nguoi { display: flex; gap: 12px; align-items: center; padding: 20px 0; border-top: 1px solid var(--line); flex-wrap: wrap; }
  .mh-avatar { display: inline-flex; align-items: center; justify-content: center; flex: none; width: 44px; height: 44px; border-radius: 50%; background: #eef5ff; font-size: 13px; color: #244564; }
  .mh-thong-tin { flex: 1; min-width: 150px; display: flex; flex-direction: column; gap: 6px; } .mh-thong-tin small, .mh-thong-tin span { font-size: 12px; color: var(--muted); } .mh-thao-tac { display: flex; gap: 6px; align-items: center; } .mh-dang-hien { color: #167344; background: #effbf4; }
  .mh-xem-dau { margin: 4px 0 14px; flex-wrap: wrap; } .mh-bang { padding: 20px; } .mh-bang h3 { font-size: 17px; margin: 0 0 18px; } .mh-tabs { display: flex; background: var(--ground); border-radius: 10px; padding: 4px; gap: 3px; margin-bottom: 12px; } .mh-tabs button { flex: 1; border: 0; background: transparent; border-radius: 7px; padding: 9px 4px; color: var(--muted); font: inherit; font-size: 12px; cursor: pointer; } .mh-tabs .mh-chon { background: white; color: var(--text); box-shadow: 0 1px 3px #0000000d; }
  .mh-hang { display: flex; gap: 9px; align-items: center; padding: 14px 4px; border-bottom: 1px solid var(--line); font-size: 12px; } .mh-hang:last-child { border: 0; } .mh-hang > div { flex: 1; } .mh-hang small { display: block; color: var(--muted); font-size: 11px; margin-top: 5px; } .mh-hang strong { text-align: right; } .mh-nho { width: 32px; height: 32px; font-size: 11px; } .mh-trong { text-align: center; color: var(--muted); padding: 30px 12px; font-size: 13px; }
  .mh-form { display: flex; flex-direction: column; gap: 18px; } .mh-form label { display: flex; flex-direction: column; gap: 8px; font-size: 13px; } .mh-so { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; } .mh-lua-chon { border: 1px solid var(--line); padding: 16px; border-radius: 12px; display: flex; flex-direction: column; gap: 14px; } .mh-lua-chon label { flex-direction: row; align-items: center; } .mh-lua-chon input { accent-color: var(--son); }
  @media (max-width: 1100px) { .mh-bo-cuc { grid-template-columns: 1fr; } } @media (max-width: 600px) { .mh-dau { align-items: flex-start; flex-direction: column; } .mh-dau h1 { font-size: 24px; } .mh-thanh .inp { max-width: none; } .mh-so { grid-template-columns: 1fr; } .mh-quan-ly, .mh-bang { padding: 16px; } }
</style>
