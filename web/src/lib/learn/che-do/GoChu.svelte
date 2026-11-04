<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { settings } from '$lib/stores/settings.svelte';
  import { speak } from '$lib/learn/speech';
  import { toneParts } from '$lib/utils/pinyin';
  import { POS_LABELS } from '$lib/utils/format';
  import KhungCheDo from './KhungCheDo.svelte';
  import type { GoChu } from './state.svelte';

  // Gõ chữ: nhìn nghĩa, gõ chữ Hán hoặc pinyin (không dấu, thanh bằng số đều được) — máy chưa cài
  // bộ gõ tiếng Trung vẫn luyện được. Enter: lần 1 kiểm tra, lần 2 sang câu sau.
  let { st, m = false }: { st: GoChu; m?: boolean } = $props();

  const w = $derived(st.cau);
  /** Mã từ loại ("n") → "Danh từ"; mã lạ thì để nguyên. */
  const tuLoai = (pos: string): string => {
    const ten = POS_LABELS[pos] ?? pos;
    return ten.charAt(0).toLocaleUpperCase('vi-VN') + ten.slice(1);
  };
  let o: HTMLInputElement | undefined = $state();
  let nutTiep: HTMLButtonElement | undefined = $state();

  // Câu mới thì đặt con trỏ vào ô gõ, khỏi phải chạm thêm một lần.
  $effect(() => {
    void st.vt;
    if (st.trangThai === 'go') queueMicrotask(() => o?.focus());
  });

  function guiDi(e: SubmitEvent): void {
    e.preventDefault();
    if (st.trangThai === 'go') {
      st.kiemTra();
      if (st.trangThai !== 'go') daChamXong();
    } else st.tiep();
  }

  function boQua(): void {
    st.boQua();
    daChamXong();
  }

  function daChamXong(): void {
    if (w && settings.value.autoSpeak) void speak(w.hanzi, null, w.pinyin, 'nam');
    queueMicrotask(() => nutTiep?.focus());
  }
</script>

{#snippet pinyinTo(py: string, size: number)}
  <span class="num" style="font-size: {size}px; font-weight: 600" class:no-tone={!settings.value.toneColors}>
    {#each toneParts(py) as p, k (k)}<span class="t{p.tone}">{p.text}</span>{/each}
  </span>
{/snippet}

{#if w}
  <KhungCheDo
    nhan="Gõ chữ Hán hoặc pinyin"
    so="{st.vt + 1}/{st.ds.length}"
    {m}
  >
    <div class="cd-de">
      {#if w.pos}<span class="tag">{tuLoai(w.pos)}</span>{/if}
      <span class="cd-nghia-to" style="font-size: {m ? 21 : 26}px">{w.meaning}</span>
      {#if st.trangThai === 'go' && st.goiY > 0}
        <span class="cd-goi-y">
          Gợi ý: {@render pinyinTo(w.pinyin, 16)}
          {#if st.goiY > 1}<span class="hz" lang="zh" style="font-size: 20px">{w.hanzi}</span>{/if}
        </span>
      {/if}
    </div>

    <form class="cd-form" onsubmit={guiDi}>
      <input
        class="inp cd-nhap"
        class:cd-nhap-m={m}
        bind:this={o}
        bind:value={st.nhap}
        readonly={st.trangThai !== 'go'}
        aria-invalid={st.trangThai === 'sai'}
        aria-label="Đáp án"
        placeholder="Ví dụ: 你好 hoặc nihao"
        autocomplete="off"
        autocapitalize="off"
        spellcheck="false"
        enterkeyhint={st.trangThai === 'go' ? 'done' : 'next'}
      />

      {#if st.trangThai !== 'go'}
        <div class="cd-ket" class:cd-ket-dung={st.trangThai === 'dung'}>
          <span class="cd-dau" style="color: {st.trangThai === 'dung' ? 'var(--ngoc)' : 'var(--bad)'}">
            <Icon name={st.trangThai === 'dung' ? 'check' : 'x'} size={20} />
          </span>
          <span class="cd-ket-chu">
            <b>{st.trangThai === 'dung' ? 'Chính xác!' : 'Đáp án đúng'}</b>
            <span class="cd-dap">
              <span class="hz" lang="zh" style="font-size: 24px">{w.hanzi}</span>
              {@render pinyinTo(w.pinyin, 16)}
            </span>
          </span>
          <button type="button" class="cd-loa" aria-label="Nghe {w.hanzi}" onclick={() => speak(w.hanzi, null, w.pinyin, 'nam')}>
            <Icon name="speaker" size={17} />
          </button>
        </div>
      {/if}

      <div class="cd-nut" class:cd-nut-m={m}>
        {#if st.trangThai === 'go'}
          <button type="button" class="btn" disabled={st.goiY >= 2} onclick={() => st.xinGoiY()}>
            Gợi ý{st.goiY > 0 ? ` (${st.goiY}/2)` : ''}
          </button>
          <button type="button" class="btn" onclick={boQua}>Bỏ qua</button>
          <button type="submit" class="btn btn-son" disabled={st.nhap.trim() === ''}>Kiểm tra</button>
        {:else}
          <button type="submit" class="btn btn-son" bind:this={nutTiep}>Tiếp tục <Icon name="right" size={16} /></button>
        {/if}
      </div>
    </form>
  </KhungCheDo>
{/if}

<style>
  .cd-de {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    text-align: center;
  }
  .cd-nghia-to {
    font-weight: 800;
    line-height: 1.3;
    overflow-wrap: break-word;
    max-width: 100%;
  }
  .cd-goi-y {
    display: inline-flex;
    align-items: baseline;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: center;
    font-size: 14px;
    color: var(--text-2);
  }
  .cd-form {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .cd-nhap {
    height: 54px;
    font-size: 20px;
    text-align: center;
  }
  /* 16px trở lên để Safari trên iPhone không tự phóng to trang khi chạm vào ô gõ. */
  .cd-nhap-m {
    font-size: 18px;
  }
  .cd-ket {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border: 1px solid var(--bad);
    border-radius: 14px;
    background: var(--son-soft);
  }
  .cd-ket-dung {
    border-color: var(--ngoc);
    background: var(--ngoc-soft);
  }
  .cd-dau {
    flex: none;
    display: grid;
  }
  .cd-ket-chu {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
  }
  .cd-dap {
    display: flex;
    align-items: baseline;
    gap: 10px;
    flex-wrap: wrap;
  }
  .cd-loa {
    flex: none;
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
  .cd-nut {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    flex-wrap: wrap;
  }
  /* Điện thoại: nút chia đều bề ngang, Kiểm tra / Tiếp tục to nhất ở cuối cho ngón cái. */
  .cd-nut-m > :global(button) {
    flex: 1 1 0;
  }
</style>
