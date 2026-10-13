<script lang="ts">
  import { page } from '$app/state';
  import { toast } from '$lib/stores/toast.svelte';
  import { ApiError } from '$lib/api/client';
  import Icon from '$lib/components/Icon.svelte';
  import Select from '$lib/components/Select.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import LessonHeader from '$lib/admin/LessonHeader.svelte';
  import { adminApi, type DialogueLine, type DialogueQuestion, type DialogueSpeaker } from '$lib/admin/api';

  // Soạn hội thoại — /admin/lessons/:id/dialogue (08 §11). Lưu cả khối một lần.
  const COLORS = ['#DCEBFF', '#CFF5E1', '#FFF0B3', '#E8E0FF', '#FFE0DC', '#FFE9C7'];

  const lessonId = $derived(Number(page.params.id));

  let loaded = $state(false);
  let title = $state('');
  let scene = $state('');
  let speakers = $state<DialogueSpeaker[]>([]);
  let lines = $state<DialogueLine[]>([]);
  let quiz = $state<DialogueQuestion[]>([]);
  let dirty = $state(false);
  let saving = $state(false);

  function touch(): void {
    dirty = true;
  }

  async function load(): Promise<void> {
    try {
      const d = await adminApi.dialogue(lessonId);
      if (d) {
        title = d.title;
        scene = d.scene;
        speakers = d.speakers.map((s) => ({ ...s, key: s.key ?? String(s.id) }));
        lines = d.lines.map((l) => ({ ...l, speakerKey: l.speakerKey ?? String((l as { speakerId?: number }).speakerId ?? '') }));
        quiz = d.questions ?? [];
      } else {
        title = '';
        scene = '';
        speakers = [
          { key: 'A', name: '', avatarChar: '', color: COLORS[0], roleVi: '' },
          { key: 'B', name: '', avatarChar: '', color: COLORS[1], roleVi: '' },
        ];
        lines = [];
        quiz = [];
      }
      dirty = false;
    } catch {
      toast.show('Không tải được hội thoại.');
    } finally {
      loaded = true;
    }
  }
  // Đổi bài mà vẫn ở cùng một phần thì SvelteKit giữ nguyên trang (chỉ đổi tham số),
  // nên phải nạp lại theo lessonId chứ onMount không chạy lại.
  $effect(() => {
    void lessonId;
    void load();
  });

  function addSpeaker(): void {
    if (speakers.length >= 6) return;
    const key = String.fromCharCode(65 + speakers.length);
    speakers = [...speakers, { key, name: '', avatarChar: '', color: COLORS[speakers.length % COLORS.length], roleVi: '' }];
    touch();
  }

  function removeSpeaker(key: string): void {
    speakers = speakers.filter((s) => s.key !== key);
    lines = lines.filter((l) => l.speakerKey !== key);
    touch();
  }

  function addLine(): void {
    lines = [...lines, { speakerKey: speakers[0]?.key ?? 'A', zh: '', pinyin: '', vi: '', highlight: null }];
    touch();
  }

  function moveLine(i: number, d: -1 | 1): void {
    const j = i + d;
    if (j < 0 || j >= lines.length) return;
    const next = [...lines];
    [next[i], next[j]] = [next[j], next[i]];
    lines = next;
    touch();
  }

  function addQuiz(): void {
    if (quiz.length >= 5) return;
    quiz = [...quiz, { prompt: '', options: ['', '', ''], answer: 0 }];
    touch();
  }

  async function save(): Promise<void> {
    saving = true;
    try {
      await adminApi.saveDialogue(lessonId, {
        title: title.trim(),
        scene: scene.trim(),
        speakers: speakers.filter((s) => s.name.trim() !== ''),
        lines: lines.filter((l) => l.zh.trim() !== ''),
        questions: quiz.filter((q) => q.prompt.trim() !== ''),
      });
      dirty = false;
      toast.ok('Đã lưu hội thoại.');
      await load();
    } catch (e) {
      toast.show(e instanceof ApiError ? (Object.values(e.fields)[0] ?? e.message) : 'Không lưu được.');
    } finally {
      saving = false;
    }
  }
</script>

<svelte:head><title>Soạn hội thoại · Quản trị · Zìkǎ</title></svelte:head>

<LessonHeader {lessonId} part="ht" {dirty} {saving} onsave={save} />

{#if !loaded}
  <Skeleton h="320px" r={16} />
{:else}
  <section class="block card">
    <h2 class="h2">Thông tin chung</h2>
    <div class="field">
      <label class="lbl" for="d-title">Tên hội thoại</label>
      <input id="d-title" class="inp" bind:value={title} oninput={touch} placeholder="Ở nhà hàng" />
    </div>
    <div class="field">
      <label class="lbl" for="d-scene">Tình huống</label>
      <textarea id="d-scene" class="inp" rows="2" bind:value={scene} oninput={touch}
                placeholder="小王 và 小李 vào nhà hàng gọi món…"></textarea>
    </div>
  </section>

  <!-- Nhân vật -->
  <section class="block card">
    <div style="display: flex; align-items: center; justify-content: space-between">
      <h2 class="h2">Nhân vật</h2>
      <button type="button" class="btn btn-sm btn-paper" onclick={addSpeaker}><Icon name="plus" size={15} /> Thêm nhân vật</button>
    </div>
    {#each speakers as s (s.key)}
      <div class="row">
        <span class="who hz" lang="zh" style="background: {s.color}">{s.avatarChar || s.name.slice(-1) || '?'}</span>
        <input class="inp hz" lang="zh" style="flex: 1 1 120px" bind:value={s.name} oninput={touch} placeholder="小王" />
        <input class="inp hz" lang="zh" style="flex: 0 0 70px" bind:value={s.avatarChar} oninput={touch} placeholder="王" maxlength="2" />
        <input class="inp" style="flex: 1 1 120px" bind:value={s.roleVi} oninput={touch} placeholder="khách" />
        <div class="swatch">
          {#each COLORS as col (col)}
            <button type="button" aria-label="Màu {col}" style="background: {col}; outline: {s.color === col ? '2px solid var(--text)' : 'none'}"
                    onclick={() => { s.color = col; touch(); }}></button>
          {/each}
        </div>
        <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá nhân vật" onclick={() => removeSpeaker(s.key)}><Icon name="trash" size={16} /></button>
      </div>
    {/each}
  </section>

  <!-- Lời thoại -->
  <section class="block card">
    <div style="display: flex; align-items: center; justify-content: space-between">
      <h2 class="h2">Lời thoại · {lines.length} câu</h2>
      <button type="button" class="btn btn-sm btn-paper" onclick={addLine}><Icon name="plus" size={15} /> Thêm câu</button>
    </div>

    {#each lines as l, i (i)}
      <div class="line">
        <span class="lno">{i + 1}</span>
        <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px">
          <div style="display: flex; gap: 8px; flex-wrap: wrap">
            <div style="flex: 0 0 150px">
              <Select
                bind:value={l.speakerKey}
                ariaLabel="Người nói"
                onchange={touch}
                options={speakers.map((s) => ({ v: s.key, label: s.name || s.key }))}
              />
            </div>
            <input class="inp hz" lang="zh" style="flex: 1 1 200px" bind:value={l.zh} oninput={touch} placeholder="你好，你们想吃什么？" />
          </div>
          <input class="inp" bind:value={l.pinyin} oninput={touch} placeholder="Nǐ hǎo, nǐmen xiǎng chī shénme?" />
          <input class="inp" bind:value={l.vi} oninput={touch} placeholder="Xin chào, các bạn muốn ăn gì?" />
          <input class="inp hz" lang="zh" bind:value={l.highlight} oninput={touch} placeholder="Từ mới cần tô sáng (để trống nếu không có)" />
        </div>
        <div style="display: flex; flex-direction: column; gap: 2px">
          <button type="button" class="btn btn-bare btn-sm" aria-label="Lên" onclick={() => moveLine(i, -1)}><Icon name="chev" size={14} /></button>
          <button type="button" class="btn btn-bare btn-sm" aria-label="Xuống" onclick={() => moveLine(i, 1)}><Icon name="down" size={14} /></button>
          <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá câu" onclick={() => { lines = lines.filter((_, k) => k !== i); touch(); }}>
            <Icon name="trash" size={15} />
          </button>
        </div>
      </div>
    {/each}

    {#if lines.length === 0}
      <p class="muted" style="font-size: 13.5px">Chưa có câu nào. Bấm “Thêm câu”.</p>
    {/if}
  </section>

  <!-- Câu hỏi hiểu bài -->
  <section class="block card">
    <div style="display: flex; align-items: center; justify-content: space-between">
      <h2 class="h2">Câu hỏi hiểu bài</h2>
      <button type="button" class="btn btn-sm btn-paper" onclick={addQuiz}><Icon name="plus" size={15} /> Thêm câu hỏi</button>
    </div>

    {#each quiz as q, qi (qi)}
      <div class="line">
        <span class="lno">{qi + 1}</span>
        <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px">
          <input class="inp hz" lang="zh" bind:value={q.prompt} oninput={touch} placeholder="小王想吃什么？" />
          {#each q.options as _, oi (oi)}
            <div style="display: flex; align-items: center; gap: 8px">
              <input type="radio" name="dq{qi}" checked={q.answer === oi} onchange={() => { q.answer = oi; touch(); }} style="width: 18px; height: 18px; accent-color: var(--son)" />
              <input class="inp hz" lang="zh" value={q.options[oi]}
                     oninput={(e) => { q.options[oi] = e.currentTarget.value; touch(); }} placeholder="Phương án {String.fromCharCode(65 + oi)}" />
            </div>
          {/each}
        </div>
        <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá câu hỏi" onclick={() => { quiz = quiz.filter((_, k) => k !== qi); touch(); }}>
          <Icon name="trash" size={15} />
        </button>
      </div>
    {/each}

    {#if quiz.length === 0}
      <p class="muted" style="font-size: 13.5px">Chưa có câu hỏi nào (không bắt buộc).</p>
    {/if}
  </section>

  <div style="display: flex; justify-content: flex-end; gap: 10px">
    <button type="button" class="btn btn-son btn-lg" disabled={saving} onclick={save}>{saving ? 'Đang lưu…' : 'Lưu hội thoại'}</button>
  </div>
{/if}

<style>
  .card {
    padding: 18px 20px;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .field {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }
  .line {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 12px;
    background: var(--surface-2);
    border: 1px solid var(--line);
    border-radius: var(--r);
  }
  .lno {
    width: 24px;
    height: 24px;
    flex: none;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: var(--surface);
    border: 1px solid var(--line-3);
    font-size: 12px;
    font-weight: 700;
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
</style>
