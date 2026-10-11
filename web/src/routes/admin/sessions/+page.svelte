<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { num } from '$lib/utils/format';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import { PART } from '$lib/parts';
  import type { Part } from '$lib/api/types';

  // Quản trị · Phiên truy cập (08 §5).
  interface Row {
    visitId: number;
    user: { id: number; initials: string; name: string };
    date: string; start: string; end: string | null; durationMin: number;
    device: string; os: string; parts: Part[]; online: boolean;
  }
  interface Meta {
    total: number; page: number; per: number; keepDays: number;
    kpi: { visits: number; online: number; avgMin: number; topPart: string | null };
  }

  let rows = $state<Row[] | null>(null);
  let meta = $state<Meta | null>(null);
  let failed = $state(false);

  let range = $state<'today' | '7d' | '30d'>('today');
  let q = $state('');
  let device = $state('all');
  let part = $state('all');
  let pageNo = $state(1);
  let searchTimer: ReturnType<typeof setTimeout> | null = null;

  async function load(): Promise<void> {
    failed = false;
    try {
      const res = await api.getFull<Row[]>('/admin/sessions', { query: { range, q, device, part, page: pageNo, per: 20 } });
      rows = res.data;
      meta = res.meta as unknown as Meta;
    } catch {
      failed = true;
    }
  }

  onMount(load);
  $effect(() => {
    void range;
    void device;
    void part;
    void pageNo;
    void load();
  });

  function onSearch(): void {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
      pageNo = 1;
      void load();
    }, 300);
  }

  const pages = $derived(meta ? Math.max(1, Math.ceil(meta.total / meta.per)) : 1);
  const rangeLabel = $derived(range === 'today' ? 'Phiên hôm nay' : range === '7d' ? 'Phiên 7 ngày' : 'Phiên 30 ngày');
</script>

<svelte:head><title>Phiên truy cập · Quản trị · Zìkǎ</title></svelte:head>

<div class="head">
  <div>
    <h1>Phiên truy cập</h1>
    <p class="muted" style="font-size: 14px">Ai vào web lúc nào, học bao lâu, học phần nào.</p>
  </div>
</div>

<div class="filters">
  <div class="seg">
    <button type="button" class:on={range === 'today'} onclick={() => { range = 'today'; pageNo = 1; }}>Hôm nay</button>
    <button type="button" class:on={range === '7d'} onclick={() => { range = '7d'; pageNo = 1; }}>7 ngày</button>
    <button type="button" class:on={range === '30d'} onclick={() => { range = '30d'; pageNo = 1; }}>30 ngày</button>
  </div>
  <span class="ico-in" style="flex: 1 1 220px">
    <Icon name="search" size={16} />
    <input class="inp" bind:value={q} oninput={onSearch} placeholder="Tìm theo tên hoặc email…" />
  </span>
  <div class="sel" style="flex: 0 0 170px">
    <select class="inp" bind:value={device}>
      <option value="all">Thiết bị: Tất cả</option><option value="phone">Điện thoại</option>
      <option value="desktop">Máy tính</option><option value="tablet">Máy tính bảng</option>
    </select>
    <Icon name="down" size={18} />
  </div>
  <div class="sel" style="flex: 0 0 180px">
    <select class="inp" bind:value={part}>
      <option value="all">Phần học: Tất cả</option>
      <option value="lt">Lật thẻ</option><option value="bt">Bài tập</option><option value="kt">Kiểm tra</option>
      <option value="lv">Luyện viết</option><option value="ht">Hội thoại</option><option value="np">Ngữ pháp</option>
    </select>
    <Icon name="down" size={18} />
  </div>
</div>

{#if meta}
  <section class="kpi" style="margin-bottom: 14px">
    <div><span class="muted">{rangeLabel}</span><b class="bignum" style="font-size: 26px">{num(meta.kpi.visits)}</b></div>
    <div><span class="muted"><span class="dot"></span> Đang online</span><b class="bignum" style="font-size: 26px">{num(meta.kpi.online)}</b></div>
    <div><span class="muted">Thời lượng trung bình</span><b class="bignum" style="font-size: 26px">{meta.kpi.avgMin} <span style="font-size: 14px">phút</span></b></div>
    <div><span class="muted">Phần học nhiều nhất</span><b class="bignum" style="font-size: 22px">{meta.kpi.topPart ? PART[meta.kpi.topPart as Part].name : '—'}</b></div>
  </section>
{/if}

{#if failed}
  <EmptyState title="Không tải được danh sách phiên.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if rows === null}
  <Skeleton h="320px" r={16} />
{:else if rows.length === 0}
  <EmptyState title="Không có phiên nào trong khoảng này." />
{:else if media.isAdminMobile}
  <div style="display: flex; flex-direction: column; gap: 10px">
    {#each rows as r (r.visitId)}
      <a href="/admin/users/{r.user.id}" class="paper mcard">
        <span class="ava" style="width: 34px; height: 34px; font-size: 12px">{r.user.initials}</span>
        <span style="flex: 1; min-width: 0">
          <b style="display: block">{r.user.name} {#if r.online}<span class="dot"></span>{/if}</b>
          <span class="muted num" style="font-size: 12.5px">{r.start} → {r.end ?? 'nay'} · <b>{r.durationMin} phút</b></span>
          <span style="display: flex; gap: 4px; flex-wrap: wrap; margin-top: 4px">
            {#each r.parts as p (p)}<span class="tag" style="background: {PART[p].chip}">{PART[p].short}</span>{/each}
          </span>
        </span>
      </a>
    {/each}
  </div>
{:else}
  <div class="paper" style="overflow-x: auto">
    <table class="tbl">
      <thead>
        <tr><th>Người dùng</th><th>Vào lúc</th><th>Ra lúc</th><th class="r">Thời lượng</th><th>Thiết bị</th><th>Đã học phần</th><th>Trạng thái</th></tr>
      </thead>
      <tbody>
        {#each rows as r (r.visitId)}
          <tr>
            <td>
              <a href="/admin/users/{r.user.id}" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text)">
                <span class="ava" style="width: 30px; height: 30px; font-size: 11.5px">{r.user.initials}</span>
                <b>{r.user.name}</b>
              </a>
            </td>
            <td class="num">{range === 'today' ? r.start : `${r.date.slice(8, 10)}/${r.date.slice(5, 7)} ${r.start}`}</td>
            <td class="num">{r.end ?? '—'}</td>
            <td class="r num"><b>{r.durationMin} phút</b></td>
            <td>
              <span style="display: inline-flex; align-items: center; gap: 6px">
                <Icon name={r.device === 'desktop' ? 'monitor' : r.device === 'tablet' ? 'tablet' : 'phone'} size={15} />
                {r.os}
              </span>
            </td>
            <td>
              <span style="display: flex; gap: 4px; flex-wrap: wrap">
                {#each r.parts as p (p)}<span class="tag" style="background: {PART[p].chip}">{PART[p].short}</span>{/each}
              </span>
            </td>
            <td>
              {#if r.online}<span class="tag tag-ok"><span class="dot"></span> Online</span>
              {:else}<span class="tag"><span class="dot dot-off"></span> Đã thoát</span>{/if}
            </td>
          </tr>
        {/each}
      </tbody>
    </table>
  </div>

  <div class="pager">
    {#if meta}
      <span class="muted num" style="font-size: 13px">
        Hiển thị {(meta.page - 1) * meta.per + 1}–{Math.min(meta.page * meta.per, meta.total)} trên {num(meta.total)}
      </span>
    {/if}
    <span style="flex: 1"></span>
    <button type="button" class="btn btn-sm btn-icon" aria-label="Trang trước" disabled={pageNo === 1} onclick={() => pageNo--}><Icon name="left" size={15} /></button>
    <span class="num" style="font-size: 13.5px">{pageNo} / {pages}</span>
    <button type="button" class="btn btn-sm btn-icon" aria-label="Trang sau" disabled={pageNo >= pages} onclick={() => pageNo++}><Icon name="right" size={15} /></button>
  </div>

  {#if meta}
    <p class="muted" style="font-size: 12.5px; margin-top: 8px">
      Nhật ký phiên lưu {meta.keepDays} ngày (đổi trong Cài đặt → Quyền riêng tư).
    </p>
  {/if}
{/if}

<style>
  .head h1 {
    font-size: 28px;
  }
  .filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: center;
    margin: 16px 0 14px;
  }
  .mcard {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 12px 14px;
    text-decoration: none;
    color: var(--text);
  }
  .pager {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 12px;
  }
</style>
