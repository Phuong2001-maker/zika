<script lang="ts">
  // Khung xương khi tải (05 §7.10). Dựng đúng hình khối của màn.
  let {
    w = '100%',
    h = '16px',
    r = 12,
    style = '',
  }: { w?: string; h?: string; r?: number; style?: string } = $props();
</script>

<span class="skel" style="display: block; width: {w}; height: {h}; border-radius: {r}px; {style}" aria-hidden="true"></span>
