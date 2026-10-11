<script lang="ts">
  import { ICONS, FILLED } from '$lib/icons';

  let { name, size = 20, class: cls = '', style = '' }: { name: string; size?: number; class?: string; style?: string } = $props();
  const filled = $derived(FILLED.has(name));
</script>

<svg
  width={size}
  height={size}
  viewBox="0 0 24 24"
  fill={filled ? 'currentColor' : 'none'}
  stroke={filled ? 'none' : 'currentColor'}
  stroke-width="1.8"
  stroke-linecap="round"
  stroke-linejoin="round"
  aria-hidden="true"
  class={cls}
  {style}
>
  <!-- Nội dung path lấy từ design/icons.json (tĩnh, không phải dữ liệu người dùng). -->
  {@html ICONS[name] ?? ''}
</svg>
