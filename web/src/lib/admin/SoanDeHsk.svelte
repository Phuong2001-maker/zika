<script lang="ts">
  import { api, ApiError } from '$lib/api/client';
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Skeleton from '$lib/components/Skeleton.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import Modal from '$lib/components/Modal.svelte';
  import QuestionForm from '$lib/admin/QuestionForm.svelte';
  import ImportModal from '$lib/admin/ImportModal.svelte';
  import { adminApi, type AdminQuestion, type Draft } from '$lib/admin/api';
  import { questionsImport, type ImportMode, type ImportOutcome, type QuestionRow } from '$lib/admin/import';
  import { TYPE_LABEL } from '$lib/utils/grade';
  import { PHAN, TEN_PHAN, moTaPhan, type Phan } from '$lib/learn/thi-hsk/cau-truc';

  // Màn soạn đề khung HSK 3.0, dùng chung cho đề Thi thử HSK 3.0 (/admin/hsk30/[id]) và đề cuộc thi (/admin/exams/[id]):
  // câu gom theo phần Nghe / Đọc / Viết / Nói, mỗi phần chỉ cho các dạng câu hợp với nó. Đề cuộc thi không có công tắc
  // Công khai (luôn Nháp, chỉ mở trong giờ thi) và không đặt thời gian (lấy số phút của cuộc thi).
  let { deId, cuocThi = false }: { deId: number; cuocThi?: boolean } = $props();

  interface De { id: number; levelId: number; title: string; status: 'draft' | 'published'; durationSec: number; kind: string }

  const DANG: Record<Phan, [string, string][]> = {
    listening: [['listen', 'Nghe và chọn'], ['pic', 'Nghe chọn tranh']],
    reading: [['pic', 'Đọc chọn tranh'], ['blank', 'Chọn từ điền câu'], ['pick', 'Chọn câu đúng'], ['tf', 'Đúng / Sai'], ['match', 'Nối từ']],
    writing: [['order', 'Sắp xếp câu'], ['toZh', 'Chọn câu tiếng Trung'], ['blank', 'Chọn từ điền câu']],
    speaking: [['speak', 'Nói']],
  };
  /** Tên dạng câu theo phần: câu chọn tranh ở phần Đọc là "Đọc chọn tranh", không phải "Nghe chọn tranh". */
  const tenDang = (t: string, phan?: string | null): string =>
    DANG[(phan ?? 'reading') as Phan]?.find(([c]) => c === t)?.[1] ?? Object.values(DANG).flat().find(([c]) => c === t)?.[1] ?? TYPE_LABEL[t] ?? t;
  /** Dạng câu cho ô chọn: dạng của phần, cộng dạng cũ của câu đang sửa (đề cuộc thi soạn trước 10/11 có thể có
   *  câu Chọn nghĩa, Thanh điệu…) để mở ra sửa được mà không mất dạng. */
  const dangCho = (d: Draft): [string, string][] => {
    const ds = DANG[(d.section ?? 'reading') as Phan];
    return ds.some(([c]) => c === d.type) ? ds : [...ds, [d.type, TYPE_LABEL[d.type] ?? d.type]];
  };

  const spec = questionsImport('hsk30');

  let de = $state<De | null>(null);
  let cau = $state<AdminQuestion[]>([]);
  let loi = $state(false);
  let draft = $state<Draft | null>(null);
  let errors = $state<Record<string, string>>({});
  let dirty = $state(false);
  let saving = $state(false);
  let formKey = $state(0);
  let suaMo = $state(false);
  let suaTen = $state('');
  let suaPhut = $state(0);
  let nhapMo = $state(false);

  const theoPhan = $derived(PHAN.map((p) => ({ ...p, ds: cau.filter((q) => (q.section ?? 'reading') === p.code) })));
  const soCau = $derived(Object.fromEntries(theoPhan.map((p) => [p.code, p.ds.length])));
  const quayVe = $derived(
    cuocThi || !de ? { href: '/admin/exams', label: 'Đề thi' } : { href: `/admin/hsk30?cap=${de.levelId}`, label: `Đề HSK 3.0 · HSK ${de.levelId}` },
  );

  function trong(phan: Phan): Draft {
    const type = DANG[phan][0][0];
    const payload: Draft['payload'] =
      type === 'speak'
        ? { task: 'answer', prompt: '', audioText: '', vi: '', prepSec: 10, answerSec: 30, sample: '', sampleVi: '', imageUrl: null }
        : type === 'order'
          ? { vi: '', answer: '', tiles: [] }
          : { audioText: '', ask: '', sentence: '', vi: '', showMeaning: false, options: type === 'pic' ? ['', '', ''] : ['', '', '', ''], answer: 0, imageUrl: null };
    return { id: null, type, payload, explain: '', score: 1, section: phan };
  }

  async function tai(): Promise<void> {
    loi = false;
    try {
      const r = await api.get<{ mock: De; questions: AdminQuestion[] }>(`/admin/mock-tests/${deId}/questions`);
      de = r.mock;
      cau = r.questions;
      dirty = false;
      chon(r.questions[0] ?? null, 'listening', true);
    } catch {
      loi = true;
    }
  }
  // Đổi đề mà vẫn ở cùng route thì SvelteKit không dựng lại trang (bẫy số 2).
  $effect(() => {
    void deId;
    void tai();
  });

  function chon(q: AdminQuestion | null, phan: Phan = 'listening', boQua = false): void {
    if (!boQua && dirty && !confirm('Câu đang sửa chưa lưu. Bỏ thay đổi?')) return;
    errors = {};
    dirty = false;
    formKey++;
    draft = q === null ? trong(phan) : { id: q.id, type: q.type, payload: $state.snapshot(q.payload), explain: q.explain ?? '', score: q.score, section: q.section ?? 'reading' };
  }

  function doiPhan(phan: Phan): void {
    if (!draft || draft.section === phan) return;
    // Đổi phần thì dạng câu phải hợp với phần mới: câu mới dựng lại khung, câu cũ đổi dạng về dạng đầu của phần.
    if (draft.id === null) {
      draft = trong(phan);
      formKey++;
    } else {
      draft.section = phan;
      if (!DANG[phan].some(([c]) => c === draft!.type)) {
        draft = { ...trong(phan), id: draft.id };
        formKey++;
      }
    }
    dirty = true;
  }

  async function luu(themTiep = false): Promise<void> {
    if (!draft) return;
    saving = true;
    errors = {};
    try {
      const body = { type: draft.type, payload: draft.payload, explain: draft.explain, score: draft.score, section: draft.section };
      const daLuu =
        draft.id === null
          ? await api.post<AdminQuestion>(`/admin/mock-tests/${deId}/questions`, body)
          : await adminApi.updateQuestion(draft.id, body);
      cau = draft.id === null ? [...cau, daLuu] : cau.map((q) => (q.id === daLuu.id ? daLuu : q));
      dirty = false;
      toast.ok('Đã lưu.');
      const phan = (draft.section ?? 'reading') as Phan;
      chon(themTiep ? null : daLuu, phan, true);
    } catch (e) {
      if (e instanceof ApiError) {
        errors = e.fields;
        toast.show(Object.values(e.fields)[0] ?? e.message);
      } else toast.show('Không lưu được.');
    } finally {
      saving = false;
    }
  }

  async function xoaCau(): Promise<void> {
    if (!draft?.id || !confirm('Xoá câu này?')) return;
    const id = draft.id;
    try {
      await adminApi.deleteQuestion(id);
      cau = cau.filter((q) => q.id !== id);
      chon(cau[0] ?? null, 'listening', true);
      toast.ok('Đã xoá.');
    } catch {
      toast.show('Không xoá được.');
    }
  }

  async function doiTrangThai(): Promise<void> {
    if (!de) return;
    const moi = de.status === 'published' ? 'draft' : 'published';
    if (moi === 'published' && cau.length === 0) {
      toast.show('Đề chưa có câu nào.');
      return;
    }
    try {
      await api.patch(`/admin/mock-tests/${de.id}`, { status: moi });
      de = { ...de, status: moi };
      toast.ok(moi === 'published' ? 'Đã công khai đề.' : 'Đã chuyển về Nháp.');
    } catch {
      toast.show('Không đổi được trạng thái.');
    }
  }

  function moSua(): void {
    if (!de) return;
    suaTen = de.title;
    suaPhut = Math.round(de.durationSec / 60);
    suaMo = true;
  }

  async function luuThongTin(): Promise<void> {
    if (!de) return;
    try {
      const body = cuocThi ? { title: suaTen } : { title: suaTen, durationSec: Math.round(suaPhut * 60) };
      await api.patch(`/admin/mock-tests/${de.id}`, body);
      de = { ...de, title: suaTen.trim(), durationSec: cuocThi ? de.durationSec : Math.round(suaPhut * 60) };
      suaMo = false;
      toast.ok('Đã lưu.');
    } catch (e) {
      toast.show(e instanceof ApiError ? (Object.values(e.fields)[0] ?? e.message) : 'Không lưu được.');
    }
  }

  async function nhap(items: QuestionRow[], mode: ImportMode): Promise<ImportOutcome> {
    const out = await api.post<ImportOutcome>(`/admin/mock-tests/${deId}/questions/bulk`, { mode, items });
    dirty = false;
    await tai();
    return out;
  }
</script>

<svelte:head><title>{de?.title ?? 'Soạn đề'} · {cuocThi ? 'Đề thi' : 'Đề HSK 3.0'} · Quản trị · Zìkǎ</title></svelte:head>

{#if loi}
  <EmptyState title="Không tải được đề.">
    {#snippet action()}<button type="button" class="btn btn-sm" onclick={tai}>Thử lại</button>{/snippet}
  </EmptyState>
{:else if de === null}
  <Skeleton h="320px" r={16} />
{:else}
  <div class="head">
    <div style="min-width: 0">
      <a class="muted back" href={quayVe.href}><Icon name="chev" size={15} style="transform: rotate(180deg)" /> {quayVe.label}</a>
      <h1>{de.title}</h1>
      <p class="muted num" style="font-size: 14px">
        {cau.length} câu · {moTaPhan(soCau) || 'chưa có câu'} · {cuocThi ? 'thời gian theo cuộc thi' : `${Math.round(de.durationSec / 60)} phút`}
      </p>
    </div>
    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap">
      <button type="button" class="btn btn-sm" onclick={() => (nhapMo = true)}><Icon name="upload" size={15} /> Nhập Excel</button>
      <button type="button" class="btn btn-sm" onclick={moSua}><Icon name="edit" size={15} /> {cuocThi ? 'Đổi tên' : 'Sửa tên, thời gian'}</button>
      {#if cuocThi}
        <span class="tag" title="Đề cuộc thi chỉ mở cho người đã đăng ký, trong giờ thi">Nháp · mở trong giờ thi</span>
      {:else}
        <label class="sw" style="margin: 0">
          <input type="checkbox" checked={de.status === 'published'} onchange={doiTrangThai} aria-label="Công khai đề" />
          <span></span> {de.status === 'published' ? 'Công khai' : 'Nháp'}
        </label>
      {/if}
    </div>
  </div>

  <div class="cols">
    <aside class="paper list">
      {#each theoPhan as p (p.code)}
        <div class="list-head">
          <b><Icon name={p.icon} size={15} /> {p.ten} <span class="muted num" style="font-weight: 500">{p.ds.length}</span></b>
          <button type="button" class="btn btn-sm btn-paper" onclick={() => chon(null, p.code)}><Icon name="plus" size={15} /> Thêm</button>
        </div>
        <ul class="qlist">
          {#each p.ds as q (q.id)}
            <li>
              <button type="button" class="les qrow" class:on={draft?.id === q.id} onclick={() => chon(q, p.code)}>
                <span class="qno">{cau.indexOf(q) + 1}</span>
                <span style="min-width: 0">
                  <span class="qtype">{tenDang(q.type, q.section)}</span>
                  <span class="hz qprompt" lang="zh">{q.promptText || '—'}</span>
                </span>
              </button>
            </li>
          {/each}
        </ul>
      {/each}
    </aside>

    <section class="block form">
      {#if draft}
        <h2 class="h2">{draft.id === null ? 'Câu mới' : 'Sửa câu'} · {TEN_PHAN[draft.section ?? 'reading']}</h2>
        <div class="field">
          <span class="lbl">Phần</span>
          <div class="seg" role="group" aria-label="Phần của câu">
            {#each PHAN as p (p.code)}
              <button type="button" class:on={draft.section === p.code} onclick={() => doiPhan(p.code)}>{p.ten}</button>
            {/each}
          </div>
        </div>
        {#key formKey}
          <QuestionForm bind:draft={draft!} types={dangCho(draft)} {errors} hinh onchange={() => (dirty = true)} />
        {/key}
        <div class="form-foot">
          {#if draft.id !== null}
            <button type="button" class="btn btn-danger btn-sm" onclick={xoaCau}><Icon name="trash" size={15} /> Xoá câu</button>
          {/if}
          <span style="flex: 1"></span>
          <button type="button" class="btn btn-sm" disabled={saving} onclick={() => luu(true)}>Lưu &amp; thêm câu</button>
          <button type="button" class="btn btn-son btn-sm" disabled={saving} onclick={() => luu(false)}>{saving ? 'Đang lưu…' : 'Lưu câu'}</button>
        </div>
      {/if}
    </section>
  </div>
{/if}

{#if suaMo}
  <Modal title="Sửa đề" onclose={() => (suaMo = false)} width={460}>
    <div class="field">
      <label class="lbl" for="hs-sua-ten">Tên đề</label>
      <input id="hs-sua-ten" class="inp" bind:value={suaTen} maxlength="80" />
    </div>
    {#if !cuocThi}
      <div class="field">
        <label class="lbl" for="hs-sua-phut">Thời gian làm bài (phút)</label>
        <input id="hs-sua-phut" class="inp num" type="number" min="1" max="180" bind:value={suaPhut} />
      </div>
    {/if}
    {#snippet actions()}
      <button type="button" class="btn btn-sm" onclick={() => (suaMo = false)}>Huỷ</button>
      <button type="button" class="btn btn-son btn-sm" disabled={suaTen.trim() === ''} onclick={luuThongTin}>Lưu</button>
    {/snippet}
  </Modal>
{/if}

{#if nhapMo}
  <ImportModal {spec} replaceNote="Xoá hết câu đang có của đề này rồi nhập mới." onclose={() => (nhapMo = false)} onrun={nhap} />
{/if}

<style>
  .head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 14px; }
  .head h1 { font-size: 28px; margin-top: 4px; }
  .back { display: inline-flex; align-items: center; gap: 4px; font-size: 13px; text-decoration: none; }
  .cols { display: flex; flex-wrap: wrap; gap: 22px; align-items: flex-start; }
  .list { flex: 1 1 320px; min-width: 0; max-width: 380px; padding: 16px; display: flex; flex-direction: column; gap: 10px; max-height: 78vh; overflow-y: auto; }
  @media (max-width: 899px) { .list { max-width: none; max-height: none; } }
  .list-head { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding-top: 4px; }
  .list-head b { display: inline-flex; align-items: center; gap: 6px; }
  .qlist { display: flex; flex-direction: column; gap: 6px; }
  .qrow { flex-direction: row; align-items: center; gap: 10px; width: 100%; }
  .qno { width: 26px; height: 26px; flex: none; display: grid; place-items: center; border-radius: 7px; background: var(--surface-2); border: 1px solid var(--ink-strong); font-size: 12.5px; font-weight: 700; color: var(--text-2); }
  .qtype { display: block; font-size: 11.5px; font-weight: 700; color: var(--text-3); }
  .qprompt { display: block; font-size: 14.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .form { flex: 999 1 520px; min-width: 0; padding: 20px 22px; display: flex; flex-direction: column; gap: 14px; }
  .field { display: flex; flex-direction: column; gap: 6px; }
  .form-foot { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding-top: 6px; border-top: 1px solid var(--line); }
</style>
