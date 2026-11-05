<script lang="ts">
  import { onMount } from 'svelte';
  import { api, ApiError } from '$lib/api/client';
  import { toast } from '$lib/stores/toast.svelte';
  import { num, pct } from '$lib/utils/format';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';

  // Quản trị · Ủng hộ & Shopee (08 §15).
  interface Link {
    id: number; name: string; url: string; imageUrl: string | null;
    placeIcon: boolean; placeFund: boolean;
    active: boolean; views: number; clicks: number; ctr: number;
  }
  interface Donate { enabled: boolean; bank: string; owner: string; account: string; memo: string; qr_path: string | null }
  interface Data {
    enabled: boolean;
    kpi: { views: number; clicks: number; ctr: number; active: number; total: number };
    links: Link[];
    donate: Donate;
    fund: Fund;
  }
  interface Fund { amount: number | null; month: string; updatedOn: string | null }

  let d = $state<Data | null>(null);
  let failed = $state(false);
  let editing = $state<Link | null>(null);
  let formOpen = $state(false);
  let saving = $state(false);

  // Biểu mẫu link
  let name = $state('');
  let url = $state('');
  let placeIcon = $state(false);
  let placeFund = $state(true);

  // Bé túi chỉ mang một link: link khác đang giữ thì khoá cả hai ô, link này mặc định ở Quỹ tháng.
  const iconOwner = $derived(d?.links.find((l) => l.placeIcon && l.id !== editing?.id) ?? null);

  // Tiền quỹ tháng do admin tự nhập
  let fundInput = $state('');
  let savingFund = $state(false);
  const groupDigits = (digits: string): string => (digits === '' ? '' : num(Number(digits)));

  async function load(): Promise<void> {
    failed = false;
    try {
      d = await api.get<Data>('/admin/support');
      fundInput = d.fund.amount === null ? '' : groupDigits(String(d.fund.amount));
    } catch {
      failed = true;
    }
  }
  onMount(load);

  async function toggleAll(on: boolean): Promise<void> {
    try {
      await api.patch('/admin/support', { enabled: on });
      if (d) d = { ...d, enabled: on };
      toast.ok(on ? 'Đã bật khối ủng hộ.' : 'Đã tắt khối ủng hộ.');
    } catch {
      toast.show('Không đổi được.');
    }
  }

  function onFundInput(e: Event & { currentTarget: HTMLInputElement }): void {
    fundInput = groupDigits(e.currentTarget.value.replace(/\D/g, ''));
    e.currentTarget.value = fundInput;
  }

  async function saveFund(): Promise<void> {
    const digits = fundInput.replace(/\D/g, '');
    savingFund = true;
    try {
      const fund = await api.put<Fund>('/admin/support/fund', { amount: digits === '' ? null : Number(digits) });
      if (d) d = { ...d, fund };
      toast.ok(fund.amount === null ? 'Đã xoá số tiền quỹ.' : 'Đã lưu số tiền quỹ.');
    } catch (e) {
      toast.show(e instanceof ApiError ? (e.fields.amount ?? e.message) : 'Không lưu được.');
    } finally {
      savingFund = false;
    }
  }

  function openNew(): void {
    editing = null;
    name = ''; url = ''; placeIcon = false; placeFund = true;
    formOpen = true;
  }

  function openEdit(l: Link): void {
    editing = l;
    name = l.name; url = l.url; placeIcon = l.placeIcon;
    placeFund = iconOwner !== null || l.placeFund;
    formOpen = true;
  }

  async function saveLink(): Promise<void> {
    saving = true;
    try {
      const body = { name, url, placeIcon, placeFund };
      if (editing) await api.put(`/admin/support/links/${editing.id}`, body);
      else await api.post('/admin/support/links', body);
      formOpen = false;
      toast.ok('Đã lưu link.');
      await load();
    } catch (e) {
      toast.show(e instanceof ApiError ? (e.fields.url ?? Object.values(e.fields)[0] ?? e.message) : 'Không lưu được.');
    } finally {
      saving = false;
    }
  }

  async function toggleLink(l: Link): Promise<void> {
    try {
      await api.patch(`/admin/support/links/${l.id}`, { active: !l.active });
      await load();
    } catch {
      toast.show('Không đổi được.');
    }
  }

  async function removeLink(l: Link): Promise<void> {
    if (!confirm(`Xoá link “${l.name}”?`)) return;
    try {
      await api.del(`/admin/support/links/${l.id}`);
      await load();
      toast.ok('Đã xoá.');
    } catch {
      toast.show('Không xoá được.');
    }
  }

  async function saveDonate(): Promise<void> {
    if (!d) return;
    try {
      await api.put('/admin/support/donate', {
        enabled: d.donate.enabled,
        bank: d.donate.bank,
        owner: d.donate.owner,
        account: d.donate.account,
        memo: d.donate.memo,
        qrPath: d.donate.qr_path,
      });
      toast.ok('Đã lưu thông tin ủng hộ.');
    } catch {
      toast.show('Không lưu được.');
    }
  }
</script>

<svelte:head><title>Ủng hộ &amp; Shopee · Quản trị · Zìkǎ</title></svelte:head>

<div class="head">
  <div>
    <h1>Ủng hộ &amp; Shopee</h1>
    <p class="muted" style="font-size: 14px">Link hiển thị cho người học, luôn kèm nhãn “Liên kết tiếp thị”.</p>
  </div>
  <button type="button" class="btn btn-son btn-sm" onclick={openNew}><Icon name="plus" size={15} /> Thêm link</button>
</div>

{#if failed}
  <EmptyState title="Không tải được dữ liệu.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if d === null}
  <Skeleton h="280px" r={16} />
{:else}
  <!-- Công tắc tổng -->
  <section class="block" style="padding: 16px 20px; display: flex; align-items: center; gap: 14px; flex-wrap: wrap">
    <label class="sw">
      <input type="checkbox" checked={d.enabled} onchange={(e) => toggleAll(e.currentTarget.checked)} />
      <span></span>
      Hiện link ủng hộ cho người học
    </label>
    {#if !d.enabled}
      <span class="muted" style="font-size: 13.5px; flex: 1 1 300px">
        Đang tắt: người học không thấy link Shopee hay mục ủng hộ nào.
      </span>
    {/if}
  </section>

  <!-- Tiền quỹ tháng -->
  <section class="block" style="padding: 16px 20px; display: flex; flex-direction: column; gap: 8px">
    <h2 class="h2">Quỹ tháng</h2>
    <p class="muted" style="font-size: 13.5px">Số tiền người học thấy ở thanh đầu trang và hộp quỹ. Bạn tự nhập, web không tự cộng.</p>
    <div class="fund-row">
      <div class="field fund-field">
        <label class="lbl" for="f-amount">Số tiền (đ)</label>
        <input id="f-amount" class="inp num" inputmode="numeric" autocomplete="off" placeholder="Ví dụ: 1.250.000" value={fundInput} oninput={onFundInput} />
      </div>
      <button type="button" class="btn btn-son btn-sm" disabled={savingFund} onclick={saveFund}>Lưu số tiền</button>
    </div>
    <p class="hint" style="margin-top: 0">
      {#if d.fund.amount === null}
        Chưa nhập: người học đang thấy “Đang cập nhật”.
      {:else}
        Đang hiện cho tháng {d.fund.month}, cập nhật ngày {d.fund.updatedOn}. Sang tháng mới, bấm Lưu lại để đổi sang tháng đó. Để trống rồi lưu là xoá.
      {/if}
    </p>
  </section>

  <section class="kpi">
    <div><span class="muted">Lượt hiển thị · 7 ngày</span><b class="bignum" style="font-size: 26px">{num(d.kpi.views)}</b></div>
    <div><span class="muted">Lượt bấm</span><b class="bignum" style="font-size: 26px">{num(d.kpi.clicks)}</b></div>
    <div><span class="muted">Tỉ lệ bấm</span><b class="bignum" style="font-size: 26px">{pct(d.kpi.ctr)}</b></div>
    <div><span class="muted">Link đang bật</span><b class="bignum" style="font-size: 26px">{d.kpi.active} <span style="font-size: 15px">/ {d.kpi.total}</span></b></div>
  </section>

  <!-- Danh sách link -->
  <section class="paper" style="overflow-x: auto">
    <table class="tbl">
      <thead>
        <tr><th>Tên hiển thị</th><th>Hiện ở đâu</th><th class="r">Hiển thị</th><th class="r">Bấm</th><th class="r">Tỉ lệ</th><th>Trạng thái</th><th></th></tr>
      </thead>
      <tbody>
        {#each d.links as l (l.id)}
          <tr>
            <td>
              <b>{l.name}</b>
              <span class="muted" style="display: block; font-size: 12.5px; max-width: 320px; overflow: hidden; text-overflow: ellipsis">{l.url}</span>
            </td>
            <td>
              {#if l.placeIcon}<span class="tag">Icon</span>{/if}
              {#if l.placeFund}<span class="tag">Quỹ tháng</span>{/if}
              {#if !l.placeIcon && !l.placeFund}<span class="muted">Chưa gắn vị trí</span>{/if}
            </td>
            <td class="r num">{num(l.views)}</td>
            <td class="r num">{num(l.clicks)}</td>
            <td class="r num">{pct(l.ctr)}</td>
            <td>
              <label class="sw">
                <input type="checkbox" checked={l.active} onchange={() => toggleLink(l)} />
                <span></span>
              </label>
            </td>
            <td style="white-space: nowrap">
              <button type="button" class="btn btn-bare btn-sm" aria-label="Sửa {l.name}" onclick={() => openEdit(l)}><Icon name="edit" size={15} /></button>
              <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá {l.name}" onclick={() => removeLink(l)}><Icon name="trash" size={15} /></button>
            </td>
          </tr>
        {/each}
      </tbody>
    </table>
    {#if d.links.length === 0}
      <p class="muted" style="padding: 16px; font-size: 14px">Chưa có link nào.</p>
    {/if}
  </section>

  <!-- Ủng hộ trực tiếp -->
  <section class="block" style="padding: 18px 20px; display: flex; flex-direction: column; gap: 12px">
    <h2 class="h2">Ủng hộ trực tiếp</h2>
    <p class="muted" style="font-size: 13.5px">Hiện khi người học bấm “ủng hộ trực tiếp”. Không qua trung gian.</p>

    <label class="sw"><input type="checkbox" bind:checked={d.donate.enabled} /><span></span> Đang hiện</label>

    <div class="grid2">
      <div class="field"><label class="lbl" for="s-bank">Ngân hàng / ví điện tử</label><input id="s-bank" class="inp" bind:value={d.donate.bank} /></div>
      <div class="field"><label class="lbl" for="s-owner">Chủ tài khoản</label><input id="s-owner" class="inp" bind:value={d.donate.owner} /></div>
      <div class="field"><label class="lbl" for="s-acc">Số tài khoản</label><input id="s-acc" class="inp num" bind:value={d.donate.account} /></div>
      <div class="field"><label class="lbl" for="s-memo">Nội dung chuyển khoản gợi ý</label><input id="s-memo" class="inp" bind:value={d.donate.memo} /></div>
    </div>

    <button type="button" class="btn btn-son btn-sm" style="align-self: flex-start" onclick={saveDonate}>Lưu thông tin</button>
  </section>
{/if}

{#if formOpen}
  <Modal title={editing ? 'Sửa link' : 'Thêm link mới'} onclose={() => (formOpen = false)} width={520}>
    <div class="field">
      <label class="lbl" for="l-url">Link tiếp thị liên kết Shopee</label>
      <input id="l-url" class="inp" bind:value={url} placeholder="https://s.shopee.vn/..." />
      <p class="hint">Chỉ nhận link https:// thuộc shopee.vn, s.shopee.vn hoặc shope.ee.</p>
    </div>
    <div class="field">
      <label class="lbl" for="l-name">Tên hiển thị</label>
      <input id="l-name" class="inp" bind:value={name} placeholder="Bộ thẻ từ vựng HSK 1-2" />
    </div>
    <div class="field">
      <span class="lbl">Hiện ở đâu</span>
      <label class="chk" class:place-locked={iconOwner}><input type="checkbox" bind:checked={placeIcon} disabled={iconOwner !== null} /> Icon (bé túi di chuyển, chỉ 1 link)</label>
      <label class="chk" class:place-locked={iconOwner}><input type="checkbox" bind:checked={placeFund} disabled={iconOwner !== null} /> Quỹ tháng (hiện được nhiều link)</label>
      {#if iconOwner}
        <p class="hint" style="margin-top: 0">Icon đang dùng link “{iconOwner.name}” nên link này mặc định ở Quỹ tháng. Muốn đổi Icon, sửa link đó và bỏ chọn Icon trước.</p>
      {/if}
    </div>
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (formOpen = false)}>Huỷ</button>
      <button type="button" class="btn btn-son btn-sm" disabled={saving} onclick={saveLink}>Lưu link</button>
    {/snippet}
  </Modal>
{/if}

<style>
  .head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 16px;
  }
  .head h1 {
    font-size: 28px;
  }
  .field {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .place-locked {
    opacity: 0.5;
    cursor: not-allowed;
  }
  .place-locked input {
    cursor: not-allowed;
  }
  .fund-row {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    flex-wrap: wrap;
  }
  .fund-field {
    flex: 0 1 260px;
  }
  .grid2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(200px, 100%), 1fr));
    gap: 12px;
  }
</style>
