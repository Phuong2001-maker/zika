<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { dinhDangGiay } from '$lib/learn/sach';
  import type { DocSach } from './doc-sach.svelte';

  // Danh sách file nghe của cả quyển, gom theo bài đúng thứ tự sách. Bài đang đọc đánh dấu "Đang đọc" và được
  // focus khi mở ngăn, để người học thấy ngay file của trang trước mặt mà không phải dò.
  let { st, ondi }: { st: DocSach; ondi: (trang: number) => void } = $props();

  const nhom = $derived(
    Object.entries(st.ngheTheoBai).map(([so, ds]) => ({ so: Number(so), ds, bai: st.baiTheoSo[Number(so)] })).sort((a, b) => a.so - b.so),
  );
  const baiDangDoc = $derived(st.dapAn ? null : st.baiHienTai?.so);
</script>

{#each nhom as g (g.so)}
  {@const dangDoc = g.so === baiDangDoc}
  {@const khoa = !st.baiMo(g.so)}
  <section class="gt-ng" class:gt-ng-on={dangDoc} aria-label="File nghe bài {g.so}">
    <header class="gt-ng-dau">
      <span class="gt-ng-bai">Bài {g.so}</span>
      {#if g.bai}<b lang="zh" class="gt-ng-ten">{g.bai.ten}</b>{/if}
      {#if dangDoc}<span class="tag tag-blue">Đang đọc</span>{/if}
      {#if khoa}<span class="gt-ng-khoa"><Icon name="lock" size={13} />Chưa mở</span>{/if}
      {#if g.bai}
        <button type="button" class="btn btn-text btn-sm gt-ng-trang" onclick={() => g.bai && ondi(g.bai.trang)}>Trang {g.bai.trang}</button>
      {/if}
    </header>
    <ol class="gt-ng-ds">
      {#each g.ds as f (f.so)}
        {@const mo = st.may.dang?.so === f.so}
        <li>
          <button type="button" class="gt-ng-file" class:on={mo} disabled={khoa} aria-current={mo || (dangDoc && f === g.ds[0] && !st.may.dang)}
            aria-label="{mo && st.may.phat ? 'Tạm dừng' : 'Nghe'} {f.ten}, bài {g.so}"
            onclick={() => (mo ? st.may.batTat() : st.may.chon(f))}>
            <span class="gt-ng-nut">
              {#if khoa}
                <Icon name="lock" size={14} />
              {:else if mo && st.may.phat}
                <span class="gt-song" aria-hidden="true"><i></i><i></i><i></i></span>
              {:else}
                <Icon name="play" size={14} />
              {/if}
            </span>
            <span class="gt-ng-ten-file">{f.ten}</span>
            <span class="gt-ng-giay num">{dinhDangGiay(f.giay)}</span>
          </button>
        </li>
      {/each}
    </ol>
  </section>
{/each}

<style>
  .gt-ng { padding: 10px 8px; border-radius: var(--r-md); }
  .gt-ng + .gt-ng { margin-top: 2px; }
  .gt-ng-on { background: var(--troi); }
  .gt-ng-dau { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 8px; padding: 0 4px 6px; }
  .gt-ng-bai { font-size: 12px; font-weight: 700; letter-spacing: .02em; color: var(--text-2); text-transform: uppercase; }
  .gt-ng-ten { font-family: var(--font-hz), var(--font-ui); font-size: 15px; font-weight: 600; }
  .gt-ng-trang { margin-left: auto; min-height: 28px; font-size: 12.5px; }
  .gt-ng-ds { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 2px; }
  .gt-ng-file {
    display: grid; grid-template-columns: 32px 1fr auto; align-items: center; gap: 10px;
    width: 100%; min-height: 44px; padding: 6px 8px; border: 0; border-radius: var(--r);
    background: none; color: var(--text); font: inherit; font-size: 14px; text-align: left; cursor: pointer;
  }
  .gt-ng-file:hover:not(:disabled) { background: var(--surface); }
  .gt-ng-file:disabled { color: var(--text-3); cursor: default; }
  .gt-ng-khoa { display: inline-flex; align-items: center; gap: 3px; font-size: 12px; font-weight: 600; color: var(--text-3); }
  .gt-ng-file:focus-visible { outline: 2px solid var(--focus); outline-offset: -2px; }
  .gt-ng-file.on { background: var(--surface); box-shadow: var(--sh-sm); font-weight: 600; }
  .gt-ng-nut {
    display: grid; place-items: center; width: 32px; height: 32px; border-radius: 50%;
    background: var(--surface-3); color: var(--text);
  }
  .gt-ng-file.on .gt-ng-nut { background: var(--son); color: var(--on-accent); }
  .gt-ng-giay { font-size: 12.5px; color: var(--text-2); }
  /* Ba vạch nhảy = đang phát; tắt chuyển động thì đứng yên nhưng vẫn khác nút ▶. */
  .gt-song { display: flex; align-items: flex-end; gap: 2px; height: 12px; }
  .gt-song i { width: 3px; height: 100%; border-radius: 2px; background: currentColor; animation: gt-nhay .9s ease-in-out infinite; }
  .gt-song i:nth-child(2) { animation-delay: -.3s; }
  .gt-song i:nth-child(3) { animation-delay: -.6s; }
  @keyframes gt-nhay { 0%, 100% { transform: scaleY(.35); } 50% { transform: scaleY(1); } }
  .gt-song i { transform-origin: bottom; }
  @media (prefers-reduced-motion: reduce) { .gt-song i { animation: none; } .gt-song i:nth-child(2) { transform: scaleY(.6); } }
</style>
