<script lang="ts">
  import { onMount } from 'svelte';
  import { api, ApiError } from '$lib/api/client';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';

  // Quản trị · Cài đặt (08 §16).
  interface Settings {
    site: { name: string; slogan: string; logo_path: string | null; contact_email: string };
    auth: { google: boolean; email: boolean; require_email_verify: boolean; allow_signup: boolean };
    content: { default_voice: string; show_feedback_button: boolean };
    privacy: { visit_log_days: number; mask_emails: boolean; privacy_page: string; terms_page: string };
    backup: { daily: boolean; last_at: string | null; last_size: number };
    levels: { id: number; name: string; visible: boolean }[];
    staff: { id: number; name: string; email: string; role: string; isMe: boolean }[];
    invites: { id: number; email: string; role: string; expiresAt: string }[];
  }

  let s = $state<Settings | null>(null);
  let failed = $state(false);
  let saving = $state(false);

  async function load(): Promise<void> {
    failed = false;
    try {
      s = await api.get<Settings>('/admin/settings');
    } catch {
      failed = true;
    }
  }
  onMount(load);

  async function save(): Promise<void> {
    if (!s) return;
    saving = true;
    try {
      s = await api.put<Settings>('/admin/settings', {
        site: s.site,
        auth: s.auth,
        content: s.content,
        privacy: s.privacy,
        backup: s.backup,
        levels: s.levels.map((l) => ({ id: l.id, visible: l.visible })),
      });
      toast.ok('Đã lưu cài đặt.');
    } catch (e) {
      toast.show(e instanceof ApiError ? (Object.values(e.fields)[0] ?? e.message) : 'Không lưu được.');
    } finally {
      saving = false;
    }
  }

  async function removeStaff(id: number, name: string): Promise<void> {
    if (!confirm(`Gỡ quyền của ${name}? Họ sẽ thành người học bình thường.`)) return;
    try {
      await api.del(`/admin/staff/${id}`);
      toast.ok('Đã gỡ quyền.');
      await load();
    } catch (e) {
      toast.show(e instanceof ApiError ? e.message : 'Không gỡ được.');
    }
  }

  const ROLE_LABEL: Record<string, string> = { admin: 'Toàn quyền', editor: 'Chỉ soạn nội dung', learner: 'Người học' };
</script>

<svelte:head><title>Cài đặt · Quản trị · Zìkǎ</title></svelte:head>

<div class="head">
  <div>
    <h1>Cài đặt</h1>
    <p class="muted" style="font-size: 14px">Cài đặt chung của web.</p>
  </div>
  <button type="button" class="btn btn-son btn-sm" disabled={saving || s === null} onclick={save}>
    {saving ? 'Đang lưu…' : 'Lưu thay đổi'}
  </button>
</div>

{#if failed}
  <EmptyState title="Không tải được cài đặt.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if s === null}
  <Skeleton h="300px" r={16} />
{:else}
  <section class="block card">
    <h2 class="h2">Thông tin web</h2>
    <div class="grid2">
      <div class="field"><label class="lbl" for="st-name">Tên web</label><input id="st-name" class="inp" bind:value={s.site.name} /></div>
      <div class="field"><label class="lbl" for="st-mail">Email liên hệ</label><input id="st-mail" class="inp" bind:value={s.site.contact_email} /></div>
    </div>
    <div class="field"><label class="lbl" for="st-slogan">Khẩu hiệu</label><input id="st-slogan" class="inp" bind:value={s.site.slogan} /></div>
  </section>

  <section class="block card">
    <h2 class="h2">Đăng nhập và tài khoản</h2>
    <label class="sw"><input type="checkbox" bind:checked={s.auth.google} /><span></span> Đăng nhập bằng Google</label>
    <label class="sw"><input type="checkbox" bind:checked={s.auth.email} /><span></span> Đăng nhập bằng email và mật khẩu</label>
    <label class="sw"><input type="checkbox" bind:checked={s.auth.require_email_verify} /><span></span> Bắt xác minh email khi đăng ký</label>
    <label class="sw"><input type="checkbox" bind:checked={s.auth.allow_signup} /><span></span> Cho phép tạo tài khoản mới</label>
    <p class="hint">Không thể tắt cùng lúc cả hai cách đăng nhập.</p>
  </section>

  <section class="block card">
    <h2 class="h2">Nội dung học</h2>
    <div class="field">
      <span class="lbl">Cấp HSK đang mở cho người học</span>
      <div style="display: flex; gap: 8px; flex-wrap: wrap">
        {#each s.levels as l (l.id)}
          <label class="pick">
            <input type="checkbox" bind:checked={l.visible} />
            <span>{l.name} · {l.visible ? 'mở' : 'ẩn'}</span>
          </label>
        {/each}
      </div>
    </div>
    <div class="field">
      <span class="lbl">Giọng đọc mặc định</span>
      <div class="seg">
        <button type="button" class:on={s.content.default_voice === 'xiaoxiao'} onclick={() => (s!.content.default_voice = 'xiaoxiao')}>Xiaoxiao · nữ</button>
        <button type="button" class:on={s.content.default_voice === 'yunxi'} onclick={() => (s!.content.default_voice = 'yunxi')}>Yunxi · nam</button>
      </div>
    </div>
    <label class="sw"><input type="checkbox" bind:checked={s.content.show_feedback_button} /><span></span> Hiện nút “Góp ý” ở mọi màn học</label>
  </section>

  <section class="block card">
    <h2 class="h2">Quyền riêng tư</h2>
    <div class="field">
      <span class="lbl">Lưu nhật ký phiên truy cập</span>
      <div class="seg">
        {#each [30, 90, 365] as dd (dd)}
          <button type="button" class:on={s.privacy.visit_log_days === dd} onclick={() => (s!.privacy.visit_log_days = dd)}>
            {dd === 365 ? '1 năm' : `${dd} ngày`}
          </button>
        {/each}
      </div>
    </div>
    <label class="sw"><input type="checkbox" bind:checked={s.privacy.mask_emails} /><span></span> Che bớt email trong danh sách người dùng</label>
  </section>

  <section class="block card">
    <h2 class="h2">Sao lưu dữ liệu</h2>
    <label class="sw"><input type="checkbox" bind:checked={s.backup.daily} /><span></span> Tự sao lưu mỗi ngày lúc 03:00</label>
    {#if s.backup.last_at}
      <p class="muted" style="font-size: 13.5px">
        Bản gần nhất: {s.backup.last_at} · {(s.backup.last_size / 1024 / 1024).toFixed(1)} MB
      </p>
    {:else}
      <p class="muted" style="font-size: 13.5px">Chưa có bản sao lưu nào.</p>
    {/if}
  </section>

  <section class="block card">
    <h2 class="h2">Người quản trị</h2>
    <ul style="display: flex; flex-direction: column; gap: 8px">
      {#each s.staff as u (u.id)}
        <li class="staff">
          <span class="ava" style="width: 34px; height: 34px; font-size: 12px; background: var(--ngoc-soft)">
            {u.name.slice(0, 2).toUpperCase()}
          </span>
          <span style="flex: 1; min-width: 0">
            <b style="display: block">{u.name}{u.isMe ? ' (bạn)' : ''}</b>
            <span class="muted" style="font-size: 12.5px">{u.email}</span>
          </span>
          <span class="tag {u.role === 'admin' ? 'tag-ok' : ''}">{ROLE_LABEL[u.role]}</span>
          {#if !u.isMe}
            <button type="button" class="btn btn-bare btn-sm" aria-label="Gỡ quyền {u.name}" onclick={() => removeStaff(u.id, u.name)}>
              <Icon name="trash" size={15} />
            </button>
          {/if}
        </li>
      {/each}
    </ul>

    {#if s.invites.length > 0}
      <p class="muted" style="font-size: 13px; margin-top: 8px">Lời mời đang chờ</p>
      <ul style="display: flex; flex-direction: column; gap: 6px">
        {#each s.invites as i (i.id)}
          <li class="staff">
            <span style="flex: 1">{i.email}</span>
            <span class="tag">{ROLE_LABEL[i.role]}</span>
            <span class="muted num" style="font-size: 12.5px">hết hạn {i.expiresAt}</span>
          </li>
        {/each}
      </ul>
    {/if}
  </section>
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
  .card {
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .field {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .grid2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr));
    gap: 12px;
  }
  .staff {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border: 1px solid var(--line);
    border-radius: var(--r);
    background: var(--surface-2);
  }
</style>
