<script lang="ts">
  import { onMount } from 'svelte';
  import { api, ApiError } from '$lib/api/client';
  import { toast } from '$lib/stores/toast.svelte';
  import { num } from '$lib/utils/format';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';

  // Quản trị · Thông báo (08 §13).
  interface Ann {
    id: number; title: string; body: string; kind: 'banner' | 'modal' | 'email';
    audience: 'all' | 'level' | 'new7d'; audienceLevel: number | null;
    startsAt: string | null; endsAt: string | null; ctaText: string | null; ctaRoute: string | null;
    status: 'draft' | 'scheduled' | 'published' | 'ended'; isAutoWelcome: boolean;
    views: number; clicks: number; emailsSent: number;
  }

  const KIND_LABEL = { banner: 'Dải trên cùng', modal: 'Hộp nổi', email: 'Email' };
  const AUD_LABEL = { all: 'Tất cả người học', level: 'Theo cấp', new7d: 'Người mới (7 ngày)' };
  const STATUS_LABEL = { draft: 'Nháp', scheduled: 'Đã lên lịch', published: 'Đang hiện', ended: 'Đã kết thúc' };

  let list = $state<Ann[] | null>(null);
  let targets = $state<{ label: string; route: string }[]>([]);
  let failed = $state(false);
  let saving = $state(false);

  // Biểu mẫu
  let id = $state<number | null>(null);
  let title = $state('');
  let body = $state('');
  let kind = $state<'banner' | 'modal' | 'email'>('banner');
  let audience = $state<'all' | 'level' | 'new7d'>('all');
  let audienceLevel = $state(1);
  let startsAt = $state('');
  let endsAt = $state('');
  let ctaText = $state('');
  let ctaRoute = $state('');
  let isWelcome = $state(false);

  async function load(): Promise<void> {
    failed = false;
    try {
      const [l, t] = await Promise.all([
        api.get<Ann[]>('/admin/announcements'),
        api.get<{ label: string; route: string }[]>('/admin/announcements/targets'),
      ]);
      list = l;
      targets = t;
    } catch {
      failed = true;
    }
  }
  onMount(load);

  function reset(): void {
    id = null; title = ''; body = ''; kind = 'banner'; audience = 'all'; audienceLevel = 1;
    startsAt = ''; endsAt = ''; ctaText = ''; ctaRoute = ''; isWelcome = false;
  }

  function edit(a: Ann): void {
    id = a.id; title = a.title; body = a.body; kind = a.kind; audience = a.audience;
    audienceLevel = a.audienceLevel ?? 1;
    startsAt = (a.startsAt ?? '').slice(0, 16).replace(' ', 'T');
    endsAt = (a.endsAt ?? '').slice(0, 16).replace(' ', 'T');
    ctaText = a.ctaText ?? ''; ctaRoute = a.ctaRoute ?? ''; isWelcome = a.isAutoWelcome;
  }

  async function save(action: 'draft' | 'schedule' | 'publish'): Promise<void> {
    saving = true;
    try {
      const payload = { title, body, kind, audience, audienceLevel, startsAt, endsAt, ctaText, ctaRoute, action };
      if (id === null) await api.post('/admin/announcements', payload);
      else await api.put(`/admin/announcements/${id}`, payload);
      toast.ok(action === 'publish' ? 'Đã đăng.' : action === 'schedule' ? 'Đã lên lịch.' : 'Đã lưu nháp.');
      reset();
      await load();
    } catch (e) {
      toast.show(e instanceof ApiError ? (Object.values(e.fields)[0] ?? e.message) : 'Không lưu được.');
    } finally {
      saving = false;
    }
  }

  async function remove(a: Ann): Promise<void> {
    if (!confirm(`Xoá thông báo “${a.title}”?`)) return;
    try {
      await api.del(`/admin/announcements/${a.id}`);
      if (id === a.id) reset();
      await load();
      toast.ok('Đã xoá.');
    } catch (e) {
      toast.show(e instanceof ApiError ? e.message : 'Không xoá được.');
    }
  }
</script>

<svelte:head><title>Thông báo · Quản trị · Zìkǎ</title></svelte:head>

<div class="head">
  <div>
    <h1>Thông báo</h1>
    <p class="muted" style="font-size: 14px">Gửi tin tới người học: dải trên cùng trang, hộp nổi hoặc email.</p>
  </div>
  <button type="button" class="btn btn-son btn-sm" onclick={reset}><Icon name="plus" size={15} /> Tạo thông báo</button>
</div>

{#if failed}
  <EmptyState title="Không tải được thông báo.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if list === null}
  <Skeleton h="300px" r={16} />
{:else}
  <div class="cols">
    <!-- Danh sách -->
    <aside class="paper list">
      <b>{list.length} thông báo</b>
      <ul style="display: flex; flex-direction: column; gap: 6px">
        {#each list as a (a.id)}
          <li class="row" class:on={id === a.id}>
            <button type="button" class="pick" onclick={() => edit(a)}>
              <b style="display: block; font-size: 14px">{a.title}</b>
              <span class="muted" style="font-size: 12.5px">
                {KIND_LABEL[a.kind]} · {AUD_LABEL[a.audience]}{a.audienceLevel ? ` ${a.audienceLevel}` : ''}
              </span>
              <span style="display: flex; gap: 6px; margin-top: 4px; flex-wrap: wrap">
                <span class="tag {a.status === 'published' ? 'tag-ok' : a.status === 'scheduled' ? 'tag-warn' : ''}">{STATUS_LABEL[a.status]}</span>
                {#if a.isAutoWelcome}<span class="tag tag-g">Tự động</span>{/if}
                {#if a.views > 0}<span class="tag num">{num(a.views)} lượt xem</span>{/if}
              </span>
            </button>
            {#if !a.isAutoWelcome}
              <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá" onclick={() => remove(a)}><Icon name="trash" size={15} /></button>
            {/if}
          </li>
        {/each}
      </ul>
      {#if list.length === 0}<p class="muted" style="font-size: 13.5px">Chưa có thông báo nào.</p>{/if}
    </aside>

    <!-- Soạn -->
    <section class="block form">
      <h2 class="h2">{id === null ? 'Soạn thông báo' : 'Sửa thông báo'}</h2>
      {#if isWelcome}
        <div class="fb" style="background: var(--bo-soft); border-color: #FDE68A">
          <Icon name="flag" size={18} />
          <span style="font-size: 13.5px">Đây là email chào mừng tự động gửi cho người mới. Chỉ sửa được tiêu đề và nội dung.</span>
        </div>
      {/if}

      <div class="field">
        <label class="lbl" for="n-title">Tiêu đề</label>
        <input id="n-title" class="inp" bind:value={title} />
      </div>
      <div class="field">
        <label class="lbl" for="n-body">Nội dung</label>
        <textarea id="n-body" class="inp" rows="4" bind:value={body}></textarea>
      </div>

      {#if !isWelcome}
        <div class="field">
          <span class="lbl">Kiểu hiện</span>
          <div class="seg">
            <button type="button" class:on={kind === 'banner'} onclick={() => (kind = 'banner')}>Dải trên cùng</button>
            <button type="button" class:on={kind === 'modal'} onclick={() => (kind = 'modal')}>Hộp nổi</button>
            <button type="button" class:on={kind === 'email'} onclick={() => (kind = 'email')}>Email</button>
          </div>
        </div>

        <div class="grid2">
          <div class="field">
            <label class="lbl" for="n-aud">Ai thấy</label>
            <div class="sel">
              <select id="n-aud" class="inp" bind:value={audience}>
                <option value="all">Tất cả người học</option>
                <option value="level">Người học một cấp</option>
                <option value="new7d">Người mới (7 ngày)</option>
              </select>
              <Icon name="down" size={18} />
            </div>
          </div>
          {#if audience === 'level'}
            <div class="field">
              <label class="lbl" for="n-lv">Cấp</label>
              <div class="sel">
                <select id="n-lv" class="inp" bind:value={audienceLevel}>
                  {#each [1, 2, 3, 4, 5, 6] as l (l)}<option value={l}>HSK {l}</option>{/each}
                </select>
                <Icon name="down" size={18} />
              </div>
            </div>
          {/if}
        </div>

        <div class="grid2">
          <div class="field">
            <label class="lbl" for="n-from">Hiện từ</label>
            <input id="n-from" class="inp" type="datetime-local" bind:value={startsAt} />
          </div>
          <div class="field">
            <label class="lbl" for="n-to">Đến</label>
            <input id="n-to" class="inp" type="datetime-local" bind:value={endsAt} />
          </div>
        </div>

        <div class="grid2">
          <div class="field">
            <label class="lbl" for="n-cta">Nút trong thông báo (tuỳ chọn)</label>
            <input id="n-cta" class="inp" bind:value={ctaText} placeholder="Thử ngay" />
          </div>
          <div class="field">
            <label class="lbl" for="n-route">Bấm nút thì đi đâu</label>
            <div class="sel">
              <select id="n-route" class="inp" bind:value={ctaRoute}>
                <option value="">— Không có nút —</option>
                {#each targets as t (t.route)}<option value={t.route}>{t.label}</option>{/each}
              </select>
              <Icon name="down" size={18} />
            </div>
          </div>
        </div>
      {/if}

      <!-- Xem trước -->
      <div class="field">
        <span class="lbl">Người học sẽ thấy</span>
        <div class="preview">
          <b>{title || 'Tiêu đề thông báo'}</b>
          <span style="font-size: 13.5px">{body || 'Nội dung thông báo…'}</span>
          {#if ctaText}<span class="btn btn-son btn-sm" style="align-self: flex-start">{ctaText}</span>{/if}
        </div>
      </div>

      <div class="form-foot">
        <span style="flex: 1"></span>
        <button type="button" class="btn btn-sm" disabled={saving || title.trim() === ''} onclick={() => save('draft')}>Lưu nháp</button>
        {#if !isWelcome}
          <button type="button" class="btn btn-sm" disabled={saving || title.trim() === ''} onclick={() => save('schedule')}>Lên lịch</button>
        {/if}
        <button type="button" class="btn btn-son btn-sm" disabled={saving || title.trim() === ''} onclick={() => save('publish')}>Đăng ngay</button>
      </div>
    </section>
  </div>
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
  .cols {
    display: flex;
    flex-wrap: wrap;
    gap: 22px;
    align-items: flex-start;
  }
  .list {
    flex: 1 1 300px;
    max-width: 360px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  .row {
    display: flex;
    align-items: flex-start;
    gap: 4px;
    border-radius: var(--r);
  }
  .row.on {
    background: var(--son-soft);
  }
  .pick {
    flex: 1;
    min-width: 0;
    text-align: left;
    border: 0;
    background: none;
    padding: 8px 10px;
    border-radius: var(--r);
    cursor: pointer;
  }
  .pick:hover {
    background: var(--surface-2);
  }
  .form {
    flex: 999 1 460px;
    min-width: 0;
    padding: 20px 22px;
    display: flex;
    flex-direction: column;
    gap: 14px;
  }
  .field {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .grid2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(200px, 100%), 1fr));
    gap: 12px;
  }
  .preview {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px 16px;
    background: var(--bo-soft);
    border: 1px solid #fde68a;
    border-radius: var(--r);
  }
  .form-foot {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    padding-top: 8px;
    border-top: 1px solid var(--line);
  }
</style>
