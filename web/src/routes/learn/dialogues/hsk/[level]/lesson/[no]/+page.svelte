<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import { media } from '$lib/stores/media.svelte';
  import { settings } from '$lib/stores/settings.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import DauBai from '$lib/components/DauBai.svelte';
  import { completePart, lessonByNo, saveProgress } from '$lib/learn/lesson';
  import { speak } from '$lib/learn/speech';
  import type { LessonRef, NextLesson } from '$lib/api/types';

  // Đọc hội thoại (07 §10).
  interface Speaker { id: number; name: string; avatarChar: string; color: string; roleVi: string }
  interface Line { id: number; speakerId: number; zh: string; highlight: string | null; pinyin: string; vi: string; audioUrl: string | null }
  /** Một bài khoá (课文一, 课文二…): mỗi cảnh có nhân vật và lời thoại riêng. */
  interface BaiKhoa { id: number; no: number; title: string; scene: string; audioUrl: string | null; speakers: Speaker[]; lines: Line[] }
  interface Data {
    lesson: LessonRef;
    dialogues: BaiKhoa[];
    next: NextLesson | null;
  }

  const levelId = $derived(Number(page.params.level));
  const lessonNo = $derived(Number(page.params.no));

  let data = $state<Data | null>(null);
  let failed = $state(false);
  let dangDoc = $state<string>('');   // 'chỉ số bài khoá:chỉ số câu' đang đọc
  let showPy = $state(true);
  let showVi = $state(true);
  let done = $state(false);

  const khoi = $derived(data?.dialogues ?? []);
  const tongCau = $derived(khoi.reduce((n, k) => n + k.lines.length, 0));

  async function load(): Promise<void> {
    failed = false;
    data = null;
    try {
      const l = await lessonByNo(levelId, lessonNo);
      if (!l) { failed = true; return; }
      const res = await api.get<Data>(`/learn/lessons/${l.id}/dialogue`);
      data = res;
      dangDoc = '';
      done = false;
      showPy = settings.value.dialogue.showPinyin;
      showVi = settings.value.dialogue.showVi;
      heartbeat.setActivity({ part: 'ht', screen: 'do', level: levelId, lessonId: l.id, lessonNo: l.no });
    } catch {
      failed = true;
    }
  }

  onMount(load);
  $effect(() => { void levelId; void lessonNo; void load(); });

  /** Số câu đã đi qua tính từ đầu bài, để lưu tiến độ chung cho cả bài. */
  function viTri(ki: number, i: number): number {
    return khoi.slice(0, ki).reduce((n, k) => n + k.lines.length, 0) + i + 1;
  }

  function playLine(ki: number, i: number): void {
    dangDoc = `${ki}:${i}`;
    const line = khoi[ki]?.lines[i];
    // Chỉ phát bản thu thật; câu không có file thì chỉ đánh dấu đang đọc, không dùng giọng máy
    if (line?.audioUrl) void speak(line.zh, line.audioUrl);
    if (data) void saveProgress(data.lesson.id, 'ht', viTri(ki, i), tongCau).catch(() => {});
  }

  /** File thu âm cả bài khoá đang phát (giáo trình thu nguyên đoạn, không cắt theo câu). */
  let amThanh: HTMLAudioElement | null = null;
  let dangPhat = $state<number | null>(null);

  function dungPhat(): void {
    amThanh?.pause();
    amThanh = null;
    dangPhat = null;
  }

  onMount(() => dungPhat);

  /** Nghe cả một bài khoá bằng file thu âm của giáo trình (bấm lần nữa để dừng). Không có file thì nút ẩn. */
  function playKhoi(ki: number): void {
    const k = khoi[ki];
    if (!k?.audioUrl) return;
    const dangLaKhoiNay = dangPhat === ki;
    dungPhat();
    dangDoc = ''; // nghe cả bài thì bỏ tô câu vừa bấm, không thì như đang đọc riêng câu đó
    if (dangLaKhoiNay) return;
    const a = new Audio(k.audioUrl);
    amThanh = a;
    dangPhat = ki;
    a.onended = () => { if (amThanh === a) dungPhat(); };
    void a.play().catch(() => { if (amThanh === a) dungPhat(); });
  }

  async function finish(): Promise<void> {
    if (!data) return;
    try {
      await completePart(data.lesson.id, 'ht');
      done = true;
      toast.ok('Đã xong bài hội thoại.');
    } catch {
      toast.show('Chưa lưu được tiến độ.');
    }
  }

  const speakerOf = (k: BaiKhoa, id: number): Speaker | undefined => k.speakers.find((s) => s.id === id);

  /** Tô sáng từ mới trong câu. */
  function parts(line: Line): [string, string, string] {
    if (!line.highlight || !line.zh.includes(line.highlight)) return [line.zh, '', ''];
    const i = line.zh.indexOf(line.highlight);
    return [line.zh.slice(0, i), line.highlight, line.zh.slice(i + line.highlight.length)];
  }
</script>

<svelte:head><title>{data ? `${data.lesson.title} · Hội thoại` : 'Hội thoại'} · Zìkǎ</title></svelte:head>

<!-- Một khung chứa trọn bài như Lật thẻ: hàng Pinyin | Nghĩa ở đầu khung, các bài khoá ngăn nhau
     bằng đường kẻ, nút “Đã đọc xong” ở hàng cuối khung. -->
{#snippet khung(m: boolean)}
  <section class="block zk-khung" class:zk-khung-m={m}>
    {#if khoi.length === 0}
      <EmptyState title="Bài này chưa có hội thoại." />
    {:else}
      <!-- Hàng công cụ của cả bài: chỉ còn bật/tắt pinyin và nghĩa. -->
      <div class="zk-khung-dau">
        <h2 class="zk-khung-ten">{khoi.length > 1 ? `${khoi.length} bài khoá` : 'Bài khoá'}</h2>
        <div class="seg">
          <button type="button" class:on={showPy} aria-pressed={showPy} onclick={() => (showPy = !showPy)}>Pinyin</button>
          <button type="button" class:on={showVi} aria-pressed={showVi} onclick={() => (showVi = !showVi)}>Nghĩa</button>
        </div>
      </div>

      <!-- Mỗi bài khoá một phần riêng: đọc tới đâu biết đang ở cảnh nào. -->
      {#each khoi as k, ki (k.id)}
        <div class="ht-khoi">
          <div class="ht-tren">
            <span class="ht-so">{ki + 1}</span>
            <div style="flex: 1 1 180px; min-width: 0">
              <h3 style="font-size: {m ? 16 : 18}px; line-height: 1.3">{k.title}</h3>
              {#if k.scene}
                <p class="ht-mota">{k.scene}</p>
              {/if}
            </div>
            {#if k.audioUrl}
              <button type="button" class="btn btn-sm ht-nghe" class:ht-nghe-m={m} onclick={() => playKhoi(ki)} aria-label="{dangPhat === ki ? 'Dừng' : 'Nghe'} bài khoá {ki + 1}">
                <Icon name="speaker" size={16} /> {dangPhat === ki ? 'Dừng' : 'Nghe'}
              </button>
            {/if}
          </div>

          <ul style="display: flex; flex-direction: column; gap: 10px">
            {#each k.lines as line, i (line.id)}
              {@const sp = speakerOf(k, line.speakerId)}
              {@const [pre, hl, post] = parts(line)}
              <li style="display: flex; gap: 10px">
                <span class="who hz" lang="zh" style="background: {sp?.color ?? 'var(--surface)'}{sp?.color ? '; color: #1F2430' : ''}">{sp?.avatarChar ?? '?'}</span>
                <button
                  type="button"
                  class="bub"
                  class:now={dangDoc === `${ki}:${i}`}
                  style="flex: 1 1 auto; min-width: 0; max-width: 86%; text-align: left; cursor: pointer"
                  onclick={() => playLine(ki, i)}
                >
                  {#if sp}<span class="muted" style="font-size: 12px; font-weight: 700">{sp.name}{sp.roleVi ? ` · ${sp.roleVi}` : ''}</span>{/if}
                  <span class="hz" lang="zh" style="font-size: {m ? 19 : 22}px">{pre}{#if hl}<b class="hl">{hl}</b>{/if}{post}</span>
                  {#if showPy && line.pinyin}<span class="muted" style="font-size: 13.5px">{line.pinyin}</span>{/if}
                  {#if showVi && line.vi}<span style="font-size: 14px">{line.vi}</span>{/if}
                </button>
              </li>
            {/each}
          </ul>
        </div>
      {/each}

      <div class="zk-chan">
        {#if done}
          {#if data?.next}
            <a href="/learn/dialogues/hsk/{levelId}/lesson/{data.next.no}" class="btn btn-son btn-lg ht-xong" class:ht-xong-m={m}>
              <span class="ht-cat">Bài tiếp: {data.next.title}</span>
            </a>
          {:else}
            <a href="/learn/dialogues/hsk/{levelId}" class="btn btn-son btn-lg ht-xong" class:ht-xong-m={m}>Về danh sách bài</a>
          {/if}
        {:else}
          <button type="button" class="btn btn-son btn-lg ht-xong" class:ht-xong-m={m} onclick={finish}><Icon name="check" size={18} /> Đã đọc xong</button>
        {/if}
      </div>
    {/if}
  </section>
{/snippet}

{#snippet loi()}
  <EmptyState title="Bài này chưa có hội thoại.">
    {#snippet action()}<a href="/learn/dialogues/hsk/{levelId}" class="btn btn-sm">Về danh sách bài</a>{/snippet}
  </EmptyState>
{/snippet}

{#if media.isMobile}
  <!-- ============ Điện thoại ============ -->
  <div class="mscreen">
    <header class="mtop">
      <a href="/learn/dialogues/hsk/{levelId}" class="btn btn-bare" aria-label="Về danh sách bài"><Icon name="left" size={20} /></a>
      <span style="flex: 1; min-width: 0; text-align: center">
        <b style="display: block; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis">{data?.lesson.title ?? 'Hội thoại'}</b>
        <span class="muted" style="font-size: 12px">Hội thoại · HSK {levelId} · Bài {lessonNo}</span>
      </span>
      <Avatar size={36} />
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 12px">
      {#if failed}
        {@render loi()}
      {:else if data === null}
        <Skeleton h="420px" r={22} />
      {:else}
        {@render khung(true)}
      {/if}
    </main>
  </div>
{:else}
  <!-- ============ Máy tính ============ -->
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <nav class="crumb" style="margin-left: 8px">
      <a href="/learn/dialogues/hsk/{levelId}">Hội thoại · HSK {levelId}</a> / <b>Bài {lessonNo}{data ? ` · ${data.lesson.title}` : ''}</b>
    </nav>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 12px"><Switcher current="ht" /><Avatar size={44} /></div>
  </header>
  <main class="page zk-cot" style="display: flex; flex-direction: column; gap: 18px">
    {#if failed}
      {@render loi()}
    {:else if data === null}
      <Skeleton h="520px" r={22} />
    {:else}
      <DauBai href="/learn/dialogues/hsk/{levelId}" kicker="HSK {levelId} · Bài {lessonNo} · {tongCau} câu" title={data.lesson.title} />
      {@render khung(false)}
    {/if}
  </main>
{/if}

<style>
  /* Bài khoá ngăn nhau (và ngăn với hàng Pinyin | Nghĩa) bằng một đường kẻ, như các phần ở Lật thẻ. */
  .ht-khoi {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding-top: 18px;
    border-top: 1px solid var(--line);
  }
  .ht-tren {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    flex-wrap: wrap;
  }
  /* Số bài khoá: người đọc liếc là biết đang ở cảnh thứ mấy. */
  .ht-so {
    flex: none;
    display: grid;
    place-items: center;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--son-soft);
    color: var(--son-text);
    font-weight: 800;
    font-size: 14px;
  }
  .ht-nghe {
    flex: none;
  }
  /* Điện thoại: đủ 44px cho ngón tay. */
  .ht-nghe-m {
    min-height: 44px;
  }
  /* Hiện đủ tình huống, xuống dòng tự nhiên trên mọi khổ màn hình. */
  .ht-mota {
    margin: 2px 0 0;
    font-size: 13.5px;
    line-height: 1.5;
    color: var(--text-2);
  }
  /* Nút cuối khung nằm bên phải; điện thoại thì trải hết bề ngang cho ngón cái. */
  .ht-xong {
    margin-left: auto;
    min-width: 0;
    max-width: 100%;
  }
  .ht-xong-m {
    flex: 1 1 100%;
  }
  /* Tên bài tiếp có thể dài: cắt bớt thay vì đẩy nút tràn ngang. */
  .ht-cat {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
  }
</style>
