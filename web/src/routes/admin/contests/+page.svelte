<script lang="ts">
  import { onMount } from 'svelte';
  import { api, ApiError } from '$lib/api/client';
  import { toast } from '$lib/stores/toast.svelte';
  import { num } from '$lib/utils/format';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';
  import Select from '$lib/components/Select.svelte';

  // Quản trị · Cuộc thi: giờ bắt đầu + số phút thi, ai đăng ký, cơ cấu giải.
  type Status = 'upcoming' | 'open' | 'ended';
  interface Contest {
    id: number; title: string; mockTestId: number; mockTitle: string; mockStatus: 'draft' | 'published';
    levelId: number; startsAt: string; durationMin: number; registrants: number; status: Status;
  }
  interface Mock { id: number; levelId: number; title: string; status: 'draft' | 'published'; durationSec: number; questionCount: number }
  interface Prize { label: string; percent: number; amount: number | null }
  interface Data { contests: Contest[]; mocks: Mock[]; prizes: Prize[] }
  interface Registrant { name: string; email: string; at: string }

  const NEW_MOCK = 'new';
  const STATUS: Record<Status, { label: string; cls: string }> = {
    upcoming: { label: 'Sắp diễn ra', cls: 'tag-warn' },
    open: { label: 'Đang thi', cls: 'tag-ok' },
    ended: { label: 'Đã kết thúc', cls: '' },
  };
  const LEVELS = [1, 2, 3, 4, 5, 6].map((n) => ({ v: n, label: `HSK ${n}` }));

  let d = $state<Data | null>(null);
  let failed = $state(false);
  let formOpen = $state(false);
  let saving = $state(false);
  let editing = $state<Contest | null>(null);

  let title = $state('');
  let levelId = $state(1);
  let mockId = $state<number | string>(NEW_MOCK);
  let newCount = $state(40);
  let startsAt = $state('');
  let durationMin = $state(60);

  let prizes = $state<{ label: string; percent: number }[]>([]);
  let savingPrizes = $state(false);
  const prizeTotal = $derived(Math.round(prizes.reduce((sum, p) => sum + (Number(p.percent) || 0), 0) * 10) / 10);

  let listOf = $state<Contest | null>(null);
  let registrants = $state<Registrant[] | null>(null);

  const mockOptions = $derived([
    { v: NEW_MOCK, label: '+ Tạo đề mới' },
    ...(d?.mocks ?? [])
      .filter((m) => m.levelId === levelId)
      .map((m) => ({ v: m.id, label: `${m.title} · ${m.questionCount} câu · ${m.status === 'published' ? 'Công khai' : 'Nháp'}` })),
  ]);
  const selectedMock = $derived(d?.mocks.find((m) => m.id === mockId) ?? null);

  /** "2026-11-30 20:00:00" → "20:00, 30/11/2026" */
  const showTime = (sql: string): string => `${sql.slice(11, 16)}, ${sql.slice(8, 10)}/${sql.slice(5, 7)}/${sql.slice(0, 4)}`;
  const toInput = (sql: string): string => sql.slice(0, 16).replace(' ', 'T');
  const errorText = (e: unknown, fallback: string): string =>
    e instanceof ApiError ? (Object.values(e.fields)[0] ?? e.message) : fallback;

  async function load(): Promise<void> {
    failed = false;
    try {
      d = await api.get<Data>('/admin/contests');
      prizes = d.prizes.map((p) => ({ label: p.label, percent: p.percent }));
    } catch {
      failed = true;
    }
  }
  onMount(load);

  function openNew(): void {
    editing = null;
    title = ''; levelId = 1; mockId = NEW_MOCK; newCount = 40; startsAt = ''; durationMin = 60;
    formOpen = true;
  }

  function openEdit(c: Contest): void {
    editing = c;
    title = c.title; levelId = c.levelId; mockId = c.mockTestId;
    startsAt = toInput(c.startsAt); durationMin = c.durationMin;
    formOpen = true;
  }

  async function save(): Promise<void> {
    saving = true;
    let createdMock: number | null = null;
    try {
      let useMock = mockId;
      if (useMock === NEW_MOCK) {
        const res = await api.post<{ id: number; copied: number }>(`/admin/levels/${levelId}/mock-tests`, {
          title: title.slice(0, 80),
          durationSec: durationMin * 60,
          copyFromTests: { count: newCount },
        });
        createdMock = res.id;
        useMock = res.id;
      }
      const body = { title, mockTestId: useMock, startsAt, durationMin };
      if (editing) await api.put(`/admin/contests/${editing.id}`, body);
      else await api.post('/admin/contests', body);
      formOpen = false;
      toast.ok('Đã lưu bài thi.');
      await load();
    } catch (e) {
      // Lưu bài thi hỏng thì đừng để lại đề rỗng vừa tạo trong danh sách Đề thi thử.
      if (createdMock !== null) await api.del(`/admin/mock-tests/${createdMock}`).catch(() => {});
      toast.show(errorText(e, 'Không lưu được bài thi.'));
    } finally {
      saving = false;
    }
  }

  async function remove(c: Contest): Promise<void> {
    if (!confirm(`Xoá bài thi “${c.title}”? Danh sách đăng ký và bảng xếp hạng của bài này sẽ mất, đề thi vẫn còn.`)) return;
    try {
      await api.del(`/admin/contests/${c.id}`);
      toast.ok('Đã xoá bài thi.');
      await load();
    } catch {
      toast.show('Không xoá được.');
    }
  }

  async function openRegistrants(c: Contest): Promise<void> {
    listOf = c;
    registrants = null;
    try {
      registrants = await api.get<Registrant[]>(`/admin/contests/${c.id}/registrants`);
    } catch {
      registrants = [];
      toast.show('Không tải được danh sách.');
    }
  }

  async function savePrizes(): Promise<void> {
    savingPrizes = true;
    try {
      const saved = await api.put<Prize[]>('/admin/contests/prizes', { rows: prizes });
      if (d) d = { ...d, prizes: saved };
      toast.ok('Đã lưu cơ cấu giải.');
    } catch (e) {
      toast.show(errorText(e, 'Không lưu được.'));
    } finally {
      savingPrizes = false;
    }
  }
</script>

<svelte:head><title>Cuộc thi · Quản trị · Zìkǎ</title></svelte:head>

<div class="head">
  <div>
    <h1>Cuộc thi</h1>
    <p class="muted" style="font-size: 14px">Đặt giờ bắt đầu và số phút thi. Chỉ người đăng ký trước giờ bắt đầu mới được làm bài, hết giờ tự nộp.</p>
  </div>
  <button type="button" class="btn btn-son btn-sm" onclick={openNew}><Icon name="plus" size={15} /> Tạo bài thi</button>
</div>

{#if failed}
  <EmptyState title="Không tải được danh sách bài thi.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if d === null}
  <Skeleton h="220px" r={16} />
{:else}
  {#if d.contests.length === 0}
    <EmptyState title="Chưa có bài thi nào." desc="Người học đang thấy “Chưa có lịch” trong hộp quỹ.">
      {#snippet action()}<button type="button" class="btn btn-son btn-sm" onclick={openNew}>Tạo bài thi</button>{/snippet}
    </EmptyState>
  {:else}
    <section class="paper" style="overflow-x: auto">
      <table class="tbl">
        <thead>
          <tr><th>Bài thi</th><th>Đề</th><th>Bắt đầu</th><th class="r">Số phút</th><th class="r">Đăng ký</th><th>Trạng thái</th><th></th></tr>
        </thead>
        <tbody>
          {#each d.contests as c (c.id)}
            <tr>
              <td><b>{c.title}</b></td>
              <td>
                <a href="/admin/mock-tests/{c.mockTestId}">HSK {c.levelId} · {c.mockTitle}</a>
                {#if c.mockStatus === 'published' && c.status === 'upcoming'}
                  <span class="err" style="display: block; font-size: 12.5px">Đề đang công khai, làm trước giờ thi được</span>
                {/if}
              </td>
              <td class="num">{showTime(c.startsAt)}</td>
              <td class="r num">{num(c.durationMin)}</td>
              <td class="r"><button type="button" class="btn btn-sm" onclick={() => openRegistrants(c)}>{num(c.registrants)} người</button></td>
              <td><span class="tag {STATUS[c.status].cls}">{STATUS[c.status].label}</span></td>
              <td style="white-space: nowrap">
                <button type="button" class="btn btn-bare btn-sm" aria-label="Sửa {c.title}" onclick={() => openEdit(c)}><Icon name="edit" size={15} /></button>
                <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá {c.title}" onclick={() => remove(c)}><Icon name="trash" size={15} /></button>
              </td>
            </tr>
          {/each}
        </tbody>
      </table>
    </section>
  {/if}

  <!-- Cơ cấu giải -->
  <section class="block prize-block">
    <h2 class="h2">Cơ cấu giải thưởng</h2>
    <p class="muted" style="font-size: 13.5px">
      Thứ tự dòng là thứ hạng nhận giải. Số tiền tính theo phần trăm của Quỹ tháng đang nhập ở mục Ủng hộ &amp; Shopee.
    </p>
    <div class="prize-rows">
      {#each prizes as p, i (i)}
        <div class="prize-row">
          <span class="muted prize-rank">Hạng {i + 1}</span>
          <input class="inp" aria-label="Tên giải hạng {i + 1}" maxlength="60" bind:value={p.label} />
          <span class="prize-percent">
            <input class="inp num" type="number" min="0.1" max="100" step="0.1" aria-label="Phần trăm hạng {i + 1}" bind:value={p.percent} />
            <span class="muted">%</span>
          </span>
          <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá giải {p.label}" onclick={() => (prizes = prizes.filter((_, k) => k !== i))}><Icon name="trash" size={15} /></button>
        </div>
      {/each}
    </div>
    <div class="prize-foot">
      <button type="button" class="btn btn-sm" disabled={prizes.length >= 20} onclick={() => (prizes = [...prizes, { label: `Giải ${prizes.length + 1}`, percent: 5 }])}><Icon name="plus" size={15} /> Thêm giải</button>
      <span class={prizeTotal > 100 ? 'err' : 'muted'} style="font-size: 13.5px">Tổng {prizeTotal}%{prizeTotal > 100 ? ', không được quá 100%' : ''}</span>
      <span style="flex: 1"></span>
      <button type="button" class="btn btn-son btn-sm" disabled={savingPrizes || prizeTotal > 100} onclick={savePrizes}>Lưu cơ cấu giải</button>
    </div>
  </section>
{/if}

{#if formOpen}
  <Modal title={editing ? 'Sửa bài thi' : 'Tạo bài thi'} onclose={() => (formOpen = false)} width={520}>
    <div class="field">
      <label class="lbl" for="c-title">Tên bài thi</label>
      <input id="c-title" class="inp" bind:value={title} maxlength="120" placeholder="Thi giải đề tháng 11" />
    </div>
    <div class="grid2">
      <div class="field"><label class="lbl" for="c-start">Bắt đầu lúc</label><input id="c-start" class="inp" type="datetime-local" bind:value={startsAt} /></div>
      <div class="field"><label class="lbl" for="c-min">Thời gian thi (phút)</label><input id="c-min" class="inp" type="number" min="1" max="1440" bind:value={durationMin} /></div>
    </div>
    <p class="hint" style="margin-top: 0">Tới giờ bắt đầu ai đã đăng ký mới vào làm được. Hết số phút này tất cả tự nộp bài.</p>
    <div class="grid2">
      <div class="field">
        <span class="lbl">Cấp</span>
        <Select bind:value={levelId} options={LEVELS} ariaLabel="Cấp HSK" onchange={() => (mockId = NEW_MOCK)} />
      </div>
      <div class="field">
        <span class="lbl">Đề thi</span>
        <Select bind:value={mockId} options={mockOptions} ariaLabel="Đề thi" />
      </div>
    </div>
    {#if mockId === NEW_MOCK}
      <div class="field"><label class="lbl" for="c-count">Số câu lấy từ các bài kiểm tra của cấp</label><input id="c-count" class="inp" type="number" min="1" max="200" bind:value={newCount} /></div>
      <p class="hint" style="margin-top: 0">Đề mới ở trạng thái Nháp nên người học không làm trước được. Sửa câu hoặc nhập Excel ở nút Soạn đề của mục Đề thi thử.</p>
    {:else if selectedMock}
      {#if selectedMock.status === 'published'}
        <p class="err">Đề này đang công khai ở mục Thi thử, người học làm trước giờ thi được. Nên chuyển về Nháp.</p>
      {:else}
        <p class="hint" style="margin-top: 0">{selectedMock.questionCount} câu. <a href="/admin/mock-tests/{selectedMock.id}">Mở trang soạn đề</a></p>
      {/if}
    {/if}
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (formOpen = false)}>Huỷ</button>
      <button type="button" class="btn btn-son btn-sm" disabled={saving} onclick={save}>Lưu bài thi</button>
    {/snippet}
  </Modal>
{/if}

{#if listOf}
  <Modal title={`Người đăng ký · ${listOf.title}`} onclose={() => (listOf = null)} width={560}>
    {#if registrants === null}
      <Skeleton h="120px" r={12} />
    {:else if registrants.length === 0}
      <p class="muted" style="font-size: 14px">Chưa có ai đăng ký.</p>
    {:else}
      <div style="overflow-x: auto">
        <table class="tbl">
          <thead><tr><th>Tên</th><th>Email</th><th>Đăng ký lúc</th></tr></thead>
          <tbody>
            {#each registrants as x, i (i)}
              <tr><td><b>{x.name}</b></td><td>{x.email}</td><td class="num">{x.at}</td></tr>
            {/each}
          </tbody>
        </table>
      </div>
    {/if}
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
    min-width: 0;
  }
  .grid2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(200px, 100%), 1fr));
    gap: 12px;
  }
  .prize-block {
    margin-top: 16px;
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .prize-rows {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .prize-row {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .prize-rank {
    flex: none;
    width: 56px;
    font-size: 13.5px;
  }
  .prize-row > .inp {
    flex: 1 1 0;
    min-width: 0;
  }
  .prize-percent {
    flex: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
  }
  .prize-percent .inp {
    width: 84px;
  }
  /* Điện thoại: tên giải chiếm một hàng riêng, xếp chung thì ô tên bị bóp còn vài chữ. */
  @media (max-width: 600px) {
    .prize-row {
      flex-wrap: wrap;
      padding-bottom: 8px;
      border-bottom: 1px dashed var(--line);
    }
    .prize-row > .inp {
      flex: 1 1 100%;
      order: 3;
    }
    .prize-rank {
      flex: 1;
    }
  }
  .prize-foot {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }
</style>
