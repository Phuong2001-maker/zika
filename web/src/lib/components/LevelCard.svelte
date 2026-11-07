<script lang="ts">
  import { cap } from '$lib/stores/cap.svelte';
  import type { LevelInfo } from '$lib/api/types';
  import { num } from '$lib/utils/format';
  import Bar from './Bar.svelte';
  import { SO_HAN, taiChuBut } from '$lib/learn/hoa-tiet';

  taiChuBut();

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

{#if !level.visible || total === 0}
  <!-- Cấp sắp có, hoặc giáo trình của học viên chưa có bài ở cấp này: nét đứt, không bấm được. -->
  <div class="lv soon">
    <span class="lv-top">
      <span class="lv-hsk"><span class="disp lv-lbl">{cap.nhan}</span><span class="bignum" style="font-size: {mobile ? 40 : 58}px">{n}</span></span>
      <span class="tag">Sắp có</span>
    </span>
    {#if !mobile && level.sampleText}<span class="hz" lang="zh" style="font-size: 24px; line-height: 1.1">{level.sampleText}</span>{/if}
    <span class="num" style="font-size: 14px"><b>Đang soạn</b></span>
  </div>
{:else}
  <a {href} class="lv lv{n} lv-nen" style="text-decoration: none; --mau: var(--hsk{n})">
    <span class="lv-hoa" lang="zh" aria-hidden="true" style="font-size: {mobile ? 96 : 150}px; right: {mobile ? -4 : 6}px; bottom: {mobile ? -18 : -30}px">{SO_HAN[n]}</span>
    <span class="lv-top">
      <span class="lv-hsk"><span class="disp lv-lbl">{cap.nhan}</span><span class="bignum" style="font-size: {mobile ? 40 : 58}px">{n}</span></span>
      {#if current}<span class="tag tag-bad">Đang học</span>{/if}
    </span>
    {#if !mobile && level.sampleText}<span class="hz" lang="zh" style="font-size: 24px; line-height: 1.1">{level.sampleText}</span>{/if}
    <span class="lv-meta">
      <b class="num">{meta}</b>
      {#if !past && !mobile}<span class="num" style="color: var(--text-2)">{state}</span>{/if}
    </span>
    <Bar value={done} {total} trackStyle="background: rgba(255,255,255,.7)" />
    {#if !mobile}
      <span class="lv-more">Xem các bài </span>
    {/if}
  </a>
{/if}

<style>
  .lv-top {
    display: flex;
    /* Máy 320px không đủ chỗ cho số cấp và nhãn trên một hàng, nhãn phải xuống dòng thay vì bị cắt. */
    flex-wrap: wrap;
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
