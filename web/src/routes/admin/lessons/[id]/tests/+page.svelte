<script lang="ts">
  import { SvelteSet } from 'svelte/reactivity';
  import { page } from '$app/state';
  import { toast } from '$lib/stores/toast.svelte';
  import { ApiError } from '$lib/api/client';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';
  import LessonHeader from '$lib/admin/LessonHeader.svelte';
  import QuestionForm from '$lib/admin/QuestionForm.svelte';
  import ImportModal from '$lib/admin/ImportModal.svelte';
  import { adminApi, type AdminQuestion, type Draft, type TestConfig } from '$lib/admin/api';
  import { questionsImport, type ImportMode, type QuestionRow } from '$lib/admin/import';

  // Soạn bài kiểm tra — /admin/lessons/:id/tests (08 §9).
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

  const lessonId = $derived(Number(page.params.id));

  let questions = $state<AdminQuestion[] | null>(null);
  let config = $state<TestConfig | null>(null);
  let failed = $state(false);
  let draft = $state<Draft | null>(null);
  let errors = $state<Record<string, string>>({});
  let dirty = $state(false);
  let saving = $state(false);
  let formKey = $state(0);
  let cfgOpen = $state(false);
  let copyOpen = $state(false);
  let nhapOpen = $state(false);
  /** Tăng sau mỗi lần nhập để LessonHeader đọc lại số lượng trên hàng tabs. */
  let reload = $state(0);

  const nhapSpec = questionsImport('kt');

  /** Nhập xong thì tải lại cả danh sách — câu mới do server đánh số thứ tự. */
  async function nhapExcel(items: QuestionRow[], mode: ImportMode) {
    const out = await adminApi.bulkQuestions(lessonId, { part: 'kt', mode, items });
    dirty = false;
    await load();
    reload++;
    return out;
  }
  let btQuestions = $state<AdminQuestion[]>([]);
  const copyPick = new SvelteSet<number>();

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
      const res = await adminApi.questions(lessonId, 'kt');
      questions = res.questions;
      config = res.config ?? null;
      pick(res.questions[0] ?? null);
    } catch {
      failed = true;
    }
  }
  // Đổi bài mà vẫn ở cùng một phần thì SvelteKit giữ nguyên trang (chỉ đổi tham số),
  // nên phải nạp lại theo lessonId chứ onMount không chạy lại.
  $effect(() => {
    void lessonId;
    void load();
  });

  function pick(q: AdminQuestion | null): void {
    if (dirty && !confirm('Câu đang sửa chưa lưu. Bỏ thay đổi?')) return;
    errors = {};
    dirty = false;
    formKey++;
    draft = q === null ? blank() : { id: q.id, type: q.type, payload: structuredClone(q.payload), explain: q.explain ?? '', score: q.score };
  }

  async function save(andNext = false): Promise<void> {
    if (!draft) return;
    saving = true;
    errors = {};
    try {
      const body = { part: 'kt', type: draft.type, payload: draft.payload, explain: draft.explain, score: draft.score };
      const saved = draft.id === null ? await adminApi.createQuestion(lessonId, body) : await adminApi.updateQuestion(draft.id, body);
      const list = questions ?? [];
      questions = draft.id === null ? [...list, saved] : list.map((q) => (q.id === saved.id ? saved : q));
      dirty = false;
      toast.ok('Đã lưu.');
      if (andNext) pick(null);
      else pick(saved);
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
      questions = (questions ?? []).filter((q) => q.id !== id);
      dirty = false;
      pick(questions[0] ?? null);
      toast.ok('Đã xoá.');
    } catch {
      toast.show('Không xoá được.');
    }
  }

  async function saveConfig(): Promise<void> {
    if (!config) return;
    try {
      config = await adminApi.saveTestConfig(lessonId, config);
      cfgOpen = false;
      toast.ok('Đã lưu cài đặt bài kiểm tra.');
    } catch {
      toast.show('Không lưu được cài đặt.');
    }
  }

  async function openCopy(): Promise<void> {
    try {
      const res = await adminApi.questions(lessonId, 'bt');
      // Chỉ chép được các dạng mà bài kiểm tra có.
      btQuestions = res.questions.filter((q) => TYPES.some(([t]) => t === q.type));
      copyPick.clear();
      for (const q of btQuestions) copyPick.add(q.id);
      copyOpen = true;
    } catch {
      toast.show('Không tải được câu bài tập.');
    }
  }

  async function doCopy(): Promise<void> {
    try {
      const res = await adminApi.copyFromBt(lessonId, [...copyPick]);
      copyOpen = false;
      toast.ok(`Đã chép ${res.copied} câu.`);
      await load();
    } catch {
      toast.show('Không chép được.');
    }
  }

  const index = $derived(draft?.id ? (questions ?? []).findIndex((q) => q.id === draft!.id) : -1);
</script>

<svelte:head><title>Soạn bài kiểm tra · Quản trị · Zìkǎ</title></svelte:head>

<LessonHeader {lessonId} part="kt" {dirty} {saving} {reload} onsave={() => save(false)} />

{#if failed}
  <EmptyState title="Không tải được câu hỏi.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if questions === null}
  <Skeleton h="320px" r={16} />
{:else}
  <!-- Cài đặt bài kiểm tra -->
  {#if config}
    <section class="paper cfg">
      <b style="font-size: 14px">Cài đặt bài kiểm tra</b>
      <span class="muted num" style="font-size: 13.5px">
        {questions.length} câu · {Math.round(config.durationSec / 60)} phút · đạt từ {config.passScore} điểm ·
        {config.attemptsLimit ? `tối đa ${config.attemptsLimit} lượt` : 'không giới hạn lượt'}
        {config.shuffleQuestions ? ' · trộn câu' : ''}{config.autoSubmit ? ' · hết giờ tự nộp' : ''}
      </span>
      <span style="flex: 1"></span>
      <button type="button" class="btn btn-sm" onclick={() => (cfgOpen = true)}><Icon name="sliders" size={15} /> Chỉnh</button>
    </section>
  {/if}

  <div class="cols">
    <aside class="paper list">
      <div class="list-head">
        <b>{questions.length} câu kiểm tra</b>
        <div style="display: flex; gap: 6px; flex-wrap: wrap">
          <button type="button" class="btn btn-sm btn-paper" onclick={() => (nhapOpen = true)}>
            <Icon name="upload" size={14} /> Nhập Excel
          </button>
          <button type="button" class="btn btn-sm btn-paper" onclick={openCopy}><Icon name="download" size={14} /> Lấy từ Bài tập</button>
          <button type="button" class="btn btn-sm btn-paper" onclick={() => pick(null)}><Icon name="plus" size={15} /> Thêm</button>
        </div>
      </div>

      {#if questions.length === 0}
        <p class="muted" style="font-size: 13.5px; padding: 8px 2px">Chưa có câu nào. Bấm “Thêm” hoặc chép từ phần Bài tập.</p>
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
        <h2 class="h2">{index >= 0 ? `Câu ${index + 1}` : 'Câu mới'} · {typeName(draft.type)}</h2>
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

{#if nhapOpen}
  <ImportModal
    spec={nhapSpec}
    replaceNote="Xoá hết câu kiểm tra đang có của bài rồi nhập mới."
    onclose={() => (nhapOpen = false)}
    onrun={nhapExcel}
  />
{/if}

{#if cfgOpen && config}
  <Modal title="Cài đặt bài kiểm tra" onclose={() => (cfgOpen = false)} width={520}>
    <div class="cfg-grid">
      <div class="field">
        <label class="lbl" for="c-dur">Thời gian làm bài (phút)</label>
        <input id="c-dur" class="inp" type="number" min="1" max="120"
               value={Math.round(config.durationSec / 60)}
               oninput={(e) => (config!.durationSec = Math.max(1, Number(e.currentTarget.value)) * 60)} />
      </div>
      <div class="field">
        <label class="lbl" for="c-pass">Điểm đạt</label>
        <input id="c-pass" class="inp" type="number" min="0" bind:value={config.passScore} />
      </div>
      <div class="field">
        <label class="lbl" for="c-listen">Số lần được nghe</label>
        <input id="c-listen" class="inp" type="number" min="1" max="10" bind:value={config.listenLimit} />
      </div>
      <div class="field">
        <label class="lbl" for="c-att">Số lượt làm (để trống = không giới hạn)</label>
        <input id="c-att" class="inp" type="number" min="1" max="50"
               value={config.attemptsLimit ?? ''}
               oninput={(e) => (config!.attemptsLimit = e.currentTarget.value === '' ? null : Number(e.currentTarget.value))} />
      </div>
    </div>
    <label class="sw"><input type="checkbox" bind:checked={config.shuffleQuestions} /><span></span> Trộn thứ tự câu</label>
    <label class="sw"><input type="checkbox" bind:checked={config.shuffleOptions} /><span></span> Trộn thứ tự đáp án</label>
    <label class="sw"><input type="checkbox" bind:checked={config.autoSubmit} /><span></span> Hết giờ tự nộp</label>
    <label class="sw"><input type="checkbox" bind:checked={config.showAnswersAfter} /><span></span> Cho xem đáp án sau khi nộp</label>
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (cfgOpen = false)}>Huỷ</button>
      <button type="button" class="btn btn-son btn-sm" onclick={saveConfig}>Lưu</button>
    {/snippet}
  </Modal>
{/if}

{#if copyOpen}
  <Modal title="Lấy câu từ phần Bài tập" onclose={() => (copyOpen = false)} width={560}>
    {#if btQuestions.length === 0}
      <p class="muted">Phần Bài tập chưa có câu nào dùng được cho Kiểm tra.</p>
    {:else}
      <p class="muted" style="font-size: 13.5px">Chọn câu muốn chép sang. Dạng “Nghe chọn chữ” và “Chọn pinyin” không có ở Kiểm tra nên không hiện ở đây.</p>
      <ul style="display: flex; flex-direction: column; gap: 4px; max-height: 320px; overflow-y: auto">
        {#each btQuestions as q (q.id)}
          <li>
            <label class="chk">
              <input type="checkbox" checked={copyPick.has(q.id)}
                     onchange={(e) => {
                       if (e.currentTarget.checked) copyPick.add(q.id);
                       else copyPick.delete(q.id);
                     }} />
              <span style="flex: 1; min-width: 0">
                <b style="font-size: 12px; color: var(--text-3)">{typeName(q.type)}</b>
                <span class="hz" lang="zh" style="display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{q.promptText}</span>
              </span>
            </label>
          </li>
        {/each}
      </ul>
    {/if}
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (copyOpen = false)}>Huỷ</button>
      <button type="button" class="btn btn-son btn-sm" disabled={copyPick.size === 0} onclick={doCopy}>Chép {copyPick.size} câu</button>
    {/snippet}
  </Modal>
{/if}

<style>
  .cfg {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    padding: 12px 16px;
  }
  .cols {
    display: flex;
    flex-wrap: wrap;
    gap: 22px;
    align-items: flex-start;
  }
  .list {
    flex: 1 1 320px;
    /* min-width: 0 để cột co lại được: flex-basis 320px cộng padding vượt bề ngang điện thoại. */
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
    flex-wrap: wrap;
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
  .cfg-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(180px, 100%), 1fr));
    gap: 12px;
  }
  .field {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
</style>
