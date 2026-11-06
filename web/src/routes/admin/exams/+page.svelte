<script lang="ts">
  import { onMount } from 'svelte';
  import { api, ApiError } from '$lib/api/client';
  import { toast } from '$lib/stores/toast.svelte';
  import { num } from '$lib/utils/format';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';

  // Quản trị · Đề thi: chỉ cần tên để chọn khi tạo bài thi, câu hỏi nạp sau ở nút Soạn câu.
  interface Exam { id: number; title: string; status: 'draft' | 'published'; questionCount: number; contestCount: number }

  let list = $state<Exam[] | null>(null);
  let failed = $state(false);
  let saving = $state(false);
  let title = $state('');
  let editing = $state<Exam | null>(null);
  let editTitle = $state('');

  const errorText = (e: unknown, fallback: string): string =>
    e instanceof ApiError ? (Object.values(e.fields)[0] ?? e.message) : fallback;

  async function load(): Promise<void> {
    failed = false;
    try {
      list = await api.get<Exam[]>('/admin/exams');
    } catch {
      failed = true;
    }
  }
  onMount(load);

  async function add(): Promise<void> {
    if (title.trim() === '') {
      toast.show('Nhập tên đề thi.');
      return;
    }
    saving = true;
    try {
      await api.post('/admin/exams', { title: title.trim() });
      title = '';
      toast.ok('Đã thêm đề thi.');
      await load();
    } catch (e) {
      toast.show(errorText(e, 'Không thêm được đề thi.'));
    } finally {
      saving = false;
    }
  }

  async function saveName(): Promise<void> {
    if (!editing) return;
    saving = true;
    try {
      await api.patch(`/admin/mock-tests/${editing.id}`, { title: editTitle.trim() });
      editing = null;
      toast.ok('Đã đổi tên đề.');
      await load();
    } catch (e) {
      toast.show(errorText(e, 'Không đổi được tên.'));
    } finally {
      saving = false;
    }
  }

  async function remove(x: Exam): Promise<void> {
    if (!confirm(`Xoá đề “${x.title}”?`)) return;
    try {
      await api.del(`/admin/mock-tests/${x.id}`);
      toast.ok('Đã xoá đề.');
      await load();
    } catch (e) {
      toast.show(errorText(e, 'Không xoá được.'));
    }
  }
</script>

<svelte:head><title>Đề thi · Quản trị · Zìkǎ</title></svelte:head>

<div class="head">
  <h1>Đề thi</h1>
  <p class="muted" style="font-size: 14px">Nhập tên đề để chọn khi tạo bài thi ở mục Cuộc thi. Câu hỏi có thể nạp sau.</p>
</div>

<form
  class="block ex-add"
  onsubmit={(e) => {
    e.preventDefault();
    void add();
  }}
>
  <label class="lbl" for="ex-title">Tên đề thi</label>
  <div class="ex-add-row">
    <input id="ex-title" class="inp" maxlength="80" bind:value={title} placeholder="Ví dụ: Đề thi tháng 11" />
    <button type="submit" class="btn btn-son" disabled={saving}><Icon name="plus" size={16} /> Thêm đề</button>
  </div>
</form>

{#if failed}
  <EmptyState title="Không tải được danh sách đề.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if list === null}
  <Skeleton h="180px" r={16} />
{:else if list.length === 0}
  <EmptyState title="Chưa có đề thi nào." desc="Nhập tên ở ô phía trên để thêm đề đầu tiên." />
{:else}
  <section class="paper" style="overflow-x: auto">
    <table class="tbl">
      <thead><tr><th>Tên đề</th><th class="r">Số câu</th><th>Đang dùng</th><th></th></tr></thead>
      <tbody>
        {#each list as x (x.id)}
          <tr>
            <td><b>{x.title}</b></td>
            <td class="r num">
              {#if x.questionCount > 0}{num(x.questionCount)}{:else}<span class="tag tag-warn">Chưa có câu</span>{/if}
            </td>
            <td>{x.contestCount > 0 ? `${x.contestCount} bài thi` : '—'}</td>
            <td style="white-space: nowrap; text-align: right">
              <a class="btn btn-sm" href="/admin/mock-tests/{x.id}"><Icon name="edit" size={15} /> Soạn câu</a>
              <button
                type="button"
                class="btn btn-bare btn-sm"
                aria-label="Đổi tên {x.title}"
                onclick={() => {
                  editing = x;
                  editTitle = x.title;
                }}><Icon name="gear" size={15} /></button
              >
              <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá {x.title}" onclick={() => remove(x)}><Icon name="trash" size={15} /></button>
            </td>
          </tr>
        {/each}
      </tbody>
    </table>
  </section>
{/if}

{#if editing}
  <Modal title="Đổi tên đề" onclose={() => (editing = null)}>
    <label class="lbl" for="ex-rename">Tên đề thi</label>
    <input id="ex-rename" class="inp" maxlength="80" bind:value={editTitle} />
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (editing = null)}>Huỷ</button>
      <button type="button" class="btn btn-son btn-sm" disabled={saving || editTitle.trim() === ''} onclick={saveName}>Lưu</button>
    {/snippet}
  </Modal>
{/if}

<style>
  .head {
    margin-bottom: 16px;
  }
  .head h1 {
    font-size: 28px;
  }
  .ex-add {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 16px 18px;
    margin-bottom: 16px;
  }
  .ex-add-row {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
  }
  .ex-add-row .inp {
    flex: 1 1 240px;
    min-width: 0;
  }
</style>
