<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { goto } from '$app/navigation';
  import { ApiError } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { toast } from '$lib/stores/toast.svelte';
  import { num } from '$lib/utils/format';
  import Icon from '$lib/components/Icon.svelte';
  import Bar from '$lib/components/Bar.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import { adminApi, type AdminLevel, type AdminWord, type DictLookup, type WordExample } from '$lib/admin/api';
  import { toneParts } from '$lib/utils/pinyin';

  // Quản trị · Từ vựng HSK (08 §7).
  const POS: [string, string][] = [
    ['n', 'Danh từ'], ['v', 'Động từ'], ['adj', 'Tính từ'], ['adv', 'Phó từ'],
    ['mw', 'Lượng từ'], ['phrase', 'Cụm từ'], ['num', 'Số từ'], ['pron', 'Đại từ'],
    ['prep', 'Giới từ'], ['conj', 'Liên từ'], ['part', 'Trợ từ'], ['other', 'Khác'],
  ];

  let levels = $state<AdminLevel[] | null>(null);
  let lessons = $state<{ id: number; no: number; title: string }[]>([]);
  let words = $state<AdminWord[]>([]);
  let total = $state(0);
  let failed = $state(false);
  let search = $state('');

  // Biểu mẫu
  let editId = $state<number | null>(null);
  let lessonId = $state<number | null>(null);
  let hanzi = $state('');
  let pinyin = $state('');
  let hanViet = $state('');
  let meaning = $state('');
  let pos = $state('n');
  let examples = $state<WordExample[]>([]);
  let errors = $state<Record<string, string>>({});
  let saving = $state(false);

  let dict = $state<DictLookup | null>(null);
  let dictTimer: ReturnType<typeof setTimeout> | null = null;
  let side = $state<'front' | 'back'>('front');

  const levelId = $derived(Number(page.url.searchParams.get('level')) || 1);
  const level = $derived(levels?.find((l) => l.id === levelId) ?? null);

  async function loadLevels(): Promise<void> {
    try {
      levels = await adminApi.levels();
    } catch {
      failed = true;
    }
  }

  async function loadWords(): Promise<void> {
    try {
      const res = await adminApi.words({ level: levelId, q: search, per: 200 });
      words = res.data;
      total = Number(res.meta?.total ?? res.data.length);
    } catch {
      failed = true;
    }
  }

  async function loadLessons(): Promise<void> {
    try {
      const res = await adminApi.lessons(levelId);
      lessons = res.lessons.map((l) => ({ id: l.id, no: l.no, title: l.title }));
    } catch {
      lessons = [];
    }
  }

  onMount(loadLevels);

  $effect(() => {
    void levelId;
    void loadWords();
    void loadLessons();
    // Bài mặc định lấy từ URL (khi bấm từ màn Bài học HSK sang).
    const l = Number(page.url.searchParams.get('lesson'));
    if (l > 0) lessonId = l;
  });

  // Mở sẵn từ cần sửa khi URL có ?edit=
  $effect(() => {
    const id = Number(page.url.searchParams.get('edit'));
    if (id > 0 && editId !== id) void editWord(id);
  });

  function pickLevel(id: number): void {
    void goto(`/admin/words?level=${id}`, { keepFocus: true, noScroll: true });
  }

  /** Gõ chữ Hán xong thì tra từ điển để điền nhanh. */
  function onHanzi(): void {
    dict = null;
    if (dictTimer) clearTimeout(dictTimer);
    const v = hanzi.trim();
    if (v === '') return;
    dictTimer = setTimeout(async () => {
      try {
        dict = await adminApi.dict(v, levelId);
      } catch {
        dict = null;
      }
    }, 400);
  }

  function autofill(): void {
    const e = dict?.entries[0];
    if (e) {
      if (pinyin.trim() === '') pinyin = e.pinyin;
      if (hanViet.trim() === '') hanViet = e.hanViet;
      if (meaning.trim() === '') meaning = e.meaning;
      if (e.pos) pos = e.pos;
    } else if (dict?.hanVietByChar && hanViet.trim() === '') {
      hanViet = dict.hanVietByChar;
    }
  }

  function clearForm(): void {
    editId = null;
    hanzi = '';
    pinyin = '';
    hanViet = '';
    meaning = '';
    pos = 'n';
    examples = [];
    errors = {};
    dict = null;
  }

  async function editWord(id: number): Promise<void> {
    try {
      const w = await adminApi.word(id);
      editId = w.id;
      lessonId = w.lessonId;
      hanzi = w.hanzi;
      pinyin = w.pinyin;
      hanViet = w.hanViet;
      meaning = w.meaning;
      pos = w.pos;
      examples = (w.examples ?? []).map((e) => ({ ...e }));
      errors = {};
      dict = null;
    } catch {
      toast.show('Không mở được từ này.');
    }
  }

  function addExample(): void {
    if (examples.length >= 3) return;
    examples = [...examples, { zh: '', pinyin: '', vi: '', highlight: null }];
  }

  async function save(andNext: boolean): Promise<void> {
    saving = true;
    errors = {};
    const body = {
      levelId,
      lessonId,
      hanzi: hanzi.trim(),
      pinyin: pinyin.trim(),
      hanViet: hanViet.trim(),
      meaning: meaning.trim(),
      pos,
      examples: examples.filter((e) => e.zh.trim() !== ''),
    };
    try {
      if (editId === null) await adminApi.createWord(body);
      else await adminApi.updateWord(editId, body);
      toast.ok('Đã lưu.');
      await loadWords();
      await loadLevels();
      if (andNext) clearForm();
      else if (editId === null) clearForm();
    } catch (e) {
      if (e instanceof ApiError) {
        errors = e.fields;
        toast.show(Object.values(e.fields)[0] ?? e.message);
      } else {
        toast.show('Không lưu được.');
      }
    } finally {
      saving = false;
    }
  }

  async function remove(id: number, hz: string): Promise<void> {
    if (!confirm(`Xoá từ ${hz}?`)) return;
    try {
      await adminApi.deleteWord(id);
      if (editId === id) clearForm();
      await loadWords();
      await loadLevels();
      toast.ok('Đã xoá.');
    } catch {
      toast.show('Không xoá được.');
    }
  }

  async function toggleVisible(on: boolean): Promise<void> {
    if (!level) return;
    try {
      await adminApi.patchLevel(level.id, { visible: on });
      levels = (levels ?? []).map((l) => (l.id === level.id ? { ...l, visible: on } : l));
      toast.ok('Đã lưu.');
    } catch {
      toast.show('Không lưu được.');
    }
  }
</script>

<svelte:head><title>Từ vựng HSK · Quản trị · Zìkǎ</title></svelte:head>

{#snippet levelList()}
  <div class="lv-list">
    {#each levels ?? [] as l (l.id)}
      <button type="button" class="lv lv{l.id}" class:on={l.id === levelId} onclick={() => pickLevel(l.id)}>
        <span style="display: flex; justify-content: space-between; align-items: center">
          <span style="display: flex; align-items: baseline; gap: 4px">
            <b style="font-size: 13px">HSK</b><span class="bignum" style="font-size: 26px">{l.id}</span>
          </span>
          <span class="tag {l.visible ? 'tag-ok' : ''}">{l.visible ? 'Đang hiện' : 'Đang ẩn'}</span>
        </span>
        <span class="num" style="font-size: 13px"><b>{num(l.enteredWords)}</b> / {num(l.targetWords)} từ</span>
        <Bar value={l.enteredWords} total={l.targetWords} />
      </button>
    {/each}
  </div>
{/snippet}

{#snippet form()}
  <section class="block" style="padding: 20px 22px; display: flex; flex-direction: column; gap: 14px">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap">
      <h2 class="h2">
        {#if editId === null}Thêm từ vào HSK {levelId}{:else}Sửa từ <span class="hz" lang="zh">{hanzi}</span>{/if}
      </h2>
      {#if editId !== null}<button type="button" class="btn btn-sm" onclick={clearForm}>Huỷ sửa</button>{/if}
    </div>

    <div class="field">
      <label class="lbl" for="w-lesson">Thuộc bài</label>
      <div class="sel">
        <select id="w-lesson" class="inp" bind:value={lessonId}>
          <option value={null}>— Chưa gắn bài —</option>
          {#each lessons as l (l.id)}<option value={l.id}>Bài {l.no} · {l.title}</option>{/each}
        </select>
        <Icon name="down" size={18} />
      </div>
      <p class="hint">Từ sẽ hiện trong phần Lật thẻ của bài này, và dùng để tự tạo câu bài tập, chữ luyện viết.</p>
    </div>

    <div class="field">
      <label class="lbl" for="w-hz">Chữ Hán *</label>
      <input id="w-hz" class="inp hz" lang="zh" style="height: 70px; font-size: 38px; letter-spacing: .1em" bind:value={hanzi} oninput={onHanzi} />
      {#if errors.hanzi}<p class="err">{errors.hanzi}</p>{/if}

      {#if dict}
        {#if dict.existing}
          <div class="dictbox" style="background: var(--son-soft); border-color: var(--son-line)">
            <span style="flex: 1">Từ này đã có trong HSK {levelId}{dict.existing.lessonNo ? ` · Bài ${dict.existing.lessonNo}` : ''}.</span>
            <button type="button" class="btn btn-sm" onclick={() => editWord(dict!.existing!.wordId)}>Mở để sửa</button>
          </div>
        {:else if dict.found}
          <div class="dictbox" style="background: var(--bo-soft); border-color: #FDE68A">
            <span style="flex: 1">
              <b>Có trong từ điển:</b>
              {dict.entries[0].pinyin} · {dict.entries[0].hanViet} · {dict.entries[0].meaning}
            </span>
            <button type="button" class="btn btn-muc btn-sm" onclick={autofill}>Điền tự động</button>
          </div>
        {:else}
          <div class="dictbox" style="background: var(--surface-2)">
            <span style="flex: 1">Chưa có trong từ điển. Hán Việt theo chữ: <b>{dict.hanVietByChar}</b></span>
            <button type="button" class="btn btn-sm" onclick={autofill}>Điền tự động</button>
          </div>
        {/if}
      {/if}
    </div>

    <div class="grid3">
      <div class="field">
        <label class="lbl" for="w-py">Pinyin</label>
        <input id="w-py" class="inp" bind:value={pinyin} placeholder="jīdàn" />
      </div>
      <div class="field">
        <label class="lbl" for="w-hv">Âm Hán Việt</label>
        <input id="w-hv" class="inp" bind:value={hanViet} placeholder="Kê đản" />
      </div>
      <div class="field">
        <label class="lbl" for="w-pos">Loại từ</label>
        <div class="sel">
          <select id="w-pos" class="inp" bind:value={pos}>
            {#each POS as [v, label] (v)}<option value={v}>{label}</option>{/each}
          </select>
          <Icon name="down" size={18} />
        </div>
      </div>
    </div>

    <div class="field">
      <label class="lbl" for="w-vi">Nghĩa tiếng Việt *</label>
      <input id="w-vi" class="inp" bind:value={meaning} placeholder="trứng gà" />
      {#if errors.meaning}<p class="err">{errors.meaning}</p>{/if}
    </div>

    <!-- Câu ví dụ -->
    <div class="field">
      <span class="lbl">Câu ví dụ · {examples.length} câu</span>
      {#each examples as ex, k (k)}
        <div class="ex">
          <span class="exno">{k + 1}</span>
          <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px">
            <input class="inp hz" lang="zh" style="font-size: 19px" bind:value={ex.zh} placeholder="我早上吃了两个鸡蛋。" />
            <input class="inp" bind:value={ex.pinyin} placeholder="Wǒ zǎoshang chīle liǎng ge jīdàn." />
            <input class="inp" bind:value={ex.vi} placeholder="Sáng nay tôi ăn hai quả trứng." />
          </div>
          <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá câu {k + 1}" onclick={() => (examples = examples.filter((_, j) => j !== k))}>
            <Icon name="trash" size={16} />
          </button>
        </div>
      {/each}
      {#if errors.examples}<p class="err">{errors.examples}</p>{/if}
      {#if examples.length < 3}
        <button type="button" class="btn btn-sm btn-paper" style="align-self: flex-start" onclick={addExample}>
          <Icon name="plus" size={15} /> Thêm câu ví dụ
        </button>
      {/if}
    </div>

    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap; border-top: 1px solid var(--line); padding-top: 12px">
      <button type="button" class="btn-text" onclick={clearForm}>Xoá trắng</button>
      <span style="flex: 1"></span>
      <button type="button" class="btn btn-sm" disabled={saving} onclick={() => save(true)}>Lưu &amp; thêm từ khác</button>
      <button type="button" class="btn btn-son btn-sm" disabled={saving} onclick={() => save(false)}>{saving ? 'Đang lưu…' : 'Lưu từ'}</button>
    </div>
  </section>
{/snippet}

{#snippet preview()}
  <section class="block" style="padding: 16px; display: flex; flex-direction: column; gap: 10px">
    <div style="display: flex; align-items: center; justify-content: space-between">
      <p class="kicker">Người học sẽ thấy</p>
      <div class="seg">
        <button type="button" class:on={side === 'front'} onclick={() => (side = 'front')}>Trước</button>
        <button type="button" class:on={side === 'back'} onclick={() => (side = 'back')}>Sau</button>
      </div>
    </div>
    <div class="pv" style="background: {side === 'front' ? 'var(--tim)' : 'var(--surface)'}">
      {#if side === 'front'}
        <span class="kicker">Nghĩa tiếng Việt</span>
        <b style="font-size: 26px">{meaning || '…'}</b>
      {:else}
        <span class="num" style="font-size: 17px; font-weight: 600">
          {#each toneParts(pinyin) as p (p.text + p.tone)}<span class="t{p.tone}">{p.text}</span>{/each}
        </span>
        <span class="tian hz" lang="zh" style="width: 72px; height: 72px; font-size: 44px">{hanzi.slice(0, 1) || '字'}</span>
        <span class="muted" style="font-size: 12px; font-weight: 700; text-transform: uppercase">{hanViet}</span>
      {/if}
    </div>
  </section>
{/snippet}

{#snippet wordList()}
  <section class="paper" style="padding: 16px; display: flex; flex-direction: column; gap: 10px">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px">
      <b>Từ trong HSK {levelId}</b>
      <span class="muted num" style="font-size: 13px">{num(total)} từ</span>
    </div>
    <span class="ico-in">
      <Icon name="search" size={16} />
      <input class="inp" bind:value={search} oninput={() => loadWords()} placeholder="Tìm chữ Hán, pinyin, nghĩa…" />
    </span>

    {#if words.length === 0}
      <div class="empty"><b>Chưa có từ nào</b><span class="muted" style="font-size: 13.5px">Thêm từ đầu tiên ở bên trái.</span></div>
    {:else}
      <ul style="display: flex; flex-direction: column; gap: 4px; max-height: 420px; overflow-y: auto">
        {#each words as w (w.id)}
          <li class="wrow" class:on={editId === w.id}>
            <span class="hz" lang="zh" style="font-size: 18px; min-width: 56px">{w.hanzi}</span>
            <span style="flex: 1; min-width: 0">
              <span class="muted" style="font-size: 12.5px; display: block">{w.pinyin}{w.lessonNo ? ` · Bài ${w.lessonNo}` : ''}</span>
              <span style="font-size: 13.5px">{w.meaning}</span>
            </span>
            <button type="button" class="btn btn-bare btn-sm" aria-label="Sửa {w.hanzi}" onclick={() => editWord(w.id)}><Icon name="edit" size={15} /></button>
            <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá {w.hanzi}" onclick={() => remove(w.id, w.hanzi)}><Icon name="trash" size={15} /></button>
          </li>
        {/each}
      </ul>
    {/if}
  </section>
{/snippet}

<div class="head">
  <div>
    <h1>Từ vựng HSK</h1>
    <p class="muted" style="font-size: 14px">
      6 cấp · {num((levels ?? []).reduce((s, l) => s + l.enteredWords, 0))} từ đã nhập ·
      {(levels ?? []).filter((l) => l.visible).length} cấp đang hiện cho người học
    </p>
  </div>
</div>

{#if failed}
  <EmptyState title="Không tải được dữ liệu.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={loadLevels}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if levels === null}
  <Skeleton h="300px" r={16} />
{:else}
  <div class="cols">
    {#if !media.isAdminMobile}
      <aside style="flex: 0 0 260px">{@render levelList()}</aside>
    {:else}
      <div style="display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px">
        {#each levels as l (l.id)}
          <button type="button" class="btn btn-sm {l.id === levelId ? 'btn-muc' : 'btn-paper'}" onclick={() => pickLevel(l.id)}>
            HSK {l.id} {#if !l.visible}<Icon name="eye" size={13} />{/if}
          </button>
        {/each}
      </div>
    {/if}

    <div style="flex: 999 1 420px; min-width: 0; display: flex; flex-direction: column; gap: 16px">
      {#if level}
        <section class="block" style="padding: 16px 20px; display: flex; align-items: center; gap: 16px; flex-wrap: wrap">
          <span class="ib lv{level.id}" style="width: 56px; height: 56px; border-radius: 14px"><span class="bignum" style="font-size: 26px">{level.id}</span></span>
          <div style="flex: 1 1 200px; min-width: 0">
            <h2 class="h2">HSK {level.id}</h2>
            <p class="muted num" style="font-size: 13.5px">Đã nhập {num(level.enteredWords)} / {num(level.targetWords)} từ</p>
            <Bar value={level.enteredWords} total={level.targetWords} />
          </div>
          <label class="sw">
            <input type="checkbox" checked={level.visible} onchange={(e) => toggleVisible(e.currentTarget.checked)} />
            <span></span>
            Hiện cho người học
          </label>
        </section>
      {/if}

      <div class="cols2">
        <div style="flex: 999 1 380px; min-width: 0">{@render form()}</div>
        <div style="flex: 1 1 280px; min-width: 0; display: flex; flex-direction: column; gap: 14px">
          {@render preview()}
          {@render wordList()}
        </div>
      </div>
    </div>
  </div>
{/if}

<style>
  .head {
    margin-bottom: 16px;
  }
  .head h1 {
    font-size: 28px;
  }
  .cols {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    align-items: flex-start;
  }
  .cols2 {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
    align-items: flex-start;
  }
  .lv-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  .field {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .grid3 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(150px, 100%), 1fr));
    gap: 12px;
  }
  .dictbox {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 10px 12px;
    border: 1px solid var(--line);
    border-radius: var(--r);
    font-size: 13.5px;
    margin-top: 6px;
  }
  .ex {
    display: flex;
    gap: 10px;
    align-items: flex-start;
    padding: 12px;
    background: var(--surface-2);
    border: 1px solid var(--line);
    border-radius: var(--r);
    margin-bottom: 8px;
  }
  .exno {
    width: 22px;
    height: 22px;
    flex: none;
    display: grid;
    place-items: center;
    border-radius: 50%;
    background: var(--son-soft);
    color: var(--son-text);
    font-size: 12px;
    font-weight: 700;
  }
  .pv {
    min-height: 150px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border: 1px solid var(--line);
    border-radius: var(--r-lg);
    box-shadow: var(--sh-md);
    padding: 16px;
    text-align: center;
  }
  .wrow {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 8px;
    border-radius: var(--r-sm);
  }
  .wrow:hover {
    background: var(--surface-2);
  }
  .wrow.on {
    background: var(--son-soft);
  }
</style>
