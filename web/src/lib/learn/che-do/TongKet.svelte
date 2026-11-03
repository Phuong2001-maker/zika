<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { speak } from '$lib/learn/speech';
  import { dinhDangGiay } from './cham';
  import type { KetQua } from './state.svelte';

  // Màn cuối của một chế độ: điểm, thời gian, danh sách từ cần ôn và lối luyện lại riêng từ sai.
  let {
    kq,
    ten,
    m = false,
    onLuyenSai,
    onLamLai,
  }: { kq: KetQua; ten: string; m?: boolean; onLuyenSai: () => void; onLamLai: () => void } = $props();
</script>

<section class="cd-tk" style="padding: {m ? 8 : 16}px 0">
  <span class="stamp" style="font-size: {m ? 24 : 30}px">完成</span>
  <h2 style="font-size: {m ? 21 : 26}px">Xong phần {ten}</h2>
  <div class="cd-so-lieu">
    <span><b class="num">{kq.dung}/{kq.tong}</b> từ đúng ngay</span>
    <span class="muted">·</span>
    <span class="num"><Icon name="clock" size={15} /> {dinhDangGiay(kq.giay)}</span>
  </div>

  {#if kq.sai.length > 0}
    <div class="cd-ds">
      <p class="kicker">Cần ôn thêm ({kq.sai.length})</p>
      <ul>
        {#each kq.sai as w (w.id)}
          <li>
            <span class="hz" lang="zh" style="font-size: 20px">{w.hanzi}</span>
            <span class="num" style="font-size: 13.5px; font-weight: 600; color: var(--text-2)">{w.pinyin}</span>
            <span class="cd-ds-nghia">{w.meaning}</span>
            <button type="button" class="cd-loa" aria-label="Nghe {w.hanzi}" onclick={() => speak(w.hanzi, null, w.pinyin, 'nam')}>
              <Icon name="speaker" size={16} />
            </button>
          </li>
        {/each}
      </ul>
    </div>
  {:else}
    <p class="muted">Không sai từ nào. Giỏi lắm!</p>
  {/if}

  <div class="cd-nut" class:cd-nut-m={m}>
    <button type="button" class="btn" onclick={onLamLai}><Icon name="repeat" size={16} /> Làm lại từ đầu</button>
    {#if kq.sai.length > 0}
      <button type="button" class="btn btn-son" onclick={onLuyenSai}>Luyện lại {kq.sai.length} từ sai</button>
    {/if}
  </div>
</section>

<style>
  .cd-tk {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 14px;
    text-align: center;
  }
  .cd-so-lieu {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    justify-content: center;
    font-size: 15px;
  }
  .cd-so-lieu span {
    display: inline-flex;
    align-items: center;
    gap: 5px;
  }
  .cd-ds {
    width: 100%;
    max-width: 520px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    text-align: left;
  }
  .cd-ds ul {
    display: flex;
    flex-direction: column;
    border: 1px solid var(--line);
    border-radius: 14px;
    overflow: hidden;
  }
  .cd-ds li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px 8px 14px;
    background: var(--surface);
  }
  .cd-ds li + li {
    border-top: 1px solid var(--line);
  }
  .cd-ds-nghia {
    flex: 1;
    min-width: 0;
    font-size: 14px;
    color: var(--text-2);
    overflow-wrap: break-word;
  }
  .cd-loa {
    flex: none;
    display: grid;
    place-items: center;
    width: 36px;
    height: 36px;
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
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
  }
  .cd-nut-m {
    width: 100%;
    flex-direction: column-reverse;
  }
</style>
