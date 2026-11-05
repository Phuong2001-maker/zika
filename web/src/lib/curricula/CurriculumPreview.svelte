<script lang="ts">
  import { onMount } from 'svelte';
  import Modal from '$lib/components/Modal.svelte';
  import Select from '$lib/components/Select.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { PARTS } from '$lib/parts';
  import { toast } from '$lib/stores/toast.svelte';
  import { curricula, DEMO_LEARNERS, type Curriculum } from './demo.svelte';
  import { SO_HAN, taiChuBut } from '$lib/learn/hoa-tiet';

  let { admin = false, onaccess }: { admin?: boolean; onaccess?: () => void } = $props();
  let query = $state('');
  let filter = $state('all');
  let selectedId = $state<string | null>(null);
  let level = $state(1);
  let lesson = $state<number | null>(null);
  let part = $state('lt');
  let edit = $state<Curriculum | null>(null);
  let error = $state('');
  let showSimulation = $state(false);
  const selected = $derived(curricula.items.find((c) => c.id === selectedId && (admin || curricula.allowed(c))));
  const visible = $derived(
    curricula.items.filter(
      (c) =>
        (admin || curricula.allowed(c)) &&
        c.name.toLocaleLowerCase('vi').includes(query.toLocaleLowerCase('vi')) &&
        (filter === 'all' || (filter === 'published' ? c.published : !c.published)),
    ),
  );
  const count = (c: Curriculum): number => c.levels.reduce((a, b) => a + b, 0);
  onMount(() => {
    curricula.load();
    taiChuBut();
  });

  function openEdit(c?: Curriculum, access = false): void {
    if (access) {
      onaccess?.();
      return;
    }
    edit = c
      ? structuredClone($state.snapshot(c))
      : {
          id: crypto.randomUUID(),
          name: '',
          description: '',
          color: '#E4EEDF',
          published: false,
          access: 'selected',
          members: [],
          levels: [0, 0, 0, 0, 0, 0],
        };
    error = '';
  }
  function save(): void {
    if (!edit) return;
    if (!edit.name.trim()) {
      error = 'Vui lòng nhập tên giáo trình.';
      return;
    }
    curricula.save({ ...$state.snapshot(edit), name: edit.name.trim() });
    edit = null;
    toast.ok('Đã lưu bản xem trước.');
  }
</script>

<svelte:head><title>{admin ? 'Quản lý giáo trình' : 'Giáo trình của tôi'} · Zìkǎ</title></svelte:head>

<div class="ct-shell" class:ct-admin={admin}>
  <header class="ct-heading">
    <div>
      <h1>{admin ? 'Bộ giáo trình' : 'Giáo trình của tôi'}</h1>
      {#if !admin}<p class="muted">Chọn giáo trình, sau đó chọn cấp HSK để bắt đầu học.</p>{/if}
    </div>
    {#if admin}<button class="btn btn-son" onclick={() => openEdit()}><Icon name="plus" size={18} /> Thêm giáo trình</button>{/if}
  </header>

  {#if !admin}
    <button
      class="btn btn-bare btn-sm ct-demo-toggle"
      aria-expanded={showSimulation}
      onclick={() => {
        showSimulation = !showSimulation;
      }}><Icon name="users" size={16} /> Xem thử quyền học <Icon name="down" size={14} /></button
    >
  {/if}
  {#if !admin && showSimulation}
    <div class="ct-simulation">
      <div>
        <b>Xem thử với tài khoản học viên</b>
        <p class="muted">Đổi người để xem danh sách thay đổi theo quyền đã cấp ở admin.</p>
      </div>
      <Select
        ariaLabel="Học viên xem thử"
        bind:value={curricula.learner}
        options={DEMO_LEARNERS.map((u) => ({ v: u.id, label: u.name }))}
        onchange={() => {
          selectedId = null;
          lesson = null;
        }}
      />
    </div>
  {/if}

  {#if selected}
    <button
      class="btn btn-bare"
      onclick={() => {
        selectedId = null;
        lesson = null;
      }}>← Tất cả giáo trình</button
    >
    <section class="ct-banner block">
      <span class="seal" lang="zh" style="width: 48px; height: 48px; font-size: 27px">学</span>
      <div>
        <p class="ct-eyebrow">{admin ? 'NỘI DUNG GIÁO TRÌNH' : 'GIÁO TRÌNH ĐANG CHỌN'}</p>
        <h2>{selected.name}</h2>
        <p>{selected.description}</p>
      </div>
      {#if admin}<button class="btn" onclick={() => openEdit(selected, true)}>Quyền học</button>{/if}
    </section>
    <div class="ct-levels" aria-label="Chọn cấp HSK">
      {#each selected.levels as total, i}<button
          class:ct-active={level === i + 1}
          onclick={() => {
            level = i + 1;
            lesson = null;
          }}
          style="--ct-level-color: var(--hsk{i + 1}); --ct-level-bg: var(--lv{i + 1})"
          ><b>HSK <strong>{i + 1}</strong></b><span>{total ? `${total} bài học` : 'Đang soạn'}</span><i lang="zh" aria-hidden="true"
            >{SO_HAN[i + 1]}</i
          ></button
        >{/each}
    </div>
    <div class="ct-section-title">
      <div>
        <h2>HSK {level} <span class="muted">/ {selected.name}</span></h2>
        <p class="muted">
          {admin
            ? 'Các bài học và sáu phần nội dung thuộc riêng bộ giáo trình này.'
            : 'Tiến độ hiển thị riêng cho bộ giáo trình đang chọn.'}
        </p>
      </div>
      {#if admin}<button
          class="btn"
          onclick={() => {
            if (selected) {
              const next = $state.snapshot(selected);
              next.levels[level - 1]++;
              curricula.save(next);
              toast.ok('Đã thêm bài mẫu vào bản xem trước.');
            }
          }}>+ Thêm bài mẫu</button
        >{/if}
    </div>
    {#if selected.levels[level - 1] === 0}<div class="ct-empty">
        <Icon name="book" size={32} />
        <h3>Nội dung đang được chuẩn bị</h3>
        <p class="muted">{admin ? 'Thêm bài đầu tiên cho cấp HSK này.' : 'Bạn có thể quay lại sau hoặc chọn một cấp khác.'}</p>
      </div>
    {:else}<div class="ct-lessons">
        {#each Array.from({ length: selected.levels[level - 1] }) as _, i}<button
            class="ct-lesson"
            class:ct-active={lesson === i + 1}
            onclick={() => {
              lesson = i + 1;
            }}
            ><span class="ct-lesson-number">{String(i + 1).padStart(2, '0')}</span>
            <div>
              <b>{i === 0 ? '你好 · Xin chào' : i === 1 ? '谢谢你 · Cảm ơn bạn' : `Bài học ${i + 1}`}</b>
              <p class="muted">{admin ? '6 phần nội dung · Bài mẫu' : 'Chưa bắt đầu · 6 phần học'}</p>
            </div>
            <span>→</span></button
          >{/each}
      </div>{/if}
    {#if lesson}<section class="ct-study">
        <div class="ct-section-title">
          <h2>Bài {lesson} · Chọn phần {admin ? 'soạn' : 'học'}</h2>
          <span class="tag">Nội dung minh họa</span>
        </div>
        <div class="ct-parts">
          {#each PARTS as p}<button
              class:ct-active={part === p.code}
              onclick={() => {
                part = p.code;
              }}><span style:background={p.accent}><Icon name={p.icon} size={22} /></span>{p.name}</button
            >{/each}
        </div>
        <div class="ct-study-example">
          <span lang="zh">你好</span><b>nǐ hǎo</b>
          <p>Xin chào</p>
          <p class="muted">
            {admin ? 'Vùng soạn nội dung của bài trong giáo trình đã chọn.' : 'Vùng học của bài trong giáo trình đã chọn.'} Đây là màn minh họa,
            chưa nạp nội dung thật.
          </p>
        </div>
      </section>{/if}
  {:else}
    {#if !admin}<div class="ct-toolbar">
        <input class="inp" aria-label="Tìm giáo trình" placeholder="Tìm tên giáo trình…" bind:value={query} />{#if admin}<Select
            ariaLabel="Trạng thái giáo trình"
            bind:value={filter}
            options={[
              { v: 'all', label: 'Tất cả trạng thái' },
              { v: 'published', label: 'Đang hiển thị' },
              { v: 'draft', label: 'Bản nháp' },
            ]}
          />{:else}<span class="muted">{visible.length} giáo trình được cấp quyền</span>{/if}
      </div>{/if}
    {#if admin}
      <div class="ct-table-wrap block">
        <table class="tbl ct-table">
          <thead><tr><th>Bộ giáo trình</th><th>Cấp HSK</th><th>Số bài</th><th>Trạng thái</th><th>Quyền học</th></tr></thead>
          <tbody
            >{#each visible as c}<tr>
                <td
                  ><div class="ct-table-name">
                    <span class="ct-book-icon" style:background={c.color}><Icon name="book" size={21} /></span>
                    <div>
                      <b>{c.name}</b>
                      <p class="muted">{c.description}</p>
                    </div>
                  </div></td
                >
                <td><span class="tag">HSK 1–6</span></td><td class="num">{count(c)}</td>
                <td
                  ><span class="tag" class:tag-ok={c.published} class:tag-warn={!c.published}>{c.published ? 'Đang hiện' : 'Bản nháp'}</span
                  ></td
                >
                <td
                  ><button class="btn btn-bare btn-sm" onclick={() => openEdit(c, true)}
                    ><Icon name="users" size={16} />{c.access === 'all' ? 'Tất cả học viên' : `${c.members.length} học viên`}</button
                  ></td
                >
              </tr>{/each}</tbody
          >
        </table>
      </div>
    {:else}
      <div class="ct-grid">
        {#each visible as c}<article class="ct-card">
            <div class="ct-card-body">
              <div class="ct-book-heading">
                <span class="ct-book-icon" style:background={c.color}><Icon name="book" size={23} /></span><span class="tag">HSK 1–6</span>
              </div>
              <div class="ct-card-tags">
                {#if admin}<span class="tag" class:tag-warn={!c.published}>{c.published ? 'Đang hiển thị' : 'Bản nháp'}</span>{/if}
              </div>
              <h2>{c.name}</h2>
              <p class="ct-description muted">{c.description}</p>
              <div class="ct-card-meta"><span><b class="num">{count(c)}</b> bài học</span><span>6 phần học</span></div>
              <div class="ct-hsk-dots" aria-label="Nội dung theo cấp">
                {#each c.levels as total, i}<span
                    class:ct-coming={total === 0}
                    style="--ct-level-color: var(--hsk{i + 1}); --ct-level-bg: var(--lv{i + 1})">HSK {i + 1}</span
                  >{/each}
              </div>
              {#if admin}<button class="ct-access" onclick={() => openEdit(c, true)}
                  ><Icon name="users" size={17} /><span
                    >{c.access === 'all' ? 'Tất cả học viên' : `${c.members.length} học viên được chọn`}</span
                  ><span>→</span></button
                >{/if}
              <div class="ct-card-actions">
                <button
                  class="btn"
                  class:btn-son={!admin}
                  onclick={() => {
                    selectedId = c.id;
                    level = 1;
                    lesson = null;
                  }}>{admin ? 'Quản lý nội dung' : 'Chọn cấp HSK'} <Icon name="right" size={16} /></button
                >{#if admin}<button class="btn btn-bare" aria-label={`Chỉnh sửa ${c.name}`} onclick={() => openEdit(c)}
                    ><Icon name="gear" size={19} /></button
                  >{/if}
              </div>
            </div>
          </article>{/each}
      </div>
    {/if}
    {#if visible.length === 0}<div class="ct-empty">
        <Icon name="book" size={36} />
        <h2>{query ? 'Không tìm thấy giáo trình' : 'Chưa có giáo trình để học'}</h2>
        <p class="muted">{query ? 'Thử tìm bằng tên khác.' : 'Giáo trình sẽ xuất hiện tại đây khi quản trị viên cấp quyền cho bạn.'}</p>
      </div>{/if}
    {#if !admin}<p class="ct-footnote">
        <Icon name="users" size={16} /> Bạn chỉ thấy giáo trình đang hiển thị và được cấp quyền. Cần thêm giáo trình? Hãy liên hệ quản trị viên.
      </p>{/if}
  {/if}
</div>

{#if edit}
  <Modal
    title={curricula.items.some((c) => c.id === edit?.id) ? 'Thiết lập giáo trình' : 'Thêm giáo trình mới'}
    width={640}
    onclose={() => {
      edit = null;
    }}
  >
    <div class="ct-form">
      <label>Tên bộ giáo trình <input class="inp" maxlength="100" placeholder="Ví dụ: HSK Standard Course" bind:value={edit.name} /></label
      >{#if error}<p class="ct-error" role="alert">{error}</p>{/if}<label
        >Giới thiệu ngắn<textarea
          class="inp"
          rows="3"
          maxlength="300"
          placeholder="Giới thiệu lộ trình và nội dung bộ sách…"
          bind:value={edit.description}></textarea></label
      >
      <fieldset>
        <legend>Màu nhận diện</legend>
        <div class="ct-colors">
          {#each ['#E4EEDF', '#E3EAF6', '#F0E6D6', '#EDE4F4', '#F5E0DE'] as color}<button
              class:ct-active={edit.color === color}
              style:background={color}
              aria-label={`Màu ${color}`}
              aria-pressed={edit.color === color}
              onclick={() => {
                if (edit) edit.color = color;
              }}>{edit.color === color ? '✓' : ''}</button
            >{/each}
        </div>
      </fieldset>
      <label class="ct-check"
        ><input type="checkbox" bind:checked={edit.published} /><span
          ><b>Hiển thị cho người học</b><small>Chỉ những người được cấp quyền mới thấy bộ giáo trình này.</small></span
        ></label
      >
      <p class="ct-form-note">Mỗi bộ có sẵn HSK 1–6. Bạn thêm bài vào từng cấp sau khi tạo.</p>
    </div>
    {#snippet actions()}<button
        class="btn"
        onclick={() => {
          edit = null;
        }}>Hủy</button
      ><button class="btn btn-son" onclick={save}>Lưu giáo trình</button>{/snippet}
  </Modal>
{/if}

<style>
  .ct-shell {
    display: flex;
    flex-direction: column;
    gap: 24px;
  }
  .ct-heading,
  .ct-section-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 18px;
  }
  .ct-heading h1 {
    font-size: 32px;
    line-height: 1.2;
    margin-bottom: 8px;
  }
  .ct-heading .muted {
    font-size: 14px;
  }
  .ct-admin .ct-heading h1 {
    font-size: 28px;
  }
  .ct-eyebrow {
    font-size: 11px;
    color: var(--muted);
    font-weight: 700;
  }
  .ct-toolbar {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
  }
  .ct-toolbar > input {
    flex: 1;
    min-width: 180px;
    max-width: 380px;
    margin-right: auto;
  }
  .ct-toolbar > span {
    font-size: 12px;
  }
  .ct-table-wrap {
    overflow-x: auto;
    padding: 6px 10px;
    border-radius: 20px;
  }
  .ct-table-name {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 250px;
  }
  .ct-table-name b {
    font-size: 13px;
  }
  .ct-table-name p {
    font-size: 11px;
    max-width: 270px;
    text-overflow: ellipsis;
    overflow: hidden;
    margin-top: 4px;
  }
  .ct-table :global(tbody tr:last-child td) {
    border-bottom: 0;
  }
  .ct-book-icon {
    width: 46px;
    height: 46px;
    border: 1px solid var(--line);
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #27382e;
    flex-shrink: 0;
  }
  .ct-book-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 18px;
  }
  .ct-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(min(300px, 100%), 1fr));
    gap: 18px;
    align-items: start;
  }
  .ct-card {
    border: 1px solid var(--line);
    border-radius: 22px;
    background: var(--surface);
    box-shadow: var(--sh);
    transition:
      border-color 0.15s,
      transform 0.15s;
  }
  .ct-card:hover {
    border-color: var(--ink-strong);
    transform: translateY(-2px);
  }
  .ct-card-body {
    padding: 24px;
  }
  .ct-card-tags {
    display: flex;
    gap: 8px;
  }
  .ct-card h2 {
    font-size: 20px;
    font-weight: 800;
    line-height: 1.4;
  }
  .ct-description {
    font-size: 13px;
    line-height: 1.8;
    min-height: 64px;
    margin-top: 8px;
  }
  .ct-card-meta {
    display: flex;
    gap: 16px;
    font-size: 12px;
    color: var(--text-2);
    margin: 18px 0 14px;
  }
  .ct-hsk-dots {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    padding-bottom: 20px;
    border-bottom: 1px dashed var(--line);
  }
  .ct-hsk-dots span {
    background: var(--ct-level-bg);
    color: var(--ct-level-color);
    border: 1px solid var(--line);
    border-radius: 6px;
    font-size: 10px;
    font-weight: 700;
    padding: 4px 6px;
  }
  .ct-hsk-dots .ct-coming {
    color: var(--muted);
    background: var(--surface-2);
  }
  .ct-access {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 12px 0;
    border: 0;
    background: transparent;
    color: var(--text-2);
    font-size: 12px;
    cursor: pointer;
  }
  .ct-access > span:last-child {
    margin-left: auto;
  }
  .ct-card-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 18px;
  }
  .ct-card-actions > button:first-child {
    flex: 1;
  }
  .ct-demo-toggle {
    align-self: flex-end;
    margin-top: -12px;
    color: var(--muted);
  }
  .ct-simulation {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px 20px;
    border: 1px dashed var(--line);
    background: var(--surface);
    border-radius: 16px;
    font-size: 13px;
  }
  .ct-simulation p {
    font-size: 12px;
    margin-top: 5px;
  }
  .ct-banner {
    padding: 22px 24px;
    display: flex;
    align-items: center;
    gap: 18px;
  }
  .ct-banner h2 {
    font-size: 23px;
    margin: 4px 0;
  }
  .ct-banner p {
    font-size: 13px;
    color: var(--text-2);
  }
  .ct-banner > button {
    margin-left: auto;
    flex-shrink: 0;
  }
  .ct-levels {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 12px;
  }
  .ct-levels button {
    position: relative;
    isolation: isolate;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    gap: 12px;
    text-align: left;
    padding: 20px 16px;
    border: 1px solid var(--line);
    border-left: 3px solid var(--ct-level-color);
    background: var(--ct-level-bg);
    border-radius: 16px;
    color: var(--text);
    cursor: pointer;
  }
  .ct-levels strong {
    font-size: 36px;
    font-weight: 800;
    margin-left: 3px;
  }
  .ct-levels button > span {
    font-size: 11px;
    color: var(--text-2);
  }
  .ct-levels i {
    position: absolute;
    right: -4px;
    bottom: -24px;
    font-size: 100px;
    font-family: var(--font-brush);
    color: var(--ct-level-color);
    opacity: 0.1;
    font-style: normal;
    z-index: -1;
  }
  .ct-active {
    outline: 2px solid var(--son);
    outline-offset: -2px;
  }
  .ct-section-title h2 {
    font-size: 18px;
  }
  .ct-section-title h2 span {
    font-size: 12px;
    font-weight: 400;
  }
  .ct-section-title p {
    font-size: 12px;
    margin-top: 6px;
  }
  .ct-lessons {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }
  .ct-lesson {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 20px;
    text-align: left;
    border: 1px solid var(--line);
    border-radius: 20px;
    background: var(--surface);
    box-shadow: var(--sh-sm);
    color: var(--text);
    cursor: pointer;
  }
  .ct-lesson > span:last-child {
    margin-left: auto;
  }
  .ct-lesson b {
    font-size: 14px;
  }
  .ct-lesson p {
    font-size: 12px;
    margin-top: 5px;
  }
  .ct-lesson-number {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    background: var(--surface-2);
    border: 1px solid var(--line);
    border-radius: 12px;
    font-size: 15px;
    font-weight: 800;
  }
  .ct-empty {
    padding: 50px 20px;
    border: 1px dashed var(--line);
    border-radius: 22px;
    text-align: center;
  }
  .ct-empty h2,
  .ct-empty h3 {
    margin: 15px 0 8px;
  }
  .ct-empty :global(svg) {
    margin: auto;
    color: var(--muted);
  }
  .ct-footnote {
    display: flex;
    align-items: center;
    gap: 8px;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.8;
  }
  .ct-form {
    display: flex;
    flex-direction: column;
    gap: 17px;
    padding: 4px 0 15px;
  }
  .ct-form > label:not(.ct-check) {
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
  }
  .ct-form fieldset {
    border: 0;
    padding: 0;
  }
  .ct-form legend {
    font-size: 13px;
    margin-bottom: 8px;
  }
  .ct-colors {
    display: flex;
    gap: 12px;
  }
  .ct-colors button {
    width: 36px;
    height: 36px;
    border: 1px solid var(--line);
    border-radius: 10px;
    cursor: pointer;
    color: #27382e;
  }
  .ct-check {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 15px;
    border: 1px solid var(--line);
    border-radius: 12px;
    font-size: 13px;
    cursor: pointer;
  }
  .ct-form input[type='checkbox'] {
    accent-color: var(--son);
    margin-top: 3px;
    width: 17px;
    height: 17px;
    flex-shrink: 0;
  }
  .ct-form small {
    display: block;
    font-size: 11px;
    color: var(--muted);
    margin-top: 5px;
    font-weight: 400;
  }
  .ct-form-note {
    font-size: 12px;
    color: var(--text-2);
    line-height: 1.8;
  }
  .ct-error {
    color: var(--bad);
    font-size: 12px;
  }
  .ct-study {
    border: 1px solid var(--line);
    background: var(--surface);
    box-shadow: var(--sh);
    padding: 24px;
    border-radius: 22px;
  }
  .ct-parts {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-top: 20px;
  }
  .ct-parts button {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    border: 1px solid var(--line);
    background: var(--surface);
    color: var(--text);
    border-radius: 12px;
    font-size: 12px;
    cursor: pointer;
  }
  .ct-parts button > span {
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 8px;
    color: #27382e;
  }
  .ct-study-example {
    display: flex;
    flex-direction: column;
    gap: 12px;
    align-items: center;
    padding: 35px 0 10px;
    text-align: center;
  }
  .ct-study-example > span {
    font-size: 60px;
    font-family: var(--font-hz);
  }
  .ct-study-example .muted {
    max-width: 420px;
    font-size: 12px;
    line-height: 1.8;
  }
  @media (max-width: 900px) {
    .ct-table {
      min-width: 860px;
    }
    .ct-levels {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
  }
  @media (max-width: 700px) {
    .ct-shell {
      gap: 18px;
    }
    .ct-heading,
    .ct-simulation,
    .ct-section-title {
      align-items: stretch;
      flex-direction: column;
      gap: 12px;
    }
    .ct-heading h1,
    .ct-admin .ct-heading h1 {
      font-size: 22px;
    }
    .ct-heading .muted {
      font-size: 13px;
    }
    .ct-heading > button {
      align-self: flex-start;
    }
    .ct-card-body {
      padding: 20px;
    }
    .ct-card h2 {
      font-size: 18px;
    }
    .ct-description {
      min-height: auto;
    }
    .ct-lessons {
      grid-template-columns: 1fr;
    }
    .ct-banner {
      padding: 18px;
      flex-wrap: wrap;
      gap: 12px;
    }
    .ct-banner h2 {
      font-size: 19px;
    }
    .ct-levels {
      gap: 8px;
    }
    .ct-levels button {
      padding: 14px 10px;
    }
    .ct-levels strong {
      font-size: 28px;
    }
    .ct-parts {
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .ct-footnote {
      align-items: flex-start;
    }
  }
</style>
