<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { goto } from '$app/navigation';
  import { media } from '$lib/stores/media.svelte';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';
  import { adminApi, PART_COL, PART_SLUG, type AdminLevel, type LessonsPage } from '$lib/admin/api';
  import { rememberLesson } from '$lib/admin/menu';
  import { ApiError } from '$lib/api/client';

  // Bài học HSK (08 §6).
  let levels = $state<AdminLevel[] | null>(null);
  let data = $state<LessonsPage | null>(null);
  let failed = $state(false);
  let busy = $state(false);

  let addOpen = $state(false);
  let addTitle = $state('');
  let addError = $state('');

  const levelId = $derived(Number(page.url.searchParams.get('level')) || 2);
  const level = $derived(levels?.find((l) => l.id === levelId) ?? null);

  async function loadLevels(): Promise<void> {
    try {
      levels = await adminApi.levels();
    } catch {
      failed = true;
    }
  }

  async function loadLessons(id: number): Promise<void> {
    data = null;
    failed = false;
    try {
      data = await adminApi.lessons(id);
    } catch {
      failed = true;
    }
  }

  onMount(loadLevels);

  // Đổi cấp trên URL → tải lại danh sách bài.
  $effect(() => {
    void loadLessons(levelId);
  });

  function pickLevel(id: number): void {
    void goto(`/admin/lessons?level=${id}`, { keepFocus: true, noScroll: true });
  }

  /** Mở một phần của bài; nhớ bài để menu trái trỏ đúng. */
  function openPart(lessonId: number, part: keyof typeof PART_SLUG): void {
    rememberLesson(lessonId);
    const slug = PART_SLUG[part];
    void goto(slug === 'words' ? `/admin/words?level=${levelId}&lesson=${lessonId}` : `/admin/lessons/${lessonId}/${slug}`);
  }

  async function addLesson(): Promise<void> {
    const title = addTitle.trim();
    if (title === '') {
      addError = 'Nhập tên bài.';
      return;
    }
    busy = true;
    addError = '';
    try {
      const res = await adminApi.createLesson(levelId, title);
      addOpen = false;
      addTitle = '';
      toast.ok('Đã thêm bài.');
      openPart(res.id, 'lt');
    } catch (e) {
      addError = e instanceof ApiError ? (e.fields.title ?? e.message) : 'Không thêm được bài.';
    } finally {
      busy = false;
    }
  }

  async function toggleReviewOnly(on: boolean): Promise<void> {
    if (!level) return;
    try {
      await adminApi.patchLevel(level.id, { reviewPublishedOnly: on });
      levels = (levels ?? []).map((l) => (l.id === level.id ? { ...l, reviewPublishedOnly: on } : l));
      toast.ok('Đã lưu.');
    } catch {
      toast.show('Không lưu được, thử lại nhé.');
    }
  }
</script>

<svelte:head><title>Bài học HSK · Quản trị · Zìkǎ</title></svelte:head>

{#snippet kpiRow()}
  {#if data}
    <div class="kpi">
      <div><span class="muted">Số bài</span><b class="bignum">{data.kpi.total}</b></div>
      <div><span class="muted">Đủ cả 6 phần</span><b class="bignum">{data.kpi.complete}</b></div>
      <div><span class="muted">Còn thiếu phần</span><b class="bignum">{data.kpi.missing}</b></div>
      <div><span class="muted">Nháp, đang ẩn</span><b class="bignum">{data.kpi.draft}</b></div>
    </div>
  {/if}
{/snippet}

{#snippet endOfLevel()}
  {#if level && data}
    <h2 class="h2" style="margin-top: 28px">Cuối cấp HSK {level.id}</h2>
    <div class="end-grid">
      <section class="block" style="background: var(--tim)">
        <b style="font-size: 17px">Các bài Tổng hợp</b>
        <p class="muted" style="font-size: 14px">
          Tự gộp từ {data.kpi.total} bài: Lật thẻ, Bài tập (lấy ngẫu nhiên {level.reviewBtCount} câu), Luyện viết, Hội thoại
          và bảng ngữ pháp. Không cần soạn riêng.
        </p>
        <label class="sw">
          <input
            type="checkbox"
            checked={level.reviewPublishedOnly}
            onchange={(e) => toggleReviewOnly(e.currentTarget.checked)}
          />
          <span></span>
          Chỉ gộp các bài đang hiện
        </label>
      </section>

      <section class="block" style="background: var(--son-soft)">
        <b style="font-size: 17px">Thi thử HSK {level.id}</b>
        {#if data.mock.count > 0}
          <p class="muted" style="font-size: 14px">
            {data.mock.questionCount} câu · {data.mock.durationMin} phút · đạt từ {data.mock.passPoints}/{data.mock.maxPoints} điểm
            · {data.mock.count} đề
          </p>
        {:else}
          <p class="muted" style="font-size: 14px">Chưa có đề thi thử.</p>
        {/if}
        <a href="/admin/mock-tests?level={level.id}" class="btn btn-sm btn-paper" style="align-self: flex-start">
          <Icon name="edit" size={16} /> Soạn đề thi thử
        </a>
      </section>
    </div>
  {/if}
{/snippet}

<div class="head">
  <div>
    <h1>Bài học HSK</h1>
    <p class="muted" style="font-size: 14px">
      Mỗi cấp gồm nhiều bài. Mỗi bài có 6 phần: từ vựng, bài tập, kiểm tra, luyện viết, hội thoại, ngữ pháp.
    </p>
  </div>
  <button type="button" class="btn btn-son btn-sm" onclick={() => (addOpen = true)}>
    <Icon name="plus" size={16} /> Thêm bài
  </button>
</div>

<!-- Chọn cấp -->
{#if levels}
  <div class="lv-tabs" role="tablist" aria-label="Chọn cấp HSK">
    {#each levels as l (l.id)}
      <button
        type="button"
        role="tab"
        aria-selected={l.id === levelId}
        class="btn btn-sm {l.id === levelId ? 'btn-muc' : 'btn-paper'}"
        onclick={() => pickLevel(l.id)}
      >
        {l.name} <span class="num" style="opacity: .75">{l.lessonCount} bài</span>
      </button>
    {/each}
  </div>
{:else}
  <Skeleton h="40px" r={12} />
{/if}

{#if failed}
  <EmptyState title="Không tải được danh sách bài.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={() => loadLessons(levelId)}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if !data}
  <Skeleton h="120px" r={16} />
  <Skeleton h="320px" r={16} />
{:else if data.lessons.length === 0}
  <EmptyState title="Cấp này chưa có bài nào." desc="Thêm bài đầu tiên để bắt đầu soạn nội dung.">
    {#snippet action()}<button type="button" class="btn btn-son btn-sm" onclick={() => (addOpen = true)}>Thêm bài</button>{/snippet}
  </EmptyState>
{:else if media.isAdminMobile}
  <!-- ============ Điện thoại ============ -->
  {@render kpiRow()}
  <p class="muted note">TV từ vựng · BT bài tập · KT kiểm tra · LV luyện viết · HT hội thoại · NP ngữ pháp. Ô đỏ là còn thiếu.</p>
  <div class="mlist">
    {#each data.lessons as l (l.id)}
      <article class="block mcard">
        <div class="mhead">
          <span class="no lv{levelId}">{l.no}</span>
          <span style="flex: 1; min-width: 0">
            <b style="display: block">{l.title}</b>
            <span class="tag {l.status === 'published' ? 'tag-ok' : ''}">{l.status === 'published' ? 'Hiện' : 'Nháp'}</span>
          </span>
          <button type="button" class="btn btn-bare btn-sm" aria-label="Sửa {l.title}" onclick={() => openPart(l.id, 'lt')}>
            <Icon name="edit" size={18} />
          </button>
        </div>
        <div class="parts">
          {#each PART_COL as c (c.key)}
            <button type="button" class="pbox" class:miss={l.counts[c.key] === 0} onclick={() => openPart(l.id, c.part)}>
              <b>{c.short}</b>
              <span class="num">{l.counts[c.key] || '—'}</span>
            </button>
          {/each}
        </div>
      </article>
    {/each}
  </div>
  {@render endOfLevel()}
{:else}
  <!-- ============ Máy tính ============ -->
  {@render kpiRow()}
  <div class="paper tbl-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th style="width: 56px">Bài</th>
          <th>Tên bài</th>
          {#each PART_COL as c (c.key)}<th>{c.label}</th>{/each}
          <th>Trạng thái</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        {#each data.lessons as l (l.id)}
          <tr>
            <td><b class="num" style="font-size: 17px">{l.no}</b></td>
            <td>
              <button type="button" class="btn-text name" onclick={() => openPart(l.id, 'lt')}>{l.title}</button>
              {#if l.sampleText}<span class="hz muted sample" lang="zh">{l.sampleText}</span>{/if}
            </td>
            {#each PART_COL as c (c.key)}
              <td>
                <button type="button" class="cellbtn" onclick={() => openPart(l.id, c.part)}>
                  {#if l.counts[c.key] > 0}
                    <span class="num">{l.counts[c.key]} {c.unit}</span>
                  {:else}
                    <span class="tag tag-bad">Thiếu</span>
                  {/if}
                </button>
              </td>
            {/each}
            <td>
              <span class="tag {l.status === 'published' ? 'tag-ok' : ''}">
                {l.status === 'published' ? 'Đang hiện' : 'Nháp · đang ẩn'}
              </span>
            </td>
            <td>
              <button type="button" class="btn btn-sm btn-paper" onclick={() => openPart(l.id, 'lt')}>
                <Icon name="edit" size={15} /> Sửa
              </button>
            </td>
          </tr>
        {/each}
      </tbody>
    </table>
  </div>
  <p class="muted note">
    Phần nào “Thiếu” thì bên người học hiện “Sắp có” cho phần đó. Bài ở trạng thái Nháp thì người học không thấy.
  </p>
  {@render endOfLevel()}
{/if}

{#if addOpen}
  <Modal title="Thêm bài mới" onclose={() => (addOpen = false)}>
    <label class="lbl" for="new-lesson">Tên bài</label>
    <!-- svelte-ignore a11y_autofocus -->
    <input
      id="new-lesson"
      class="inp"
      autofocus
      bind:value={addTitle}
      placeholder="Ví dụ: Ăn uống"
      onkeydown={(e) => e.key === 'Enter' && addLesson()}
    />
    {#if addError}<p class="err">{addError}</p>{/if}
    <p class="hint">Bài mới được thêm vào cuối cấp HSK {levelId} ở trạng thái Nháp.</p>
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (addOpen = false)}>Huỷ</button>
      <button type="button" class="btn btn-son btn-sm" disabled={busy} onclick={addLesson}>Thêm bài</button>
    {/snippet}
  </Modal>
{/if}

<style>
  .head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 18px;
  }
  .head h1 {
    font-size: 28px;
  }
  .lv-tabs {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 18px;
  }
  .tbl-wrap {
    overflow-x: auto;
    margin-top: 18px;
  }
  .name {
    font-weight: 700;
    color: var(--text);
    cursor: pointer;
  }
  .name:hover {
    color: var(--son-text);
  }
  .sample {
    display: block;
    font-size: 12.5px;
  }
  .cellbtn {
    background: none;
    border: 0;
    padding: 0;
    font: inherit;
    color: inherit;
    cursor: pointer;
  }
  .note {
    font-size: 13px;
    margin-top: 12px;
  }
  .end-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(300px, 100%), 1fr));
    gap: 16px;
    margin-top: 12px;
  }
  .end-grid .block {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 18px 20px;
  }

  /* --------- Điện thoại --------- */
  .mlist {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 14px;
  }
  .mcard {
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .mhead {
    display: flex;
    align-items: center;
    gap: 12px;
  }
  .no {
    width: 38px;
    height: 38px;
    flex: none;
    display: grid;
    place-items: center;
    border: 1px solid var(--line);
    border-radius: var(--r);
    font-weight: 700;
  }
  .parts {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 6px;
  }
  .pbox {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    padding: 6px 2px;
    border: 1px solid #CDEBD6;
    border-radius: var(--r-sm);
    background: var(--ngoc-soft);
    color: var(--green-text);
    font-size: 11px;
    cursor: pointer;
  }
  .pbox.miss {
    background: var(--son-soft);
    border: 1px dashed var(--son-line);
    color: var(--bad-text);
  }
</style>
