<script lang="ts">
  import Modal from '$lib/components/Modal.svelte';
  import { HINH, urlHinh } from '$lib/learn/thi-hsk/hinh';

  // Hộp chọn một hình trong kho hình của đề HSK 3.0, gom theo nhóm, tìm theo nghĩa tiếng Việt hoặc chữ Hán.
  let { onpick, onclose }: { onpick: (ma: string) => void; onclose: () => void } = $props();
  let tim = $state('');

  const nhom = $derived.by(() => {
    const t = tim.trim().toLocaleLowerCase('vi');
    const ds = t ? HINH.filter((h) => h.vi.toLocaleLowerCase('vi').includes(t) || h.zh.includes(t) || h.ma.includes(t)) : HINH;
    const out: { ten: string; ds: typeof HINH }[] = [];
    for (const h of ds) {
      const g = out.find((x) => x.ten === h.nhom);
      if (g) g.ds.push(h);
      else out.push({ ten: h.nhom, ds: [h] });
    }
    return out;
  });
</script>

<Modal title="Chọn hình" {onclose} width={760}>
  <input class="inp" bind:value={tim} placeholder="Tìm: táo, 苹果, trời mưa…" aria-label="Tìm hình" />
  <div class="hp-cuon">
    {#each nhom as g (g.ten)}
      <p class="hp-nhom">{g.ten}</p>
      <div class="hp-luoi">
        {#each g.ds as h (h.ma)}
          <button type="button" class="hp-o" onclick={() => onpick(h.ma)} title="{h.zh} · {h.vi}">
            <img src={urlHinh(h.ma)} alt="" width="120" height="90" loading="lazy" />
            <span><b class="hz" lang="zh">{h.zh}</b> {h.vi}</span>
          </button>
        {/each}
      </div>
    {:else}
      <p class="muted" style="padding: 12px 0">Không có hình nào khớp.</p>
    {/each}
  </div>
</Modal>

<style>
  .hp-cuon { max-height: 60vh; overflow-y: auto; margin-top: 12px; display: flex; flex-direction: column; gap: 8px; }
  .hp-nhom { font-size: 12px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; color: var(--text-3); margin-top: 6px; }
  .hp-luoi { display: grid; grid-template-columns: repeat(auto-fill, minmax(112px, 1fr)); gap: 8px; }
  .hp-o { display: flex; flex-direction: column; align-items: center; gap: 4px; padding: 6px; border: 1px solid var(--line); border-radius: 12px; background: var(--surface); cursor: pointer; font-size: 12px; color: var(--text-2); }
  .hp-o:hover { border-color: var(--son-line); }
  .hp-o img { width: 100%; height: auto; border-radius: 8px; background: #FFFDF8; }
</style>
