<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { media } from '$lib/stores/media.svelte';
  import { settings } from '$lib/stores/settings.svelte';
  import { speak } from '$lib/learn/speech';
  import { toneParts } from '$lib/utils/pinyin';
  import KhungCheDo from './KhungCheDo.svelte';
  import type { TracNghiem } from './state.svelte';

  // Trắc nghiệm: nhìn chữ Hán, chọn nghĩa. Điện thoại xếp 4 đáp án thành 4 hàng dài cho dễ chạm,
  // máy tính lưới 2×2. Chọn xong hiện pinyin + đúng/sai, bấm Tiếp tục (hoặc Enter) sang câu sau.
  let { st, m = false }: { st: TracNghiem; m?: boolean } = $props();

  const w = $derived(st.cau);
  let nutTiep: HTMLButtonElement | undefined = $state();

  function chon(i: number): void {
    const kq = st.traLoi(i);
    if (kq === null || !w) return;
    if (settings.value.autoSpeak) void speak(w.hanzi, null, w.pinyin, 'nam');
    // Đưa focus sang Tiếp tục: Enter lúc này không được rơi vào ô đáp án vừa bấm của câu sau.
    queueMicrotask(() => nutTiep?.focus());
  }

  function onKey(e: KeyboardEvent): void {
    if (st.xong || e.ctrlKey || e.metaKey || e.altKey) return;
    if (e.key === 'Enter' && st.daTraLoi) {
      e.preventDefault();
      st.tiep();
      return;
    }
    const n = Number(e.key);
    if (!st.daTraLoi && Number.isInteger(n) && n >= 1 && n <= st.dapAn.length) {
      e.preventDefault();
      chon(n - 1);
    }
  }
</script>

<svelte:window onkeydown={onKey} />

{#snippet pinyinTo(py: string)}
  <span class="num cd-py" class:no-tone={!settings.value.toneColors}>
    {#each toneParts(py) as p, k (k)}<span class="t{p.tone}">{p.text}</span>{/each}
  </span>
{/snippet}

{#if w}
  <KhungCheDo
    nhan="Chọn nghĩa đúng"
    so="Câu {st.vt + 1}/{st.hangDoi.length}"
    {m}
  >
    <div class="cd-de">
      <span class="hz" lang="zh" style="font-size: {m ? 54 : 76}px; line-height: 1.1">{w.hanzi}</span>
      <!-- Giữ chỗ dòng pinyin từ đầu để lúc hiện ra các đáp án không bị đẩy xuống. -->
      <span class="cd-dong-py">{#if st.daTraLoi}{@render pinyinTo(w.pinyin)}{/if}</span>
      <button type="button" class="cd-loa" aria-label="Nghe {w.hanzi}" onclick={() => speak(w.hanzi, null, w.pinyin, 'nam')}>
        <Icon name="speaker" size={18} />
      </button>
    </div>

    <div class="cd-dap-an" class:cd-dap-an-m={m}>
      {#each st.dapAn as d, i (`${st.vt}-${i}`)}
        <button
          type="button"
          class="cd-o"
          class:da-dung={st.daTraLoi && i === st.dungIdx}
          class:da-sai={st.daTraLoi && i === st.chon && i !== st.dungIdx}
          class:mo-di={st.daTraLoi && i !== st.dungIdx && i !== st.chon}
          disabled={st.daTraLoi}
          onclick={() => chon(i)}
        >
          {#if media.coBanPhim}<span class="cd-phim">{i + 1}</span>{/if}
          <span class="cd-chu-dap">{d}</span>
          {#if st.daTraLoi && i === st.dungIdx}<span class="cd-dau" style="color: var(--ngoc)"><Icon name="check" size={18} /></span>{/if}
          {#if st.daTraLoi && i === st.chon && i !== st.dungIdx}<span class="cd-dau" style="color: var(--bad)"><Icon name="x" size={18} /></span>{/if}
        </button>
      {/each}
    </div>

    {#if st.daTraLoi}
      <div class="cd-ket" class:cd-ket-m={m}>
        <b style="color: {st.chon === st.dungIdx ? 'var(--green-text)' : 'var(--bad-text)'}">
          {st.chon === st.dungIdx ? 'Chính xác!' : 'Chưa đúng — câu này sẽ được hỏi lại.'}
        </b>
        <button type="button" class="btn btn-son" bind:this={nutTiep} onclick={() => st.tiep()}>Tiếp tục <Icon name="right" size={16} /></button>
      </div>
    {/if}
  </KhungCheDo>
{/if}

<style>
  .cd-de {
    position: relative;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 8px 48px 0;
    text-align: center;
  }
  /* Chiều cao dòng cố định, trùng đúng dòng pinyin, để hiện pinyin không xô đáp án xuống. */
  .cd-dong-py {
    height: 28px;
    line-height: 28px;
  }
  .cd-py {
    font-size: 19px;
    font-weight: 600;
    line-height: 28px;
  }
  .cd-loa {
    position: absolute;
    top: 0;
    right: 0;
    display: grid;
    place-items: center;
    width: 40px;
    height: 40px;
    border: 1px solid var(--line);
    border-radius: 50%;
    background: var(--surface);
    color: var(--text-2);
    cursor: pointer;
  }
  .cd-loa:hover {
    color: var(--son-text);
  }
  .cd-loa:focus-visible {
    outline: 2px solid var(--focus);
    outline-offset: 2px;
  }
  .cd-dap-an {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
  }
  .cd-dap-an-m {
    grid-template-columns: minmax(0, 1fr);
    gap: 8px;
  }
  .cd-chu-dap {
    flex: 1;
    min-width: 0;
    font-size: 15px;
    font-weight: 600;
    line-height: 1.35;
    overflow-wrap: break-word;
  }
  .cd-dau {
    flex: none;
    display: grid;
  }
  .cd-ket {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
  }
  .cd-ket-m {
    flex-direction: column;
    align-items: stretch;
    text-align: center;
  }
</style>
