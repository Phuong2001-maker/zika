<script lang="ts">
  import { onMount } from 'svelte';
  import { api, ApiError } from '$lib/api/client';
  import { toast } from '$lib/stores/toast.svelte';
  import { ago } from '$lib/utils/format';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';

  // Quản trị · Góp ý (08 §14).
  interface Item {
    id: number; category: string; preview: string; status: 'new' | 'doing' | 'done';
    isRead: boolean; createdAt: string;
    user: { id: number; initials: string; name: string; email: string } | null;
  }
  interface Detail extends Item {
    message: string; screenshotUrl: string | null; internalNote: string | null;
    reply: string | null; repliedAt: string | null;
    ctx: Record<string, unknown>; editLink: string | null;
  }

  const CAT_LABEL: Record<string, string> = {
    content_error: 'Lỗi nội dung',
    pronunciation: 'Lỗi phát âm',
    display: 'Lỗi hiển thị',
    feature: 'Góp ý tính năng',
    content_suggestion: 'Góp ý nội dung',
    other: 'Khác',
  };
  const CAT_CLASS: Record<string, string> = {
    content_error: 'tag tag-bad', pronunciation: 'tag tag-warn', display: 'tag tag-warn',
    feature: 'tag tag-g', content_suggestion: 'tag tag-g', other: 'tag',
  };
  const PART_LABEL: Record<string, string> = {
    lt: 'Lật thẻ', bt: 'Bài tập', kt: 'Kiểm tra', lv: 'Luyện viết', ht: 'Hội thoại', np: 'Ngữ pháp',
  };

  let items = $state<Item[] | null>(null);
  let counts = $state({ unread: 0, errors: 0, ideas: 0, done: 0 });
  let detail = $state<Detail | null>(null);
  let failed = $state(false);
  let tab = $state<'unread' | 'errors' | 'ideas' | 'done'>('unread');
  let reply = $state('');
  let note = $state('');
  let busy = $state(false);

  async function load(): Promise<void> {
    failed = false;
    try {
      const res = await api.getFull<{ items: Item[] }>('/admin/feedback', { query: { tab } });
      items = res.data.items;
      counts = (res.meta as { counts?: typeof counts })?.counts ?? counts;
      if (items.length > 0) await open(items[0].id);
      else detail = null;
    } catch {
      failed = true;
    }
  }

  onMount(load);
  $effect(() => {
    void tab;
    void load();
  });

  async function open(id: number): Promise<void> {
    try {
      detail = await api.get<Detail>(`/admin/feedback/${id}`);
      reply = detail.reply ?? '';
      note = detail.internalNote ?? '';
      // Mở là đã đọc → bỏ chấm đỏ trong danh sách.
      items = (items ?? []).map((x) => (x.id === id ? { ...x, isRead: true } : x));
    } catch {
      toast.show('Không mở được góp ý.');
    }
  }

  async function setStatus(status: 'new' | 'doing' | 'done'): Promise<void> {
    if (!detail) return;
    try {
      await api.patch(`/admin/feedback/${detail.id}`, { status });
      detail = { ...detail, status };
      items = (items ?? []).map((x) => (x.id === detail!.id ? { ...x, status } : x));
      toast.ok('Đã cập nhật.');
    } catch {
      toast.show('Không cập nhật được.');
    }
  }

  async function saveNote(): Promise<void> {
    if (!detail) return;
    try {
      await api.patch(`/admin/feedback/${detail.id}`, { internalNote: note });
      toast.ok('Đã lưu ghi chú.');
    } catch {
      toast.show('Không lưu được ghi chú.');
    }
  }

  async function sendReply(): Promise<void> {
    if (!detail || reply.trim() === '') return;
    busy = true;
    try {
      await api.post(`/admin/feedback/${detail.id}/reply`, { reply });
      toast.ok('Đã gửi trả lời.');
      await load();
    } catch (e) {
      toast.show(e instanceof ApiError ? e.message : 'Không gửi được.');
    } finally {
      busy = false;
    }
  }

  const TABS: [typeof tab, string, keyof typeof counts][] = [
    ['unread', 'Chưa đọc', 'unread'],
    ['errors', 'Lỗi nội dung', 'errors'],
    ['ideas', 'Góp ý tính năng', 'ideas'],
    ['done', 'Đã xử lý', 'done'],
  ];
</script>

<svelte:head><title>Góp ý · Quản trị · Zìkǎ</title></svelte:head>

<div class="head">
  <h1>Góp ý</h1>
  <p class="muted" style="font-size: 14px">Người học gửi từ nút “Góp ý” ở mỗi màn. Góp ý gắn sẵn vị trí để sửa nhanh.</p>
</div>

<nav class="tabs" style="margin-bottom: 14px">
  {#each TABS as [v, label, key] (v)}
    <button type="button" class="tab" class:on={tab === v} onclick={() => (tab = v)}>
      {label} <span class="muted num" style="font-weight: 500">{counts[key]}</span>
    </button>
  {/each}
</nav>

{#if failed}
  <EmptyState title="Không tải được góp ý.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if items === null}
  <Skeleton h="300px" r={16} />
{:else if items.length === 0}
  <EmptyState title="Không có góp ý nào ở mục này." />
{:else}
  <div class="cols">
    <ul class="list">
      {#each items as it (it.id)}
        <li>
          <button type="button" class="les row" class:on={detail?.id === it.id} onclick={() => open(it.id)}>
            <span class="ava" style="width: 34px; height: 34px; font-size: 12px">{it.user?.initials ?? '?'}</span>
            <span style="flex: 1; min-width: 0">
              <span style="display: flex; align-items: center; gap: 6px">
                <b style="font-size: 14px">{it.user?.name ?? 'Khách'}</b>
                {#if !it.isRead}<span class="dot" style="background: var(--son); box-shadow: none"></span>{/if}
                <span class="muted" style="margin-left: auto; font-size: 11.5px">{ago(it.createdAt)}</span>
              </span>
              <span class={CAT_CLASS[it.category] ?? 'tag'} style="height: 22px; font-size: 11px">{CAT_LABEL[it.category] ?? it.category}</span>
              <span style="display: block; font-size: 13px; color: var(--text-2); overflow: hidden; text-overflow: ellipsis; white-space: nowrap">
                {it.preview}
              </span>
            </span>
          </button>
        </li>
      {/each}
    </ul>

    {#if detail}
      <section class="block detail">
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap">
          <span class="ava" style="width: 46px; height: 46px">{detail.user?.initials ?? '?'}</span>
          <div style="flex: 1; min-width: 0">
            <h2 class="h2">{detail.user?.name ?? 'Khách'}</h2>
            <p class="muted" style="font-size: 13px">{detail.user?.email ?? ''} · {ago(detail.createdAt)}</p>
          </div>
          <span class={CAT_CLASS[detail.category] ?? 'tag'}>{CAT_LABEL[detail.category] ?? detail.category}</span>
        </div>

        {#if detail.ctx && (detail.ctx.lessonId || detail.ctx.part)}
          <div class="ctx">
            <Icon name="quiz" size={18} />
            <span style="flex: 1; font-size: 14px; font-weight: 700">
              Gửi từ: {PART_LABEL[String(detail.ctx.part ?? '')] ?? 'Màn học'}
              {detail.ctx.levelId ? ` · HSK ${detail.ctx.levelId}` : ''}
              {detail.ctx.lessonNo ? ` · Bài ${detail.ctx.lessonNo}` : ''}
              {detail.ctx.questionNo ? ` · Câu ${detail.ctx.questionNo}` : ''}
            </span>
            {#if detail.editLink}
              <a href={detail.editLink} class="btn btn-sm"><Icon name="edit" size={15} /> Mở để sửa</a>
            {/if}
          </div>
        {/if}

        <p style="font-size: 15px; line-height: 1.6">{detail.message}</p>

        {#if detail.screenshotUrl}
          <img src={detail.screenshotUrl} alt="Ảnh chụp màn hình người học gửi" style="max-width: 320px; border-radius: var(--r); border: 1px solid var(--line)" />
        {/if}

        <div class="field">
          <span class="lbl">Trạng thái</span>
          <div class="seg">
            <button type="button" class:on={detail.status === 'new'} onclick={() => setStatus('new')}>Mới</button>
            <button type="button" class:on={detail.status === 'doing'} onclick={() => setStatus('doing')}>Đang xử lý</button>
            <button type="button" class:on={detail.status === 'done'} onclick={() => setStatus('done')}>Đã xử lý</button>
          </div>
        </div>

        <div class="field">
          <label class="lbl" for="f-note">Ghi chú nội bộ</label>
          <textarea id="f-note" class="inp" rows="2" bind:value={note}></textarea>
          <button type="button" class="btn btn-sm" style="align-self: flex-start" onclick={saveNote}>Lưu ghi chú</button>
        </div>

        <div class="field">
          <label class="lbl" for="f-reply">Trả lời qua email</label>
          <textarea id="f-reply" class="inp" rows="3" bind:value={reply}
                    placeholder="Cảm ơn bạn! Mình đã sửa lại câu này."></textarea>
          {#if detail.repliedAt}<p class="hint">Đã trả lời {ago(detail.repliedAt)}.</p>{/if}
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px">
          <button type="button" class="btn btn-son btn-sm" disabled={busy || reply.trim() === ''} onclick={sendReply}>Gửi trả lời</button>
        </div>
      </section>
    {/if}
  </div>
{/if}

<style>
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
    flex: 1 1 320px;
    max-width: 400px;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .row {
    flex-direction: row;
    gap: 10px;
    align-items: flex-start;
    width: 100%;
  }
  .detail {
    flex: 999 1 460px;
    min-width: 0;
    padding: 20px 22px;
    display: flex;
    flex-direction: column;
    gap: 14px;
  }
  .ctx {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 10px 12px;
    background: var(--son-soft);
    border: 1px solid var(--son-line);
    border-radius: var(--r);
  }
  .field {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
</style>
