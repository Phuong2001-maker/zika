<script lang="ts">
  import HanziWriter from 'hanzi-writer';

  // Ô tập viết một chữ Hán (07 §12).
  //
  // Trước đây trang tự vẽ path SVG nên chỉ “hiện dần từng nét” được, không chấm được chữ người
  // học viết. Thư viện hanzi-writer vốn đã nằm trong package.json làm đúng việc đó: tô theo thì
  // nó so nét vừa vẽ với nét mẫu, sai thì nhấp nháy gợi ý.
  //
  // Dữ liệu nét lấy từ uploads của chính mình (xem HanziDataService ở API), không gọi ra CDN.
  interface Props {
    hanzi: string;
    dataUrl: string | null;
    size: number;
    mode: 'demo' | 'trace' | 'free';
    /** Hết một lượt tô đúng cả chữ. */
    onpass?: (mistakes: number) => void;
  }

  let { hanzi, dataUrl, size, mode, onpass }: Props = $props();

  let host = $state<HTMLDivElement | null>(null);
  let writer: HanziWriter | null = null;
  let medians = $state<number[][][]>([]);
  let loi = $state(false);

  /** Nét người học sắp phải viết — để nút “Gợi ý” biết nhấp nháy nét nào. */
  let netHienTai = $state(0);

  const PAD = 20;
  const ti_le = $derived((size - PAD * 2) / 1024);
  // Chữ trong bộ makemeahanzi nằm trong hộp y từ -124 đến 900 chứ không phải 0–1024, nên đổi
  // toạ độ nét sang pixel phải cộng 124 — thiếu chỗ này là số thứ tự trôi khỏi nét đúng chừng ấy.
  const DAY = 124;

  /** Màu lấy từ biến CSS của giao diện: hanzi-writer chỉ nhận mã màu, không nhận var(). */
  function mau(ten: string, duphong: string): string {
    if (typeof window === 'undefined') return duphong;
    const v = getComputedStyle(document.documentElement).getPropertyValue(ten).trim();
    return v === '' ? duphong : v;
  }

  function huy(): void {
    writer?.cancelQuiz();
    // Vùng này do hanzi-writer tự vẽ, Svelte không quản lý node nào bên trong, nên dọn tay là an toàn.
    // eslint-disable-next-line svelte/no-dom-manipulating -- node bên trong là của thư viện, không phải của Svelte
    if (host) host.innerHTML = '';
    writer = null;
  }

  /** Dựng lại ô viết mỗi khi đổi chữ, đổi cỡ hoặc đổi chế độ. */
  function dung(): void {
    huy();
    if (!host || dataUrl === null) return;
    loi = false;
    netHienTai = 0;

    writer = HanziWriter.create(host, hanzi, {
      width: size,
      height: size,
      padding: PAD,
      showCharacter: false,
      showOutline: mode !== 'free', // Tự viết: không có chữ mờ để nhìn theo.
      // Chậm hơn mặc định hẳn một nửa: người mới học cần nhìn kịp hướng bút.
      strokeAnimationSpeed: 0.5,
      delayBetweenStrokes: 900,
      strokeHighlightSpeed: 1.2,
      drawingWidth: 24,
      strokeColor: mau('--text', '#22262F'),
      outlineColor: mau('--line-3', '#D7DCE5'),
      drawingColor: mau('--son', '#E5484D'),
      highlightColor: mau('--son-mid', '#FF9A90'),
      charDataLoader: (_char, onComplete, onError) => {
        // Không viết dạng async: hanzi-writer chờ hàm này trả về dữ liệu hoặc undefined,
        // một Promise<void> sẽ bị nó hiểu nhầm là dữ liệu rỗng.
        fetch(dataUrl as string)
          .then((r) => r.json())
          .then((json) => {
            medians = json.medians ?? [];
            onComplete(json);
          })
          .catch(() => {
            loi = true;
            onError?.();
          });
      },
    });

    if (mode === 'demo') {
      void writer.animateCharacter();
    } else {
      batDauTo();
    }
  }

  function batDauTo(): void {
    if (!writer) return;
    netHienTai = 0;
    void writer.quiz({
      leniency: 1.1, // Rộng tay một chút: viết bằng chuột khó hơn bằng ngón tay.
      showHintAfterMisses: 3,
      highlightOnComplete: true,
      onCorrectStroke: (d) => (netHienTai = d.strokeNum + 1),
      onMistake: (d) => (netHienTai = d.strokeNum),
      onComplete: (s) => onpass?.(s.totalMistakes),
    });
  }

  export function chayLai(): void {
    if (!writer) return;
    if (mode === 'demo') void writer.animateCharacter();
    else batDauTo();
  }

  export function goiY(): void {
    writer?.highlightStroke(netHienTai);
  }

  export function xoa(): void {
    if (mode === 'demo') writer?.hideCharacter();
    else batDauTo();
  }

  // Dựng lại khi bất cứ thứ gì ảnh hưởng tới ô viết thay đổi.
  $effect(() => {
    void hanzi;
    void dataUrl;
    void size;
    void mode;
    void host;
    dung();
    return huy;
  });
</script>

<div class="o" style="width: {size}px; height: {size}px">
  <div bind:this={host} class="bang"></div>

  {#if mode === 'demo' && medians.length > 0}
    <!-- Số thứ tự nét đặt ở đầu mỗi nét, lấy từ “medians” trong dữ liệu nét. -->
    <svg class="so" viewBox="0 0 {size} {size}" width={size} height={size} aria-hidden="true">
      {#each medians as m, k (k)}
        {@const p = m[0] ?? [512, 512]}
        {@const x = PAD + p[0] * ti_le}
        {@const y = size - PAD - (p[1] + DAY) * ti_le}
        <circle cx={x} cy={y} r="11" />
        <text {x} y={y + 4}>{k + 1}</text>
      {/each}
    </svg>
  {/if}

  {#if loi}
    <span class="muted bao">Chưa tải được nét của chữ này.</span>
  {:else if dataUrl === null}
    <span class="hz bao" lang="zh" style="font-size: {size * 0.6}px">{hanzi}</span>
  {/if}
</div>

<style>
  .o {
    position: relative;
    flex: none;
  }
  .bang {
    position: absolute;
    inset: 0;
    touch-action: none; /* Kéo ngón tay là viết chữ, không phải cuộn trang. */
  }
  .so {
    position: absolute;
    inset: 0;
    pointer-events: none;
  }
  .so circle {
    fill: var(--son);
    opacity: 0.92;
  }
  .so text {
    fill: #fff;
    font: 700 13px/1 var(--font-ui);
    text-anchor: middle;
  }
  .bao {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    text-align: center;
    font-size: 13px;
  }
</style>
