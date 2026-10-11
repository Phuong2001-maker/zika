<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api, ApiError } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { toast } from '$lib/stores/toast.svelte';
  import { duration, lastSeen, num } from '$lib/utils/format';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';

  // Quản trị · Người dùng (08 §3).
  interface Row {
    id: number; initials: string; name: string; email: string; isNew: boolean;
    method: 'google' | 'email'; role: string; createdAt: string; lastSeenAt: string | null;
    lastVisit: { start: string; end: string | null; durationMin: number } | null;
    totalStudySec: number; currentLevelId: number | null; lessonsDone: number;
    status: 'online' | 'offline' | 'locked';
  }
  interface Meta { total: number; page: number; per: number; counts: { all: number; online: number; new: number; locked: number } }

  let rows = $state<Row[] | null>(null);
  let meta = $state<Meta | null>(null);
  let failed = $state(false);

  let q = $state('');
  let method = $state('all');
  let sort = $state('recent');
  let tab = $state(page.url.searchParams.get('tab') ?? 'all');
  let pageNo = $state(1);
  let searchTimer: ReturnType<typeof setTimeout> | null = null;

  let menuFor = $state<number | null>(null);
  let roleFor = $state<Row | null>(null);
  let newRole = $state('learner');

  async function load(): Promise<void> {
    failed = false;
    try {
      const res = await api.getFull<Row[]>('/admin/users', { query: { q, method, sort, tab, page: pageNo, per: 10 } });
      rows = res.data;
      meta = res.meta as unknown as Meta;
    } catch {
      failed = true;
    }
  }

  onMount(load);
  $effect(() => {
    void tab;
    void method;
    void sort;
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

  async function lock(u: Row): Promise<void> {
    const reason = prompt(`Khoá tài khoản ${u.name}? Lý do (không bắt buộc):`);
    if (reason === null) return;
    try {
      await api.post(`/admin/users/${u.id}/lock`, { reason });
      toast.ok('Đã khoá tài khoản.');
      await load();
    } catch (e) {
      toast.show(e instanceof ApiError ? e.message : 'Không khoá được.');
    }
    menuFor = null;
  }

  async function unlock(u: Row): Promise<void> {
    try {
      await api.post(`/admin/users/${u.id}/unlock`);
      toast.ok('Đã mở khoá.');
      await load();
    } catch {
      toast.show('Không mở khoá được.');
    }
    menuFor = null;
  }

  async function saveRole(): Promise<void> {
    if (!roleFor) return;
    try {
      await api.patch(`/admin/users/${roleFor.id}/role`, { role: newRole });
      toast.ok('Đã đổi quyền.');
      roleFor = null;
      await load();
    } catch (e) {
      toast.show(e instanceof ApiError ? e.message : 'Không đổi được quyền.');
    }
  }

  async function remove(u: Row): Promise<void> {
    if (!confirm(`Xoá tài khoản ${u.name}? Không khôi phục được.`)) return;
    try {
      await api.del(`/admin/users/${u.id}`);
      toast.ok('Đã xoá tài khoản.');
      await load();
    } catch (e) {
      toast.show(e instanceof ApiError ? e.message : 'Không xoá được.');
    }
    menuFor = null;
  }

  const TABS: [string, string, keyof Meta['counts']][] = [
    ['all', 'Tất cả', 'all'],
    ['online', 'Đang online', 'online'],
    ['new', 'Mới tuần này', 'new'],
    ['locked', 'Bị khoá', 'locked'],
  ];
  const pages = $derived(meta ? Math.max(1, Math.ceil(meta.total / meta.per)) : 1);
  const ROLE_LABEL: Record<string, string> = { learner: 'Người học', editor: 'Biên tập', admin: 'Quản trị' };
</script>

<svelte:head><title>Người dùng · Quản trị · Zìkǎ</title></svelte:head>

<div class="head">
  <div>
    <h1>Người dùng</h1>
    {#if meta}
      <p class="muted" style="font-size: 14px">{num(meta.counts.all)} tài khoản · {meta.counts.online} đang online</p>
    {/if}
  </div>
</div>

<div class="filters">
  <span class="ico-in" style="flex: 1 1 240px">
    <Icon name="search" size={16} />
    <input class="inp" bind:value={q} oninput={onSearch} placeholder="Tìm theo tên hoặc email…" />
  </span>
  <div class="sel" style="flex: 0 0 180px">
    <select class="inp" bind:value={method}><option value="all">Đăng ký qua: Tất cả</option><option value="google">Google</option><option value="email">Email</option></select>
    <Icon name="down" size={18} />
  </div>
  <div class="sel" style="flex: 0 0 190px">
    <select class="inp" bind:value={sort}><option value="recent">Vào gần nhất</option><option value="study">Học nhiều nhất</option><option value="new">Mới tạo</option></select>
    <Icon name="down" size={18} />
  </div>
</div>

{#if meta}
  <nav class="tabs" style="margin-bottom: 14px">
    {#each TABS as [v, label, key] (v)}
      <button type="button" class="tab" class:on={tab === v} onclick={() => { tab = v; pageNo = 1; }}>
        {label} <span class="muted num" style="font-weight: 500">{num(meta.counts[key])}</span>
      </button>
    {/each}
  </nav>
{/if}

{#if failed}
  <EmptyState title="Không tải được danh sách.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if rows === null}
  <Skeleton h="320px" r={16} />
{:else if rows.length === 0}
  <EmptyState title="Không có tài khoản nào khớp." />
{:else if media.isAdminMobile}
  <div style="display: flex; flex-direction: column; gap: 10px">
    {#each rows as u (u.id)}
      <a href="/admin/users/{u.id}" class="paper mcard">
        <span class="ava">{u.initials}</span>
        <span style="flex: 1; min-width: 0">
          <b style="display: block">{u.name} {#if u.status === 'online'}<span class="dot"></span>{/if}</b>
          <span class="muted" style="font-size: 12.5px; display: block">{u.email}</span>
          <span class="muted num" style="font-size: 12.5px">
            {lastSeen(u.lastSeenAt)} · {u.currentLevelId ? `HSK ${u.currentLevelId}` : '—'} · {duration(u.totalStudySec)}
          </span>
        </span>
        <Icon name="right" size={16} />
      </a>
    {/each}
  </div>
{:else}
  <div class="paper" style="overflow-x: auto">
    <table class="tbl">
      <thead>
        <tr>
          <th>Người dùng</th><th>Đăng ký qua</th><th>Ngày tạo</th><th>Lần vào gần nhất</th>
          <th>Phiên gần nhất</th><th class="r">Tổng thời gian</th><th>Đang học</th>
          <th class="r">Bài xong</th><th>Trạng thái</th><th></th>
        </tr>
      </thead>
      <tbody>
        {#each rows as u (u.id)}
          <tr>
            <td>
              <a href="/admin/users/{u.id}" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: var(--text)">
                <span class="ava" style="width: 32px; height: 32px; font-size: 12px">{u.initials}</span>
                <span>
                  <b style="display: block">{u.name} {#if u.isNew}<span class="tag tag-ok">Mới</span>{/if}</b>
                  <span class="muted" style="font-size: 12.5px">{u.email}</span>
                </span>
              </a>
            </td>
            <td><span class="tag {u.method === 'google' ? 'tag-g' : ''}">{u.method === 'google' ? 'Google' : 'Email'}</span></td>
            <td class="num">{u.createdAt}</td>
            <td class="num">{lastSeen(u.lastSeenAt)}</td>
            <td class="num">
              {#if u.lastVisit}{u.lastVisit.start} → {u.lastVisit.end ?? 'nay'} · {u.lastVisit.durationMin} phút{:else}—{/if}
            </td>
            <td class="r num">{duration(u.totalStudySec)}</td>
            <td>{#if u.currentLevelId}<span class="ib lv{u.currentLevelId}" style="padding: 2px 8px; border-radius: 7px; font-size: 12.5px; font-weight: 700">HSK {u.currentLevelId}</span>{:else}—{/if}</td>
            <td class="r num">{u.lessonsDone} bài</td>
            <td>
              {#if u.status === 'locked'}<span class="tag tag-bad">Bị khoá</span>
              {:else if u.status === 'online'}<span class="tag tag-ok"><span class="dot"></span> Online</span>
              {:else}<span class="tag"><span class="dot dot-off"></span> Offline</span>{/if}
            </td>
            <td style="position: relative">
              <button type="button" class="btn btn-bare btn-sm" aria-label="Thao tác với {u.name}"
                      onclick={() => (menuFor = menuFor === u.id ? null : u.id)}><Icon name="dots" size={16} /></button>
              {#if menuFor === u.id}
                <div class="menu">
                  <a href="/admin/users/{u.id}">Xem chi tiết</a>
                  <button type="button" onclick={() => { roleFor = u; newRole = u.role; menuFor = null; }}>Đổi quyền…</button>
                  {#if u.status === 'locked'}
                    <button type="button" onclick={() => unlock(u)}>Mở khoá</button>
                  {:else}
                    <button type="button" onclick={() => lock(u)}>Khoá tài khoản</button>
                  {/if}
                  <button type="button" class="danger" onclick={() => remove(u)}>Xoá tài khoản</button>
                </div>
              {/if}
            </td>
          </tr>
        {/each}
      </tbody>
    </table>
  </div>

  {#if meta && pages > 1}
    <div class="pager">
      <span class="muted num" style="font-size: 13px">
        Hiển thị {(meta.page - 1) * meta.per + 1}–{Math.min(meta.page * meta.per, meta.total)} trên {num(meta.total)}
      </span>
      <span style="flex: 1"></span>
      <button type="button" class="btn btn-sm btn-icon" aria-label="Trang trước" disabled={pageNo === 1} onclick={() => pageNo--}><Icon name="left" size={15} /></button>
      <span class="num" style="font-size: 13.5px">{pageNo} / {pages}</span>
      <button type="button" class="btn btn-sm btn-icon" aria-label="Trang sau" disabled={pageNo >= pages} onclick={() => pageNo++}><Icon name="right" size={15} /></button>
    </div>
  {/if}
{/if}

{#if roleFor}
  <Modal title="Đổi quyền · {roleFor.name}" onclose={() => (roleFor = null)}>
    <div class="sel">
      <select class="inp" bind:value={newRole}>
        {#each Object.entries(ROLE_LABEL) as [v, label] (v)}<option value={v}>{label}</option>{/each}
      </select>
      <Icon name="down" size={18} />
    </div>
    <p class="hint">Biên tập chỉ soạn được nội dung, không xem được người dùng.</p>
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (roleFor = null)}>Huỷ</button>
      <button type="button" class="btn btn-son btn-sm" onclick={saveRole}>Lưu</button>
    {/snippet}
  </Modal>
{/if}

<style>
  .head h1 {
    font-size: 28px;
  }
  .filters {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin: 16px 0 14px;
  }
  .mcard {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    text-decoration: none;
    color: var(--text);
  }
  .menu {
    position: absolute;
    right: 0;
    top: 100%;
    z-index: 20;
    min-width: 180px;
    background: var(--surface);
    border: 1px solid var(--line);
    border-radius: var(--r);
    box-shadow: var(--sh-lg);
    padding: 6px;
    display: flex;
    flex-direction: column;
  }
  .menu a,
  .menu button {
    text-align: left;
    padding: 8px 10px;
    border: 0;
    background: none;
    border-radius: var(--r-sm);
    font-size: 13.5px;
    color: var(--text);
    text-decoration: none;
    cursor: pointer;
  }
  .menu a:hover,
  .menu button:hover {
    background: var(--surface-2);
  }
  .menu .danger {
    color: var(--bad-text);
  }
  .pager {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 12px;
  }
</style>
