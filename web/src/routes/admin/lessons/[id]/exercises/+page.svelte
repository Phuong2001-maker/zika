<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { toast } from '$lib/stores/toast.svelte';
  import { ApiError } from '$lib/api/client';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import LessonHeader from '$lib/admin/LessonHeader.svelte';
  import QuestionForm from '$lib/admin/QuestionForm.svelte';
  import { adminApi, type AdminQuestion, type Draft } from '$lib/admin/api';

  // Soạn bài tập — /admin/lessons/:id/exercises (08 §8).
  const TYPES: [string, string][] = [
    ['meaning', 'Chọn nghĩa'],
    ['blank', 'Điền chỗ trống'],
    ['audio', 'Nghe chọn chữ'],
    ['pinyin', 'Chọn pinyin'],
    ['order', 'Sắp xếp câu'],
    ['trans', 'Dịch sang tiếng Việt'],
    ['tf', 'Đúng / Sai'],
    ['match', 'Nối từ'],
  ];
  const typeName = (t: string): string => TYPES.find(([c]) => c === t)?.[1] ?? t;

  const lessonId = $derived(Number(page.params.id));

  let questions = $state<AdminQuestion[] | null>(null);
  let failed = $state(false);
  let draft = $state<Draft | null>(null);
  let errors = $state<Record<string, string>>({});
  let dirty = $state(false);
  let saving = $state(false);
  /** Khoá tăng dần để ép QuestionForm dựng lại khi đổi câu (tránh giữ state cũ). */
  let formKey = $state(0);

  function blank(): Draft {
    return {
      id: null,
      type: 'meaning',
      payload: { word: '', ask: 'Từ này nghĩa là gì?', options: ['', '', '', ''], answer: 0 },
      explain: '',
      score: 1,
    };
  }

  async function load(): Promise<void> {
    failed = false;
    try {
      const res = await adminApi.questions(lessonId, 'bt');
      questions = res.questions;
      pick(res.questions[0] ?? null);
    } catch {
      failed = true;
    }
  }

  onMount(load);

  function pick(q: AdminQuestion | null): void {
    if (dirty && !confirm('Câu đang sửa chưa lưu. Bỏ thay đổi?')) return;
    errors = {};
    dirty = false;
    formKey++;
    draft = q === null ? blank() : { id: q.id, type: q.type, payload: structuredClone(q.payload), explain: q.explain ?? '', score: q.score };
  }

  function addNew(): void {
    pick(null);
  }

  async function save(andNext = false): Promise<void> {
    if (!draft) return;
    saving = true;
    errors = {};
    try {
      const body = { part: 'bt', type: draft.type, payload: draft.payload, explain: draft.explain, score: draft.score };
      const saved = draft.id === null
        ? await adminApi.createQuestion(lessonId, body)
        : await adminApi.updateQuestion(draft.id, body);

      // Cập nhật danh sách tại chỗ, khỏi tải lại cả trang.
      const list = questions ?? [];
      questions = draft.id === null ? [...list, saved] : list.map((q) => (q.id === saved.id ? saved : q));
      dirty = false;
      toast.ok('Đã lưu.');
      if (andNext) addNew();
      else pick(saved);
    } catch (e) {
      if (e instanceof ApiError) {
        errors = e.fields;
        toast.show(Object.values(e.fields)[0] ?? e.message);
      } else {
        toast.show('Không lưu được, thử lại nhé.');
      }
    } finally {
      saving = false;
    }
  }

  async function remove(): Promise<void> {
    if (!draft?.id) return;
    if (!confirm('Xoá câu này?')) return;
    const id = draft.id;
    try {
      await adminApi.deleteQuestion(id);
      questions = (questions ?? []).filter((q) => q.id !== id);
      dirty = false;
      pick(questions[0] ?? null);
      toast.ok('Đã xoá.');
    } catch {
      toast.show('Không xoá được.');
    }
  }

  const index = $derived(draft?.id ? (questions ?? []).findIndex((q) => q.id === draft!.id) : -1);
  const title = $derived(index >= 0 ? `Câu ${index + 1} · ${typeName(draft?.type ?? '')}` : `Câu mới · ${typeName(draft?.type ?? '')}`);
</script>

<svelte:head><title>Soạn bài tập · Quản trị · Zìkǎ</title></svelte:head>

<LessonHeader {lessonId} part="bt" {dirty} {saving} onsave={() => save(false)} />

{#if failed}
  <EmptyState title="Không tải được danh sách câu.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if questions === null}
  <Skeleton h="320px" r={16} />
{:else}
  <div class="cols">
    <!-- Danh sách câu -->
    <aside class="paper list">
      <div class="list-head">
        <b>{questions.length} câu bài tập</b>
        <button type="button" class="btn btn-sm btn-paper" onclick={addNew}><Icon name="plus" size={15} /> Thêm câu</button>
      </div>

      {#if questions.length === 0}
        <p class="muted" style="font-size: 13.5px; padding: 8px 2px">Chưa có câu nào. Bấm “Thêm câu” rồi nhập ở bên phải.</p>
      {/if}

      <ul class="qlist">
        {#each questions as q, i (q.id)}
          <li>
            <button type="button" class="les qrow" class:on={draft?.id === q.id} onclick={() => pick(q)}>
              <span class="qno">{i + 1}</span>
              <span style="min-width: 0">
                <span class="qtype">{typeName(q.type)}</span>
                <span class="hz qprompt" lang="zh">{q.promptText || '—'}</span>
              </span>
            </button>
          </li>
        {/each}
      </ul>
    </aside>

    <!-- Biểu mẫu -->
    <section class="block form">
      {#if draft}
        <div class="form-head">
          <h2 class="h2">{title}</h2>
        </div>

        {#key formKey}
          <QuestionForm bind:draft={draft!} types={TYPES} {errors} onchange={() => (dirty = true)} />
        {/key}

        <div class="form-foot">
          {#if draft.id !== null}
            <button type="button" class="btn btn-danger btn-sm" onclick={remove}><Icon name="trash" size={15} /> Xoá câu</button>
          {/if}
          <span class="grow"></span>
          <button type="button" class="btn btn-sm" disabled={saving} onclick={() => save(true)}>Lưu &amp; thêm câu</button>
          <button type="button" class="btn btn-son btn-sm" disabled={saving} onclick={() => save(false)}>
            {saving ? 'Đang lưu…' : 'Lưu câu'}
          </button>
        </div>
      {/if}
    </section>
  </div>
{/if}

<style>
  .cols {
    display: flex;
    flex-wrap: wrap;
    gap: 22px;
    align-items: flex-start;
  }
  .list {
    flex: 1 1 320px;
    max-width: 380px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  .list-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }
  .qlist {
    display: flex;
    flex-direction: column;
    gap: 6px;
    max-height: 60vh;
    overflow-y: auto;
  }
  .qrow {
    flex-direction: row;
    align-items: center;
    gap: 10px;
    width: 100%;
  }
  .qno {
    width: 26px;
    height: 26px;
    flex: none;
    display: grid;
    place-items: center;
    border-radius: 7px;
    background: var(--surface-2);
    border: 1px solid var(--ink-strong);
    font-size: 12.5px;
    font-weight: 700;
    color: var(--text-2);
  }
  .qtype {
    display: block;
    font-size: 11.5px;
    font-weight: 700;
    color: var(--text-3);
  }
  .qprompt {
    display: block;
    font-size: 14.5px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .form {
    flex: 999 1 520px;
    min-width: 0;
    padding: 20px 22px;
    display: flex;
    flex-direction: column;
    gap: 14px;
  }
  .form-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }
  .form-foot {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding-top: 6px;
    border-top: 1px solid var(--line);
  }

  @media (max-width: 899px) {
    .list {
      max-width: none;
    }
  }
</style>
