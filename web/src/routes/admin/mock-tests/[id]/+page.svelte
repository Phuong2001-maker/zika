<script lang="ts">
  import { page } from '$app/state';
  import { goto } from '$app/navigation';
  import { api, ApiError } from '$lib/api/client';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import QuestionForm from '$lib/admin/QuestionForm.svelte';
  import ImportModal from '$lib/admin/ImportModal.svelte';
  import { adminApi, type AdminQuestion, type Draft } from '$lib/admin/api';
  import { questionsImport, type ImportMode, type ImportOutcome, type QuestionRow } from '$lib/admin/import';

  // Soạn đề thi thử cuối cấp cũ: danh sách câu, thêm tay hoặc nạp Excel. Dạng câu dùng chung với bài kiểm tra.
  // Đề cuộc thi và đề HSK 3.0 soạn ở màn khung HSK 3.0 (SoanDeHsk), đường dẫn cũ tới đây thì chuyển sang đó.
  interface Mock { id: number; levelId: number; title: string; status: 'draft' | 'published'; durationSec: number; forContest: boolean; kind?: string }

  const TYPES: [string, string][] = [
    ['blank', 'Chọn từ điền câu'],
    ['listen', 'Nghe và chọn'],
    ['meaning', 'Chọn nghĩa'],
    ['pick', 'Chọn câu đúng'],
    ['order', 'Sắp xếp câu'],
    ['trans', 'Dịch sang tiếng Việt'],
    ['tf', 'Đúng / Sai'],
    ['match', 'Nối từ'],
    ['tone', 'Thanh điệu'],
    ['toZh', 'Chọn câu tiếng Trung'],
  ];
  const typeName = (t: string): string => TYPES.find(([c]) => c === t)?.[1] ?? t;

  const mockId = $derived(Number(page.params.id));
  const spec = questionsImport('kt');

  let mock = $state<Mock | null>(null);
  let questions = $state<AdminQuestion[]>([]);
  let failed = $state(false);
  let draft = $state<Draft | null>(null);
  let errors = $state<Record<string, string>>({});
  let dirty = $state(false);
  let saving = $state(false);
  let formKey = $state(0);
  let importOpen = $state(false);

  function blank(): Draft {
    return {
      id: null,
      type: 'blank',
      payload: { pre: '', post: '', sentence: '', vi: '', showMeaning: false, options: ['', '', '', ''], answer: 0 },
      explain: '',
      score: 1,
    };
  }

  async function load(): Promise<void> {
    failed = false;
    try {
      const res = await api.get<{ mock: Mock; questions: AdminQuestion[] }>(`/admin/mock-tests/${mockId}/questions`);
      if (res.mock.forContest || res.mock.kind === 'hsk30') {
        void goto(res.mock.forContest ? `/admin/exams/${mockId}` : `/admin/hsk30/${mockId}`, { replaceState: true });
        return;
      }
      mock = res.mock;
      questions = res.questions;
      dirty = false;
      pick(res.questions[0] ?? null);
    } catch {
      failed = true;
    }
  }
  // Đổi đề mà vẫn ở cùng route thì SvelteKit không dựng lại trang.
  $effect(() => {
    void mockId;
    void load();
  });

  function pick(q: AdminQuestion | null): void {
    if (dirty && !confirm('Câu đang sửa chưa lưu. Bỏ thay đổi?')) return;
    errors = {};
    dirty = false;
    formKey++;
    draft = q === null ? blank() : { id: q.id, type: q.type, payload: $state.snapshot(q.payload), explain: q.explain ?? '', score: q.score };
  }

  async function save(andNext = false): Promise<void> {
    if (!draft) return;
    saving = true;
    errors = {};
    try {
      const body = { type: draft.type, payload: draft.payload, explain: draft.explain, score: draft.score };
      const saved =
        draft.id === null
          ? await api.post<AdminQuestion>(`/admin/mock-tests/${mockId}/questions`, body)
          : await adminApi.updateQuestion(draft.id, body);
      questions = draft.id === null ? [...questions, saved] : questions.map((q) => (q.id === saved.id ? saved : q));
      dirty = false;
      toast.ok('Đã lưu.');
      pick(andNext ? null : saved);
    } catch (e) {
      if (e instanceof ApiError) {
        errors = e.fields;
        toast.show(Object.values(e.fields)[0] ?? e.message);
      } else toast.show('Không lưu được.');
    } finally {
      saving = false;
    }
  }

  async function remove(): Promise<void> {
    if (!draft?.id || !confirm('Xoá câu này?')) return;
    const id = draft.id;
    try {
      await adminApi.deleteQuestion(id);
      questions = questions.filter((q) => q.id !== id);
      dirty = false;
      pick(questions[0] ?? null);
      toast.ok('Đã xoá.');
    } catch {
      toast.show('Không xoá được.');
    }
  }

  async function runImport(items: QuestionRow[], mode: ImportMode): Promise<ImportOutcome> {
    const out = await api.post<ImportOutcome>(`/admin/mock-tests/${mockId}/questions/bulk`, { mode, items });
    dirty = false;
    await load();
    return out;
  }
</script>

<svelte:head><title>{mock?.title ?? 'Soạn đề'} · Quản trị · Zìkǎ</title></svelte:head>

{#if failed}
  <EmptyState title="Không tải được đề.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if mock === null}
  <Skeleton h="320px" r={16} />
{:else}
  <div class="head">
    <div style="min-width: 0">
      <a class="muted back" href="/admin/mock-tests?level={mock.levelId}"><Icon name="chev" size={15} style="transform: rotate(180deg)" /> Đề thi thử HSK {mock.levelId}</a>
      <h1>{mock.title}</h1>
      <p class="muted" style="font-size: 14px">{questions.length} câu</p>
    </div>
    <div style="display: flex; align-items: center; gap: 8px">
      <span class="tag {mock.status === 'published' ? 'tag-ok' : ''}">{mock.status === 'published' ? 'Công khai' : 'Nháp'}</span>
      <button type="button" class="btn btn-sm" onclick={() => (importOpen = true)}><Icon name="upload" size={15} /> Nhập Excel</button>
    </div>
  </div>

  <div class="cols">
    <aside class="paper list">
      <div class="list-head">
        <b>{questions.length} câu</b>
        <button type="button" class="btn btn-sm btn-paper" onclick={() => pick(null)}><Icon name="plus" size={15} /> Thêm</button>
      </div>
      {#if questions.length === 0}
        <p class="muted" style="font-size: 13.5px; padding: 8px 2px">Chưa có câu nào. Bấm “Thêm” hoặc nhập từ Excel.</p>
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

    <section class="block form">
      {#if draft}
        <h2 class="h2">{draft.id === null ? 'Câu mới' : 'Sửa câu'} · {typeName(draft.type)}</h2>
        {#key formKey}
          <QuestionForm bind:draft={draft!} types={TYPES} {errors} onchange={() => (dirty = true)} />
        {/key}
        <div class="form-foot">
          {#if draft.id !== null}
            <button type="button" class="btn btn-danger btn-sm" onclick={remove}><Icon name="trash" size={15} /> Xoá câu</button>
          {/if}
          <span style="flex: 1"></span>
          <button type="button" class="btn btn-sm" disabled={saving} onclick={() => save(true)}>Lưu &amp; thêm câu</button>
          <button type="button" class="btn btn-son btn-sm" disabled={saving} onclick={() => save(false)}>{saving ? 'Đang lưu…' : 'Lưu câu'}</button>
        </div>
      {/if}
    </section>
  </div>
{/if}

{#if importOpen}
  <ImportModal
    {spec}
    replaceNote="Xoá hết câu đang có của đề này rồi nhập mới."
    onclose={() => (importOpen = false)}
    onrun={runImport}
  />
{/if}

<style>
  .head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 14px;
  }
  .head h1 {
    font-size: 26px;
    margin-top: 4px;
  }
  .back {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
    text-decoration: none;
  }
  .cols {
    display: flex;
    flex-wrap: wrap;
    gap: 22px;
    align-items: flex-start;
  }
  .list {
    flex: 1 1 320px;
    min-width: 0;
    max-width: 380px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  @media (max-width: 899px) {
    .list {
      max-width: none;
    }
  }
  .list-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
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
  .form-foot {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding-top: 6px;
    border-top: 1px solid var(--line);
  }
</style>
