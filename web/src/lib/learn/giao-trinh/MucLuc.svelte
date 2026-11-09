<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import type { DocSach } from './doc-sach.svelte';

  // Mục lục của phần đang xem (sách hoặc đáp án). Mỗi bài cho biết luôn có file nghe, có đáp án hay không,
  // để người học khỏi phải mở từng bài mới biết.
  let { st, ondi }: { st: DocSach; ondi: (trang: number) => void } = $props();
</script>

<ol class="gt-ml">
  {#if !st.dapAn && (st.mucLuc[0]?.trang ?? 1) > 1}
    <li>
      <button type="button" class="gt-ml-dong" class:on={st.baiHienTai === null} aria-current={st.baiHienTai === null} onclick={() => ondi(1)}>
        <span class="gt-ml-so">—</span>
        <span class="gt-ml-ten"><b>Đầu sách</b><span class="muted">Bìa, lời nói đầu, mục lục của sách</span></span>
        <span class="gt-ml-phai num">tr. 1</span>
      </button>
    </li>
  {/if}
  {#each st.mucLucHien as b (b.so)}
    {@const soNghe = st.ngheTheoBai[b.so]?.length ?? 0}
    {@const dangDoc = st.baiHienTai?.so === b.so}
    {@const khoa = !st.baiMo(b.so)}
    <li>
      <button type="button" class="gt-ml-dong" class:on={dangDoc} aria-current={dangDoc} onclick={() => ondi(b.trang)}>
        <span class="gt-ml-so num">{b.so}</span>
        <span class="gt-ml-ten">
          <b lang="zh">{b.ten || `Bài ${b.so}`}</b>
          {#if b.tenVi}<span class="muted">{b.tenVi}</span>{/if}
        </span>
        <span class="gt-ml-phai">
          {#if khoa}<span class="gt-ml-khoa" title={st.laBaiKe(b.so) ? 'Bài mở tiếp được bằng 1 điểm' : 'Chưa mở'}><Icon name="lock" size={14} />{st.laBaiKe(b.so) ? 'Mở được' : ''}</span>{/if}
          {#if !st.dapAn && soNghe && !khoa}<span class="gt-ml-co" title="{soNghe} file nghe"><Icon name="headphones" size={14} />{soNghe}</span>{/if}
          {#if !st.dapAn && b.so in st.dapAnTheoBai}<span class="gt-ml-co" title="Có đáp án"><Icon name="answer" size={14} /></span>{/if}
          <span class="num">tr. {b.trang}</span>
        </span>
      </button>
    </li>
  {/each}
</ol>

<style>
  .gt-ml { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px; }
  .gt-ml-dong {
    display: grid; grid-template-columns: 32px 1fr auto; align-items: center; gap: 10px;
    width: 100%; min-height: 52px; padding: 8px 10px; border: 0; border-radius: var(--r-md);
    background: none; color: var(--text); font: inherit; text-align: left; cursor: pointer;
  }
  .gt-ml-dong:hover { background: var(--surface-2); }
  .gt-ml-dong:focus-visible { outline: 2px solid var(--focus); outline-offset: -2px; }
  .gt-ml-dong.on { background: var(--son-soft); }
  .gt-ml-so {
    display: grid; place-items: center; width: 32px; height: 32px; border-radius: 50%;
    background: var(--surface-3); color: var(--text-2); font-size: 13px; font-weight: 700;
  }
  .gt-ml-dong.on .gt-ml-so { background: var(--son); color: var(--on-accent); }
  .gt-ml-ten { display: flex; flex-direction: column; gap: 1px; min-width: 0; }
  .gt-ml-ten b { font-size: 15px; font-weight: 600; font-family: var(--font-mix); }
  .gt-ml-ten .muted { font-size: 12.5px; line-height: 1.35; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .gt-ml-phai { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--text-2); white-space: nowrap; }
  .gt-ml-co { display: inline-flex; align-items: center; gap: 2px; color: var(--blue-text); }
  .gt-ml-khoa { display: inline-flex; align-items: center; gap: 3px; color: var(--text-3); font-weight: 600; }
</style>
