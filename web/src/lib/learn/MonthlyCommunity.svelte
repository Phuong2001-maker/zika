<script lang="ts">
  import Icon from '$lib/components/Icon.svelte';
  import Modal from '$lib/components/Modal.svelte';

  // Dữ liệu duyệt giao diện; chưa phải số liệu Shopee hoặc kết quả thi thật.
  const month = '11/2026';
  const commission = 1250000;
  const currency = (value: number): string =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(value);
  let dialog = $state<'overview' | 'contest' | 'ranking' | 'shopee' | null>(null);
  let ranking = $state<'study' | 'contest'>('study');
  const MEDALS = ['🥇', '🥈', '🥉'];
  const students = [
    { name: 'Nguyễn Thảo Linh', initials: 'TL', days: 26, lessons: 48, minutes: 1240 },
    { name: 'Trần Minh Anh', initials: 'MA', days: 24, lessons: 45, minutes: 1125 },
    { name: 'Lê Hoàng Nam', initials: 'HN', days: 23, lessons: 42, minutes: 980 },
    { name: 'Phạm Ngọc Mai', initials: 'NM', days: 21, lessons: 39, minutes: 875 },
    { name: 'Đỗ Hải Yến', initials: 'HY', days: 20, lessons: 36, minutes: 810 },
    { name: 'Vũ Đức Anh', initials: 'ĐA', days: 18, lessons: 32, minutes: 740 },
    { name: 'Bùi Thu Hà', initials: 'TH', days: 17, lessons: 30, minutes: 685 },
    { name: 'Nguyễn Tuấn Tú', initials: 'TT', days: 16, lessons: 28, minutes: 620 },
    { name: 'Trần Khánh Vy', initials: 'KV', days: 15, lessons: 26, minutes: 580 },
    { name: 'Lê Minh Châu', initials: 'MC', days: 14, lessons: 24, minutes: 540 },
  ];
</script>

{#snippet bag()}
  <svg class="mc-bag" viewBox="0 0 64 70" fill="none" aria-hidden="true">
    <path d="M21 23v-7a11 11 0 0 1 22 0v7" stroke="currentColor" stroke-width="3.5" stroke-linecap="round" />
    <path d="M13 21h38l4 36a7 7 0 0 1-7 8H16a7 7 0 0 1-7-8l4-36Z" fill="currentColor" />
    <ellipse cx="23" cy="40" rx="2" ry="2.6" fill="white" /><ellipse cx="41" cy="40" rx="2" ry="2.6" fill="white" />
    <path d="M28 46q4 5 8 0" stroke="white" stroke-width="2.5" stroke-linecap="round" />
    <ellipse cx="17" cy="46" rx="4" ry="2" fill="#FFCCB8" /><ellipse cx="47" cy="46" rx="4" ry="2" fill="#FFCCB8" />
    <path d="m54 9 2 4 4 2-4 2-2 4-2-4-4-2 4-2 2-4Z" fill="#E8BC64" />
  </svg>
{/snippet}

<div class="mc-header-actions" role="group" aria-label="Quỹ thưởng, Shopee và top chăm học">
  <div class="mc-fund-pill">
    <button
      type="button"
      class="mc-header-shop"
      title="Shopee · Góp quỹ thưởng"
      aria-label="Shopee · Góp quỹ thưởng"
      onclick={() => {
        dialog = 'shopee';
      }}>{@render bag()}</button
    >
    <button
      type="button"
      class="mc-header-fund"
      aria-label={`Xem quỹ thưởng tháng ${month}: ${currency(commission)}, số tiền minh họa`}
      title="Quỹ thưởng tháng · Số tiền minh họa"
      onclick={() => {
        dialog = 'overview';
      }}><span>Quỹ tháng <span class="mc-desktop-month">{month}</span></span><b>{currency(commission)}</b></button
    >
  </div>
  <button
    type="button"
    class="mc-header-top"
    title="Top chăm học"
    aria-label="Xem top học viên chăm học"
    onclick={() => {
      ranking = 'study';
      dialog = 'ranking';
    }}><span aria-hidden="true">🏆</span><b>Top chăm học</b></button
  >
</div>

{#if dialog === 'overview'}
  <Modal
    title="Cùng học, cùng tiến bộ"
    width={570}
    onclose={() => {
      dialog = null;
    }}
  >
    <section class="mc-contest" aria-label="Cuộc thi tháng và quỹ thưởng">
      <div class="mc-contest-top">
        <span class="mc-kicker"><span class="mc-medal" aria-hidden="true">🏆</span> THỬ THÁCH CUỐI THÁNG</span><span class="tag"
          >Sắp diễn ra</span
        >
      </div>
      <div class="mc-contest-copy">
        <h3>Giải đề hay, rinh thưởng nhé!</h3>
        <p class="muted">Một cuộc thi mỗi tháng. Cùng thử sức và nhận phần thưởng cho những vị trí dẫn đầu.</p>
      </div>
      <div class="mc-fund">
        <div>
          <span class="mc-fund-label">Quỹ thưởng tháng này</span><strong class="num">{currency(commission)}</strong><span class="mc-source"
            >Tổng hoa hồng Shopee · <span>Số tiền minh họa</span></span
          >
        </div>
        <span class="mc-fund-mark" lang="zh" aria-hidden="true">奖</span>
      </div>
      <div class="mc-contest-bottom">
        <span><Icon name="clock" size={16} /> Cuối tháng {month}</span><button
          type="button"
          class="btn btn-sm"
          onclick={() => {
            dialog = 'contest';
          }}>Xem cuộc thi <Icon name="right" size={15} /></button
        >
      </div>
      <div class="mc-support">
        <button
          type="button"
          class="mc-shopee"
          onclick={() => {
            dialog = 'shopee';
          }}
          aria-label="Xem cách mua sắm Shopee góp quỹ thưởng"
          >{@render bag()}<span
            ><b>Ghé Shopee, góp một chút niềm vui</b><small>Mua khi bạn cần, cùng góp vào quỹ thưởng.</small><small class="mc-affiliate"
              >Liên kết tiếp thị</small
            ></span
          ><Icon name="right" size={16} /></button
        >
      </div>
    </section>
  </Modal>
{/if}

{#if dialog === 'contest'}
  <Modal
    title={`Cuộc thi giải đề tháng ${month}`}
    width={570}
    onclose={() => {
      dialog = null;
    }}
  >
    <div class="mc-modal-content">
      <span class="mc-modal-emoji" aria-hidden="true">🏆</span>
      <p class="mc-modal-intro">Một tháng nỗ lực, một lần thử sức.</p>
      <div class="mc-modal-fund">
        <span>Quỹ thưởng dự kiến</span><strong>{currency(commission)}</strong><small
          >Số tiền minh họa · Dựa trên tổng hoa hồng Shopee của tháng.</small
        >
      </div>
      <div class="mc-detail-lines">
        <p>
          <Icon name="clock" size={18} /><span
            ><b>Thi vào cuối tháng</b><small>Ngày thi và thời lượng sẽ được thông báo tại đây.</small></span
          >
        </p>
        <p>
          <Icon name="quiz" size={18} /><span
            ><b>Cùng giải đề, cùng thử sức</b><small>Thể lệ và cách xếp hạng sẽ công bố trước khi mở thi.</small></span
          >
        </p>
        <p>
          <Icon name="flag" size={18} /><span
            ><b>Tiền thưởng dành cho top đầu</b><small
              >Tổng giải thưởng và mức thưởng từng hạng được chốt sau khi đối soát hoa hồng tháng.</small
            ></span
          >
        </p>
      </div>
      <p class="mc-muted-note">Đây là bản giao diện xem trước. Cuộc thi chưa mở đăng ký.</p>
    </div>
    {#snippet actions()}<button
        type="button"
        class="btn"
        onclick={() => {
          ranking = 'contest';
          dialog = 'ranking';
        }}>Xem bảng xếp hạng</button
      ><button
        type="button"
        class="btn btn-son"
        onclick={() => {
          dialog = null;
        }}>Mình hiểu rồi</button
      >{/snippet}
  </Modal>
{:else if dialog === 'ranking'}
  <Modal
    title={`Bảng xếp hạng tháng ${month}`}
    width={650}
    onclose={() => {
      dialog = null;
    }}
  >
    <div class="mc-modal-content">
      <div class="tabs">
        <button
          type="button"
          class="tab"
          class:on={ranking === 'study'}
          onclick={() => {
            ranking = 'study';
          }}>🌱 Chăm học</button
        ><button
          type="button"
          class="tab"
          class:on={ranking === 'contest'}
          onclick={() => {
            ranking = 'contest';
          }}>🏆 Cuộc thi</button
        >
      </div>
      {#if ranking === 'study'}<p class="mc-muted-note">
          Top 10 theo thời gian học trong tháng. Cùng thời gian thì ưu tiên số bài hoàn thành. Dữ liệu minh họa.
        </p>
        <ol class="mc-full-list">
          {#each students as student, i (student.name)}<li>
              <span class="mc-place" class:mc-medal-place={i < 3} role="img" aria-label={`Hạng ${i + 1}`}>{MEDALS[i] ?? i + 1}</span><span
                class="mc-avatar"
                style="background: var(--lv{(i % 3) + 1})">{student.initials}</span
              ><span class="mc-person"><b>{student.name}</b><small>{student.days} ngày học · {student.lessons} bài hoàn thành</small></span
              ><span class="mc-minutes"><b>{student.minutes.toLocaleString('vi-VN')}</b><small>phút học</small></span>
            </li>{/each}
        </ol>{:else}<div class="mc-contest-empty">
          <span aria-hidden="true">🏆</span>
          <h3>Những vị trí đầu đang chờ bạn</h3>
          <p class="muted">Bảng xếp hạng sẽ xuất hiện sau khi cuộc thi bắt đầu.</p>
          <button
            type="button"
            class="btn btn-sm"
            onclick={() => {
              dialog = 'contest';
            }}>Thông tin cuộc thi</button
          >
        </div>{/if}
    </div>
  </Modal>
{:else if dialog === 'shopee'}
  <Modal
    title="Một chút mua sắm, một phần quỹ vui"
    width={490}
    onclose={() => {
      dialog = null;
    }}
  >
    <div class="mc-modal-content mc-shop-modal">
      {@render bag()}
      <h3>Góp quỹ thưởng qua Shopee</h3>
      <p>Khi mua hàng qua liên kết Shopee của Zìkǎ, hoa hồng được ghi nhận sẽ góp vào quỹ thưởng cuộc thi cuối tháng.</p>
      <div class="mc-shop-note">
        <b>Bạn cứ mua khi cần nhé.</b>
        <p>Bấm liên kết chưa làm tăng quỹ. Số tiền được cập nhật từ hoa hồng hợp lệ sau khi đối soát.</p>
      </div>
      <p class="mc-muted-note">Liên kết tiếp thị · Bản giao diện chưa kết nối link Shopee trong admin.</p>
    </div>
    {#snippet actions()}<button
        type="button"
        class="btn"
        onclick={() => {
          dialog = null;
        }}>Đóng</button
      ><button type="button" class="btn mc-shop-button" disabled>Ghé Shopee · Sắp mở</button>{/snippet}
  </Modal>
{/if}

<style>
  /* Cụm trên thanh đầu trang */
  .mc-header-actions {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex: none;
  }
  .mc-fund-pill {
    display: inline-flex;
    align-items: stretch;
    border: 1px solid var(--bo-dot);
    border-radius: 13px;
    background: var(--bo-paper);
    height: 44px;
    padding: 3px;
  }
  .mc-header-shop,
  .mc-header-fund,
  .mc-header-top {
    border: 0;
    background: transparent;
    cursor: pointer;
    color: var(--text);
  }
  .mc-header-shop {
    display: grid;
    place-items: center;
    padding: 0 5px;
    border-radius: 10px;
  }
  .mc-header-shop .mc-bag {
    width: 29px;
    height: 32px;
  }
  .mc-header-shop:hover,
  .mc-header-fund:hover {
    background: var(--dao);
  }
  .mc-header-fund {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: center;
    gap: 2px;
    margin-left: 3px;
    padding: 0 10px;
    border-radius: 10px;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
  }
  .mc-header-fund > span {
    font-size: 11px;
    color: var(--text-2);
    line-height: 1.2;
  }
  .mc-header-fund > b {
    font-size: 14px;
    color: var(--warn-text);
    line-height: 1.2;
  }
  .mc-header-top {
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 0 12px;
    background: var(--surface-2);
    border: 1px solid var(--line);
    border-radius: 12px;
    white-space: nowrap;
  }
  .mc-header-top > span {
    font-size: 21px;
    line-height: 1;
  }
  .mc-header-top > b {
    font-size: 13px;
  }
  .mc-header-top:hover {
    border-color: var(--bo-dot);
    background: var(--bo-paper);
  }
  /* Máy 320px vẫn phải vừa cả cụm với nút chuyển nhanh, chuông và tài khoản nên chỉ nới tới đây. */
  @media (max-width: 767px) {
    .mc-header-actions {
      gap: 4px;
    }
    .mc-fund-pill {
      height: 40px;
      border-radius: 11px;
      padding: 2px;
    }
    .mc-header-shop {
      padding: 0 3px;
    }
    .mc-header-shop .mc-bag {
      width: 24px;
      height: 27px;
    }
    .mc-header-fund {
      margin-left: 1px;
      padding: 0 6px;
      gap: 1px;
    }
    .mc-header-fund > span {
      font-size: 10px;
    }
    .mc-header-fund > b {
      font-size: 12px;
    }
    .mc-desktop-month {
      display: none;
    }
    .mc-header-top {
      height: 40px;
      width: 36px;
      padding: 0;
      border-radius: 11px;
    }
    .mc-header-top > span {
      font-size: 19px;
    }
    .mc-header-top > b {
      display: none;
    }
  }

  /* Hộp quỹ thưởng */
  .mc-contest {
    margin-top: 14px;
    min-width: 0;
  }
  .mc-contest-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
  }
  .mc-kicker {
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.04em;
    color: var(--warn-text);
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .mc-medal {
    font-size: 21px;
  }
  .mc-contest-copy {
    margin-top: 14px;
  }
  .mc-contest-copy h3 {
    font-size: 22px;
    font-weight: 800;
    line-height: 1.3;
  }
  .mc-contest-copy p {
    font-size: 14px;
    line-height: 1.5;
    margin-top: 6px;
  }
  .mc-fund {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid var(--bo-dot);
    border-radius: 14px;
    background: var(--bo-paper);
    padding: 16px 20px;
    margin-top: 16px;
    position: relative;
    overflow: hidden;
  }
  .mc-fund > div {
    display: flex;
    flex-direction: column;
    gap: 4px;
    z-index: 1;
  }
  .mc-fund-label {
    font-size: 13px;
    color: var(--text-2);
  }
  .mc-fund strong {
    font-size: 32px;
    font-weight: 800;
    line-height: 1.3;
    color: var(--warn-text);
  }
  .mc-source {
    font-size: 12px;
    color: var(--muted);
  }
  .mc-source > span {
    font-style: italic;
  }
  .mc-fund-mark {
    position: absolute;
    right: 14px;
    bottom: -10px;
    font-family: var(--font-hz-serif);
    font-size: 96px;
    color: var(--bo-dot);
    /* Chữ nền nằm dưới số tiền, đậm hơn mức này là cản đọc số. */
    opacity: 0.4;
  }
  .mc-contest-bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 0;
  }
  .mc-contest-bottom > span {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--muted);
    font-size: 13px;
  }
  .mc-support {
    border-top: 1px dashed var(--line);
    padding-top: 8px;
  }
  .mc-shopee {
    border: 0;
    background: transparent;
    width: 100%;
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--text);
    text-align: left;
    cursor: pointer;
    padding: 8px;
    border-radius: 12px;
  }
  .mc-shopee:hover {
    background: var(--surface-2);
  }
  .mc-shopee > span {
    flex: 1;
    min-width: 0;
  }
  .mc-shopee b {
    font-size: 14px;
    line-height: 1.35;
  }
  .mc-shopee small {
    display: block;
    font-size: 12px;
    color: var(--muted);
    margin-top: 3px;
    line-height: 1.4;
  }
  .mc-shopee .mc-affiliate {
    font-size: 11px;
  }
  .mc-bag {
    width: 44px;
    height: 48px;
    color: #ed6b45;
    flex-shrink: 0;
  }

  /* Bảng xếp hạng */
  .mc-full-list {
    list-style: none;
    margin: 0;
    padding: 0;
  }
  .mc-full-list li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 0;
    border-bottom: 1px solid var(--line);
  }
  .mc-full-list li:last-child {
    border-bottom: 0;
  }
  .mc-place {
    width: 24px;
    flex-shrink: 0;
    font-size: 14px;
    color: var(--muted);
    font-weight: 800;
    text-align: center;
    font-variant-numeric: tabular-nums;
  }
  .mc-medal-place {
    font-size: 20px;
    line-height: 1;
  }
  .mc-avatar {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: grid;
    place-items: center;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-2);
    border: 1px solid var(--line);
    flex-shrink: 0;
  }
  .mc-person {
    min-width: 0;
    flex: 1;
  }
  .mc-person b {
    font-size: 14px;
    display: block;
    line-height: 1.3;
  }
  .mc-person small {
    display: block;
    font-size: 12px;
    color: var(--muted);
    margin-top: 2px;
    line-height: 1.35;
  }
  .mc-minutes {
    display: flex;
    flex-direction: column;
    flex-shrink: 0;
    text-align: right;
    gap: 2px;
    font-variant-numeric: tabular-nums;
  }
  .mc-minutes b {
    font-size: 15px;
  }
  .mc-minutes small {
    font-size: 11px;
    color: var(--muted);
  }
  .mc-contest-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 24px 10px;
    text-align: center;
  }
  .mc-contest-empty > span {
    font-size: 48px;
  }
  .mc-contest-empty h3 {
    font-size: 18px;
  }
  .mc-contest-empty p {
    font-size: 14px;
    line-height: 1.5;
  }

  /* Hộp cuộc thi và hộp Shopee */
  .mc-modal-content {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding-top: 14px;
  }
  .mc-modal-emoji {
    font-size: 36px;
    text-align: center;
  }
  .mc-modal-intro {
    text-align: center;
    font-weight: 700;
    font-size: 16px;
  }
  .mc-modal-fund {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 18px;
    border: 1px solid var(--bo-dot);
    border-radius: 14px;
    background: var(--bo-paper);
    text-align: center;
  }
  .mc-modal-fund > span {
    font-size: 13px;
  }
  .mc-modal-fund strong {
    font-size: 32px;
    color: var(--warn-text);
    font-weight: 800;
    line-height: 1.25;
  }
  .mc-modal-fund small {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.45;
  }
  .mc-detail-lines {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .mc-detail-lines p {
    display: flex;
    align-items: flex-start;
    gap: 12px;
  }
  .mc-detail-lines :global(svg) {
    flex-shrink: 0;
    margin-top: 2px;
    color: var(--muted);
  }
  .mc-detail-lines b {
    font-size: 14px;
  }
  .mc-detail-lines small {
    display: block;
    font-size: 13px;
    color: var(--muted);
    line-height: 1.45;
    margin-top: 2px;
  }
  .mc-muted-note {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.5;
  }
  .mc-shop-modal {
    align-items: center;
    text-align: center;
  }
  .mc-shop-modal .mc-bag {
    width: 74px;
    height: 82px;
  }
  .mc-shop-modal h3 {
    font-size: 18px;
  }
  .mc-shop-modal > p {
    font-size: 14px;
    line-height: 1.5;
  }
  .mc-shop-modal > .mc-muted-note {
    font-size: 12px;
  }
  .mc-shop-note {
    padding: 14px 16px;
    background: var(--dao);
    border: 1px solid var(--line);
    border-radius: 12px;
    font-size: 14px;
    line-height: 1.5;
  }
  .mc-shop-note p {
    color: var(--text-2);
    font-size: 13px;
    margin-top: 4px;
  }
  /* Nút chưa bấm được nhưng chữ vẫn phải đọc được để người học hiểu là sắp có. */
  .mc-shop-button:disabled {
    opacity: 1;
    color: var(--muted);
    background: var(--surface-2);
    border: 1px dashed var(--line);
    cursor: not-allowed;
  }

  @media (max-width: 700px) {
    .mc-contest-copy h3 {
      font-size: 20px;
    }
    .mc-fund strong {
      font-size: 28px;
    }
  }
  @media (prefers-reduced-motion: no-preference) {
    .mc-shopee:hover .mc-bag {
      transform: rotate(-5deg);
      transition: transform 0.18s ease;
    }
  }
</style>
