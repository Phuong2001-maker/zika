<script lang="ts">
  import { onMount } from 'svelte';
  import { api, ApiError } from '$lib/api/client';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';

  // Quản trị · Quán trà: tin bị báo cáo, tin gần đây, người đang bị cấm, đóng/mở phòng.
  interface Tin { id: number; userId: number; name: string; email: string; body: string; createdAt: string; deleted: boolean; reports: number; reasons: string; room: string }
  interface Ban { id: number; slug: string; name: string; description: string; icon: string; open: boolean; sortOrder: number; today: number; lastId: number }
  interface Cam { userId: number; name: string; email: string; until: string | null; reason: string }
  interface Data { open: boolean; counts: { reported: number; muted: number }; messages?: Tin[]; mutes?: Cam[]; rooms?: Ban[] }

  let tab = $state<'reported' | 'all' | 'muted' | 'rooms'>('reported');
  /** Bàn đang sửa; id 0 = thêm bàn mới. */
  let suaBan = $state<{ id: number; name: string; description: string; icon: string; sortOrder: number; open: boolean } | null>(null);
  let d = $state<Data | null>(null);
  let loi = $state(false);
  let cam = $state<{ userId: number; name: string } | null>(null);
  let phut = $state<number | null>(60 * 24);
  let lyDo = $state('');
  const MUC_CAM: [string, number | null][] = [['1 giờ', 60], ['1 ngày', 60 * 24], ['7 ngày', 60 * 24 * 7], ['30 ngày', 60 * 24 * 30], ['Vĩnh viễn', null]];

  const ngay = (iso: string | null) =>
    iso ? new Date(iso).toLocaleString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : 'Vĩnh viễn';
  const baoLoi = (e: unknown, macDinh: string) => toast.show(e instanceof ApiError ? e.message : macDinh, 'info', 5000);

  async function tai(): Promise<void> {
    loi = false;
    try {
      d = await api.get<Data>('/admin/chat', { query: { tab } });
    } catch {
      loi = true;
    }
  }
  onMount(tai);

  function doiTab(t: typeof tab): void {
    tab = t;
    d = null;
    void tai();
  }

  async function doiMo(mo: boolean): Promise<void> {
    try {
      await api.patch('/admin/chat', { open: mo });
      toast.ok(mo ? 'Đã mở cửa Quán trà.' : 'Đã tạm đóng Quán trà: học viên chỉ đọc được, không gửi được.');
      await tai();
    } catch (e) {
      baoLoi(e, 'Chưa đổi được.');
    }
  }

  async function xoa(t: Tin): Promise<void> {
    try {
      await api.del(`/chat/tin/${t.id}`);
      toast.ok('Đã xoá tin.');
      await tai();
    } catch (e) {
      baoLoi(e, 'Chưa xoá được tin.');
    }
  }

  async function camChat(): Promise<void> {
    if (!cam) return;
    try {
      await api.post('/admin/chat/mute', { userId: cam.userId, minutes: phut, reason: lyDo });
      toast.ok(`Đã cấm ${cam.name} trò chuyện ${phut === null ? 'vĩnh viễn' : MUC_CAM.find((m) => m[1] === phut)?.[0] ?? ''}.`);
      cam = null;
      lyDo = '';
      await tai();
    } catch (e) {
      baoLoi(e, 'Chưa cấm được.');
    }
  }

  async function luuBan(): Promise<void> {
    if (!suaBan) return;
    const { id, ...body } = suaBan;
    try {
      if (id) await api.put(`/admin/chat/rooms/${id}`, body);
      else await api.post('/admin/chat/rooms', body);
      toast.ok(id ? 'Đã lưu bàn.' : 'Đã thêm bàn mới.');
      suaBan = null;
      await tai();
    } catch (e) {
      baoLoi(e, 'Chưa lưu được bàn.');
    }
  }

  async function boCam(c: Cam): Promise<void> {
    try {
      await api.del(`/admin/chat/mute/${c.userId}`);
      toast.ok(`Đã bỏ cấm ${c.name}.`);
      await tai();
    } catch (e) {
      baoLoi(e, 'Chưa bỏ cấm được.');
    }
  }
</script>

<svelte:head><title>Quán trà · Quản trị · Zìkǎ</title></svelte:head>

<div class="ct-dau">
  <div>
    <h1>Quán trà</h1>
    <p class="muted" style="font-size: 14px">Phòng trò chuyện của người học, chia bàn theo chủ đề. Xoá tin xấu, cấm người phá, đóng bàn hay cả quán khi cần dọn dẹp.</p>
  </div>
  {#if tab === 'rooms'}
    <button type="button" class="btn btn-son btn-sm" onclick={() => (suaBan = { id: 0, name: '', description: '', icon: '🍵', sortOrder: (d?.rooms?.length ?? 0) + 1, open: true })}><Icon name="plus" size={15} />Thêm bàn</button>
  {/if}
  {#if d}
    <label class="sw"><input type="checkbox" checked={d.open} onchange={(e) => doiMo(e.currentTarget.checked)} /><span></span> {d.open ? 'Đang mở cửa' : 'Đang đóng cửa'}</label>
  {/if}
</div>

<div class="tabs" role="tablist" style="margin-bottom: 14px">
  <button type="button" role="tab" class="tab" class:on={tab === 'reported'} aria-selected={tab === 'reported'} onclick={() => doiTab('reported')}>
    Bị báo cáo{#if d?.counts.reported}<span class="tag tag-bad num">{d.counts.reported}</span>{/if}
  </button>
  <button type="button" role="tab" class="tab" class:on={tab === 'all'} aria-selected={tab === 'all'} onclick={() => doiTab('all')}>Tin gần đây</button>
  <button type="button" role="tab" class="tab" class:on={tab === 'muted'} aria-selected={tab === 'muted'} onclick={() => doiTab('muted')}>
    Đang bị cấm{#if d?.counts.muted}<span class="tag num">{d.counts.muted}</span>{/if}
  </button>
  <button type="button" role="tab" class="tab" class:on={tab === 'rooms'} aria-selected={tab === 'rooms'} onclick={() => doiTab('rooms')}>Các bàn</button>
</div>

{#if loi}
  <EmptyState title="Không tải được Quán trà.">{#snippet action()}<button type="button" class="btn btn-sm" onclick={tai}>Thử lại</button>{/snippet}</EmptyState>
{:else if d === null}
  <Skeleton h="220px" r={16} />
{:else if tab === 'rooms'}
  <section class="paper" style="overflow-x: auto">
    <table class="tbl">
      <thead><tr><th>Bàn</th><th>Mô tả</th><th class="r">Tin 24 giờ</th><th>Trạng thái</th><th></th></tr></thead>
      <tbody>
        {#each d.rooms ?? [] as b (b.id)}
          <tr>
            <td style="white-space: nowrap"><span aria-hidden="true">{b.icon}</span> <b>{b.name}</b><span class="muted" style="display: block; font-size: 12px">/learn/chat/{b.slug}</span></td>
            <td style="font-size: 13.5px">{b.description}</td>
            <td class="r num">{b.today}</td>
            <td><span class="tag" class:tag-ok={b.open}>{b.open ? 'Đang mở' : 'Đã đóng'}</span></td>
            <td style="text-align: right"><button type="button" class="btn btn-sm btn-text" onclick={() => (suaBan = { id: b.id, name: b.name, description: b.description, icon: b.icon, sortOrder: b.sortOrder, open: b.open })}><Icon name="edit" size={15} />Sửa</button></td>
          </tr>
        {/each}
      </tbody>
    </table>
  </section>
  <p class="hint">Đóng bàn là chỉ đọc: tin cũ còn, không ai gửi thêm được. Không xoá bàn để khỏi mất tin.</p>
{:else if tab === 'muted'}
  {#if !d.mutes?.length}
    <EmptyState title="Không ai đang bị cấm." />
  {:else}
    <section class="paper" style="overflow-x: auto">
      <table class="tbl">
        <thead><tr><th>Học viên</th><th>Cấm đến</th><th>Lý do</th><th></th></tr></thead>
        <tbody>
          {#each d.mutes as c (c.userId)}
            <tr>
              <td>{c.name}<span class="muted" style="display: block; font-size: 12.5px">{c.email}</span></td>
              <td class="num">{ngay(c.until)}</td>
              <td>{c.reason}</td>
              <td style="text-align: right"><button type="button" class="btn btn-sm" onclick={() => boCam(c)}>Bỏ cấm</button></td>
            </tr>
          {/each}
        </tbody>
      </table>
    </section>
  {/if}
{:else if !d.messages?.length}
  <EmptyState title={tab === 'reported' ? 'Không có tin nào bị báo cáo.' : 'Quán trà chưa có tin nào.'} />
{:else}
  <section class="paper" style="overflow-x: auto">
    <table class="tbl">
      <thead><tr><th>Người gửi</th><th>Bàn</th><th>Tin nhắn</th><th>Lúc</th><th></th></tr></thead>
      <tbody>
        {#each d.messages as t (t.id)}
          <tr class:ct-da-xoa={t.deleted}>
            <td style="white-space: nowrap">{t.name}<span class="muted" style="display: block; font-size: 12.5px">{t.email}</span></td>
            <td style="white-space: nowrap; font-size: 13px">{t.room}</td>
            <td>
              <span class="ct-noi-dung">{t.body}</span>
              {#if t.reports}<span class="ct-bao"><Icon name="flag" size={13} />{t.reports} báo cáo{t.reasons ? ` · ${t.reasons}` : ''}</span>{/if}
              {#if t.deleted}<span class="tag" style="margin-top: 4px">Đã xoá</span>{/if}
            </td>
            <td class="num" style="white-space: nowrap">{ngay(t.createdAt)}</td>
            <td style="white-space: nowrap; text-align: right">
              {#if !t.deleted}<button type="button" class="btn btn-sm btn-danger" onclick={() => xoa(t)}><Icon name="trash" size={14} />Xoá</button>{/if}
              <button type="button" class="btn btn-sm" onclick={() => (cam = { userId: t.userId, name: t.name })}>Cấm chat</button>
            </td>
          </tr>
        {/each}
      </tbody>
    </table>
  </section>
{/if}

{#if suaBan}
  <Modal title={suaBan.id ? 'Sửa bàn' : 'Thêm bàn'} onclose={() => (suaBan = null)} width={480}>
    <div class="ct-hang">
      <div class="field" style="flex: 0 0 90px"><label class="lbl" for="ct-icon">Biểu tượng</label><input id="ct-icon" class="inp" maxlength="8" bind:value={suaBan.icon} /></div>
      <div class="field" style="flex: 1"><label class="lbl" for="ct-ten">Tên bàn</label><input id="ct-ten" class="inp" bind:value={suaBan.name} placeholder="Luyện thi HSK 3" /></div>
    </div>
    <div class="field"><label class="lbl" for="ct-mo-ta">Mô tả (người học thấy dưới tên bàn)</label><input id="ct-mo-ta" class="inp" bind:value={suaBan.description} placeholder="Ôn đề, chia sẻ kinh nghiệm thi" /></div>
    <div class="field" style="max-width: 140px"><label class="lbl" for="ct-thu-tu">Thứ tự</label><input id="ct-thu-tu" class="inp num" type="number" min="0" max="999" bind:value={suaBan.sortOrder} /></div>
    <label class="sw"><input type="checkbox" bind:checked={suaBan.open} /><span></span> Đang mở (tắt = chỉ đọc)</label>
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (suaBan = null)}>Huỷ</button>
      <button type="button" class="btn btn-son btn-sm" onclick={luuBan}>Lưu</button>
    {/snippet}
  </Modal>
{/if}

{#if cam}
  <Modal title="Cấm {cam.name} trò chuyện" onclose={() => (cam = null)} width={460}>
    <div class="ct-muc" role="radiogroup" aria-label="Thời gian cấm">
      {#each MUC_CAM as [nhan, gia] (nhan)}
        <button type="button" role="radio" aria-checked={phut === gia} class:on={phut === gia} onclick={() => (phut = gia)}>{nhan}</button>
      {/each}
    </div>
    <div class="field"><label class="lbl" for="ct-ly-do">Lý do (người bị cấm không thấy)</label><input id="ct-ly-do" class="inp" bind:value={lyDo} placeholder="Nói tục nhiều lần" /></div>
    <p class="hint" style="margin: 0">Người bị cấm vẫn đọc được Quán trà nhưng không gửi được tin. Tin cũ của họ giữ nguyên, muốn gỡ thì xoá từng tin.</p>
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (cam = null)}>Thôi</button>
      <button type="button" class="btn btn-son btn-sm" onclick={camChat}>Cấm</button>
    {/snippet}
  </Modal>
{/if}

<style>
  .ct-dau { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
  .ct-dau h1 { font-size: 28px; }
  .ct-noi-dung { display: block; max-width: 520px; white-space: pre-wrap; overflow-wrap: anywhere; font-size: 14px; }
  .ct-bao { display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; font-size: 12.5px; font-weight: 600; color: var(--bad-text); }
  .ct-da-xoa td { opacity: .55; }
  .field { display: flex; flex-direction: column; gap: 4px; }
  .ct-hang { display: flex; gap: 10px; }
  .ct-muc { display: flex; flex-wrap: wrap; gap: 6px; }
  .ct-muc button {
    height: 32px; padding: 0 12px; border: 1px solid var(--line); border-radius: 999px; background: var(--surface);
    color: var(--text-2); font: 600 13px var(--font-ui); cursor: pointer;
  }
  .ct-muc button.on { background: var(--son-soft); border-color: var(--son-line); color: var(--son-text); }
</style>
