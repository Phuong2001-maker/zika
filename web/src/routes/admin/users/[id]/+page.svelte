<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api, ApiError } from '$lib/api/client';
  import { toast } from '$lib/stores/toast.svelte';
  import { date, duration, lastSeen, num } from '$lib/utils/format';
  import Icon from '$lib/components/Icon.svelte';
  import Bar from '$lib/components/Bar.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';

  // Quản trị · Chi tiết người dùng (08 §4).
  interface Detail {
    user: {
      id: number; displayName: string; email: string; initials: string; signupMethod: string;
      createdAt: string; selfLevel: string; selfLevelInitial: string; role: string;
      status: 'online' | 'offline' | 'locked'; device: string | null; lastSeenAt?: string | null;
      lockedReason: string | null;
    };
    kpi: { studySec: number; visits: number; avgVisitMin: number; currentLevelId: number | null; lessonsDone: number };
    progressByLevel: { levelId: number; done: number; total: number }[];
    minutesByHour: number[];
    hourSummary: string;
    devices: { type: string; label: string; detail: string; pct: number }[];
  }
  interface Day {
    date: string; label: string; count: number; totalMin: number;
    blocks: { start: string; end: string }[];
    rows: { start: string; end: string | null; durationMin: number; deviceLabel: string; summary: string }[];
  }

  const LEVEL_LABEL: Record<string, string> = { beginner: 'Mới bắt đầu', hsk12: 'HSK 1–2', hsk34: 'HSK 3–4', hsk5: 'HSK 5+' };

  const userId = $derived(Number(page.params.id));

  let d = $state<Detail | null>(null);
  let days = $state<Day[]>([]);
  let nextBefore = $state<string | null>(null);
  let range = $state<'7d' | '30d' | 'all'>('7d');
  let failed = $state(false);
  let notifyOpen = $state(false);
  let nKind = $state<'modal' | 'email'>('modal');
  let nTitle = $state('');
  let nBody = $state('');

  async function load(): Promise<void> {
    failed = false;
    try {
      d = await api.get<Detail>(`/admin/users/${userId}`);
      await loadVisits(true);
    } catch {
      failed = true;
    }
  }

  async function loadVisits(reset = false): Promise<void> {
    try {
      const res = await api.get<{ days: Day[]; nextBefore: string | null }>(`/admin/users/${userId}/visits`, {
        query: { range, before: reset ? '' : (nextBefore ?? '') },
      });
      days = reset ? res.days : [...days, ...res.days];
      nextBefore = res.nextBefore;
    } catch {
      // Lịch sử phiên hỏng thì vẫn hiện phần trên.
    }
  }

  onMount(load);
  $effect(() => {
    void range;
    void loadVisits(true);
  });

  async function lock(): Promise<void> {
    if (!d) return;
    const reason = prompt('Lý do khoá (không bắt buộc):');
    if (reason === null) return;
    try {
      await api.post(`/admin/users/${userId}/lock`, { reason });
      toast.ok('Đã khoá tài khoản.');
      await load();
    } catch (e) {
      toast.show(e instanceof ApiError ? e.message : 'Không khoá được.');
    }
  }

  async function unlock(): Promise<void> {
    try {
      await api.post(`/admin/users/${userId}/unlock`);
      toast.ok('Đã mở khoá.');
      await load();
    } catch {
      toast.show('Không mở khoá được.');
    }
  }

  async function notify(): Promise<void> {
    try {
      await api.post(`/admin/users/${userId}/notify`, { kind: nKind, title: nTitle, body: nBody });
      notifyOpen = false;
      nTitle = '';
      nBody = '';
      toast.ok('Đã gửi thông báo.');
    } catch (e) {
      toast.show(e instanceof ApiError ? e.message : 'Không gửi được.');
    }
  }

  const maxMin = $derived(Math.max(1, ...(d?.minutesByHour ?? [1])));
  /** Phần trăm vị trí và độ rộng của một khối giờ trên dải 24 giờ. */
  function blockStyle(b: { start: string; end: string }): string {
    const toMin = (t: string): number => {
      const [h, m] = t.split(':').map(Number);
      return h * 60 + m;
    };
    const l = (toMin(b.start) / 1440) * 100;
    const w = Math.max(0.6, ((toMin(b.end) - toMin(b.start)) / 1440) * 100);
    return `left: ${l}%; width: ${w}%`;
  }
</script>

<svelte:head><title>{d?.user.displayName ?? 'Người dùng'} · Quản trị · Zìkǎ</title></svelte:head>

<nav class="crumb"><a href="/admin/users">Người dùng</a> / <b>{d?.user.displayName ?? '…'}</b></nav>

{#if failed}
  <EmptyState title="Không tải được người dùng này.">
    {#snippet action()}<a href="/admin/users" class="btn btn-sm">Về danh sách</a>{/snippet}
  </EmptyState>
{:else if d === null}
  <Skeleton h="140px" r={16} />
  <Skeleton h="300px" r={16} />
{:else}
  <!-- Hồ sơ -->
  <section class="block" style="padding: 20px 22px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap">
    <span class="ava" style="width: 64px; height: 64px; font-size: 20px">{d.user.initials}</span>
    <div style="flex: 1 1 240px; min-width: 0">
      <h1 style="font-size: 24px">{d.user.displayName}</h1>
      <p class="muted" style="font-size: 14px">{d.user.email}</p>
      <p style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px">
        <span class="tag {d.user.signupMethod === 'google' ? 'tag-g' : ''}">{d.user.signupMethod === 'google' ? 'Google' : 'Email'}</span>
        <span class="tag">Tham gia {date(d.user.createdAt)}</span>
        <span class="tag">
          {LEVEL_LABEL[d.user.selfLevelInitial] ?? d.user.selfLevelInitial}
          {d.user.selfLevel !== d.user.selfLevelInitial ? ` → ${LEVEL_LABEL[d.user.selfLevel] ?? d.user.selfLevel}` : ''}
        </span>
        {#if d.user.status === 'locked'}
          <span class="tag tag-bad">Bị khoá{d.user.lockedReason ? ` · ${d.user.lockedReason}` : ''}</span>
        {:else if d.user.status === 'online'}
          <span class="tag tag-ok"><span class="dot"></span> Đang online</span>
        {:else}
          <span class="tag">Lần cuối {lastSeen(d.user.lastSeenAt ?? null)}</span>
        {/if}
      </p>
    </div>
    <div style="display: flex; gap: 8px; flex-wrap: wrap">
      <button type="button" class="btn btn-sm" onclick={() => (notifyOpen = true)}><Icon name="bell" size={15} /> Gửi thông báo</button>
      {#if d.user.status === 'locked'}
        <button type="button" class="btn btn-sm" onclick={unlock}><Icon name="lock" size={15} /> Mở khoá</button>
      {:else}
        <button type="button" class="btn btn-danger btn-sm" onclick={lock}><Icon name="lock" size={15} /> Khoá tài khoản</button>
      {/if}
    </div>
  </section>

  <section class="kpi">
    <div><span class="muted">Tổng thời gian học</span><b class="bignum" style="font-size: 26px">{duration(d.kpi.studySec)}</b></div>
    <div><span class="muted">Số phiên</span><b class="bignum" style="font-size: 26px">{num(d.kpi.visits)}</b></div>
    <div><span class="muted">Trung bình mỗi phiên</span><b class="bignum" style="font-size: 26px">{d.kpi.avgVisitMin} <span style="font-size: 14px">phút</span></b></div>
    <div><span class="muted">Đang học</span><b class="bignum" style="font-size: 26px">{d.kpi.currentLevelId ? `HSK ${d.kpi.currentLevelId}` : '—'}</b></div>
    <div><span class="muted">Bài đã học xong</span><b class="bignum" style="font-size: 26px">{d.kpi.lessonsDone}</b></div>
  </section>

  <div class="row2">
    <section class="paper box" style="flex: 1 1 320px">
      <h2 class="h2">Bài đã học xong theo cấp</h2>
      {#each d.progressByLevel as p (p.levelId)}
        <div style="display: flex; align-items: center; gap: 10px">
          <span class="ib lv{p.levelId}" style="width: 64px; height: 26px; border-radius: 7px; font-size: 12.5px; font-weight: 700">HSK {p.levelId}</span>
          <span style="flex: 1"><Bar value={p.done} total={p.total} color="var(--green)" /></span>
          <span class="num" style="font-size: 13px; flex: 0 0 76px; text-align: right">{p.done} / {p.total} bài</span>
        </div>
      {/each}
    </section>

    <section class="paper box" style="flex: 1 1 280px">
      <h2 class="h2">Thiết bị</h2>
      {#if d.devices.length === 0}
        <p class="muted" style="font-size: 14px">Chưa có dữ liệu.</p>
      {:else}
        {#each d.devices as dev (dev.type + dev.detail)}
          <div style="display: flex; align-items: center; gap: 10px">
            <Icon name={dev.type === 'desktop' ? 'monitor' : dev.type === 'tablet' ? 'tablet' : 'phone'} size={18} />
            <span style="flex: 1; min-width: 0"><b style="font-size: 14px">{dev.label}</b><br /><span class="muted" style="font-size: 12.5px">{dev.detail}</span></span>
            <b class="num">{dev.pct}%</b>
          </div>
        {/each}
        <p class="muted" style="font-size: 12.5px">Tính theo thời lượng phiên.</p>
      {/if}
    </section>
  </div>

  <section class="paper box">
    <h2 class="h2">Hay học lúc mấy giờ</h2>
    <p class="muted" style="font-size: 13.5px">Tổng số phút theo giờ trong ngày, 30 ngày qua</p>
    <div class="hours">
      {#each d.minutesByHour as m, h (h)}
        <span class="cbar" style="height: {Math.max(2, (m / maxMin) * 100)}%" title="{h}:00 · {m} phút"></span>
      {/each}
    </div>
    <div style="display: flex; justify-content: space-between" class="muted num">
      <span>0h</span><span>6h</span><span>12h</span><span>18h</span><span>23h</span>
    </div>
    <p style="font-size: 13.5px">{d.hourSummary}</p>
  </section>

  <!-- Lịch sử phiên -->
  <section class="paper box">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap">
      <h2 class="h2">Lịch sử phiên</h2>
      <div class="seg">
        <button type="button" class:on={range === '7d'} onclick={() => (range = '7d')}>7 ngày</button>
        <button type="button" class:on={range === '30d'} onclick={() => (range = '30d')}>30 ngày</button>
        <button type="button" class:on={range === 'all'} onclick={() => (range = 'all')}>Tất cả</button>
      </div>
    </div>

    {#if days.length === 0}
      <p class="muted" style="font-size: 14px">Chưa có phiên nào trong khoảng này.</p>
    {:else}
      {#each days as day (day.date)}
        <div class="day">
          <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap">
            <b style="flex: 0 0 150px">{day.label}</b>
            <span class="muted num" style="font-size: 13px">{day.count} phiên · {day.totalMin} phút</span>
          </div>
          <span class="track">
            {#each day.blocks as b (b.start)}<i style={blockStyle(b)} title="{b.start}–{b.end}"></i>{/each}
          </span>
          <ul style="display: flex; flex-direction: column; gap: 4px">
            {#each day.rows as r (r.start)}
              <li style="display: flex; gap: 10px; font-size: 13.5px; flex-wrap: wrap">
                <b class="num" style="flex: 0 0 140px">{r.start} → {r.end ?? 'đang online'}</b>
                <span class="num" style="flex: 0 0 64px">{r.durationMin} phút</span>
                <span class="muted" style="flex: 1; min-width: 0">{r.deviceLabel}{r.summary ? ` · ${r.summary}` : ''}</span>
              </li>
            {/each}
          </ul>
        </div>
      {/each}

      {#if nextBefore}
        <button type="button" class="btn btn-sm btn-paper" style="align-self: flex-start" onclick={() => loadVisits(false)}>Xem phiên cũ hơn</button>
      {/if}
    {/if}
  </section>
{/if}

{#if notifyOpen}
  <Modal title="Gửi thông báo riêng" onclose={() => (notifyOpen = false)}>
    <div class="seg">
      <button type="button" class:on={nKind === 'modal'} onclick={() => (nKind = 'modal')}>Hộp nổi trong app</button>
      <button type="button" class:on={nKind === 'email'} onclick={() => (nKind = 'email')}>Email</button>
    </div>
    <input class="inp" bind:value={nTitle} placeholder="Tiêu đề" />
    <textarea class="inp" rows="4" bind:value={nBody} placeholder="Nội dung"></textarea>
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (notifyOpen = false)}>Huỷ</button>
      <button type="button" class="btn btn-son btn-sm" disabled={nTitle.trim() === '' || nBody.trim() === ''} onclick={notify}>Gửi</button>
    {/snippet}
  </Modal>
{/if}

<style>
  .row2 {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
  }
  .box {
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
  }
  .hours {
    display: flex;
    align-items: flex-end;
    gap: 3px;
    height: 130px;
  }
  .day {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 12px 0;
    border-top: 1px solid var(--line-2);
  }
</style>
