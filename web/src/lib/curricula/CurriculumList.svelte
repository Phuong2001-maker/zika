<script lang="ts">
  import { onMount } from 'svelte';
  import { ApiError } from '$lib/api/client';
  import { adminApi, type AdminCurriculum } from '$lib/admin/api';
  import { toast } from '$lib/stores/toast.svelte';
  import { num } from '$lib/utils/format';
  import Icon from '$lib/components/Icon.svelte';
  import Modal from '$lib/components/Modal.svelte';
  import Select from '$lib/components/Select.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';

  let { onaccess }: { onaccess: () => void } = $props();

  const COLORS = ['#E4EEDF', '#E3EAF6', '#F0E6D6', '#EDE4F4', '#F5E0DE'];

  let list = $state<AdminCurriculum[] | null>(null);
  let failed = $state(false);
  let saving = $state(false);
  let error = $state('');
  let edit = $state<{ id: number | null; name: string; description: string; color: string; levelLabel: string } | null>(null);

  async function load(): Promise<void> {
    failed = false;
    try {
      list = await adminApi.curricula();
    } catch {
      failed = true;
    }
  }
  onMount(load);

  function openNew(): void {
    edit = { id: null, name: '', description: '', color: COLORS[0], levelLabel: 'HSK' };
    error = '';
  }

  function openEdit(c: AdminCurriculum): void {
    edit = { id: c.id, name: c.name, description: c.description, color: c.color, levelLabel: c.levelLabel };
    error = '';
  }

  async function save(): Promise<void> {
    if (!edit) return;
    if (edit.name.trim() === '') {
      error = 'Vui lòng nhập tên giáo trình.';
      return;
    }
    saving = true;
    error = '';
    try {
      await adminApi.saveCurriculum(edit.id, { name: edit.name.trim(), description: edit.description.trim(), color: edit.color, levelLabel: edit.levelLabel });
      edit = null;
      toast.ok('Đã lưu giáo trình.');
      await load();
    } catch (e) {
      error = e instanceof ApiError ? (Object.values(e.fields)[0] ?? e.message) : 'Không lưu được giáo trình.';
    } finally {
      saving = false;
    }
  }

  async function remove(c: AdminCurriculum): Promise<void> {
    if (!confirm(`Xoá giáo trình “${c.name}”? Học viên đang gán sẽ về giáo trình mặc định.`)) return;
    try {
      await adminApi.deleteCurriculum(c.id);
      toast.ok('Đã xoá giáo trình.');
      await load();
    } catch (e) {
      toast.show(e instanceof ApiError ? e.message : 'Không xoá được.');
    }
  }
</script>

<svelte:head><title>Giáo trình · Quản trị · Zìkǎ</title></svelte:head>

<div class="ct-head">
  <div>
    <h1>Bộ giáo trình</h1>
    <p class="muted">Tạo giáo trình trước, rồi vào Soạn bài để thêm bài theo từng cấp.</p>
  </div>
  <button type="button" class="btn btn-son btn-sm" onclick={openNew}><Icon name="plus" size={15} /> Thêm giáo trình</button>
</div>

{#if failed}
  <EmptyState title="Không tải được danh sách giáo trình.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if list === null}
  <Skeleton h="220px" r={16} />
{:else}
  <div class="ct-list">
    {#each list as c (c.id)}
      <article class="block ct-item">
        <div class="ct-top">
          <span class="ct-icon" style:background={c.color}><Icon name="book" size={21} /></span>
          <div class="ct-name">
            <b>{c.name}</b>
            {#if c.isDefault}<span class="tag">Mặc định</span>{/if}
            {#if c.description}<p class="muted">{c.description}</p>{/if}
          </div>
        </div>
        <div class="ct-levels" aria-label="Số bài theo cấp">
          {#each c.levels as n, i (i)}
            <a class="ct-level" class:ct-level-empty={n === 0} href="/admin/lessons?curriculum={c.id}&level={i + 1}">
              <b>{c.levelLabel} {i + 1}</b><span class="num">{n > 0 ? `${n} bài` : 'Chưa có'}</span>
            </a>
          {/each}
        </div>
        <div class="ct-foot">
          <span class="muted ct-stat num">{num(c.lessonCount)} bài · {num(c.wordCount)} từ</span>
          <button type="button" class="btn btn-sm" onclick={onaccess}><Icon name="users" size={15} /> {num(c.learnerCount)} học viên</button>
          <span class="ct-gap"></span>
          <a class="btn btn-son btn-sm" href="/admin/lessons?curriculum={c.id}"><Icon name="edit" size={15} /> Soạn bài</a>
          <button type="button" class="btn btn-bare btn-sm" aria-label="Sửa {c.name}" onclick={() => openEdit(c)}><Icon name="gear" size={16} /></button>
          {#if !c.isDefault}
            <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá {c.name}" onclick={() => remove(c)}><Icon name="trash" size={15} /></button>
          {/if}
        </div>
      </article>
    {/each}
  </div>
{/if}

{#if edit}
  <Modal title={edit.id === null ? 'Thêm giáo trình mới' : 'Sửa giáo trình'} width={560} onclose={() => (edit = null)}>
    <div class="ct-form">
      <div class="ct-field">
        <label class="lbl" for="ct-name">Tên giáo trình</label>
        <input id="ct-name" class="inp" maxlength="100" placeholder="Ví dụ: Giáo trình Hán ngữ" bind:value={edit.name} />
      </div>
      <div class="ct-field">
        <label class="lbl" for="ct-desc">Giới thiệu ngắn</label>
        <textarea id="ct-desc" class="inp" rows="3" maxlength="300" bind:value={edit.description}></textarea>
      </div>
      <div class="ct-field">
        <label class="lbl" for="ct-cap">Học viên thấy cấp là</label>
        <Select id="ct-cap" bind:value={edit.levelLabel} options={[{ v: 'HSK', label: 'HSK 1, HSK 2…' }, { v: 'Quyển', label: 'Quyển 1, Quyển 2…' }]} />
      </div>
      <div class="ct-field">
        <span class="lbl">Màu nhận diện</span>
        <div class="ct-colors">
          {#each COLORS as color (color)}
            <button
              type="button"
              class:ct-color-on={edit.color === color}
              style:background={color}
              aria-label="Chọn màu {color}"
              aria-pressed={edit.color === color}
              onclick={() => {
                if (edit) edit.color = color;
              }}>{edit.color === color ? '✓' : ''}</button
            >
          {/each}
        </div>
      </div>
      {#if edit.id === null}<p class="hint" style="margin-top: 0">Mỗi giáo trình có sẵn 6 cấp. Lưu xong bấm Soạn bài để thêm bài vào từng cấp.</p>{/if}
      {#if error}<p class="err" role="alert">{error}</p>{/if}
    </div>
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (edit = null)}>Huỷ</button>
      <button type="button" class="btn btn-son btn-sm" disabled={saving} onclick={save}>Lưu giáo trình</button>
    {/snippet}
  </Modal>
{/if}

<style>
  .ct-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 16px;
  }
  .ct-head h1 {
    font-size: 28px;
  }
  .ct-head p {
    font-size: 14px;
  }
  .ct-list {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }
  .ct-item {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 16px 18px;
  }
  .ct-top {
    display: flex;
    align-items: flex-start;
    gap: 12px;
  }
  .ct-icon {
    flex: none;
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    color: var(--text-2);
  }
  .ct-name {
    min-width: 0;
    flex: 1;
  }
  .ct-name b {
    font-size: 16px;
    margin-right: 6px;
  }
  .ct-name p {
    font-size: 13px;
    margin-top: 2px;
  }
  .ct-levels {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 8px;
  }
  .ct-level {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 8px 10px;
    border: 1px solid var(--line);
    border-radius: 10px;
    color: var(--text);
    font-size: 12px;
    text-decoration: none;
  }
  .ct-level b {
    font-size: 13px;
  }
  .ct-level:hover {
    border-color: var(--bo-dot);
    background: var(--bo-paper);
  }
  .ct-level-empty {
    border-style: dashed;
    color: var(--muted);
  }
  .ct-foot {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }
  .ct-stat {
    font-size: 13px;
  }
  .ct-gap {
    flex: 1;
  }
  .ct-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .ct-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .ct-colors {
    display: flex;
    gap: 8px;
  }
  .ct-colors button {
    width: 36px;
    height: 36px;
    border: 1px solid var(--line);
    border-radius: 10px;
    color: var(--text);
    font-weight: 800;
    cursor: pointer;
  }
  .ct-colors .ct-color-on {
    border: 2px solid var(--text);
  }
  @media (max-width: 700px) {
    .ct-levels {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }
</style>
