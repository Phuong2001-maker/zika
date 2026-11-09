<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import { dinhDangGiay } from '$lib/learn/sach';
  import type { DocSach } from './doc-sach.svelte';

  // Thanh phát dính đáy màn hình: vẫn nghe được khi cuộn sách đọc theo. Bấm tên bài để mở trang của bài đó.
  let { st, mobile, ondi }: { st: DocSach; mobile: boolean; ondi: (trang: number) => void } = $props();

  const may = $derived(st.may);
  const f = $derived(may.dang);
  const bai = $derived(f ? st.baiTheoSo[f.bai] : undefined);
  const tong = $derived(may.tongGiay || f?.giay || 0);
  const pct = $derived(tong ? Math.min(100, (may.hienTai / tong) * 100) : 0);
  const coTruoc = $derived(may.viTri > 0 || may.hienTai > 3);
  const coSau = $derived(may.viTri >= 0 && may.viTri < may.ds.length - 1);
</script>

{#if f}
  <div class="gt-may" class:gt-may-m={mobile} role="region" aria-label="Trình phát file nghe">
    <button type="button" class="gt-may-ten" title={bai ? `Mở trang ${bai.trang} — đầu bài ${f.bai}` : undefined}
      onclick={() => bai && ondi(bai.trang)}>
      <span class="gt-may-dia" aria-hidden="true"><Icon name="headphones" size={18} /></span>
      <span style="min-width: 0">
        <b>Bài {f.bai} · {f.ten}</b>
        <span class="muted">{bai?.ten ? bai.ten : may.tenSach}</span>
      </span>
    </button>

    <div class="gt-may-nut">
      <button type="button" class="gt-tron" disabled={!coTruoc} aria-label="File trước" title="File trước" onclick={() => may.truoc()}><Icon name="skip-prev" size={18} /></button>
      <button type="button" class="gt-tron" aria-label="Lùi 5 giây" title="Lùi 5 giây" onclick={() => may.tua(-5)}><Icon name="back5" size={22} /><span class="gt-so5">5</span></button>
      <button type="button" class="gt-chinh" class:gt-cho={may.dangTai && !may.loi} aria-label={may.phat ? 'Tạm dừng' : 'Phát'} onclick={() => may.batTat()}>
        <Icon name={may.phat ? 'pause' : 'play'} size={22} />
      </button>
      <button type="button" class="gt-tron" aria-label="Tới 5 giây" title="Tới 5 giây" onclick={() => may.tua(5)}><Icon name="fwd5" size={22} /><span class="gt-so5">5</span></button>
      <button type="button" class="gt-tron" disabled={!coSau} aria-label="File sau" title="File sau" onclick={() => may.sau()}><Icon name="skip-next" size={18} /></button>
    </div>

    <div class="gt-may-thanh">
      <span class="num">{dinhDangGiay(may.hienTai)}</span>
      <input type="range" class="gt-tua" min="0" max={tong || 1} step="0.1" value={may.hienTai} style="--pct: {pct}%"
        aria-label="Vị trí đang nghe" aria-valuetext="{dinhDangGiay(may.hienTai)} trên {dinhDangGiay(tong)}"
        oninput={(e) => may.den(Number(e.currentTarget.value))} />
      <span class="num">{dinhDangGiay(tong)}</span>
    </div>

    <div class="gt-may-phu">
      <button type="button" class="gt-chu" aria-label="Tốc độ {may.tocDo} lần, bấm để đổi" title="Tốc độ đọc" onclick={() => may.doiTocDo()}>{String(may.tocDo).replace('.', ',')}×</button>
      <button type="button" class="gt-tron" class:on={may.lap} aria-pressed={may.lap} aria-label="Lặp lại file này" title="Lặp lại file này" onclick={() => may.doiLap()}><Icon name="repeat" size={18} /></button>
      <button type="button" class="gt-tron" aria-label="Danh sách file nghe" title="Danh sách file nghe" onclick={() => (st.ngan = 'nghe')}><Icon name="list" size={18} /></button>
      <button type="button" class="gt-tron" aria-label="Đóng trình phát" title="Đóng" onclick={() => may.dong()}><Icon name="x" size={18} /></button>
    </div>

    {#if may.loi}
      <p class="gt-may-loi" role="alert">Không tải được file nghe. <button type="button" class="btn btn-text btn-sm" onclick={() => { may.loi = false; may.choi(); }}>Thử lại</button></p>
    {/if}
  </div>
{/if}

<style>
  .gt-may {
    position: fixed; z-index: 30; bottom: 16px; left: 50%; transform: translateX(-50%);
    width: min(820px, calc(100% - 32px));
    display: grid; align-items: center; gap: 4px 16px;
    grid-template-columns: minmax(0, 1fr) auto auto;
    grid-template-areas: 'ten nut phu' 'thanh thanh thanh' 'loi loi loi';
    padding: 10px 14px 8px;
    background: var(--surface); border: 1px solid var(--line); border-radius: 18px; box-shadow: var(--sh-lg);
  }
  .gt-may-m {
    bottom: calc(8px + env(safe-area-inset-bottom)); width: calc(100% - 16px);
    grid-template-columns: minmax(0, 1fr) auto;
    grid-template-areas: 'ten phu' 'thanh thanh' 'nut nut' 'loi loi';
    gap: 2px 8px; padding: 10px 10px 8px;
  }
  .gt-may-ten {
    grid-area: ten; display: flex; align-items: center; gap: 10px; min-width: 0;
    padding: 4px; margin: -4px; border: 0; border-radius: var(--r); background: none; color: var(--text); font: inherit; text-align: left; cursor: pointer;
  }
  .gt-may-ten:hover b { text-decoration: underline; text-underline-offset: 3px; }
  .gt-may-ten > span:last-child { display: flex; flex-direction: column; }
  .gt-may-ten b, .gt-may-ten .muted { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .gt-may-ten b { font-size: 14px; }
  .gt-may-ten .muted { font-size: 12.5px; font-family: var(--font-mix); }
  .gt-may-dia { flex: none; display: grid; place-items: center; width: 38px; height: 38px; border-radius: 12px; background: var(--son-soft); color: var(--son-text); }
  .gt-may-nut { grid-area: nut; display: flex; align-items: center; justify-content: center; gap: 4px; }
  .gt-may-m .gt-may-nut { gap: 10px; padding-top: 2px; }
  .gt-may-phu { grid-area: phu; display: flex; align-items: center; gap: 2px; }
  .gt-may-thanh { grid-area: thanh; display: flex; align-items: center; gap: 10px; font-size: 12px; color: var(--text-2); }
  .gt-may-loi { grid-area: loi; font-size: 13px; color: var(--bad-text); }

  .gt-tron, .gt-chu, .gt-chinh {
    position: relative; display: grid; place-items: center; flex: none;
    width: 38px; height: 38px; padding: 0; border: 0; border-radius: 50%;
    background: none; color: var(--text-2); font: inherit; cursor: pointer;
  }
  .gt-tron:hover:not(:disabled), .gt-chu:hover { background: var(--surface-3); color: var(--text); }
  .gt-tron:disabled { opacity: .3; cursor: default; }
  .gt-tron.on { color: var(--son-text); background: var(--son-soft); }
  .gt-tron:focus-visible, .gt-chu:focus-visible, .gt-chinh:focus-visible, .gt-may-ten:focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; }
  .gt-so5 { position: absolute; inset: 0; display: grid; place-items: center; padding-top: 2px; font-size: 8.5px; font-weight: 800; }
  .gt-chu { width: auto; min-width: 44px; padding: 0 8px; border-radius: 999px; font-size: 13px; font-weight: 700; font-variant-numeric: tabular-nums; }
  .gt-chinh { width: 46px; height: 46px; background: var(--son); color: var(--on-accent); box-shadow: var(--sh-md); }
  .gt-chinh:hover { background: var(--son-hover); }
  .gt-may-m .gt-chinh { width: 52px; height: 52px; }
  /* Đang tải: vòng quay quanh nút phát thay vì che nút, vẫn bấm tạm dừng được. */
  .gt-cho::after {
    content: ''; position: absolute; inset: -4px; border-radius: 50%;
    border: 2px solid transparent; border-top-color: var(--son); animation: gt-quay .8s linear infinite;
  }
  @keyframes gt-quay { to { transform: rotate(360deg); } }

  .gt-tua {
    flex: 1; height: 24px; margin: 0; background: none; cursor: pointer;
    -webkit-appearance: none; appearance: none;
  }
  .gt-tua::-webkit-slider-runnable-track { height: 4px; border-radius: 99px; background: linear-gradient(to right, var(--son) var(--pct), var(--track) var(--pct)); }
  .gt-tua::-moz-range-track { height: 4px; border-radius: 99px; background: linear-gradient(to right, var(--son) var(--pct), var(--track) var(--pct)); }
  .gt-tua::-webkit-slider-thumb { -webkit-appearance: none; width: 14px; height: 14px; margin-top: -5px; border-radius: 50%; background: var(--son); border: 2px solid var(--surface); box-shadow: var(--sh); }
  .gt-tua::-moz-range-thumb { width: 12px; height: 12px; border-radius: 50%; background: var(--son); border: 2px solid var(--surface); }
  .gt-tua:focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; border-radius: 4px; }
  @media (prefers-reduced-motion: reduce) { .gt-cho::after { animation-duration: 2s; } }
</style>
