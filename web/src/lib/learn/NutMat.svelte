<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';

  // Nút mắt ẩn/hiện pinyin và nghĩa của câu ví dụ. Mặc định ẩn để người học tự đọc chữ Hán trước,
  // bấm mới lộ đáp án — dùng chung cho lật thẻ và ngữ pháp.
  let { mo = $bindable(false), size = 40 }: { mo?: boolean; size?: number } = $props();
</script>

<button
  type="button"
  class="nut-mat"
  class:dang-mo={mo}
  style="width: {size}px; height: {size}px"
  aria-pressed={mo}
  aria-label={mo ? 'Ẩn pinyin và nghĩa' : 'Hiện pinyin và nghĩa'}
  title={mo ? 'Ẩn pinyin và nghĩa' : 'Hiện pinyin và nghĩa'}
  onclick={() => (mo = !mo)}
>
  <Icon name="eye" size={Math.round(size * 0.43)} />
  <!-- Gạch chéo khi đang ẩn: bộ icon chỉ có "eye", không có "eye-off". -->
  {#if !mo}<span class="gach" aria-hidden="true"></span>{/if}
</button>

<style>
  .nut-mat {
    position: relative;
    flex: none;
    display: grid;
    place-items: center;
    padding: 0;
    border: 1px solid var(--line);
    border-radius: 50%;
    background: var(--surface);
    color: var(--text-2);
    cursor: pointer;
    transition: color 0.15s ease, background 0.15s ease;
  }
  .nut-mat:hover {
    color: var(--text);
  }
  .nut-mat.dang-mo {
    border-color: var(--son-line);
    background: var(--son-soft);
    color: var(--son-text);
  }
  .nut-mat:focus-visible {
    outline: 2px solid var(--focus);
    outline-offset: 2px;
  }
  .gach {
    position: absolute;
    width: 46%;
    height: 1.8px;
    border-radius: 1px;
    background: currentColor;
    transform: rotate(-40deg);
  }
</style>
