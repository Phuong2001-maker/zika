<script lang="ts">
  import Bar from './Bar.svelte';
  import Icon from './Icon.svelte';

  // Thẻ bài (05 §7.4). Dùng cho Chọn bài (Lật thẻ) và khuôn Chọn bài 5 phần.
  let {
    no,
    title,
    sample = '',
    sub,
    done = false,
    pct = 0,
    on = false,
    soon = false,
    mobile = false,
    tag = '',
    tagClass = 'tag',
    onpick,
  }: {
    no: number;
    title: string;
    sample?: string;
    sub: string;
    done?: boolean;
    pct?: number;
    on?: boolean;
    soon?: boolean;
    mobile?: boolean;
    tag?: string;
    tagClass?: string;
    onpick?: () => void;
  } = $props();
</script>

{#if mobile}
  <button type="button" class="les les-row" class:on class:done class:soon disabled={soon} aria-pressed={on} onclick={onpick}>
    <span class="les-no bignum">{no}</span>
    <span class="les-mid">
      <span class="les-title">{title}</span>
      {#if sample}<span class="hz les-sample" lang="zh">{sample}</span>{/if}
      {#if !soon}<Bar {pct} h={7} />{/if}
    </span>
    <span class="les-end">
      {#if done}
        <span class="ok-dot" title="Đã học xong"><Icon name="check" size={13} /></span>
      {:else if tag}
        <span class={tagClass}>{tag}</span>
      {:else}
        <span class="muted num" style="font-size: 12px">{sub}</span>
      {/if}
    </span>
  </button>
{:else}
  <button type="button" class="les" class:on class:done class:soon disabled={soon} aria-pressed={on} onclick={onpick}>
    <span class="les-head">
      <span class="disp" style="font-size: 13px; font-weight: 800; color: var(--text-3)">Bài {no}</span>
      {#if done}
        <span class="ok-dot" title="Đã học xong"><Icon name="check" size={13} /></span>
      {:else if tag}
        <span class={tagClass} style="height: 22px; font-size: 11px; padding: 0 7px">{tag}</span>
      {/if}
    </span>
    <span class="les-title">{title}</span>
    {#if sample}<span class="hz" lang="zh" style="font-size: 17px; color: var(--text-2)">{sample}</span>{/if}
    <span class="les-foot">
      <span class="num" style="white-space: nowrap">{sub}</span>
      {#if !soon}<span class="grow"><Bar {pct} h={7} /></span>{/if}
    </span>
  </button>
{/if}

<style>
  .les-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 6px;
    min-height: 22px;
  }
  .les-title {
    font-size: 15px;
    font-weight: 700;
    line-height: 1.25;
  }
  .les-foot {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    font-weight: 600;
  }
  /* Bản điện thoại: hàng ngang */
  .les-row {
    flex-direction: row;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
  }
  .les-row .les-no {
    width: 40px;
    height: 40px;
    flex: none;
    display: grid;
    place-items: center;
    border: 1px solid var(--line);
    border-radius: 12px;
    font-size: 20px;
    background: var(--surface);
  }
  .les-row.done .les-no {
    background: var(--ngoc-soft);
  }
  .les-row .les-mid {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .les-row .les-sample {
    font-size: 14px;
    color: var(--text-2);
  }
  .les-row .les-end {
    flex: none;
    display: flex;
    align-items: center;
  }
</style>
