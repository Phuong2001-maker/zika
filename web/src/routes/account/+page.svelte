<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { api, ApiError } from '$lib/api/client';
  import { session } from '$lib/stores/session.svelte';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { toast } from '$lib/stores/toast.svelte';
  import { date, duration } from '$lib/utils/format';
  import Switcher from '$lib/components/Switcher.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Modal from '$lib/components/Modal.svelte';

  // Tài khoản (07 §8).
  interface Device {
    id: number;
    deviceType: 'phone' | 'tablet' | 'desktop';
    os: string;
    browser: string;
    lastUsedAt: string;
    current: boolean;
  }

  let name = $state('');
  let savingInfo = $state(false);
  let errors = $state<Record<string, string>>({});

  let devices = $state<Device[] | null>(null);

  let pwOpen = $state(false);
  let pwCurrent = $state('');
  let pwNew = $state('');
  let pwErr = $state('');
  let pwBusy = $state(false);

  let delOpen = $state(false);
  let delConfirm = $state('');
  let delBusy = $state(false);

  const user = $derived(session.user);

  onMount(() => {
    heartbeat.setActivity({ part: null, screen: 'account' });
    const u = session.user;
    if (u) {
      name = u.displayName;
    }
    void loadDevices();
  });

  async function loadDevices(): Promise<void> {
    try {
      devices = await api.get<Device[]>('/me/sessions');
    } catch {
      devices = [];
    }
  }

  async function saveInfo(): Promise<void> {
    savingInfo = true;
    errors = {};
    try {
      const res = await api.patch<{ user: typeof session.user }>('/me', {
        displayName: name.trim(),
      });
      if (res.user) session.setUser(res.user);
      toast.ok('Đã lưu.');
    } catch (e) {
      if (e instanceof ApiError) errors = e.fields;
      toast.show(e instanceof ApiError ? e.message : 'Không lưu được.');
    } finally {
      savingInfo = false;
    }
  }

  async function savePassword(): Promise<void> {
    pwBusy = true;
    pwErr = '';
    try {
      await api.put('/me/password', { currentPassword: pwCurrent, newPassword: pwNew });
      pwOpen = false;
      pwCurrent = '';
      pwNew = '';
      toast.ok('Đã đổi mật khẩu.');
      await session.refresh();
    } catch (e) {
      pwErr = e instanceof ApiError ? (e.fields.newPassword ?? e.fields.currentPassword ?? e.message) : 'Không đổi được mật khẩu.';
    } finally {
      pwBusy = false;
    }
  }

  async function revoke(id: number): Promise<void> {
    try {
      await api.del(`/me/sessions/${id}`);
      devices = (devices ?? []).filter((d) => d.id !== id);
      toast.ok('Đã đăng xuất thiết bị đó.');
    } catch {
      toast.show('Không đăng xuất được thiết bị.');
    }
  }

  async function logout(): Promise<void> {
    try {
      await api.post('/auth/logout');
    } finally {
      session.clear();
      void goto('/login', { replaceState: true });
    }
  }

  async function deleteAccount(): Promise<void> {
    if (delConfirm.trim().toUpperCase() !== 'XOA') return;
    delBusy = true;
    try {
      await api.del('/me');
      session.clear();
      void goto('/login', { replaceState: true });
    } catch {
      toast.show('Không xoá được tài khoản.');
      delBusy = false;
    }
  }

  const deviceLabel = (d: Device): string =>
    ({ phone: 'Điện thoại', tablet: 'Máy tính bảng', desktop: 'Máy tính' })[d.deviceType] ?? 'Thiết bị';
</script>

<svelte:head><title>Tài khoản · Zìkǎ</title></svelte:head>

{#snippet body(m: boolean)}
  {#if user}
    <!-- Hồ sơ -->
    <section class="block" style="padding: {m ? 18 : 24}px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap">
      <span class="ava" style="width: {m ? 56 : 64}px; height: {m ? 56 : 64}px; font-size: {m ? 18 : 20}px">{user.initials}</span>
      <div style="flex: 1 1 200px; min-width: 0">
        <h1 style="font-size: {m ? 22 : 26}px">{user.displayName}</h1>
        <p class="muted" style="font-size: 14px">{user.email}</p>
        <p style="display: flex; gap: 6px; flex-wrap: wrap; margin-top: 8px">
          <span class="tag {user.signupMethod === 'google' ? 'tag-g' : ''}">
            {user.signupMethod === 'google' ? 'Đăng nhập bằng Google' : 'Đăng nhập bằng email'}
          </span>
          <span class="tag">Tham gia {date(user.createdAt)}</span>
        </p>
      </div>
    </section>

    <!-- Ba con số -->
    <section class="kpi">
      <div><span class="muted">Đã học</span><b class="bignum" style="font-size: 26px">{duration(user.stats.studySec)}</b></div>
      <div><span class="muted">Bài học xong</span><b class="bignum" style="font-size: 26px">{user.stats.lessonsDone}</b></div>
      <div><span class="muted">Đang học</span><b class="bignum" style="font-size: 26px">{user.currentLevelId ? `HSK ${user.currentLevelId}` : '—'}</b></div>
    </section>

    <!-- Thông tin cá nhân -->
    <section class="block" style="padding: {m ? 18 : 24}px; display: flex; flex-direction: column; gap: 14px">
      <h2 class="h2">Thông tin cá nhân</h2>

      <div class="field">
        <label class="lbl" for="ac-name">Tên hiển thị</label>
        <input id="ac-name" class="inp" bind:value={name} maxlength="60" />
        {#if errors.displayName}<p class="err">{errors.displayName}</p>{/if}
      </div>

      <div class="field">
        <label class="lbl" for="ac-email">Email</label>
        <input id="ac-email" class="inp" value={user.email} readonly />
        <p class="hint">{user.signupMethod === 'google' ? 'Email lấy từ tài khoản Google.' : 'Email không đổi được.'}</p>
      </div>

      <button type="button" class="btn btn-son" style="align-self: flex-start" disabled={savingInfo} onclick={saveInfo}>
        {savingInfo ? 'Đang lưu…' : 'Lưu thông tin'}
      </button>
    </section>

    <!-- Cài đặt học: lối vào /settings (trước đây là nút trên thanh đầu). -->
    <section class="block" style="padding: {m ? 18 : 24}px; display: flex; flex-direction: column; gap: 10px">
      <h2 class="h2">Cài đặt học</h2>
      <p class="muted" style="font-size: 14px">
        Đổi giọng đọc, mặt trước của thẻ, kiểu chữ Hán, cỡ chữ và giao diện sáng/tối.
      </p>
      <a href="/settings" class="btn" style="align-self: flex-start"><Icon name="sliders" size={16} /> Mở cài đặt học</a>
    </section>

    <!-- Đăng nhập và bảo mật -->
    <section class="block" style="padding: {m ? 18 : 24}px; display: flex; flex-direction: column; gap: 14px">
      <h2 class="h2">Đăng nhập và bảo mật</h2>
      <p class="muted" style="font-size: 14px">
        {user.hasPassword
          ? 'Bạn đang dùng mật khẩu để đăng nhập.'
          : 'Bạn đăng nhập bằng Google nên chưa cần mật khẩu. Đặt thêm mật khẩu nếu muốn đăng nhập bằng email.'}
      </p>
      <button type="button" class="btn" style="align-self: flex-start" onclick={() => (pwOpen = true)}>
        <Icon name="lock" size={16} /> {user.hasPassword ? 'Đổi mật khẩu' : 'Đặt mật khẩu'}
      </button>

      <hr class="rule" />

      <h3 style="font-size: 15px; font-weight: 700">Thiết bị đang đăng nhập</h3>
      {#if devices === null}
        <p class="muted" style="font-size: 14px">Đang tải…</p>
      {:else if devices.length === 0}
        <p class="muted" style="font-size: 14px">Không có thiết bị nào khác.</p>
      {:else}
        <ul style="display: flex; flex-direction: column; gap: 8px">
          {#each devices as d (d.id)}
            <li class="dev">
              <Icon name={d.deviceType === 'desktop' ? 'monitor' : 'phone'} size={18} />
              <span style="flex: 1; min-width: 0">
                <b style="display: block; font-size: 14px">{deviceLabel(d)} · {d.browser}</b>
                <span class="muted" style="font-size: 12.5px">{d.os} · {d.current ? 'Đang dùng' : date(d.lastUsedAt)}</span>
              </span>
              {#if !d.current}
                <button type="button" class="btn btn-sm" onclick={() => revoke(d.id)}>Đăng xuất</button>
              {:else}
                <span class="tag tag-ok">Đang dùng</span>
              {/if}
            </li>
          {/each}
        </ul>
      {/if}

      <button type="button" class="btn" style="align-self: flex-start" onclick={logout}><Icon name="logout" size={16} /> Đăng xuất</button>
    </section>

    <!-- Xoá tài khoản -->
    <section class="block" style="padding: {m ? 18 : 24}px; display: flex; flex-direction: column; gap: 10px">
      <h2 class="h2">Xoá tài khoản</h2>
      <p class="muted" style="font-size: 14px">
        Xoá tài khoản sẽ xoá luôn tiến độ học và lịch sử phiên. Không khôi phục được.
      </p>
      <button type="button" class="btn btn-danger" style="align-self: flex-start" onclick={() => (delOpen = true)}>
        <Icon name="trash" size={16} /> Xoá tài khoản
      </button>
    </section>
  {/if}
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/flashcards" class="btn btn-bare" aria-label="Về Bàn học"><Icon name="left" size={20} /></a>
      <b style="flex: 1; font-size: 16px">Tài khoản</b>
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 16px">{@render body(true)}</main>
  </div>
{:else}
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 12px">
      <Switcher current="lt" />
      <Avatar size={44} current />
    </div>
  </header>
  <main class="page" style="max-width: 820px; display: flex; flex-direction: column; gap: 20px">
    <nav aria-label="Đường dẫn" class="crumb">
      <a href="/learn/flashcards">Bàn học</a><span>/</span><b>Tài khoản</b>
    </nav>
    {@render body(false)}
  </main>
{/if}

{#if pwOpen}
  <Modal title={user?.hasPassword ? 'Đổi mật khẩu' : 'Đặt mật khẩu'} onclose={() => (pwOpen = false)}>
    {#if user?.hasPassword}
      <div class="field">
        <label class="lbl" for="pw-cur">Mật khẩu hiện tại</label>
        <input id="pw-cur" class="inp" type="password" bind:value={pwCurrent} />
      </div>
    {/if}
    <div class="field">
      <label class="lbl" for="pw-new">Mật khẩu mới</label>
      <input id="pw-new" class="inp" type="password" bind:value={pwNew} placeholder="Ít nhất 8 ký tự" />
    </div>
    {#if pwErr}<p class="err">{pwErr}</p>{/if}
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (pwOpen = false)}>Huỷ</button>
      <button type="button" class="btn btn-son btn-sm" disabled={pwBusy} onclick={savePassword}>Lưu</button>
    {/snippet}
  </Modal>
{/if}

{#if delOpen}
  <Modal title="Xoá tài khoản?" onclose={() => (delOpen = false)}>
    <p style="font-size: 14px">Mọi tiến độ học sẽ mất và không lấy lại được. Gõ <b>XOA</b> để xác nhận.</p>
    <input class="inp" bind:value={delConfirm} placeholder="XOA" />
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (delOpen = false)}>Giữ lại tài khoản</button>
      <button type="button" class="btn btn-danger btn-sm" disabled={delBusy || delConfirm.trim().toUpperCase() !== 'XOA'} onclick={deleteAccount}>
        Xoá vĩnh viễn
      </button>
    {/snippet}
  </Modal>
{/if}

<style>
  .field {
    display: flex;
    flex-direction: column;
    gap: 2px;
  }
  .dev {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 12px;
    border: 1px solid var(--line);
    border-radius: var(--r);
    background: var(--surface-2);
  }
</style>
