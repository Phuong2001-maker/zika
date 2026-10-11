<script lang="ts">
  // Thanh tiến độ (05 §7.5). Dùng pct trực tiếp, hoặc value/total.
  let {
    value = 0,
    total = 0,
    pct,
    color,
    trackStyle = '',
    h,
  }: {
    value?: number;
    total?: number;
    pct?: number;
    color?: string;
    trackStyle?: string;
    h?: number;
  } = $props();

  const width = $derived(Math.max(0, Math.min(100, Math.round(pct ?? (total > 0 ? (value / total) * 100 : 0)))));
</script>

<span class="bar" style="display: block{h ? `; height: ${h}px` : ''}{trackStyle ? `; ${trackStyle}` : ''}">
  <i style="width: {width}%{color ? `; background: ${color}` : ''}"></i>
</span>
