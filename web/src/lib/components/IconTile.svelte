<script lang="ts">
  import Icon from './Icon.svelte';

  // Ô icon vuông viền mực (ô khoá nền bơ, ô dấu tích nền ngọc… ở các màn xác thực, đầu trang phần học).
  let {
    name,
    bg = 'var(--bo-soft)',
    size = 56,
    radius = 16,
    iconSize = Math.round(size * 0.46),
    shadow = false,
  }: { name: string; bg?: string; size?: number; radius?: number; iconSize?: number; shadow?: boolean } = $props();
</script>

<span
  class="ib"
  style="width: {size}px; height: {size}px; background: {bg}; border-radius: {radius}px; color: #14161C; {shadow ? 'box-shadow: var(--sh-sm);' : ''}"
>
  <Icon {name} size={iconSize} />
</span>
