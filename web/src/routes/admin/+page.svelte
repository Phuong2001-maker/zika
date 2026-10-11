<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '$lib/api/client';
  import { num, pct } from '$lib/utils/format';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';

  // Quản trị · Tổng quan (08 §2).
  interface Overview {
    updatedAt: string;
    kpi: {
      accounts: number; newThisWeek: number; online: number;
      peakToday: { count: number; at: string | null };
      activeToday: number; activeTodayPct: number; avgStudyMin: number;
      affClicks: number; affCtr: number;
    };
    heatmap: { rows: { label: string; cells: number[] }[]; max: number; summary: string };
    online: { userId: number; initials: string; name: string; activity: string; since: string; durationMin: number }[];
    newUsersDaily: { date: string; count: number }[];
    signup: { google: number; email: number };
    devices: { phone: number; desktop: number; tablet: number };
    todo: { unreadFeedback: number; inactiveLinks: number };
    levels: { levelId: number; visible: boolean; learning: number; completed: number; enteredWords: number }[];
  }

  let data = $state<Overview | null>(null);
  let failed = $state(false);
  let range = $state<'today' | '7d' | '30d'>('7d');

  async function load(): Promise<void> {
    failed = false;
    data = null;
    try {
      data = await api.get<Overview>('/admin/overview', { query: { range } });
    } catch {
      failed = true;
    }
  }

  onMount(load);
  $effect(() => {
    void range;
    void load();
  });

  /** Mức màu ô nhiệt đồ theo tỉ lệ so với đỉnh. */
  function heatClass(v: number, max: number): string {
    if (max === 0 || v === 0) return 'q0';
    const r = v / max;
    return r < 0.25 ? 'q1' : r < 0.5 ? 'q2' : r < 0.75 ? 'q3' : 'q4';
  }

  const maxNew = $derived(Math.max(1, ...(data?.newUsersDaily ?? []).map((d) => d.count)));
  const maxLevel = $derived(Math.max(1, ...(data?.levels ?? []).map((l) => l.learning + l.completed)));
  const signupTotal = $derived((data?.signup.google ?? 0) + (data?.signup.email ?? 0));
</script>

<svelte:head><title>Tổng quan · Quản trị · Zìkǎ</title></svelte:head>

<div class="head">
  <div>
    <h1>Tổng quan</h1>
    {#if data}<p class="muted" style="font-size: 14px">Cập nhật {new Date(data.updatedAt).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' })}</p>{/if}
  </div>
  <div class="seg">
    <button type="button" class:on={range === 'today'} onclick={() => (range = 'today')}>Hôm nay</button>
    <button type="button" class:on={range === '7d'} onclick={() => (range = '7d')}>7 ngày</button>
    <button type="button" class:on={range === '30d'} onclick={() => (range = '30d')}>30 ngày</button>
  </div>
</div>

{#if failed}
  <EmptyState title="Không tải được số liệu.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if data === null}
  <Skeleton h="110px" r={16} />
  <Skeleton h="300px" r={16} />
{:else}
  <!-- 5 ô số liệu -->
  <section class="kpi">
    <div>
      <span class="muted">Tài khoản</span>
      <b class="bignum" style="font-size: 28px">{num(data.kpi.accounts)}</b>
      <span style="font-size: 12.5px; color: var(--green-text)">+{data.kpi.newThisWeek} trong tuần</span>
    </div>
    <div>
      <span class="muted"><span class="dot"></span> Đang online</span>
      <b class="bignum" style="font-size: 28px">{num(data.kpi.online)}</b>
      <span class="muted" style="font-size: 12.5px">
        {data.kpi.peakToday.at ? `cao nhất hôm nay: ${data.kpi.peakToday.count} lúc ${data.kpi.peakToday.at}` : 'chưa có mốc hôm nay'}
      </span>
    </div>
    <div>
      <span class="muted">Có học hôm nay</span>
      <b class="bignum" style="font-size: 28px">{num(data.kpi.activeToday)}</b>
      <span class="muted" style="font-size: 12.5px">{pct(data.kpi.activeTodayPct)} số tài khoản</span>
    </div>
    <div>
      <span class="muted">Thời gian học TB</span>
      <b class="bignum" style="font-size: 28px">{data.kpi.avgStudyMin} <span style="font-size: 15px">phút</span></b>
      <span class="muted" style="font-size: 12.5px">mỗi người, mỗi ngày</span>
    </div>
    <div>
      <span class="muted">Bấm link Shopee</span>
      <b class="bignum" style="font-size: 28px">{num(data.kpi.affClicks)}</b>
      <span class="muted" style="font-size: 12.5px">tỉ lệ bấm {pct(data.kpi.affCtr)}</span>
    </div>
  </section>

  <div class="row2">
    <!-- Nhiệt đồ -->
    <section class="paper box" style="flex: 2 1 520px">
      <h2 class="h2">Mọi người online lúc mấy giờ?</h2>
      <p class="muted" style="font-size: 13.5px">Số người online theo từng giờ. Rê chuột lên ô để xem số.</p>
      <div style="overflow-x: auto">
        <div style="min-width: 560px; display: flex; flex-direction: column; gap: 3px">
          {#each data.heatmap.rows as r (r.label)}
            <div class="hm-row">
              <span class="muted num" style="font-size: 12px">{r.label}</span>
              {#each r.cells as v, h (h)}
                <span class="hm-c {heatClass(v, data.heatmap.max)}" title="{r.label} · {String(h).padStart(2, '0')}:00 · {v} người"></span>
              {/each}
            </div>
          {/each}
          <div class="hm-row" style="margin-top: 2px">
            <span></span>
            {#each Array.from({ length: 24 }) as _, h (h)}
              <span class="muted num" style="font-size: 10px; text-align: center">{h % 3 === 0 ? h : ''}</span>
            {/each}
          </div>
        </div>
      </div>
      <p style="font-size: 13.5px">{data.heatmap.summary}</p>
    </section>

    <!-- Đang online -->
    <section class="paper box" style="flex: 1 1 280px">
      <h2 class="h2"><span class="dot"></span> Đang online {num(data.kpi.online)}</h2>
      {#if data.online.length === 0}
        <p class="muted" style="font-size: 14px">Chưa có ai đang học.</p>
      {:else}
        <ul style="display: flex; flex-direction: column; gap: 6px">
          {#each data.online.slice(0, 6) as u (u.userId)}
            <li>
              <a href="/admin/users/{u.userId}" class="online-row">
                <span class="ava" style="width: 32px; height: 32px; font-size: 12px">{u.initials}</span>
                <span style="flex: 1; min-width: 0">
                  <b style="display: block; font-size: 14px">{u.name}</b>
                  <span class="muted" style="font-size: 12.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: block">{u.activity}</span>
                </span>
                <span class="muted num" style="font-size: 12.5px; text-align: right">{u.durationMin} phút<br />từ {u.since}</span>
              </a>
            </li>
          {/each}
        </ul>
        {#if data.kpi.online > 6}
          <a href="/admin/users?tab=online" class="btn-text" style="font-size: 13.5px">Xem cả {data.kpi.online} người</a>
        {/if}
      {/if}
    </section>
  </div>

  <div class="row2">
    <!-- Tài khoản mới -->
    <section class="paper box" style="flex: 2 1 520px">
      <h2 class="h2">Tài khoản mới mỗi ngày</h2>
      <p class="muted" style="font-size: 13.5px">30 ngày qua</p>
      <div class="chart">
        {#each data.newUsersDaily as d (d.date)}
          <span class="cbar" style="height: {Math.max(2, (d.count / maxNew) * 100)}%" title="{d.date}: {d.count} tài khoản mới"></span>
        {/each}
      </div>
    </section>

    <!-- Đăng ký & thiết bị -->
    <section class="paper box" style="flex: 1 1 280px">
      <h2 class="h2">Đăng ký và thiết bị</h2>

      <p class="muted" style="font-size: 13px">Đăng ký bằng</p>
      <div class="split">
        <span style="background: var(--blue); width: {signupTotal ? (data.signup.google / signupTotal) * 100 : 0}%"></span>
        <span style="background: #A3ABB8; flex: 1"></span>
      </div>
      <p style="font-size: 13.5px"><b>Google</b> {num(data.signup.google)} · <b>Email</b> {num(data.signup.email)}</p>

      <p class="muted" style="font-size: 13px; margin-top: 6px">Thiết bị</p>
      <div class="split">
        <span style="background: var(--text-2); width: {data.devices.phone}%"></span>
        <span style="background: #8FA0B8; width: {data.devices.desktop}%"></span>
        <span style="background: #C9D1DD; flex: 1"></span>
      </div>
      <p style="font-size: 13.5px">Điện thoại {data.devices.phone}% · Máy tính {data.devices.desktop}% · Tablet {data.devices.tablet}%</p>

      <hr class="rule" />
      <p class="muted" style="font-size: 13px">Cần xử lý</p>
      {#if data.todo.unreadFeedback === 0 && data.todo.inactiveLinks === 0}
        <p style="font-size: 13.5px">Không có việc cần xử lý.</p>
      {:else}
        {#if data.todo.unreadFeedback > 0}
          <a href="/admin/feedback" class="btn-text" style="font-size: 13.5px">{data.todo.unreadFeedback} góp ý chưa đọc ›</a>
        {/if}
        {#if data.todo.inactiveLinks > 0}
          <a href="/admin/support" class="btn-text" style="font-size: 13.5px">{data.todo.inactiveLinks} link Shopee đang tắt ›</a>
        {/if}
      {/if}
    </section>
  </div>

  <!-- Người học theo cấp -->
  <section class="paper box">
    <h2 class="h2">Người học theo cấp HSK</h2>
    <p class="muted" style="font-size: 13.5px">
      <span class="legend" style="background: #4F80E2"></span> Đang học
      <span class="legend" style="background: var(--ngoc); margin-left: 12px"></span> Học xong cả cấp
    </p>
    {#each data.levels as l (l.levelId)}
      <div class="lvrow">
        <span class="ib lv{l.levelId}" style="width: 70px; height: 30px; border-radius: 8px; font-size: 13px; font-weight: 700">HSK {l.levelId}</span>
        {#if l.visible}
          <span class="lvbar">
            <span style="background: #4F80E2; width: {(l.learning / maxLevel) * 100}%"></span>
            <span style="background: var(--ngoc); width: {(l.completed / maxLevel) * 100}%"></span>
          </span>
          <span class="num" style="font-size: 13px; flex: 0 0 210px">
            <b>{num(l.learning)}</b> đang học · <b>{num(l.completed)}</b> học xong cả cấp
          </span>
        {:else}
          <span class="lvbar off"></span>
          <span class="num muted" style="font-size: 13px; flex: 0 0 210px">
            Đang ẩn · đã nhập {num(l.enteredWords)} từ ·
            <a href="/admin/words?level={l.levelId}">Mở</a>
          </span>
        {/if}
      </div>
    {/each}
  </section>
{/if}

<style>
  .head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
  }
  .head h1 {
    font-size: 28px;
  }
  .row2 {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: stretch;
  }
  .box {
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
  }
  .online-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 8px;
    border-radius: var(--r-sm);
    text-decoration: none;
    color: var(--text);
  }
  .online-row:hover {
    background: var(--surface-2);
  }
  .chart {
    display: flex;
    align-items: flex-end;
    gap: 3px;
    height: 150px;
    padding-top: 8px;
  }
  .split {
    display: flex;
    height: 12px;
    border-radius: 999px;
    overflow: hidden;
    background: var(--track);
  }
  .legend {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 3px;
  }
  .lvrow {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }
  .lvbar {
    flex: 1 1 160px;
    display: flex;
    height: 14px;
    border-radius: 999px;
    overflow: hidden;
    background: var(--track);
    min-width: 120px;
  }
  .lvbar.off {
    border: 1px dashed var(--dash);
    background: none;
  }
</style>
