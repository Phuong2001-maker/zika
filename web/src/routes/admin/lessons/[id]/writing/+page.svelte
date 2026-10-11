<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { toast } from '$lib/stores/toast.svelte';
  import { ApiError } from '$lib/api/client';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';
  import LessonHeader from '$lib/admin/LessonHeader.svelte';
  import { adminApi, type AdminChar, type WritingConfig } from '$lib/admin/api';

  // Soạn luyện viết — /admin/lessons/:id/writing (08 §10).
  const lessonId = $derived(Number(page.params.id));

  let chars = $state<AdminChar[] | null>(null);
  let config = $state<WritingConfig | null>(null);
  let failed = $state(false);
  let busy = $state(false);
  let addOpen = $state(false);
  let addHanzi = $state('');
  let cfgOpen = $state(false);

  async function load(): Promise<void> {
    failed = false;
    try {
      const res = await adminApi.writing(lessonId);
      chars = res.chars;
      config = res.config;
    } catch {
      failed = true;
    }
  }
  onMount(load);

  async function fromWords(): Promise<void> {
    busy = true;
    try {
      const res = await adminApi.writingFromWords(lessonId);
      chars = res.chars;
      toast.ok(`Đã thêm ${res.added} chữ${res.strokeFetched > 0 ? `, tải nét cho ${res.strokeFetched} chữ` : ''}.`);
    } catch {
      toast.show('Không lấy được chữ từ từ vựng.');
    } finally {
      busy = false;
    }
  }

  async function addChar(): Promise<void> {
    const hz = addHanzi.trim();
    if (hz === '') return;
    busy = true;
    try {
      const res = await adminApi.addChar(lessonId, hz);
      chars = res.chars;
      addHanzi = '';
      addOpen = false;
      toast.ok('Đã thêm chữ.');
    } catch (e) {
      toast.show(e instanceof ApiError ? (e.fields.hanzi ?? e.message) : 'Không thêm được chữ.');
    } finally {
      busy = false;
    }
  }

  async function toggle(c: AdminChar): Promise<void> {
    try {
      await adminApi.patchLessonChar(c.lessonCharId, { visible: !c.visible });
      chars = (chars ?? []).map((x) => (x.lessonCharId === c.lessonCharId ? { ...x, visible: !x.visible } : x));
    } catch {
      toast.show('Không đổi được trạng thái.');
    }
  }

  async function remove(c: AdminChar): Promise<void> {
    if (!confirm(`Bỏ chữ ${c.hanzi} khỏi bài?`)) return;
    try {
      await adminApi.deleteLessonChar(c.lessonCharId);
      chars = (chars ?? []).filter((x) => x.lessonCharId !== c.lessonCharId);
      toast.ok('Đã bỏ chữ.');
    } catch {
      toast.show('Không bỏ được chữ.');
    }
  }

  async function refetch(c: AdminChar): Promise<void> {
    try {
      const res = await adminApi.fetchStroke(c.charId);
      toast.show(res.ok ? 'Đã tải được dữ liệu nét.' : 'Nguồn không có chữ này, bạn tải tệp nét lên nhé.');
      await load();
    } catch {
      toast.show('Không tải được dữ liệu nét.');
    }
  }

  async function saveConfig(): Promise<void> {
    if (!config) return;
    try {
      config = await adminApi.saveWritingConfig(lessonId, config);
      cfgOpen = false;
      toast.ok('Đã lưu cài đặt.');
    } catch {
      toast.show('Không lưu được cài đặt.');
    }
  }

  const STROKE_LABEL: Record<string, string> = { ok: 'Có nét', custom: 'Nét tự tải', missing: 'Thiếu nét' };
</script>

<svelte:head><title>Soạn luyện viết · Quản trị · Zìkǎ</title></svelte:head>

<LessonHeader {lessonId} part="lv" />

{#if failed}
  <EmptyState title="Không tải được danh sách chữ.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={load}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if chars === null}
  <Skeleton h="300px" r={16} />
{:else}
  <section class="paper bar">
    <b style="font-size: 14px">{chars.length} chữ trong bài</b>
    {#if config}
      <span class="muted" style="font-size: 13.5px">
        Bắt đầu ở “{{ demo: 'Xem mẫu', trace: 'Tô theo', free: 'Tự viết' }[config.startMode]}” ·
        ô {config.grid === 'tian' ? '田' : '米'} · viết đúng {config.passCount} lần là xong chữ
      </span>
    {/if}
    <span style="flex: 1"></span>
    <button type="button" class="btn btn-sm" onclick={() => (cfgOpen = true)}><Icon name="sliders" size={15} /> Chỉnh</button>
    <button type="button" class="btn btn-sm btn-paper" disabled={busy} onclick={fromWords}>
      <Icon name="download" size={15} /> Lấy chữ từ từ vựng
    </button>
    <button type="button" class="btn btn-son btn-sm" onclick={() => (addOpen = true)}><Icon name="plus" size={15} /> Thêm chữ</button>
  </section>

  {#if chars.length === 0}
    <EmptyState title="Bài chưa có chữ nào." desc="Bấm “Lấy chữ từ từ vựng” để lấy hết chữ Hán trong các từ của bài." />
  {:else}
    <div class="grid">
      {#each chars as c (c.lessonCharId)}
        <article class="paper card" class:off={!c.visible}>
          <div style="display: flex; align-items: center; gap: 12px">
            <span class="tian hz" lang="zh" style="width: 62px; height: 62px; font-size: 38px">{c.hanzi}</span>
            <div style="flex: 1; min-width: 0">
              <b class="t{c.tone}" style="font-size: 17px">{c.pinyin || '—'}</b>
              <p class="muted" style="font-size: 12.5px">{c.hanViet}</p>
              <p style="font-size: 13.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{c.meaning}</p>
            </div>
          </div>

          <p class="muted num" style="font-size: 12.5px">
            {c.strokes || '?'} nét{c.structure ? ` · ${c.structure}` : ''}
          </p>

          <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap">
            <span class="tag {c.strokeStatus === 'missing' ? 'tag-bad' : 'tag-ok'}">{STROKE_LABEL[c.strokeStatus]}</span>
            {#if c.sourceWord}<span class="tag">trong <span class="hz" lang="zh">{c.sourceWord.hanzi}</span></span>{/if}
            <span style="flex: 1"></span>
            {#if c.strokeStatus === 'missing'}
              <button type="button" class="btn btn-bare btn-sm" aria-label="Tải lại nét" onclick={() => refetch(c)}><Icon name="repeat" size={15} /></button>
            {/if}
            <button type="button" class="btn btn-bare btn-sm" aria-label={c.visible ? 'Ẩn chữ' : 'Hiện chữ'} onclick={() => toggle(c)}>
              <Icon name="eye" size={15} />
            </button>
            <button type="button" class="btn btn-bare btn-sm" aria-label="Bỏ chữ" onclick={() => remove(c)}><Icon name="trash" size={15} /></button>
          </div>
        </article>
      {/each}
    </div>
  {/if}
{/if}

{#if addOpen}
  <Modal title="Thêm chữ" onclose={() => (addOpen = false)}>
    <label class="lbl" for="w-hz">Chữ Hán (một chữ)</label>
    <input id="w-hz" class="inp hz" lang="zh" style="font-size: 32px; height: 64px" bind:value={addHanzi}
           onkeydown={(e) => e.key === 'Enter' && addChar()} />
    <p class="hint">Chữ chưa có trong kho sẽ được tạo mới và tự tải dữ liệu nét.</p>
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (addOpen = false)}>Huỷ</button>
      <button type="button" class="btn btn-son btn-sm" disabled={busy} onclick={addChar}>Thêm</button>
    {/snippet}
  </Modal>
{/if}

{#if cfgOpen && config}
  <Modal title="Cài đặt luyện viết" onclose={() => (cfgOpen = false)}>
    <div class="field">
      <span class="lbl">Bắt đầu ở chế độ</span>
      <div class="seg">
        <button type="button" class:on={config.startMode === 'demo'} onclick={() => (config!.startMode = 'demo')}>Xem mẫu</button>
        <button type="button" class:on={config.startMode === 'trace'} onclick={() => (config!.startMode = 'trace')}>Tô theo</button>
        <button type="button" class:on={config.startMode === 'free'} onclick={() => (config!.startMode = 'free')}>Tự viết</button>
      </div>
    </div>
    <div class="field">
      <span class="lbl">Kiểu ô</span>
      <div class="seg">
        <button type="button" class:on={config.grid === 'tian'} onclick={() => (config!.grid = 'tian')}>Ô 田</button>
        <button type="button" class:on={config.grid === 'mi'} onclick={() => (config!.grid = 'mi')}>Ô 米</button>
      </div>
    </div>
    <div class="field">
      <label class="lbl" for="w-pass">Viết đúng mấy lần thì xong chữ</label>
      <input id="w-pass" class="inp" type="number" min="1" max="10" bind:value={config.passCount} />
    </div>
    <label class="sw"><input type="checkbox" bind:checked={config.showAnimation} /><span></span> Cho chạy hoạt hình thứ tự nét</label>
    <label class="sw"><input type="checkbox" bind:checked={config.highlightRadical} /><span></span> Tô sáng bộ thủ</label>
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (cfgOpen = false)}>Huỷ</button>
      <button type="button" class="btn btn-son btn-sm" onclick={saveConfig}>Lưu</button>
    {/snippet}
  </Modal>
{/if}

<style>
  .bar {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    padding: 12px 16px;
  }
  .grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(min(280px, 100%), 1fr));
    gap: 12px;
  }
  .card {
    padding: 14px;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }
  .card.off {
    opacity: 0.55;
  }
  .field {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
</style>
