<script lang="ts">
  import { settings } from '$lib/stores/settings.svelte';
  import { speak } from '$lib/learn/speech';
  import KhungCheDo from './KhungCheDo.svelte';
  import type { GhepCap } from './state.svelte';

  // Ghép cặp: chữ Hán bên trái, nghĩa bên phải. Hai cột dựng chung một lưới, ô trái và ô phải
  // cùng hàng thì cao bằng nhau dù nghĩa dài xuống dòng (nghĩa dài nhất trong kho ~95 ký tự).
  let { st, m = false }: { st: GhepCap; m?: boolean } = $props();

  const PHIM_PHAI = ['q', 'w', 'e', 'r', 't', 'y'];

  function bam(ben: 'trai' | 'phai', i: number): void {
    const ti = ben === 'trai' ? i : st.chonTrai;
    const w = ti === null ? null : st.trai[ti];
    if (st.chon(ben, i) === 'dung' && w && settings.value.autoSpeak) void speak(w.hanzi, null, w.pinyin, 'nam');
  }

  function onKey(e: KeyboardEvent): void {
    if (st.xong || e.ctrlKey || e.metaKey || e.altKey) return;
    const tag = (e.target as HTMLElement | null)?.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA') return;
    const n = Number(e.key);
    if (Number.isInteger(n) && n >= 1 && n <= st.trai.length) {
      e.preventDefault();
      bam('trai', n - 1);
      return;
    }
    const k = PHIM_PHAI.indexOf(e.key.toLowerCase());
    if (k >= 0 && k < st.phai.length) {
      e.preventDefault();
      bam('phai', k);
    }
  }
</script>

<svelte:window onkeydown={onKey} />

<KhungCheDo
  nhan="Tiến độ {st.daGhep}/{st.tu.length}"
  {m}
>
  <p class="muted" style="font-size: 13.5px; text-align: center">Chọn một chữ Hán, rồi chọn nghĩa của nó.</p>
  <div class="cd-luoi" class:cd-luoi-m={m}>
    {#each st.trai as w, i (`${st.trang}-${i}`)}
      {@const p = st.phai[i]}
      <button
        type="button"
        class="cd-o"
        class:dang-chon={st.chonTrai === i}
        class:rung={st.rung?.trai === i}
        class:da-dung={st.xongTrai.has(i)}
        class:mo-di={st.xongTrai.has(i)}
        disabled={st.xongTrai.has(i)}
        aria-pressed={st.chonTrai === i}
        onclick={() => bam('trai', i)}
      >
        <span class="hz cd-chu" lang="zh">{w.hanzi}</span>
      </button>
      <button
        type="button"
        class="cd-o"
        class:dang-chon={st.chonPhai === i}
        class:rung={st.rung?.phai === i}
        class:da-dung={st.xongPhai.has(i)}
        class:mo-di={st.xongPhai.has(i)}
        disabled={st.xongPhai.has(i)}
        aria-pressed={st.chonPhai === i}
        onclick={() => bam('phai', i)}
      >
        <span class="cd-nghia">{p.meaning}</span>
      </button>
    {/each}
  </div>
</KhungCheDo>

<style>
  .cd-luoi {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1.25fr);
    gap: 10px 14px;
  }
  .cd-luoi-m {
    grid-template-columns: minmax(0, 0.85fr) minmax(0, 1.15fr);
    gap: 8px;
  }
  /* Chữ Hán không bẻ giữa từ (lỗi thấy ở trang mẫu: 为什/么). Từ dài nhất trong kho 5 chữ. */
  .cd-chu {
    font-size: 22px;
    line-height: 1.2;
    white-space: nowrap;
  }
  .cd-luoi-m .cd-chu {
    font-size: 20px;
  }
  /* Nghĩa dài thì xuống dòng, không cắt "…" như trang mẫu. */
  .cd-nghia {
    min-width: 0;
    font-size: 14.5px;
    font-weight: 600;
    line-height: 1.35;
    overflow-wrap: break-word;
  }
  .cd-luoi-m .cd-nghia {
    font-size: 13.5px;
  }
</style>
