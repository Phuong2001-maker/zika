<script lang="ts">
  import { onMount } from 'svelte';
  import Select from '$lib/components/Select.svelte';
  import { ContentVisibilityState } from './content-visibility.svelte';
  let { curriculum = 0, level = 1 }: { curriculum?: number; level?: number } = $props();
  const state = new ContentVisibilityState();
  onMount(() => { void state.load(curriculum, level); });
</script>

<section class="cv-wrap">
  <div class="cv-heading"><div><h1>Ẩn/hiện nội dung</h1><p class="muted">Áp dụng đồng thời cho Lật thẻ, Bài tập, Kiểm tra, Luyện viết, Hội thoại và Ngữ pháp.</p></div></div>
  {#if state.error}<p role="alert" class="cv-error">{state.error} {#if state.curricula.length === 0}<button type="button" class="btn btn-sm" onclick={() => state.load(curriculum, level)}>Tải lại</button>{/if}</p>{/if}
  {#if state.loading}<p class="muted">Đang tải giáo trình…</p>
  {:else if state.current}
    <div class="block cv-root">
      <div class="cv-selector"><span class="lbl">Giáo trình</span><Select bind:value={state.selected} options={state.curricula.map((c) => ({ v: c.id, label: c.name }))} ariaLabel="Chọn giáo trình quản lý ẩn hiện" /></div>
      <label class="sw"><input type="checkbox" checked={state.current.visible} disabled={state.busy} onchange={(e) => state.toggle('curriculum', e.currentTarget.checked)} /><span></span>{state.current.visible ? 'Hiện giáo trình' : 'Ẩn giáo trình'}</label>
    </div>
    <p class="cv-note muted">Tắt mục cha sẽ ẩn toàn bộ bên dưới. Lựa chọn của từng mục con được giữ lại khi bật cha trở lại. Không xoá tiến độ học.</p>
    {#each state.current.levels as lv (lv.id)}
      <section class="block cv-level">
        <div class="cv-level-head">
          <button type="button" class="cv-expand" aria-expanded={state.expanded === lv.id} onclick={() => (state.expanded = state.expanded === lv.id ? 0 : lv.id)}><span class="cv-number">{lv.id}</span><span><b>{state.current.label} {lv.id}</b><small>{lv.lessons.length} bài học</small></span><span class="cv-plus">{state.expanded === lv.id ? '−' : '+'}</span></button>
          <label class="sw"><input type="checkbox" checked={lv.visible} disabled={state.busy || !state.current.visible} onchange={(e) => state.toggle('level', e.currentTarget.checked, lv.id)} /><span></span>{!state.current.visible ? 'Ẩn theo giáo trình' : lv.visible ? 'Đang hiện' : 'Đang ẩn'}</label>
        </div>
        {#if state.expanded === lv.id}
          <div class="cv-lessons">
            {#each lv.lessons as lesson (lesson.id)}
              <div class="cv-lesson"><div><b>Bài {lesson.no} · {lesson.title}</b><small class="muted">Cả 6 phần học</small></div><label class="sw"><input type="checkbox" checked={lesson.visible} disabled={state.busy || !state.current.visible || !lv.visible} onchange={(e) => state.toggle('lesson', e.currentTarget.checked, lv.id, lesson.id)} /><span></span>{!state.current.visible || !lv.visible ? 'Ẩn theo mục cha' : lesson.visible ? 'Đang hiện' : 'Đang ẩn'}</label></div>
            {:else}<p class="muted cv-empty">Chưa có bài học.</p>{/each}
          </div>
        {/if}
      </section>
    {/each}
  {:else}<p class="muted">Chưa có giáo trình.</p>{/if}
</section>

<style>
  .cv-wrap { display: grid; gap: 14px; }
  .cv-heading h1 { font-size: 25px; margin-bottom: 7px; }
  .cv-heading p, .cv-note { font-size: 13px; line-height: 1.7; }
  .cv-root { display: flex; align-items: center; justify-content: space-between; gap: 24px; padding: 20px; }
  .cv-selector { width: min(100%, 480px); }
  .cv-note { margin: 0 4px 4px; }
  .cv-level { padding: 0; overflow: hidden; }
  .cv-level-head { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 16px 20px; }
  .cv-expand { display: flex; align-items: center; gap: 14px; flex: 1; text-align: left; border: 0; background: transparent; color: inherit; cursor: pointer; padding: 0; min-height: 44px; }
  .cv-number { display: grid; place-items: center; width: 44px; height: 44px; border-radius: 12px; background: #f0f8f3; color: #24744b; font-size: 23px; font-weight: 800; }
  .cv-expand small, .cv-lesson small { display: block; margin-top: 4px; font-size: 12px; font-weight: 400; }
  .cv-plus { margin-left: auto; font-size: 22px; color: #687386; }
  .cv-lessons { border-top: 1px solid #e5e8ef; padding: 0 20px; }
  .cv-lesson { display: flex; justify-content: space-between; align-items: center; gap: 16px; padding: 16px 0; border-bottom: 1px solid #edf0f4; font-size: 13px; }
  .cv-lesson:last-child { border-bottom: 0; }
  .cv-empty { padding: 16px 0; }
  .cv-error { background: #fff1f2; color: #b82535; padding: 12px 16px; border-radius: 12px; }
  @media (max-width: 600px) { .cv-root { align-items: stretch; flex-direction: column; gap: 16px; } .cv-level-head { padding: 14px; gap: 12px; flex-wrap: wrap; } .cv-expand { flex-basis: 100%; } .cv-lessons { padding: 0 14px; } .cv-lesson { align-items: start; flex-direction: column; gap: 12px; } }
</style>
