<script lang="ts">
  import { onDestroy, onMount } from 'svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { speak } from '$lib/learn/speech';
  import { clock } from '$lib/utils/format';
  import type { Answer, Payload } from '$lib/utils/grade';
  import { TEN_NHIEM_VU } from './cau-truc';
  import { chonDinhDang, layBanGhi, loiGhiAm, luuBanGhi } from './ghi-am';

  // Câu nói của Thi thử HSK 3.0: chuẩn bị → ghi âm → nghe lại → (luyện tập) so với bài mẫu và tự đánh giá.
  // Bản ghi chỉ ở máy người học; server chỉ nhận câu trả lời dạng chữ ("ghi", "tot"…) để đếm số câu đã nói.
  let {
    attemptId,
    qid,
    payload,
    answer = $bindable(),
    locked = false,
    mau = null,
    mobile = false,
  }: {
    attemptId: number;
    qid: number;
    payload: Payload;
    answer: Answer;
    /** Đã xem bài mẫu (luyện tập) hoặc đã nộp: không ghi lại nữa. */
    locked?: boolean;
    /** Bài nói mẫu, có sau khi bấm Kiểm tra ở chế độ luyện tập. */
    mau?: { sample: string; sampleVi: string } | null;
    mobile?: boolean;
  } = $props();

  type Trang = 'cho' | 'chuanBi' | 'ghi' | 'xong' | 'loi';
  let trang = $state<Trang>('cho');
  let thongBao = $state('');
  let conLai = $state(0);
  let daNoi = $state(0);
  let muc = $state(0);
  let url = $state('');

  const task = $derived(String(payload.task ?? 'answer'));
  const prompt = $derived(String(payload.prompt ?? ''));
  const audioText = $derived(String(payload.audioText ?? ''));
  const prepSec = $derived(Number(payload.prepSec ?? 0));
  const answerSec = $derived(Number(payload.answerSec ?? 30));
  // Nhắc lại: chữ chỉ hiện khi đã xem bài mẫu, không thì người học đọc thay vì nghe.
  const hienChu = $derived(prompt !== '' && (task !== 'repeat' || mau !== null));

  let stream: MediaStream | null = null;
  let rec: MediaRecorder | null = null;
  let manh: Blob[] = [];
  let dem: ReturnType<typeof setInterval> | null = null;
  let ctx: AudioContext | null = null;
  let raf = 0;

  const HUONG_DAN: Record<string, string> = {
    repeat: 'Nghe câu dưới đây rồi nói lại đúng như vậy.',
    read: 'Đọc to đoạn dưới đây.',
    answer: 'Nghe hoặc đọc câu hỏi rồi trả lời bằng tiếng Trung.',
    topic: 'Nói về chủ đề dưới đây bằng tiếng Trung.',
    picture: 'Nhìn tranh rồi nói vài câu bằng tiếng Trung.',
  };

  onMount(async () => {
    // Lỡ tải lại trang giữa bài: bản đã ghi vẫn còn trong máy.
    const cu = await layBanGhi(attemptId, qid);
    if (cu && trang === 'cho') datBanGhi(cu);
  });

  function datBanGhi(blob: Blob): void {
    if (url) URL.revokeObjectURL(url);
    url = URL.createObjectURL(blob);
    trang = 'xong';
  }

  function dungDem(): void {
    if (dem) clearInterval(dem);
    dem = null;
  }

  function demNguoc(giay: number, xong: () => void): void {
    dungDem();
    const het = Date.now() + giay * 1000;
    conLai = giay;
    dem = setInterval(() => {
      conLai = Math.max(0, Math.ceil((het - Date.now()) / 1000));
      if (conLai === 0) {
        dungDem();
        xong();
      }
    }, 200);
  }

  function batDau(): void {
    if (prepSec > 0) {
      trang = 'chuanBi';
      demNguoc(prepSec, () => void ghi());
    } else {
      void ghi();
    }
  }

  async function ghi(): Promise<void> {
    dungDem();
    const loi = loiGhiAm();
    if (loi) {
      thongBao = loi;
      trang = 'loi';
      return;
    }
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: { echoCancellation: true, noiseSuppression: true } });
    } catch (e) {
      thongBao =
        e instanceof DOMException && e.name === 'NotAllowedError'
          ? 'Bạn chưa cho web dùng micro. Bấm biểu tượng ổ khoá cạnh địa chỉ web, bật Micro rồi thử lại.'
          : 'Không mở được micro. Kiểm tra máy đã cắm micro hoặc chưa bị ứng dụng khác chiếm.';
      trang = 'loi';
      return;
    }
    const type = chonDinhDang((t) => MediaRecorder.isTypeSupported(t));
    // 32 kbps đủ rõ tiếng nói mà mỗi phút chỉ khoảng 240 KB trong bộ nhớ máy.
    rec = new MediaRecorder(stream, type ? { mimeType: type, audioBitsPerSecond: 32000 } : { audioBitsPerSecond: 32000 });
    manh = [];
    rec.ondataavailable = (e) => {
      if (e.data.size > 0) manh.push(e.data);
    };
    rec.onstop = () => {
      const blob = new Blob(manh, { type: rec?.mimeType || type || 'audio/webm' });
      dongMic();
      datBanGhi(blob);
      void luuBanGhi(attemptId, qid, blob);
      answer = 'ghi';
    };
    rec.start(250);
    trang = 'ghi';
    daNoi = 0;
    const tu = Date.now();
    dem = setInterval(() => {
      daNoi = Math.floor((Date.now() - tu) / 1000);
      if (daNoi >= answerSec) dung();
    }, 200);
    doMuc(stream);
  }

  /** Vạch âm lượng: người học thấy micro đang thu, khỏi nói xong mới biết máy không nghe. */
  function doMuc(s: MediaStream): void {
    try {
      ctx = new AudioContext();
      const an = ctx.createAnalyser();
      an.fftSize = 512;
      ctx.createMediaStreamSource(s).connect(an);
      const buf = new Uint8Array(an.fftSize);
      const ve = (): void => {
        an.getByteTimeDomainData(buf);
        let tong = 0;
        for (const v of buf) tong += ((v - 128) / 128) ** 2;
        muc = Math.min(1, Math.sqrt(tong / buf.length) * 4);
        raf = requestAnimationFrame(ve);
      };
      ve();
    } catch {
      // Không vẽ được vạch thì vẫn ghi âm bình thường.
    }
  }

  function dung(): void {
    dungDem();
    if (rec && rec.state !== 'inactive') rec.stop();
  }

  function dongMic(): void {
    cancelAnimationFrame(raf);
    muc = 0;
    void ctx?.close().catch(() => {});
    ctx = null;
    stream?.getTracks().forEach((t) => t.stop());
    stream = null;
  }

  function ghiLai(): void {
    trang = 'cho';
    answer = null;
  }

  onDestroy(() => {
    dungDem();
    if (rec && rec.state !== 'inactive') {
      rec.onstop = null;
      rec.stop();
    }
    dongMic();
    if (url) URL.revokeObjectURL(url);
  });
</script>

<div class="ga" class:ga-m={mobile}>
  <p class="ga-viec"><span class="zk-so">{TEN_NHIEM_VU[task] ?? 'Nói'}</span> {HUONG_DAN[task] ?? ''}</p>

  {#if payload.imageUrl}
    <img class="ga-hinh" src={payload.imageUrl} alt="Tranh để nói" width="300" height="225" />
  {/if}
  {#if audioText}
    <button type="button" class="ga-nghe" onclick={() => void speak(audioText)}>
      <Icon name="speaker" size={mobile ? 26 : 30} />
      <span>Nghe {task === 'repeat' ? 'câu cần nhắc lại' : 'câu hỏi'}</span>
    </button>
  {/if}
  {#if hienChu}
    <p class="hz ga-de" lang="zh" style="font-size: {mobile ? 22 : 26}px">{prompt}</p>
  {/if}
  {#if payload.vi && task !== 'repeat'}<p class="muted ga-goi-y">{payload.vi}</p>{/if}

  <div class="zk-o ga-may">
    {#if trang === 'cho'}
      <div class="ga-nut">
        <button type="button" class="btn btn-son" disabled={locked} onclick={batDau}><Icon name="mic" size={18} /> Bắt đầu</button>
        {#if prepSec > 0}<button type="button" class="btn" disabled={locked} onclick={() => void ghi()}>Ghi âm ngay</button>{/if}
      </div>
    {:else if trang === 'chuanBi'}
      <p class="ga-dem"><b class="num">{conLai}</b> giây chuẩn bị</p>
      <button type="button" class="btn btn-son" onclick={() => void ghi()}><Icon name="mic" size={18} /> Ghi luôn</button>
    {:else if trang === 'ghi'}
      <p class="ga-dang"><span class="ga-cham" aria-hidden="true"></span> Đang ghi <b class="num">{clock(daNoi * 1000)}</b> / {clock(answerSec * 1000)}</p>
      <span class="ga-vach" aria-hidden="true"><span style="width: {Math.round(muc * 100)}%"></span></span>
      <button type="button" class="btn btn-son" onclick={dung}><Icon name="stop" size={18} /> Dừng</button>
    {:else if trang === 'xong'}
      <audio class="ga-audio" controls src={url}></audio>
      {#if !locked}<button type="button" class="btn btn-sm" onclick={ghiLai}><Icon name="repeat" size={15} /> Ghi lại</button>{/if}
    {:else}
      <p class="ga-loi" role="alert">{thongBao}</p>
      <button type="button" class="btn btn-sm" onclick={() => (trang = 'cho')}>Thử lại</button>
    {/if}
  </div>

  {#if mau}
    <div class="ga-mau">
      <p class="kicker">Bài nói mẫu</p>
      {#if mau.sample}
        <p class="hz" lang="zh" style="font-size: {mobile ? 19 : 21}px">{mau.sample}</p>
        {#if mau.sampleVi}<p class="muted" style="font-size: 14px">{mau.sampleVi}</p>{/if}
        <button type="button" class="btn btn-sm" onclick={() => void speak(mau?.sample ?? '')}><Icon name="speaker" size={15} /> Nghe mẫu</button>
      {:else}
        <p class="muted" style="font-size: 14px">Câu này chưa có bài mẫu.</p>
      {/if}
      <p class="ga-chu">Nghe lại bản ghi của bạn và so với mẫu. Bạn tự thấy thế nào?</p>
      <div class="seg ga-tu">
        <button type="button" class:on={answer === 'tot'} onclick={() => (answer = 'tot')}>Tốt</button>
        <button type="button" class:on={answer === 'tam'} onclick={() => (answer = 'tam')}>Tạm được</button>
        <button type="button" class:on={answer === 'chua'} onclick={() => (answer = 'chua')}>Chưa được</button>
      </div>
    </div>
  {/if}
</div>

<style>
  .ga { display: flex; flex-direction: column; gap: 14px; }
  .ga-viec { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 15px; font-weight: 600; }
  .ga-nghe {
    display: inline-flex; align-items: center; gap: 10px; align-self: center; min-height: 52px; padding: 0 18px;
    border: 1px solid var(--line-3); border-radius: 999px; background: var(--surface); box-shadow: var(--sh-sm);
    color: var(--text); font-weight: 700; cursor: pointer;
  }
  .ga-nghe:hover { border-color: var(--ink-strong); }
  .ga-de { text-align: center; line-height: 1.5; }
  .ga-hinh { align-self: center; width: min(300px, 100%); height: auto; border: 1px solid var(--line); border-radius: 16px; background: #FFFDF8; }
  .ga-goi-y { text-align: center; font-size: 14px; }
  .ga-may { display: flex; flex-direction: column; align-items: center; gap: 12px; padding: 18px 16px; text-align: center; }
  .ga-chu { font-size: 14px; }
  .ga-nut { display: flex; gap: 8px; flex-wrap: wrap; justify-content: center; }
  .ga-dem { font-size: 15px; }
  .ga-dem b { font-size: 32px; margin-right: 4px; }
  .ga-dang { display: inline-flex; align-items: center; gap: 8px; font-size: 15px; }
  .ga-cham { width: 10px; height: 10px; border-radius: 50%; background: var(--son); animation: ga-nhay 1s ease-in-out infinite; }
  @keyframes ga-nhay { 50% { opacity: .25; } }
  .ga-vach { display: block; width: min(280px, 100%); height: 8px; border-radius: 999px; background: var(--track); overflow: hidden; }
  .ga-vach span { display: block; height: 100%; background: var(--ngoc, #2f9e6f); transition: width .08s linear; }
  .ga-audio { width: min(360px, 100%); }
  .ga-loi { color: var(--bad-text); font-size: 14px; max-width: 420px; }
  .ga-mau { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; padding: 14px 16px; border: 1px solid var(--ngoc-faint, var(--line)); border-radius: 14px; background: var(--ngoc-soft); }
  .ga-tu { width: 100%; }
  .ga-tu button { flex: 1 1 0; }
  .ga-m .ga-may { padding: 14px 12px; }
</style>
