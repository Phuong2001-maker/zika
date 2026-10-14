<script lang="ts" generics="T">
  import Modal from '$lib/components/Modal.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { buildXlsx, download, readDelimited, readWorkbook, XlsxError } from '$lib/admin/xlsx';
  import { sampleRows, type ImportMode, type ImportOutcome, type ImportSpec, type ParseResult } from '$lib/admin/import';

  // Hộp thoại nhập Excel dùng chung cho cả 6 phần soạn bài (08 §7–§12).
  // Mọi thứ riêng của từng phần nằm trong `spec`; ở đây chỉ lo đọc file, xem trước và báo lỗi.
  let {
    spec,
    allowReplace = true,
    replaceNote = 'Xoá hết nội dung đang có của phần này rồi nhập mới.',
    onclose,
    onrun,
  }: {
    spec: ImportSpec<T>;
    /** Phần nào không cho ghi đè thì tắt (ví dụ khi dữ liệu còn chỗ khác tham chiếu tới). */
    allowReplace?: boolean;
    replaceNote?: string;
    onclose: () => void;
    onrun: (items: T[], mode: ImportMode) => Promise<ImportOutcome>;
  } = $props();

  let result = $state<ParseResult<T> | null>(null);
  let source = $state('');
  let pasted = $state('');
  let pasteOpen = $state(false);
  let guideOpen = $state(false);
  let busy = $state(false);
  let error = $state('');
  let mode = $state<ImportMode>('append');
  let outcome = $state<ImportOutcome | null>(null);

  const count = $derived(result?.items.length ?? 0);
  const label = $derived(result?.summary ?? `${count} dòng`);

  function apply(sheets: Parameters<ImportSpec<T>['parse']>[0], from: string): void {
    error = '';
    source = from;
    try {
      result = spec.parse(sheets);
      if (result.items.length === 0 && result.issues.length === 0) {
        error = 'Không đọc được dòng dữ liệu nào. Kiểm tra lại dòng tiêu đề của bảng.';
      }
    } catch {
      result = null;
      error = 'Không đọc được nội dung bảng.';
    }
  }

  async function onFile(e: Event): Promise<void> {
    const input = e.currentTarget as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    busy = true;
    try {
      apply(await readWorkbook(file), file.name);
    } catch (err) {
      result = null;
      error = err instanceof XlsxError ? err.message : 'Không mở được file. Hãy thử lưu lại dạng .xlsx hoặc .csv.';
    } finally {
      busy = false;
      // Cho phép chọn lại đúng file đó sau khi sửa.
      input.value = '';
    }
  }

  function usePaste(): void {
    if (pasted.trim() === '') return;
    apply(readDelimited(pasted), 'nội dung đã dán');
  }

  function template(): void {
    download(
      buildXlsx(spec.sheets.map((s) => ({ name: s.name, rows: sampleRows(s) }))),
      spec.fileName,
    );
  }

  async function run(): Promise<void> {
    if (!result || count === 0) return;
    busy = true;
    error = '';
    try {
      outcome = await onrun(result.items, mode);
    } catch (e) {
      error = e instanceof Error && e.message ? e.message : 'Không nhập được, thử lại nhé.';
    } finally {
      busy = false;
    }
  }

  function reset(): void {
    result = null;
    outcome = null;
    error = '';
    pasted = '';
    source = '';
  }
</script>

<Modal title={spec.title} {onclose} width={760}>
  {#if outcome}
    <!-- ============ Đã nhập xong ============ -->
    <div class="xong">
      <span class="tag tag-ok"><Icon name="check" size={14} /> Đã thêm {outcome.created}</span>
      {#if outcome.strokeFetched !== undefined}
        <span class="tag">Tải được nét cho {outcome.strokeFetched} chữ</span>
      {/if}
      {#if outcome.skipped.length > 0}
        <span class="tag tag-warn">Bỏ qua {outcome.skipped.length}</span>
      {/if}
    </div>

    {#if outcome.skipped.length > 0}
      <ul class="loi">
        {#each outcome.skipped.slice(0, 40) as s, i (i)}
          <li><b>{s.hanzi ?? `Dòng ${s.row ?? i + 1}`}</b> — {s.reason}</li>
        {/each}
      </ul>
      {#if outcome.skipped.length > 40}
        <p class="muted" style="font-size: 13px">…và {outcome.skipped.length - 40} mục nữa.</p>
      {/if}
    {/if}

    {#if outcome.strokeFetched !== undefined && outcome.created > outcome.strokeFetched}
      <p class="muted" style="font-size: 13px">
        Chữ chưa có dữ liệu nét sẽ hiện dấu cảnh báo trong danh sách — bấm tải lại ở từng chữ là xong.
      </p>
    {/if}
  {:else if result}
    <!-- ============ Xem trước ============ -->
    <div class="xong">
      <span class="tag tag-ok">Đọc được {label}</span>
      <span class="muted" style="font-size: 13px">từ {source}</span>
      <span class="grow"></span>
      <button type="button" class="btn btn-bare btn-sm" onclick={reset}>Chọn file khác</button>
    </div>

    {#if result.preview.cols.length > 0}
      <div class="xem">
        <table class="tbl">
          <thead>
            <tr>{#each result.preview.cols as c, i (i)}<th>{c}</th>{/each}</tr>
          </thead>
          <tbody>
            {#each result.preview.rows as row, i (i)}
              <tr>{#each result.preview.cols as _, c (c)}<td>{row[c] ?? ''}</td>{/each}</tr>
            {/each}
          </tbody>
        </table>
      </div>
    {/if}

    {#if result.issues.length > 0}
      <details open={count === 0}>
        <summary class="tomtat">
          <span class="tag tag-warn">{result.issues.length} dòng có vấn đề</span>
          <span class="muted" style="font-size: 13px">— các dòng còn lại vẫn nhập được</span>
        </summary>
        <ul class="loi">
          {#each result.issues.slice(0, 40) as issue, i (i)}
            <li><b>{issue.row > 0 ? `Dòng ${issue.row}` : issue.sheet}</b> — {issue.message}</li>
          {/each}
        </ul>
        {#if result.issues.length > 40}
          <p class="muted" style="font-size: 13px">…và {result.issues.length - 40} dòng nữa.</p>
        {/if}
      </details>
    {/if}

    {#if allowReplace && count > 0}
      <fieldset class="chedo">
        <legend class="muted">Cách nhập</legend>
        <label class="chk">
          <input type="radio" name="zk-mode" value="append" bind:group={mode} />
          Thêm vào cuối danh sách đang có
        </label>
        <label class="chk">
          <input type="radio" name="zk-mode" value="replace" bind:group={mode} />
          {replaceNote}
        </label>
      </fieldset>
    {/if}

    {#if error}<p class="err-box">{error}</p>{/if}
  {:else}
    <!-- ============ Chọn nguồn ============ -->
    <p class="muted" style="font-size: 14px">{spec.intro}</p>

    <div class="nguon">
      <label class="btn btn-son">
        <Icon name="upload" size={16} /> Chọn file Excel
        <input type="file" accept=".xlsx,.csv,.tsv,.txt,text/csv" onchange={onFile} disabled={busy} />
      </label>
      <button type="button" class="btn btn-paper" onclick={template}>
        <Icon name="download" size={16} /> Tải file mẫu
      </button>
      <span class="grow"></span>
      <button type="button" class="btn btn-bare btn-sm" onclick={() => (pasteOpen = !pasteOpen)}>
        {pasteOpen ? 'Ẩn ô dán' : 'Hoặc dán từ Excel'}
      </button>
    </div>

    {#if pasteOpen}
      <div>
        <textarea
          class="inp"
          rows="5"
          bind:value={pasted}
          placeholder="Bôi đen vùng bảng trong Excel, Ctrl+C rồi dán vào đây (nhớ dán cả dòng tiêu đề)."
        ></textarea>
        <div style="margin-top: 8px">
          <button type="button" class="btn btn-sm" disabled={pasted.trim() === ''} onclick={usePaste}>Đọc nội dung đã dán</button>
        </div>
      </div>
    {/if}

    {#if error}<p class="err-box">{error}</p>{/if}

    <details bind:open={guideOpen}>
      <summary class="tomtat"><b style="font-size: 14px">Cách điền từng cột</b></summary>
      {#each spec.sheets as s (s.name)}
        <div class="huong">
          <p class="ten">
            Sheet <b>{s.name}</b>{#if s.optional}<span class="muted"> · không bắt buộc</span>{/if}
          </p>
          {#if s.about}<p class="muted" style="font-size: 13px">{s.about}</p>{/if}
          <dl>
            {#each s.columns as c (c.key)}
              <div>
                <dt>{c.header}</dt>
                <dd class="muted">{c.hint ?? ''}</dd>
              </div>
            {/each}
          </dl>
        </div>
      {/each}
    </details>
  {/if}

  {#snippet actions()}
    {#if outcome}
      <button type="button" class="btn btn-son" onclick={onclose}>Xong</button>
    {:else}
      <button type="button" class="btn" onclick={onclose}>Huỷ</button>
      <button type="button" class="btn btn-son" disabled={busy || count === 0} onclick={run}>
        {busy ? 'Đang nhập…' : count > 0 ? `Nhập ${label}` : 'Nhập'}
      </button>
    {/if}
  {/snippet}
</Modal>

<style>
  .nguon {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }
  /* Ô chọn file thật bị ẩn, cả nhãn đóng vai nút. */
  .nguon input[type='file'] {
    position: absolute;
    width: 1px;
    height: 1px;
    opacity: 0;
    pointer-events: none;
  }
  .nguon label {
    cursor: pointer;
  }

  .xong {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
  }

  .xem {
    overflow-x: auto;
    border: 1px solid var(--line);
    border-radius: var(--r);
    max-height: 240px;
  }
  .xem td {
    max-width: 260px;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .xem tr > :last-child {
    width: 100%;
  }

  .loi {
    display: flex;
    flex-direction: column;
    gap: 4px;
    max-height: 190px;
    overflow-y: auto;
    margin-top: 8px;
    font-size: 13.5px;
    line-height: 1.5;
  }
  .loi b {
    color: var(--warn-text);
  }

  .tomtat {
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 2px 0;
  }

  .chedo {
    border: 1px solid var(--line);
    border-radius: var(--r);
    padding: 4px 14px 10px;
  }
  .chedo legend {
    font-size: 12px;
    font-weight: 700;
    padding: 0 6px;
  }
  .chedo input {
    width: 18px;
    height: 18px;
    accent-color: var(--son);
    flex: none;
  }

  .huong {
    margin-top: 12px;
  }
  .huong .ten {
    font-size: 14px;
  }
  .huong dl {
    margin-top: 8px;
    display: grid;
    grid-template-columns: minmax(110px, auto) 1fr;
    gap: 2px 14px;
    font-size: 13px;
  }
  .huong dl > div {
    display: contents;
  }
  .huong dt {
    font-weight: 700;
    white-space: nowrap;
  }
  .huong dd {
    min-width: 0;
  }
</style>
