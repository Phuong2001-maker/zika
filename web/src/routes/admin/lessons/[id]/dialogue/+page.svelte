<script lang="ts">
  import { page } from '$app/state';
  import { toast } from '$lib/stores/toast.svelte';
  import { ApiError } from '$lib/api/client';
  import Icon from '$lib/components/Icon.svelte';
  import Select from '$lib/components/Select.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import LessonHeader from '$lib/admin/LessonHeader.svelte';
  import ImportModal from '$lib/admin/ImportModal.svelte';
  import { adminApi, type DialogueBlock } from '$lib/admin/api';
  import { dialogueImport, type ImportMode } from '$lib/admin/import';

  // Soạn hội thoại — /admin/lessons/:id/dialogue (08 §11).
  // Một bài có nhiều bài khoá (课文一, 课文二…), mỗi bài khoá có nhân vật và lời thoại riêng.
  // Cả danh sách lưu một lần bằng PUT, nên bộ nhập Excel chỉ đổ vào biểu mẫu chứ không tự ghi.
  const COLORS = ['#DCEBFF', '#CFF5E1', '#FFF0B3', '#E8E0FF', '#FFE0DC', '#FFE9C7'];

  const lessonId = $derived(Number(page.params.id));

  let loaded = $state(false);
  let khoi = $state<DialogueBlock[]>([]);
  let dirty = $state(false);
  let saving = $state(false);
  let nhapOpen = $state(false);
  /** Tăng sau mỗi lần nhập để LessonHeader đọc lại số lượng trên hàng tabs. */
  let reload = $state(0);

  const soCau = $derived(khoi.reduce((s, k) => s + k.lines.length, 0));

  function touch(): void {
    dirty = true;
  }

  function khoiMoi(no: number): DialogueBlock {
    return {
      no,
      title: '',
      scene: '',
      speakers: [
        { key: `k${no}-1`, name: '', avatarChar: '', color: COLORS[0], roleVi: '' },
        { key: `k${no}-2`, name: '', avatarChar: '', color: COLORS[1], roleVi: '' },
      ],
      lines: [],
    };
  }

  async function load(): Promise<void> {
    try {
      const res = await adminApi.dialogue(lessonId);
      khoi = res.dialogues.length > 0 ? res.dialogues : [khoiMoi(1)];
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

  /** Nhập Excel: thay hết hoặc nối thêm bài khoá vào cuối. */
  async function nhapExcel(items: DialogueBlock[], mode: ImportMode) {
    if (items.length === 0) return { created: 0, skipped: [] };
    const sach = khoi.filter((k) => k.lines.length > 0);
    khoi = mode === 'replace' ? items : [...sach, ...items];
    khoi = khoi.slice(0, 10).map((k, i) => ({ ...k, no: i + 1 }));
    touch();
    return { created: items.reduce((s, k) => s + k.lines.length, 0), skipped: [] };
  }

  function themKhoi(): void {
    if (khoi.length >= 10) return;
    khoi = [...khoi, khoiMoi(khoi.length + 1)];
    touch();
  }

  function xoaKhoi(i: number): void {
    khoi = khoi.filter((_, k) => k !== i).map((k, n) => ({ ...k, no: n + 1 }));
    if (khoi.length === 0) khoi = [khoiMoi(1)];
    touch();
  }

  function chuyenKhoi(i: number, d: -1 | 1): void {
    const j = i + d;
    if (j < 0 || j >= khoi.length) return;
    const next = [...khoi];
    [next[i], next[j]] = [next[j], next[i]];
    khoi = next.map((k, n) => ({ ...k, no: n + 1 }));
    touch();
  }

  function themNhanVat(k: DialogueBlock): void {
    if (k.speakers.length >= 6) return;
    k.speakers = [
      ...k.speakers,
      { key: `k${k.no}-${Date.now()}`, name: '', avatarChar: '', color: COLORS[k.speakers.length % COLORS.length], roleVi: '' },
    ];
    touch();
  }

  function xoaNhanVat(k: DialogueBlock, key: string): void {
    k.speakers = k.speakers.filter((s) => s.key !== key);
    k.lines = k.lines.filter((l) => l.speakerKey !== key);
    touch();
  }

  function themCau(k: DialogueBlock): void {
    k.lines = [...k.lines, { speakerKey: k.speakers[0]?.key ?? '', zh: '', pinyin: '', vi: '', highlight: null }];
    touch();
  }

  function chuyenCau(k: DialogueBlock, i: number, d: -1 | 1): void {
    const j = i + d;
    if (j < 0 || j >= k.lines.length) return;
    const next = [...k.lines];
    [next[i], next[j]] = [next[j], next[i]];
    k.lines = next;
    touch();
  }

  async function save(): Promise<void> {
    saving = true;
    try {
      const sach = khoi
        .map((k) => ({
          ...k,
          title: k.title.trim(),
          scene: k.scene.trim(),
          speakers: k.speakers.filter((s) => s.name.trim() !== ''),
          lines: k.lines.filter((l) => l.zh.trim() !== ''),
        }))
        .filter((k) => k.lines.length > 0)
        .map((k, i) => ({ ...k, no: i + 1 }));

      await adminApi.saveDialogue(lessonId, { dialogues: sach });
      dirty = false;
      toast.ok(sach.length > 1 ? `Đã lưu ${sach.length} bài khoá.` : 'Đã lưu hội thoại.');
      await load();
      reload++;
    } catch (e) {
      toast.show(e instanceof ApiError ? (Object.values(e.fields)[0] ?? e.message) : 'Không lưu được.');
    } finally {
      saving = false;
    }
  }
</script>

<svelte:head><title>Soạn hội thoại · Quản trị · Zìkǎ</title></svelte:head>

<LessonHeader {lessonId} part="ht" {dirty} {saving} {reload} onsave={save} />

{#if nhapOpen}
  <ImportModal
    spec={dialogueImport}
    replaceNote="Thay toàn bộ bài khoá đang có trên biểu mẫu."
    onclose={() => (nhapOpen = false)}
    onrun={nhapExcel}
  />
{/if}

{#if !loaded}
  <Skeleton h="320px" r={16} />
{:else}
  <div class="dau">
    <p class="muted" style="font-size: 13.5px">
      {khoi.length} bài khoá · {soCau} câu thoại. Giáo trình chia mỗi bài thành 课文一, 课文二… thì ở đây tách thành từng bài khoá.
    </p>
    <span style="display: flex; gap: 8px">
      <button type="button" class="btn btn-sm btn-paper" onclick={() => (nhapOpen = true)}>
        <Icon name="upload" size={14} /> Nhập Excel
      </button>
      <button type="button" class="btn btn-sm btn-paper" disabled={khoi.length >= 10} onclick={themKhoi}>
        <Icon name="plus" size={15} /> Thêm bài khoá
      </button>
    </span>
  </div>

  {#each khoi as k, ki (ki)}
    <section class="block card">
      <div class="row" style="justify-content: space-between">
        <h2 class="h2">Bài khoá {ki + 1}</h2>
        <span style="display: flex; gap: 2px">
          <button type="button" class="btn btn-bare btn-sm" aria-label="Lên" disabled={ki === 0} onclick={() => chuyenKhoi(ki, -1)}><Icon name="chev" size={15} /></button>
          <button type="button" class="btn btn-bare btn-sm" aria-label="Xuống" disabled={ki === khoi.length - 1} onclick={() => chuyenKhoi(ki, 1)}><Icon name="down" size={15} /></button>
          <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá bài khoá" onclick={() => xoaKhoi(ki)}><Icon name="trash" size={16} /></button>
        </span>
      </div>

      <div class="field">
        <label class="lbl" for="d-title-{ki}">Tên bài khoá</label>
        <input id="d-title-{ki}" class="inp" bind:value={k.title} oninput={touch} placeholder="Ở nhà hàng" />
      </div>
      <div class="field">
        <label class="lbl" for="d-scene-{ki}">Tình huống</label>
        <textarea id="d-scene-{ki}" class="inp" rows="2" bind:value={k.scene} oninput={touch}
                  placeholder="小王 và 小李 vào nhà hàng gọi món…"></textarea>
      </div>

      <h3 class="lbl" style="margin-top: 4px">Nhân vật</h3>
      {#each k.speakers as s (s.key)}
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
          <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá nhân vật" onclick={() => xoaNhanVat(k, s.key)}><Icon name="trash" size={16} /></button>
        </div>
      {/each}
      <button type="button" class="btn btn-sm btn-paper" style="align-self: flex-start" disabled={k.speakers.length >= 6} onclick={() => themNhanVat(k)}>
        <Icon name="plus" size={15} /> Thêm nhân vật
      </button>

      <h3 class="lbl" style="margin-top: 8px">Lời thoại · {k.lines.length} câu</h3>
      {#each k.lines as l, i (i)}
        <div class="line">
          <span class="lno">{i + 1}</span>
          <div style="flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px">
            <div style="display: flex; gap: 8px; flex-wrap: wrap">
              <div style="flex: 0 0 150px">
                <Select
                  bind:value={l.speakerKey}
                  ariaLabel="Người nói"
                  onchange={touch}
                  options={k.speakers.map((s) => ({ v: s.key, label: s.name || 'Chưa đặt tên' }))}
                />
              </div>
              <input class="inp hz" lang="zh" style="flex: 1 1 200px" bind:value={l.zh} oninput={touch} placeholder="你好，你们想吃什么？" />
            </div>
            <input class="inp" bind:value={l.pinyin} oninput={touch} placeholder="Nǐ hǎo, nǐmen xiǎng chī shénme?" />
            <input class="inp" bind:value={l.vi} oninput={touch} placeholder="Xin chào, các bạn muốn ăn gì?" />
            <input class="inp hz" lang="zh" bind:value={l.highlight} oninput={touch} placeholder="Từ mới cần tô sáng (để trống nếu không có)" />
          </div>
          <div style="display: flex; flex-direction: column; gap: 2px">
            <button type="button" class="btn btn-bare btn-sm" aria-label="Lên" onclick={() => chuyenCau(k, i, -1)}><Icon name="chev" size={14} /></button>
            <button type="button" class="btn btn-bare btn-sm" aria-label="Xuống" onclick={() => chuyenCau(k, i, 1)}><Icon name="down" size={14} /></button>
            <button type="button" class="btn btn-bare btn-sm" aria-label="Xoá câu" onclick={() => { k.lines = k.lines.filter((_, x) => x !== i); touch(); }}>
              <Icon name="trash" size={15} />
            </button>
          </div>
        </div>
      {/each}

      {#if k.lines.length === 0}
        <p class="muted" style="font-size: 13.5px">Chưa có câu nào.</p>
      {/if}
      <button type="button" class="btn btn-sm btn-paper" style="align-self: flex-start" onclick={() => themCau(k)}>
        <Icon name="plus" size={15} /> Thêm câu
      </button>
    </section>
  {/each}

  <div style="display: flex; justify-content: flex-end; gap: 10px">
    <button type="button" class="btn btn-son btn-lg" disabled={saving} onclick={save}>{saving ? 'Đang lưu…' : 'Lưu hội thoại'}</button>
  </div>
{/if}

<style>
  .dau {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
  }
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
