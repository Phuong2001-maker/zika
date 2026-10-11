<script lang="ts">
  import type { Snippet } from 'svelte';
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { goto } from '$app/navigation';
  import { api } from '$lib/api/client';
  import { session } from '$lib/stores/session.svelte';
  import { media } from '$lib/stores/media.svelte';
  import { toast } from '$lib/stores/toast.svelte';
  import { num } from '$lib/utils/format';
  import Icon from '$lib/components/Icon.svelte';
  import { adminApi } from '$lib/admin/api';
  import { activeKey, lastLesson, menuGroups, type AdminBadges, type MenuItem } from '$lib/admin/menu';

  // Khung khu quản trị (08 §1): menu trái ≥ 900px, thanh + mtabs < 900px.
  let { children }: { children: Snippet } = $props();

  let badges = $state<AdminBadges>({ users: 0, lessons: 0, words: 0, unreadFeedback: 0 });
  let drawer = $state(false);
  let lesson = $state<number | null>(null);

  const isAdmin = $derived(session.user?.role === 'admin');
  const current = $derived(activeKey(page.url.pathname));
  const groups = $derived(
    menuGroups(lesson)
      .map((g) => ({ ...g, items: g.items.filter((i) => i.role === 'E' || isAdmin) }))
      .filter((g) => g.items.length > 0),
  );
  /** Danh sách phẳng cho thanh tab điện thoại. */
  const flat = $derived(groups.flatMap((g) => g.items));

  async function loadBadges(): Promise<void> {
    try {
      badges = await adminApi.badges();
    } catch {
      // Số trên menu chỉ là trang trí: lỗi thì giữ số cũ, không làm phiền.
    }
  }

  onMount(() => {
    lesson = lastLesson();
    void loadBadges();
    const t = setInterval(loadBadges, 60_000);
    return () => clearInterval(t);
  });

  // Biên tập viên mở trang chỉ dành cho quản trị → đẩy về Bài học (08 §0).
  $effect(() => {
    if (!session.user || isAdmin) return;
    const adminOnly = ['overview', 'users', 'sessions', 'news', 'support', 'settings'];
    if (adminOnly.includes(current)) {
      toast.show('Bạn không có quyền xem mục này.');
      void goto('/admin/lessons', { replaceState: true });
    }
  });

  // Đổi trang thì đóng ngăn kéo.
  $effect(() => {
    void page.url.pathname;
    drawer = false;
  });

  function countOf(i: MenuItem): string {
    return i.count ? num(i.count(badges)) : '';
  }

  async function logout(): Promise<void> {
    try {
      await api.post('/auth/logout');
    } finally {
      // Dù gọi API hỏng thì vẫn xoá phiên ở máy rồi về trang đăng nhập.
      session.clear();
      void goto('/login', { replaceState: true });
    }
  }
</script>

{#snippet menu()}
  <a href="/admin" class="brand">
    <span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>
    <b>Zìkǎ</b>
    <span class="tag tag-warn">Quản trị</span>
  </a>

  <nav class="nav" aria-label="Menu quản trị">
    {#each groups as g (g.title)}
      <p class="grp">{g.title}</p>
      {#each g.items as i (i.key)}
        <a href={i.href} class="it" class:on={current === i.key} aria-current={current === i.key ? 'page' : undefined}>
          {#if i.tile}
            <span class="tile" style="background: {i.tile}"><Icon name={i.icon} size={15} /></span>
          {:else}
            <Icon name={i.icon} size={18} />
          {/if}
          <span class="lbl">{i.label}</span>
          {#if i.badge && i.badge(badges) > 0}
            <span class="new">{i.badge(badges)}</span>
          {:else if countOf(i)}
            <span class="cnt num">{countOf(i)}</span>
          {/if}
        </a>
      {/each}
    {/each}
  </nav>

  <div class="foot">
    <span class="ava" style="background: var(--ngoc-soft)">{session.user?.initials ?? ''}</span>
    <span class="who"><b>{session.user?.displayName ?? ''}</b><a href="/learn/flashcards">Về trang học</a></span>
    <button type="button" class="btn btn-bare btn-sm" aria-label="Đăng xuất" onclick={logout}>
      <Icon name="logout" size={18} />
    </button>
  </div>
{/snippet}

{#if media.isAdminMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="wrap m">
    <header class="mtop">
      <button type="button" class="btn btn-bare" aria-label="Mở menu" onclick={() => (drawer = true)}>
        <Icon name="menu" size={22} />
      </button>
      <span class="seal" lang="zh" style="width: 30px; height: 30px; font-size: 17px">字</span>
      <b class="disp" style="font-size: 18px; font-weight: 800">Quản trị</b>
      <a href="/admin/feedback" class="btn btn-bare bell" style="margin-left: auto" aria-label="Góp ý">
        <Icon name="bell" size={20} />
        {#if badges.unreadFeedback > 0}<i class="dot-red"></i>{/if}
      </a>
      <span class="ava" style="width: 36px; height: 36px">{session.user?.initials ?? ''}</span>
    </header>

    <div class="mtabs">
      {#each flat as i (i.key)}
        <a href={i.href} class:on={current === i.key}>{i.label}</a>
      {/each}
    </div>

    <main class="mcontent">{@render children()}</main>

    {#if drawer}
      <!-- svelte-ignore a11y_click_events_have_key_events, a11y_no_static_element_interactions -->
      <div class="scrim" onclick={() => (drawer = false)}></div>
      <aside class="drawer side">
        <button type="button" class="btn btn-bare close" aria-label="Đóng menu" onclick={() => (drawer = false)}>
          <Icon name="x" size={20} />
        </button>
        {@render menu()}
      </aside>
    {/if}
  </div>
{:else}
  <!-- ============ Máy tính ============ -->
  <div class="wrap">
    <aside class="side">{@render menu()}</aside>
    <main class="content">{@render children()}</main>
  </div>
{/if}

<style>
  .wrap {
    display: flex;
    flex-wrap: wrap;
    min-height: 100vh;
    min-height: 100dvh;
    background: var(--ground);
  }
  .wrap.m {
    display: block;
  }
  .side {
    flex: 1 1 240px;
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 20px 14px;
    position: sticky;
    top: 0;
    height: 100vh;
    height: 100dvh;
    overflow-y: auto;
  }
  .content {
    flex: 999 1 560px;
    min-width: 0;
    padding: 28px clamp(16px, 3vw, 40px) 48px;
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  .brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 8px 10px;
    text-decoration: none;
    color: var(--text);
    font-weight: 800;
    font-size: 19px;
  }
  .brand .tag {
    background: var(--bo-soft);
    border: 1px solid var(--line);
    color: var(--warn-text);
  }

  .nav {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }
  /* .it, .grp, .cnt, .new lấy kiểu từ “.side …” trong app.css */
  .lbl {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .tile {
    width: 26px;
    height: 26px;
    flex: none;
    display: grid;
    place-items: center;
    border: 1px solid var(--line);
    border-radius: 8px;
  }

  .foot {
    margin-top: auto;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 16px 8px 0;
    border-top: 1px solid var(--line);
  }
  .who {
    display: flex;
    flex-direction: column;
    min-width: 0;
    font-size: 13px;
  }
  .who b {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .who a {
    font-size: 12px;
  }

  /* --------- Điện thoại --------- */
  .mtop {
    display: flex;
    align-items: center;
    gap: 8px;
    height: 56px;
    padding: 0 10px;
    background: var(--surface);
    border-bottom: 1px solid var(--line);
    position: sticky;
    top: 0;
    z-index: 20;
  }
  .bell {
    position: relative;
  }
  .dot-red {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--son);
    border: 1px solid var(--line);
  }
  .mcontent {
    padding: 16px 14px 48px;
  }
  .scrim {
    position: fixed;
    inset: 0;
    background: rgba(20, 22, 28, 0.45);
    z-index: 30;
  }
  .drawer {
    position: fixed;
    inset: 0 auto 0 0;
    width: min(300px, 86vw);
    z-index: 31;
    height: 100%;
  }
  .close {
    position: absolute;
    top: 10px;
    right: 8px;
  }
</style>
