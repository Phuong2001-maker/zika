<script lang="ts">
  import { page } from '$app/state';
  import { toast } from '$lib/stores/toast.svelte';
  import { ApiError } from '$lib/api/client';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import LessonHeader from '$lib/admin/LessonHeader.svelte';
  import { adminApi, type AdminGrammarPoint, type GrammarBlock, type GrammarExample } from '$lib/admin/api';
  import { normZh } from '$lib/utils/grade';

  // Soạn ngữ pháp — /admin/lessons/:id/grammar (08 §12).
  const COLORS = ['#DCEBFF', '#CFF5E1', '#FFB3AA', '#FFF0B3', '#E8E0FF'];

  const lessonId = $derived(Number(page.params.id));

  interface Draft {
    id: number | null;
    title: string;
    subtitle: string;
    explain: string;
    note: string;
    blocks: GrammarBlock[];
    examples: GrammarExample[];
    practice: { answer: string; vi: string; tiles: string[] };
  }

  let points = $state<AdminGrammarPoint[] | null>(null);
  let draft = $state<Draft | null>(null);
  let dirty = $state(false);
  let saving = $state(false);

  function blank(): Draft {
    return {
      id: null,
      title: '',
      subtitle: '',
      explain: '',
      note: '',
      blocks: [{ label: 'Chủ ngữ', hz: '', color: COLORS[0] }],
      examples: [{ kind: 'Khẳng định', formula: '', zh: '', highlight: null, pinyin: '', vi: '' }],
      practice: { answer: '', vi: '', tiles: [] },
    };
  }

  async function load(): Promise<void> {
    try {
      points = await adminApi.grammar(lessonId);
      pick(points[0] ?? null);
    } catch {
      toast.show('Không tải được phần ngữ pháp.');
      points = [];
    }
  }
  // Đổi bài mà vẫn ở cùng một phần thì SvelteKit giữ nguyên trang (chỉ đổi tham số),
  // nên phải nạp lại theo lessonId chứ onMount không chạy lại.
  $effect(() => {
    void lessonId;
    void load();
  });

  function pick(p: AdminGrammarPoint | null): void {
    if (dirty && !confirm('Điểm đang sửa chưa lưu. Bỏ thay đổi?')) return;
    dirty = false;
    draft = p
      ? {
          id: p.id,
          title: p.title,
          subtitle: p.subtitle,
          explain: p.explain,
          note: p.note ?? '',
          blocks: p.blocks.length ? structuredClone(p.blocks) : [{ label: '', hz: '', color: COLORS[0] }],
          examples: p.examples.length ? structuredClone(p.examples) : [{ kind: '', formula: '', zh: '', highlight: null, pinyin: '', vi: '' }],
          practice: p.practice ? structuredClone(p.practice) : { answer: '', vi: '', tiles: [] },
        }
      : blank();
  }

  const touch = (): void => {
    dirty = true;
  };

  function autoTiles(): void {
    if (!draft) return;
    const text = normZh(draft.practice.answer);
    draft.practice.tiles = Array.from(text);
    touch();
  }

  function mergeTile(i: number): void {
    if (!draft) return;
    const t = [...draft.practice.tiles];
    if (i >= t.length - 1) return;
    t.splice(i, 2, t[i] + t[i + 1]);
    draft.practice.tiles = t;
    touch();
  }

  function splitTile(i: number): void {
    if (!draft) return;
    const t = [...draft.practice.tiles];
    const s = t[i] ?? '';
    if (s.length < 2) return;
    const h = Math.ceil(s.length / 2);
    t.splice(i, 1, s.slice(0, h), s.slice(h));
    draft.practice.tiles = t;
    touch();
  }

  async function save(): Promise<void> {
    if (!draft) return;
    saving = true;
    try {
      const body = {
        title: draft.title.trim(),
        subtitle: draft.subtitle.trim(),
        explain: draft.explain.trim(),
        note: draft.note.trim() || null,
        blocks: draft.blocks.filter((b) => b.label.trim() !== ''),
        examples: draft.examples.filter((e) => e.zh.trim() !== ''),
        practice: draft.practice.answer.trim() === '' ? {} : draft.practice,
      };
      const saved = draft.id === null ? await adminApi.createGrammar(lessonId, body) : await adminApi.updateGrammar(draft.id, body);
      const list = points ?? [];
      points = draft.id === null ? [...list, saved] : list.map((p) => (p.id === saved.id ? saved : p));
      dirty = false;
      pick(saved);
      toast.ok('Đã lưu.');
    } catch (e) {
      toast.show(e instanceof ApiError ? (Object.values(e.fields)[0] ?? e.message) : 'Không lưu được.');
    } finally {
      saving = false;
    }
  }

  async function remove(): Promise<void> {
    if (!draft?.id || !confirm('Xoá điểm ngữ pháp này?')) return;
    const id = draft.id;
    try {
      await adminApi.deleteGrammar(id);
      points = (points ?? []).filter((p) => p.id !== id);
      dirty = false;
      pick(points[0] ?? null);
      toast.ok('Đã xoá.');
    } catch {
      toast.show('Không xoá được.');
    }
  }
</script>

<svelte:head><title>Soạn ngữ pháp · Quản trị · Zìkǎ</title></svelte:head>

<LessonHeader {lessonId} part="np" {dirty} {saving} onsave={save} />

{#if points === null}
  <Skeleton h="320px" r={16} />
{:else}
  <div class="cols">
    <aside class="paper list">
      <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px">
        <b>{points.length} điểm ngữ pháp</b>
        <button type="button" class="btn btn-sm btn-paper" onclick={() => pick(null)}><Icon name="plus" size={15} /> Thêm</button>
      </div>
      <ul style="display: flex; flex-direction: column; gap: 6px">
        {#each points as p, i (p.id)}
          <li>
            <button type="button" class="les" class:on={draft?.id === p.id} onclick={() => pick(p)} style="width: 100%">
              <b class="hz" lang="zh">{i + 1}. {p.title}</b>
              {#if p.subtitle}<span class="muted" style="font-size: 13px">{p.subtitle}</span>{/if}
            </button>
          </li>
        {/each}
      </ul>
      {#if points.length === 0}
        <p class="muted" style="font-size: 13.5px">Chưa có điểm nào. Bấm “Thêm”.</p>
      {/if}
    </aside>

    {#if draft}
      <section class="block form">
        <h2 class="h2">{draft.id === null ? 'Điểm ngữ pháp mới' : 'Sửa điểm ngữ pháp'}</h2>

        <div class="grid2">
          <div class="field">
            <label class="lbl" for="g-title">Tên cấu trúc</label>
            <input id="g-title" class="inp hz" lang="zh" bind:value={draft.title} oninput={touch} placeholder="V + 过" />
          </div>
          <div class="field">
            <label class="lbl" for="g-sub">Mô tả ngắn</label>
            <input id="g-sub" class="inp" bind:value={draft.subtitle} oninput={touch} placeholder="Đã từng làm gì" />
          </div>
        </div>

        <div class="field">
          <label class="lbl" for="g-ex">Giải thích</label>
          <textarea id="g-ex" class="inp" rows="3" bind:value={draft.explain} oninput={touch}></textarea>
        </div>

        <!-- Khối công thức -->
        <div class="field">
          <span class="lbl">Khối công thức</span>
          {#each draft.blocks as b, k (k)}
            <div class="row">
              <input class="inp" style="flex: 1 1 110px" bind:value={b.label} oninput={touch} placeholder="Chủ ngữ" />
              <input class="inp hz" lang="zh" style="flex: 1 1 90px" bind:value={b.hz} oninput={touch} placeholder="我" />
              <div class="swatch">
                {#each COLORS as col (col)}
                  <button type="button" aria-label="Màu {col}" style="background: {col}; outline: {b.color === col ? '2px solid var(--text)' : 'none'}"
                          onclick={() => { b.color = col; touch(); }}></button>
                {/each}
              </div>
              <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá khối"
                      onclick={() => { draft!.blocks = draft!.blocks.filter((_, j) => j !== k); touch(); }}><Icon name="trash" size={15} /></button>
            </div>
          {/each}
          {#if draft.blocks.length < 6}
            <button type="button" class="btn btn-sm btn-paper" style="align-self: flex-start"
                    onclick={() => { draft!.blocks = [...draft!.blocks, { label: '', hz: '', color: COLORS[draft!.blocks.length % COLORS.length] }]; touch(); }}>
              <Icon name="plus" size={15} /> Thêm khối
            </button>
          {/if}
        </div>

        <!-- Ví dụ -->
        <div class="field">
          <span class="lbl">Ví dụ</span>
          {#each draft.examples as ex, k (k)}
            <div class="exbox">
              <div class="row">
                <input class="inp" style="flex: 1 1 120px" bind:value={ex.kind} oninput={touch} placeholder="Khẳng định" />
                <input class="inp num" style="flex: 1 1 150px" bind:value={ex.formula} oninput={touch} placeholder="S + V + 过 + O" />
                <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá ví dụ"
                        onclick={() => { draft!.examples = draft!.examples.filter((_, j) => j !== k); touch(); }}><Icon name="trash" size={15} /></button>
              </div>
              <input class="inp hz" lang="zh" bind:value={ex.zh} oninput={touch} placeholder="我吃过羊肉。" />
              <input class="inp hz" lang="zh" bind:value={ex.highlight} oninput={touch} placeholder="Từ cần tô sáng, ví dụ 过" />
              <input class="inp" bind:value={ex.pinyin} oninput={touch} placeholder="Wǒ chīguo yángròu." />
              <input class="inp" bind:value={ex.vi} oninput={touch} placeholder="Tôi từng ăn thịt cừu." />
            </div>
          {/each}
          {#if draft.examples.length < 6}
            <button type="button" class="btn btn-sm btn-paper" style="align-self: flex-start"
                    onclick={() => { draft!.examples = [...draft!.examples, { kind: '', formula: '', zh: '', highlight: null, pinyin: '', vi: '' }]; touch(); }}>
              <Icon name="plus" size={15} /> Thêm ví dụ
            </button>
          {/if}
        </div>

        <div class="field">
          <label class="lbl" for="g-note">Lưu ý / lỗi hay gặp</label>
          <textarea id="g-note" class="inp" rows="2" bind:value={draft.note} oninput={touch}></textarea>
        </div>

        <!-- Bài luyện tập -->
        <div class="field">
          <span class="lbl">Bài luyện tập xếp câu (không bắt buộc)</span>
          <div class="row">
            <input class="inp hz" lang="zh" style="flex: 1" bind:value={draft.practice.answer} oninput={touch} placeholder="我没吃过羊肉" />
            <button type="button" class="btn btn-sm" onclick={autoTiles}>Tự tách ô</button>
          </div>
          <input class="inp" bind:value={draft.practice.vi} oninput={touch} placeholder="Tôi chưa từng ăn thịt cừu." />
          <div class="tiles">
            {#each draft.practice.tiles as t, i (i)}
              <span style="display: inline-flex; align-items: center">
                <button type="button" class="wt" onclick={() => splitTile(i)} title="Bấm để tách đôi">{t}</button>
                {#if i < draft.practice.tiles.length - 1}
                  <button type="button" class="merge" onclick={() => mergeTile(i)} aria-label="Gộp hai ô">+</button>
                {/if}
              </span>
            {/each}
          </div>
        </div>

        <div class="form-foot">
          {#if draft.id !== null}
            <button type="button" class="btn btn-danger btn-sm" onclick={remove}><Icon name="trash" size={15} /> Xoá điểm</button>
          {/if}
          <span style="flex: 1"></span>
          <button type="button" class="btn btn-son btn-sm" disabled={saving} onclick={save}>{saving ? 'Đang lưu…' : 'Lưu'}</button>
        </div>
      </section>
    {/if}
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
    flex: 1 1 280px;
    max-width: 340px;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  .form {
    flex: 999 1 520px;
    min-width: 0;
    padding: 20px 22px;
    display: flex;
    flex-direction: column;
    gap: 14px;
  }
  .field {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .grid2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(200px, 100%), 1fr));
    gap: 12px;
  }
  .row {
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
  }
  .exbox {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 12px;
    background: var(--surface-2);
    border: 1px solid var(--line);
    border-radius: var(--r);
  }
  .swatch {
    display: flex;
    gap: 4px;
  }
  .swatch button {
    width: 22px;
    height: 22px;
    border: 1px solid var(--line-3);
    border-radius: 6px;
    cursor: pointer;
  }
  .tiles {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    align-items: center;
  }
  .merge {
    width: 18px;
    height: 24px;
    border: 0;
    background: none;
    color: var(--text-3);
    cursor: pointer;
    font-weight: 700;
  }
  .form-foot {
    display: flex;
    align-items: center;
    gap: 10px;
    padding-top: 8px;
    border-top: 1px solid var(--line);
  }
</style>
