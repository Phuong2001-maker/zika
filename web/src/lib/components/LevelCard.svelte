<script lang="ts">
  import type { LevelInfo } from '$lib/api/types';
  import { num } from '$lib/utils/format';
  import Bar from './Bar.svelte';
  import Icon from './Icon.svelte';

  // Thẻ cấp HSK (05 §7.3). Dùng cho Bàn học (Lật thẻ) và khuôn Chọn cấp 5 phần.
  // - lt (không truyền `past`): dòng số liệu "{bài} bài · {từ} từ" + trạng thái bên phải.
  // - 5 phần (truyền `past`): dòng số liệu "{bài} bài · {done}/{total} đã {past}".
  let {
    level,
    done = 0,
    current = false,
    slug = 'flashcards',
    past,
    mobile = false,
  }: {
    level: LevelInfo;
    done?: number;
    current?: boolean;
    slug?: string;
    past?: string;
    mobile?: boolean;
  } = $props();

  const n = $derived(level.id);
  const total = $derived(level.lessonCount);
  const href = $derived(`/learn/${slug}/hsk/${n}`);
  const state = $derived(done === 0 ? 'chưa học' : done >= total ? 'đã học xong' : `${done}/${total} bài đã học`);
  const meta = $derived(
    past ? `${num(total)} bài · ${done}/${total} đã ${past}` : `${num(total)} bài · ${num(level.wordCount)} từ`,
  );
</script>

{#if !level.visible}
  <!-- Cấp sắp có: nét đứt, không bấm được. -->
  <div class="lv soon">
    <span class="lv-top">
      <span class="lv-hsk"><span class="disp lv-lbl">HSK</span><span class="bignum" style="font-size: {mobile ? 40 : 58}px">{n}</span></span>
      <span class="tag">Sắp có</span>
    </span>
    {#if !mobile}<span class="hz" lang="zh" style="font-size: 24px; line-height: 1.1">{level.sampleText}</span>{/if}
    <span class="num" style="font-size: 14px"><b>{num(level.targetWords)} từ</b> · admin đang soạn</span>
  </div>
{:else}
  <a {href} class="lv lv{n}" style="text-decoration: none">
    <span class="lv-top">
      <span class="lv-hsk"><span class="disp lv-lbl">HSK</span><span class="bignum" style="font-size: {mobile ? 40 : 58}px">{n}</span></span>
      {#if current}<span class="tag tag-bad">Đang học</span>{/if}
    </span>
    {#if !mobile}<span class="hz" lang="zh" style="font-size: 24px; line-height: 1.1">{level.sampleText}</span>{/if}
    <span class="lv-meta">
      <b class="num">{meta}</b>
      {#if !past && !mobile}<span class="num" style="color: var(--text-2)">{state}</span>{/if}
    </span>
    <Bar value={done} {total} trackStyle="background: rgba(255,255,255,.7)" />
    {#if !mobile}
      <span class="lv-more">Xem các bài <Icon name="right" size={16} /></span>
    {/if}
  </a>
{/if}

<style>
  .lv-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
  }
  .lv-hsk {
    display: flex;
    align-items: baseline;
    gap: 4px;
  }
  .lv-lbl {
    font-size: 16px;
    font-weight: 800;
  }
  .lv-meta {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 8px;
    font-size: 14px;
  }
  .lv-more {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13.5px;
    font-weight: 700;
  }
</style>
