<script lang="ts">
  import { onMount } from 'svelte';
  import { goto } from '$app/navigation';
  import { media } from '$lib/stores/media.svelte';
  import { toast } from '$lib/stores/toast.svelte';
  import { ago } from '$lib/utils/format';
  import Icon from '$lib/components/Icon.svelte';
  import { adminApi, PART_COL, PART_SLUG, type AdminLesson } from '$lib/admin/api';
  import { rememberLesson } from '$lib/admin/menu';
  import type { Part } from '$lib/api/types';

  // Đầu 5 trang soạn theo bài (08 §1.3): đường dẫn, khối thông tin bài, tabs 6 phần.
  let {
    lessonId,
    part,
    dirty = false,
    saving = false,
    onsave,
  }: {
    lessonId: number;
    part: Part;
    /** Có thay đổi chưa lưu — bật nút “Lưu thay đổi” và cảnh báo khi rời trang. */
    dirty?: boolean;
    saving?: boolean;
    onsave?: () => void;
  } = $props();

  let lesson = $state<(AdminLesson & { levelId: number }) | null>(null);
  let busy = $state(false);

  async function load(): Promise<void> {
    try {
      lesson = await adminApi.lesson(lessonId);
      rememberLesson(lessonId);
    } catch {
      toast.show('Không tải được thông tin bài.');
    }
  }
  onMount(load);

  /** Công tắc Hiện cho người học. Bài chưa có từ vựng thì hỏi lại trước khi hiện. */
  async function toggleStatus(on: boolean): Promise<void> {
    if (!lesson) return;
    if (on && lesson.counts.words === 0) {
      const ok = confirm('Bài chưa có từ vựng, người học sẽ thấy phần Lật thẻ “Sắp có”. Vẫn hiện?');
      if (!ok) return;
    }
    busy = true;
    try {
      await adminApi.patchLesson(lessonId, { status: on ? 'published' : 'draft' });
      lesson = { ...lesson, status: on ? 'published' : 'draft' };
      toast.ok('Đã lưu.');
    } catch {
      toast.show('Không lưu được, thử lại nhé.');
    } finally {
      busy = false;
    }
  }

  async function rename(): Promise<void> {
    if (!lesson) return;
    const name = prompt('Tên bài:', lesson.title);
    if (name === null || name.trim() === '' || name.trim() === lesson.title) return;
    try {
      await adminApi.patchLesson(lessonId, { title: name.trim() });
      lesson = { ...lesson, title: name.trim() };
      toast.ok('Đã lưu.');
    } catch {
      toast.show('Không đổi được tên bài.');
    }
  }

  /** Đổi tab: nhắc nếu còn thay đổi chưa lưu. */
  function go(p: Part): void {
    if (p === part) return;
    if (dirty && !confirm('Bạn có thay đổi chưa lưu. Rời trang và bỏ thay đổi?')) return;
    const slug = PART_SLUG[p];
    void goto(slug === 'words' ? `/admin/words?level=${lesson?.levelId}&lesson=${lessonId}` : `/admin/lessons/${lessonId}/${slug}`);
  }

  const partLabel = $derived(PART_COL.find((c) => c.part === part)?.label ?? '');
  /** Mở màn người học tương ứng kèm ?preview=1 để xem cả bài Nháp. */
  const previewHref = $derived(
    lesson ? `/learn/${{ lt: 'flashcards', bt: 'exercises', kt: 'tests', lv: 'writing', ht: 'dialogues', np: 'grammar' }[part]}/hsk/${lesson.levelId}/lesson/${lesson.no}?preview=1` : '#',
  );
</script>

{#if lesson}
  {#if media.isAdminMobile}
    <!-- ============ Điện thoại ============ -->
    <header class="mhead">
      <a href="/admin/lessons?level={lesson.levelId}" class="btn btn-bare" aria-label="Về Bài học HSK"><Icon name="left" size={20} /></a>
      <span style="flex: 1; min-width: 0">
        <span class="muted" style="font-size: 11.5px; font-weight: 700; display: block">HSK {lesson.levelId} · Bài {lesson.no}</span>
        <b class="disp" style="font-size: 17px; font-weight: 800; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{lesson.title}</b>
      </span>
      {#if onsave}
        <button type="button" class="btn btn-son btn-sm" disabled={!dirty || saving} onclick={onsave}>Lưu</button>
      {/if}
    </header>
    <div class="mtabs" style="margin: 0 -14px 14px">
      {#each PART_COL as c (c.key)}
        <a
          href={'#'}
          class:on={c.part === part}
          onclick={(e) => { e.preventDefault(); go(c.part); }}
        >{c.label} <span class="muted num">{lesson.counts[c.key]}</span></a>
      {/each}
    </div>
  {:else}
    <!-- ============ Máy tính ============ -->
    <nav class="crumb" aria-label="Đường dẫn">
      <a href="/admin/lessons">Bài học HSK</a> /
      <a href="/admin/lessons?level={lesson.levelId}">HSK {lesson.levelId}</a> /
      <b>Bài {lesson.no} · {lesson.title}</b>
    </nav>

    <section class="block head">
      <span class="no lv{lesson.levelId}">
        <span style="font-size: 11px; font-weight: 800">BÀI</span>
        <span class="bignum" style="font-size: 30px">{lesson.no}</span>
      </span>
      <div style="flex: 1 1 260px; min-width: 0">
        <p class="muted" style="font-size: 13px; font-weight: 700">
          HSK {lesson.levelId} · sửa lần cuối {ago(lesson.updatedAt)}{lesson.updatedByName ? ` · ${lesson.updatedByName}` : ''}
        </p>
        <h1 style="font-size: 28px; display: flex; align-items: center; gap: 8px">
          {lesson.title}
          <button type="button" class="btn btn-bare btn-sm" aria-label="Sửa tên bài" onclick={rename}><Icon name="edit" size={16} /></button>
        </h1>
      </div>
      <label class="sw">
        <input type="checkbox" checked={lesson.status === 'published'} disabled={busy} onchange={(e) => toggleStatus(e.currentTarget.checked)} />
        <span></span>
        {lesson.status === 'published' ? 'Đang hiện' : 'Nháp · đang ẩn'}
      </label>
      <a href={previewHref} target="_blank" rel="noopener" class="btn btn-sm btn-paper"><Icon name="eye" size={15} /> Xem như người học</a>
      {#if onsave}
        <button type="button" class="btn btn-son btn-sm" disabled={!dirty || saving} onclick={onsave}>
          {saving ? 'Đang lưu…' : 'Lưu thay đổi'}
        </button>
      {/if}
    </section>

    <nav class="tabs" style="width: 100%; overflow-x: auto" aria-label="6 phần của bài">
      {#each PART_COL as c (c.key)}
        <button type="button" class="tab" class:on={c.part === part} onclick={() => go(c.part)}>
          {c.label} <span class="muted num" style="font-weight: 500">{lesson.counts[c.key]} {c.unit}</span>
        </button>
      {/each}
    </nav>
  {/if}
{:else}
  <div class="skel" style="height: 96px"></div>
{/if}

<style>
  .head {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 14px 18px;
    padding: 18px 22px;
  }
  .no {
    width: 64px;
    height: 64px;
    flex: none;
    display: grid;
    place-items: center;
    border: 1px solid var(--line);
    border-radius: var(--r-md);
    line-height: 1.05;
    text-align: center;
  }
  .mhead {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 58px;
    margin: -16px -14px 0;
    padding: 0 10px;
    background: var(--surface);
    border-bottom: 1px solid var(--line);
  }
</style>
