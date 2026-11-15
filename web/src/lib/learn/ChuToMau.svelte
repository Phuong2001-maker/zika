<script lang="ts">
  // Chữ Hán tô hai màu theo bộ phận cho ô "Cách nhớ" ở Luyện viết: nét của bộ thủ (radStrokes trong file nét) màu son,
  // các nét còn lại màu xanh — khớp hai thẻ phần a / phần b bên cạnh. Dùng lại đúng file nét của ô viết (trình duyệt đã
  // tải và giữ cache), nên không tốn thêm lượt tải.
  let { dataUrl, hanzi, size = 84 }: { dataUrl: string | null; hanzi: string; size?: number } = $props();

  let net = $state<string[]>([]);
  let netBo = $state<Set<number>>(new Set());
  let loi = $state(false);

  $effect(() => {
    const url = dataUrl;
    net = [];
    loi = false;
    if (!url) return;
    let huy = false;
    fetch(url)
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error(String(r.status)))))
      .then((d: { strokes?: string[]; radStrokes?: number[] }) => {
        if (huy) return;
        net = d.strokes ?? [];
        netBo = new Set(d.radStrokes ?? []);
      })
      .catch(() => {
        if (!huy) loi = true;
      });
    return () => {
      huy = true;
    };
  });
</script>

<span class="ctm" style="width: {size}px; height: {size}px">
  {#if net.length > 0}
    <svg viewBox="0 0 1024 1024" width="100%" height="100%" role="img" aria-label="Chữ {hanzi} tô màu theo bộ phận">
      <!-- Toạ độ của dữ liệu nét (Make Me a Hanzi) lật trục y, gốc ở 900. -->
      <g transform="scale(1,-1) translate(0,-900)">
        {#each net as d, i (i)}
          <path {d} class={netBo.size > 0 && netBo.size < net.length && netBo.has(i) ? 'ctm-a' : netBo.size > 0 && netBo.size < net.length ? 'ctm-b' : 'ctm-a'} />
        {/each}
      </g>
    </svg>
  {:else}
    <!-- Chưa có dữ liệu nét (hoặc tải lỗi): hiện chữ thường, không để ô trống. -->
    <span class="hz ctm-chu" lang="zh" class:mo={!loi && !!dataUrl}>{hanzi}</span>
  {/if}
</span>

<style>
  .ctm {
    flex: none;
    display: grid;
    place-items: center;
    padding: 6px;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: #fff;
  }
  .ctm-a {
    fill: var(--son);
  }
  .ctm-b {
    fill: var(--blue);
  }
  .ctm-chu {
    font-size: 52px;
    line-height: 1;
    color: var(--son);
  }
  .ctm-chu.mo {
    opacity: 0.25;
  }
</style>
