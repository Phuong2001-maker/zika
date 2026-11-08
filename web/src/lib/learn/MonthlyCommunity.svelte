<script lang="ts">
  import { onMount } from 'svelte';
  import Icon from '$lib/components/Icon.svelte';
  import Modal from '$lib/components/Modal.svelte';
  import EmptyState from '$lib/components/EmptyState.svelte';
  import ContestCountdown from './ContestCountdown.svelte';
  import ContestHistory from './ContestHistory.svelte';
  import { contestStore, type RankRow, type StudyRow } from './contest.svelte';
  import { feedback } from '$lib/stores/feedback.svelte';
  import { session } from '$lib/stores/session.svelte';
  import { supportLinks } from './support-links.svelte';
  import { clock } from '$lib/utils/format';
  import { ApiError } from '$lib/api/client';
  import { toast } from '$lib/stores/toast.svelte';

  // Tiền quỹ, link Shopee và giờ thi lấy theo cấu hình ở khu quản trị; Top chăm học tính từ phút học thật trong tháng.
  const currency = (value: number): string =>
    new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND', maximumFractionDigits: 0 }).format(value);
  const month = $derived(supportLinks.monthlyFund.month);
  const updatedOn = $derived(supportLinks.monthlyFund.updatedOn);
  const money = $derived(supportLinks.monthlyFund.amount === null ? 'Đang cập nhật' : currency(supportLinks.monthlyFund.amount));
  const contest = $derived(contestStore.current);
  // Đã nộp bài thì không còn đường vào thi: mỗi người một lượt.
  const examHref = $derived(
    contest?.status === 'open' && contest.mockTestId && contest.questionCount > 0 && contest.myAttempt !== 'submitted'
      ? `/learn/tests/hsk/${contest.levelId}/mock/${contest.mockTestId}`
      : null,
  );
  const examLabel = $derived(contest?.myAttempt === 'doing' ? 'Tiếp tục làm bài' : 'Vào thi ngay');
  const submitted = $derived(contest?.status === 'open' && contest.myAttempt === 'submitted');
  const contestRanking = $derived(contestStore.ranking);
  const registrants = $derived(contestStore.registrants);
  const myPrize = $derived(
    contestRanking?.contest?.status === 'ended' && contestRanking.me?.participated ? contestStore.prizes[contestRanking.me.rank - 1] : null,
  );
  let historyOpen = $state(false);
  function openHistory(): void {
    dialog = null;
    historyOpen = true;
  }
  const canRegister = $derived(contest?.status === 'upcoming' && !contest.registered);
  let registering = $state(false);
  let registrationMessage = $state('');

  async function setRegistered(on: boolean): Promise<void> {
    if (registering) return;
    registrationMessage = '';
    registering = true;
    try {
      await contestStore.setRegistered(on);
      registrationMessage = on ? 'Đăng ký thành công' : 'Đã huỷ đăng ký';
    } catch (e) {
      toast.show(e instanceof ApiError ? e.message : 'Không đổi được, thử lại nhé.');
      void contestStore.refresh();
    } finally {
      registering = false;
    }
  }
  const initials = (name: string): string =>
    name
      .trim()
      .split(/\s+/)
      .slice(-2)
      .map((word) => word.charAt(0).toUpperCase())
      .join('');

  function showRanking(tab: 'study' | 'registrants' | 'contest'): void {
    ranking = tab;
    dialog = 'ranking';
    void contestStore.refreshRanking();
    void contestStore.refreshRegistrants();
    void contestStore.refreshStudyTop();
  }
  const studyTop = $derived(contestStore.studyTop);
  // Chưa có cách nhận thưởng riêng: người đạt giải gửi tài khoản ngân hàng qua Góp ý, quản trị đọc ở /admin/feedback.
  function claimPrize(): void {
    dialog = null;
    feedback.open();
  }
  onMount(() => {
    void supportLinks.refresh();
    void contestStore.refresh();
  });
  let dialog = $state<'overview' | 'contest' | 'ranking' | 'shop' | null>(null);

  // Tìm sản phẩm: bỏ dấu để gõ "khan giay" vẫn ra "Khăn giấy".
  let search = $state('');
  const plain = (text: string): string =>
    text
      .normalize('NFD')
      .replace(/\p{Mark}/gu, '')
      .replace(/đ/gi, 'd')
      .toLowerCase()
      .trim();
  const products = $derived(supportLinks.fund.filter((item) => plain(item.name).includes(plain(search))));

  function openShop(): void {
    search = '';
    dialog = 'shop';
    supportLinks.logView('fund');
  }
  let ranking = $state<'study' | 'registrants' | 'contest'>('study');
  const MEDALS = ['🥇', '🥈', '🥉'];
  // Quỹ chưa nhập thì chưa có số tiền, hiện phần trăm chứ đừng ghi 0 đồng.
  const prizeMoney = (p: { percent: number; amount: number | null }): string => (p.amount === null ? `${p.percent}% quỹ` : currency(p.amount));

  function openFund(): void {
    dialog = 'overview';
    void supportLinks.refresh();
    void contestStore.refresh();
  }
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

{#snippet regNote()}
  {#if contest}
    {#if contest.status === 'upcoming'}
      <div class="mc-registration" class:mc-registration-done={contest.registered}>
        <span class="mc-reg-icon"><Icon name={contest.registered ? 'check' : 'quiz'} size={19} /></span>
        <div class="mc-reg-copy">
          <b>{contest.registered ? 'Bạn đã có tên trong cuộc thi' : 'Sẵn sàng thử sức cùng mọi người?'}</b>
          <span>{contest.registered ? `Bắt đầu ${contest.startsLabel} · Thi ${contest.durationMin} phút` : 'Đăng ký trước giờ bắt đầu để được vào thi.'}</span>
          {#if contest.registered}<small>Đến giờ, quay lại đây để vào làm bài nhé.</small>{/if}
        </div>
      </div>
      <div class="mc-reg-meta">
        <button type="button" onclick={() => showRanking('registrants')}>
          <Icon name="users" size={14} /> {contest.registrantCount} người đăng ký
        </button>
        {#if contest.registered}<button type="button" class="mc-cancel-registration" disabled={registering} onclick={() => setRegistered(false)}><Icon name="x" size={15} /> {registering ? 'Đang xử lý…' : 'Huỷ đăng ký'}</button>{/if}
      </div>
    {:else}
    <p class="mc-reg-note">
      {#if contest.status === 'open' && contest.mockTestId && contest.questionCount === 0}
        Đề thi đang được cập nhật, bạn quay lại sau ít phút nhé.
      {:else if contest.status === 'open' && !contest.mockTestId}
        Bài thi đã bắt đầu, bạn chưa đăng ký nên không vào làm được.
      {:else if contest.myAttempt === 'doing'}
        <Icon name="clock" size={15} /> Bạn đang làm dở bài thi này
      {:else if contest.registered || contest.myAttempt === 'submitted'}
        <Icon name="check" size={15} /> Bạn đã đăng ký · {contest.registrantCount} người tham gia
      {:else}
        {contest.registrantCount} người đã đăng ký · Hạn đăng ký: trước giờ bắt đầu
      {/if}
    </p>
    {/if}
    <span class="mc-reg-feedback" role="status">{registrationMessage}</span>
  {/if}
{/snippet}

{#snippet prizeList()}
  {#if contestStore.prizes.length > 0}
    <div class="mc-prizes">
      <div class="mc-prizes-head">
        <b>Cơ cấu giải thưởng</b>
        <small>Trích từ quỹ tháng {month}</small>
      </div>
      <ul>
        {#each contestStore.prizes as p, i (i)}
          <li class:mc-prize-top={i < 3}>
            <span class="mc-place" class:mc-medal-place={i < 3} role="img" aria-label={`Hạng ${i + 1}`}>{MEDALS[i] ?? i + 1}</span>
            <span class="mc-prize-name"><b>{p.label}</b><small>{p.percent}% quỹ</small></span>
            <span class="mc-prize-money">{prizeMoney(p)}</span>
          </li>
        {/each}
      </ul>
    </div>
  {/if}
{/snippet}

<!-- Hộp quỹ cần thấy cả Shopee lẫn giải trong một màn: ba giải đầu xếp ngang, giải còn lại gom một dòng. Bản đủ nằm ở Thể lệ. -->
{#snippet prizeCompact()}
  {#if contestStore.prizes.length > 0}
    <div class="mc-prizes mc-prizes-compact">
      <div class="mc-prizes-head">
        <b>Cơ cấu giải thưởng</b>
        <button
          type="button"
          class="mc-rule-link"
          onclick={() => {
            dialog = 'contest';
          }}>Xem thể lệ</button
        >
      </div>
      <ul class="mc-podium" style="--cot: {Math.min(contestStore.prizes.length, 3)}">
        {#each contestStore.prizes.slice(0, 3) as p, i (i)}
          <li>
            <span class="mc-podium-medal" role="img" aria-label={`Hạng ${i + 1}`}>{MEDALS[i]}</span>
            <span class="mc-podium-name">{p.label}</span>
            <b>{prizeMoney(p)}</b>
          </li>
        {/each}
      </ul>
      {#if contestStore.prizes.length > 3}
        <ul class="mc-prize-rest">
          {#each contestStore.prizes.slice(3) as p, i (i)}
            <li>{p.label} <b>{prizeMoney(p)}</b></li>
          {/each}
        </ul>
      {/if}
    </div>
  {/if}
{/snippet}

{#snippet countdown(unified = false)}
  {#if contest}
    <div class="mc-countdown" class:mc-countdown-card={unified}>
      <span class="mc-countdown-label">
        {contest.status === 'upcoming' ? 'Bắt đầu sau' : '⏱ Thời gian thi còn lại'}
      </span>
      <ContestCountdown
        {unified}
        active={contest.status === 'open'}
        seconds={contest.status === 'open' ? contest.endsInSec : contest.startsInSec}
        onend={() => void contestStore.refresh()}
      />
    </div>
  {/if}
{/snippet}

<div class="mc-header-actions" role="group" aria-label="Quỹ thưởng, Shopee và top chăm học">
  <button
    type="button"
    class="mc-fund-pill"
    aria-label={`Xem quỹ thưởng tháng ${month}: ${money}`}
    title="Quỹ thưởng tháng"
    onclick={openFund}
    >{@render bag()}<span class="mc-fund-text"
      ><span>Quỹ tháng <span class="mc-desktop-month">{month}</span></span><b>{money}</b></span
    ></button
  >
  <button
    type="button"
    class="mc-header-top"
    title="Top chăm học"
    aria-label="Xem top học viên chăm học"
    onclick={() => showRanking('study')}><span aria-hidden="true">🏆</span><b>Top chăm học</b></button
  >
</div>

{#if dialog === 'overview'}
  <Modal
    title={contest?.title ?? 'Cuộc thi giải đề'}
    anchor="top"
    width={570}
    onclose={() => {
      dialog = null;
    }}
  >
    <section class="mc-contest" aria-label="Cuộc thi tháng và quỹ thưởng">
      <div class="mc-contest-top">
        <span class="mc-kicker">Cuộc thi tháng {month}</span>
        {#if contest?.status === 'open'}<span class="tag tag-ok">Đang diễn ra</span>
        {:else if contest}<span class="tag tag-warn">Sắp diễn ra</span>
        {:else}<span class="tag">Chưa có lịch</span>{/if}
      </div>
      {@render countdown()}
      <!-- Quỹ đến từ hoa hồng Shopee nên hai thứ nằm chung một khối, thấy số tiền là thấy ngay chỗ góp quỹ. -->
      <div class="mc-fund-box">
        <div class="mc-fund">
          <div>
            <span class="mc-fund-label">Quỹ thưởng tháng {month}</span><strong class="num">{money}</strong><span class="mc-source"
              >Tổng hoa hồng Shopee{#if updatedOn}&nbsp;· Cập nhật {updatedOn}{/if}</span
            >
          </div>
          <span class="mc-fund-mark" lang="zh" aria-hidden="true">奖</span>
        </div>
        {#if supportLinks.fund.length > 0}
          <button type="button" class="mc-shopee" onclick={openShop}>
            {@render bag()}<span
              ><b>Ghé Shopee, góp một chút niềm vui</b><small
                >{supportLinks.fund.length} sản phẩm · <span class="mc-shop-why">Mua khi cần, cùng góp vào quỹ · </span>Liên kết tiếp thị</small
              ></span
            ><Icon name="chev" size={16} />
          </button>
        {/if}
      </div>
      {@render prizeCompact()}
      {@render regNote()}
      {#if canRegister}
        <button type="button" class="btn btn-son btn-lg mc-cta" disabled={registering} onclick={() => setRegistered(true)}
          >{registering ? 'Đang đăng ký…' : 'Đăng ký tham gia'}</button
        >
      {:else if contest?.status === 'upcoming'}
        <button type="button" class="btn btn-lg mc-cta" onclick={() => (dialog = 'contest')}>Xem thông tin cuộc thi</button>
      {:else if examHref}
        <a
          class="btn btn-son btn-lg mc-cta"
          href={examHref}
          onclick={() => {
            dialog = null;
          }}>{examLabel} </a
        >
      {:else if submitted}
        <button type="button" class="btn btn-son btn-lg mc-cta" onclick={() => showRanking('contest')}
          >Xem bảng xếp hạng </button
        >
      {:else if !contest}
        <p class="mc-history-hint">Lịch thi mới sẽ được thông báo tại đây.</p>
        <button type="button" class="btn btn-lg mc-cta" onclick={openHistory}><Icon name="clock" size={17} /> Lịch sử thi &amp; thưởng</button>
      {:else}
        <button
          type="button"
          class="btn btn-lg mc-cta"
          class:btn-son={contest !== null && !contest.registered}
          onclick={() => {
            dialog = 'contest';
          }}>Xem thông tin cuộc thi</button
        >
      {/if}
      {#if contest}<button type="button" class="mc-history-link" onclick={openHistory}>Lịch sử thi &amp; thưởng</button>{/if}
    </section>
  </Modal>
{/if}

{#if dialog === 'contest'}
  <Modal
    title={contest?.title ?? `Cuộc thi giải đề tháng ${month}`}
    anchor="top"
    width={840}
    mobileFullscreen
    onclose={() => {
      dialog = 'overview';
    }}
  >
    <div class="mc-modal-content mc-contest-detail" class:mc-without-contest={!contest}>
      {#if !contest}
        <p class="mc-muted-note">Lịch thi sẽ được thông báo tại đây.</p>
      {/if}
      {@render countdown(true)}
      <div class="mc-modal-fund">
        <span class="mc-fund-trophy" aria-hidden="true">🏆</span>
        <div><span>Quỹ thưởng tháng {month}</span><strong>{money}</strong><small>Từ tổng hoa hồng Shopee</small></div>
        <span class="mc-detail-fund-mark" lang="zh" aria-hidden="true">奖</span>
      </div>
      {@render prizeList()}
      <div class="mc-detail-lines">
        <h3 class="mc-rules-title">Thông tin cần biết</h3>
        <p><Icon name="flag" size={18} /><span><b>Nhận thưởng qua ngân hàng</b><small>Sau khi công bố kết quả, người đạt giải gửi tên ngân hàng, số tài khoản và tên chủ tài khoản qua Góp ý (Chuyển nhanh → Góp ý). Không cần nhập khi đăng ký thi.</small></span></p>
        {#if contest}<p><Icon name="clock" size={18} /><span><b>Hết giờ tự nộp bài</b><small>Bài thi kết thúc lúc {contest.endsLabel}. Các câu đã làm sẽ được nộp tự động.</small></span></p>{/if}
        <p>
          <Icon name="quiz" size={18} /><span
            ><b>Đăng ký trước giờ thi</b><small>Chỉ người đăng ký trước giờ bắt đầu mới được làm bài, mỗi người một lượt.</small></span
          >
        </p>
        <p>
          <Icon name="flag" size={18} /><span
            ><b>Cách xếp hạng</b><small>Đúng nhiều câu hơn xếp trên, bằng nhau thì ai làm nhanh hơn xếp trên.</small></span
          >
        </p>
      </div>
    </div>
    {#snippet actions()}{#if contest?.status === 'upcoming'}<button type="button" class="btn" onclick={() => showRanking('registrants')}
          >Danh sách đăng ký</button
        >{:else}<button type="button" class="btn" onclick={() => showRanking('contest')}>Xem bảng xếp hạng</button>{/if}{#if canRegister}<button
          type="button"
          class="btn btn-son"
          disabled={registering}
          onclick={() => setRegistered(true)}>Đăng ký thi</button
        >{:else if contest?.status === 'upcoming'}<button type="button" class="btn" onclick={() => (dialog = null)}
          >Đóng</button
        >{:else if examHref}<a
          class="btn btn-son"
          href={examHref}
          onclick={() => {
            dialog = null;
          }}>{examLabel}</a
        >{:else}<button
          type="button"
          class="btn btn-son"
          onclick={() => {
            dialog = null;
          }}>Mình hiểu rồi</button
        >{/if}{/snippet}
  </Modal>
{:else if dialog === 'ranking'}
  <Modal
    title={`Bảng xếp hạng tháng ${month}`}
    anchor="top"
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
          onclick={() => showRanking('contest')}>🏆 Cuộc thi</button
        ><button
          type="button"
          class="tab"
          class:on={ranking === 'registrants'}
          onclick={() => showRanking('registrants')}>📋 Đăng ký{registrants?.count ? ` (${registrants.count})` : ''}</button
        >
      </div>
      {#if ranking === 'study'}
        {#snippet studyRow(row: StudyRow)}
          <li class:mc-me={row.isMe}>
            <span class="mc-place" class:mc-medal-place={row.rank <= 3} role="img" aria-label={`Hạng ${row.rank}`}
              >{MEDALS[row.rank - 1] ?? row.rank}</span
            ><span class="mc-avatar" style="background: var(--lv{((row.rank - 1) % 3) + 1})">{initials(row.name)}</span><span class="mc-person"
              ><b>{row.name}{row.isMe ? ' (bạn)' : ''}</b><small>{row.days} ngày học · {row.done} phần bài học xong</small></span
            ><span class="mc-minutes"><b>{row.minutes.toLocaleString('vi-VN')}</b><small>phút học</small></span>
          </li>
        {/snippet}
        {#if studyTop && studyTop.rows.length > 0}
          <ol class="mc-full-list">
            {#each studyTop.rows as row (row.rank)}{@render studyRow(row)}{/each}
          </ol>
          {#if studyTop.me && studyTop.me.rank > studyTop.rows.length}
            <ol class="mc-full-list mc-my-rank">{@render studyRow(studyTop.me)}</ol>
          {/if}
        {:else if studyTop}
          <div class="mc-contest-empty">
            <span aria-hidden="true">🌱</span>
            <h3>Tháng này chưa có ai lên bảng</h3>
            <p class="muted">Học từ một phút trở lên là có tên ở đây.</p>
          </div>
        {/if}
        {#if studyTop?.rows.length && !studyTop.me && !session.isStaff}<p class="mc-muted-note">Bạn chưa có phút học nào trong tháng này.</p>{/if}
      {:else if ranking === 'registrants'}
        {#if registrants?.contest && registrants.rows.length > 0}
          <div class="mc-rank-head">
            <b>{registrants.contest.title}</b>
            <span class="tag">{registrants.count} người đã đăng ký</span>
          </div>
          <ol class="mc-full-list">
            {#each registrants.rows as row, i (i)}
              <li class:mc-me={row.isMe}>
                <span class="mc-place">{i + 1}</span><span class="mc-avatar" style="background: var(--lv{(i % 3) + 1})">{initials(row.name)}</span
                ><span class="mc-person"><b>{row.name}{row.isMe ? ' (bạn)' : ''}</b><small>Đăng ký lúc {row.at}</small></span>
              </li>
            {/each}
          </ol>
        {:else}
          <div class="mc-contest-empty">
            <span aria-hidden="true">📋</span>
            <h3>{registrants?.contest ? 'Chưa có ai đăng ký' : 'Chưa có lịch thi'}</h3>
            <p class="muted">
              {registrants?.contest ? 'Hãy là người đăng ký đầu tiên nhé.' : 'Có bài thi mới thì danh sách đăng ký sẽ hiện ở đây.'}
            </p>
          </div>
        {/if}
        {#if canRegister}
          <button type="button" class="btn btn-son" disabled={registering} onclick={() => setRegistered(true)}>Đăng ký thi</button>
        {/if}
      {:else if contestRanking?.contest && contestRanking.rows.length > 0}<div class="mc-rank-head">
          <b>{contestRanking.contest.title}</b>
          <span class="tag" class:tag-ok={contestRanking.contest.status === 'open'}>
            {contestRanking.contest.status === 'open' ? 'Đang diễn ra' : 'Đã kết thúc'} · {contestRanking.participants} người
          </span>
        </div>
        {#if myPrize && myPrize.amount !== null && myPrize.amount > 0}
          <div class="mc-claim-banner"><span aria-hidden="true">🎉</span><div><b>Bạn đạt {myPrize.label}!</b><small>Phần thưởng {prizeMoney(myPrize)} · Gửi tên ngân hàng, số tài khoản và tên chủ tài khoản qua Góp ý để nhận thưởng.</small></div>{#if feedback.available}<button type="button" class="btn btn-son btn-sm" onclick={claimPrize}>Gửi thông tin nhận thưởng</button>{/if}</div>
        {/if}
        {#snippet rankRow(row: RankRow)}
          <li class:mc-me={row.isMe}>
            <span class="mc-place" class:mc-medal-place={row.rank <= 3} role="img" aria-label={`Hạng ${row.rank}`}
              >{MEDALS[row.rank - 1] ?? row.rank}</span
            ><span class="mc-avatar" style="background: var(--lv{((row.rank - 1) % 3) + 1})">{initials(row.name)}</span><span class="mc-person"
              ><b>{row.name}{row.isMe ? ' (bạn)' : ''}</b><small>{row.participated ? `Làm trong ${clock(row.usedSec * 1000)}` : 'Không tham gia · 0 điểm'}</small
              >{#if row.participated && contestStore.prizes[row.rank - 1]}{@const p = contestStore.prizes[row.rank - 1]}<small class="mc-row-prize"
                  >{p.label} · {prizeMoney(p)}</small
                >{/if}</span
            ><span class="mc-minutes"><b>{row.correct}/{row.total}</b><small>câu đúng</small></span>
          </li>
        {/snippet}
        <ol class="mc-full-list">
          {#each contestRanking.rows as row, ri (ri)}{@render rankRow(row)}{/each}
        </ol>
        {#if contestRanking.me && contestRanking.me.rank > contestRanking.rows.length}
          <ol class="mc-full-list mc-my-rank">{@render rankRow(contestRanking.me)}</ol>
        {/if}
      {:else}<div class="mc-contest-empty">
          <span aria-hidden="true">🏆</span>
          <h3>{contestRanking?.contest ? 'Chưa có ai nộp bài' : 'Những vị trí đầu đang chờ bạn'}</h3>
          <p class="muted">
            {contestRanking?.contest ? 'Bạn nộp bài là có tên ở đây ngay.' : 'Bảng xếp hạng sẽ xuất hiện sau khi cuộc thi bắt đầu.'}
          </p>
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
{:else if dialog === 'shop'}
  <Modal
    title="Ghé Shopee"
    anchor="top"
    width={570}
    onclose={() => {
      dialog = null;
    }}
  >
    <div class="mc-shop">
      <p class="muted mc-shop-intro">Thích sản phẩm nào thì bấm vào để mở trên Shopee. Mua khi bạn cần, cùng góp vào quỹ thưởng.</p>
      <div class="mc-search">
        <span class="ico-in">
          <Icon name="search" size={16} />
          <input class="inp" type="search" bind:value={search} placeholder="Tìm sản phẩm" aria-label="Tìm sản phẩm" />
        </span>
      </div>
      {#if products.length > 0}
        <ul class="mc-links">
          {#each products as item (item.id)}<li>
              <a class="mc-link" href={item.url} target="_blank" rel="noopener sponsored"
                >{@render bag()}<span>{item.name}</span><Icon name="ext" size={16} /></a
              >
            </li>{/each}
        </ul>
      {:else}
        <EmptyState title="Không tìm thấy sản phẩm." desc="Thử gõ từ khác xem sao." />
      {/if}
      <small class="mc-affiliate">Liên kết tiếp thị</small>
    </div>
    {#snippet actions()}<button
        type="button"
        class="btn"
        onclick={() => {
          dialog = 'overview';
        }}><Icon name="chev" size={16} style="transform: rotate(180deg)" /> Quay lại quỹ</button
      >{/snippet}
  </Modal>
{/if}

<ContestHistory bind:open={historyOpen} />

<style>
  .mc-history-hint { margin-top: 14px; font-size: 12.5px; text-align: center; color: var(--muted); line-height: 1.6; }
  .mc-history-link { display: block; margin: 8px auto 0; padding: 10px; min-height: 44px; font: inherit; font-size: 12.5px; color: var(--muted); background: transparent; border: 0; cursor: pointer; }
  .mc-history-link:hover { color: var(--son-text); }
  .mc-claim-banner { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; padding: 16px; border: 1px solid var(--bo-dot); border-radius: 14px; background: var(--bo-paper); }
  .mc-claim-banner > span { font-size: 28px; }
  .mc-claim-banner > div { flex: 1; min-width: 160px; }
  .mc-claim-banner b { display: block; font-size: 15px; }
  .mc-claim-banner small { display: block; font-size: 12px; color: var(--muted); margin-top: 5px; line-height: 1.5; }
  /* Cụm trên thanh đầu trang */
  .mc-header-actions {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    flex: none;
  }
  .mc-fund-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    height: 44px;
    padding: 0 12px 0 8px;
    border: 1px solid var(--bo-dot);
    border-radius: 13px;
    background: var(--bo-paper);
    color: var(--text);
    cursor: pointer;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
  }
  .mc-fund-pill:hover {
    background: var(--dao);
  }
  .mc-fund-pill .mc-bag {
    width: 29px;
    height: 32px;
  }
  .mc-fund-text {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 2px;
  }
  .mc-fund-text > span {
    font-size: 11px;
    color: var(--text-2);
    line-height: 1.2;
  }
  .mc-fund-text > b {
    font-size: 14px;
    color: var(--warn-text);
    line-height: 1.2;
  }
  .mc-header-top {
    color: var(--text);
    cursor: pointer;
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
      gap: 5px;
      padding: 0 7px 0 5px;
      border-radius: 11px;
    }
    .mc-fund-pill .mc-bag {
      width: 24px;
      height: 27px;
    }
    .mc-fund-text {
      gap: 1px;
    }
    .mc-fund-text > span {
      font-size: 10px;
    }
    .mc-fund-text > b {
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
    margin-top: 8px;
    min-width: 0;
  }
  .mc-contest-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
  }
  .mc-kicker {
    font-size: 13px;
    color: var(--muted);
    line-height: 1.5;
  }
  .mc-fund-box {
    margin-top: 14px;
    border: 1px solid var(--bo-dot);
    border-radius: 14px;
    overflow: hidden;
  }
  .mc-fund {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bo-paper);
    padding: 12px 18px;
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
    font-size: 28px;
    font-weight: 800;
    line-height: 1.3;
    color: var(--warn-text);
  }
  .mc-source {
    font-size: 12px;
    color: var(--muted);
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
  .mc-countdown {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 14px;
  }
  .mc-countdown-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-2);
  }
  .mc-countdown-card {
    padding: 14px 16px;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: var(--surface-2);
    justify-content: center;
    gap: 9px;
  }
  .mc-modal-content > .mc-countdown {
    margin-top: 0;
  }
  .mc-cta {
    width: 100%;
    margin-top: 14px;
    justify-content: center;
    gap: 8px;
  }
  .mc-reg-note {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
    font-size: 13px;
    color: var(--text-2);
  }
  .mc-registration {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px;
    margin-top: 14px;
    border: 1px solid var(--line);
    border-radius: 14px;
    background: var(--surface);
  }
  .mc-registration-done { background: var(--lv1); }
  .mc-reg-icon {
    display: grid;
    place-items: center;
    flex: none;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    background: var(--surface);
    color: var(--son-text);
    border: 1px solid var(--line);
  }
  .mc-reg-copy { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
  .mc-reg-copy b { font-size: 14px; line-height: 1.5; }
  .mc-reg-copy span, .mc-reg-copy small { font-size: 12.5px; line-height: 1.6; color: var(--muted); }
  .mc-reg-meta { display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap; margin-top: 6px; }
  .mc-reg-meta button {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 8px 2px; min-height: 40px;
    border: 0; background: transparent; color: var(--muted); font: inherit; font-size: 12.5px; cursor: pointer;
  }
  .mc-reg-meta button:hover { color: var(--son-text); }
  .mc-reg-meta button:disabled { opacity: .5; cursor: wait; }
  .mc-reg-meta button.mc-cancel-registration {
    min-height: 44px;
    padding: 9px 14px;
    border: 1px solid var(--line);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text-2);
    font-size: 13px;
    font-weight: 600;
    transition: background .15s, border-color .15s;
  }
  .mc-reg-meta button.mc-cancel-registration:hover {
    background: var(--surface-2);
    border-color: var(--son-text);
    color: var(--son-text);
  }
  .mc-reg-feedback { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
  .mc-prizes {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 14px 16px;
    border: 1px solid var(--line);
    border-radius: 14px;
    font-size: 14px;
  }
  .mc-prizes-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 2px 10px;
  }
  .mc-prizes-head b {
    font-size: 15px;
  }
  .mc-prizes-head small {
    font-size: 12px;
    color: var(--muted);
  }
  .mc-prizes ul {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
  }
  .mc-prizes li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 7px 10px;
    border-radius: 10px;
  }
  /* Ba giải đầu nổi hơn để lướt qua là thấy ngay phần thưởng lớn nhất. */
  .mc-prizes li.mc-prize-top {
    background: var(--bo-paper);
  }
  .mc-prize-name {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    line-height: 1.3;
  }
  .mc-prize-name small {
    font-size: 12px;
    color: var(--muted);
  }
  .mc-prize-money {
    flex-shrink: 0;
    font-size: 15px;
    font-weight: 800;
    color: var(--warn-text);
    font-variant-numeric: tabular-nums;
  }
  .mc-prizes-compact {
    margin-top: 12px;
    padding: 12px 14px;
    gap: 8px;
  }
  .mc-rule-link {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    padding: 0;
    border: 0;
    background: none;
    color: var(--text-2);
    font: inherit;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
  }
  .mc-rule-link:hover {
    color: var(--son-text);
  }
  .mc-prizes ul.mc-podium {
    display: grid;
    grid-template-columns: repeat(var(--cot), minmax(0, 1fr));
    gap: 8px;
  }
  .mc-prizes .mc-podium li {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1px;
    padding: 8px 4px;
    background: var(--bo-paper);
    text-align: center;
  }
  .mc-podium-medal {
    font-size: 20px;
    line-height: 1.1;
  }
  .mc-podium-name {
    font-size: 12.5px;
    color: var(--text-2);
    line-height: 1.3;
  }
  .mc-podium b {
    font-size: 15px;
    font-weight: 800;
    color: var(--warn-text);
    font-variant-numeric: tabular-nums;
  }
  .mc-prizes ul.mc-prize-rest {
    flex-direction: row;
    flex-wrap: wrap;
    gap: 2px 14px;
    font-size: 13px;
    color: var(--text-2);
  }
  .mc-prizes .mc-prize-rest li {
    display: inline;
    padding: 0;
  }
  .mc-prize-rest b {
    color: var(--warn-text);
    font-variant-numeric: tabular-nums;
  }
  .mc-person small.mc-row-prize {
    color: var(--warn-text);
    font-weight: 700;
  }
  .mc-shopee {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    padding: 8px 14px;
    border: 0;
    border-top: 1px solid var(--bo-dot);
    background: var(--surface);
    color: var(--text);
    font: inherit;
    text-align: left;
    cursor: pointer;
  }
  .mc-shopee:hover {
    background: var(--surface-2);
  }
  .mc-shopee :global(svg:last-child) {
    flex-shrink: 0;
    color: var(--muted);
  }
  .mc-shop {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 6px;
  }
  .mc-shop-intro {
    font-size: 14px;
    line-height: 1.5;
  }
  /* Danh sách dài cuộn trong hộp mà ô tìm vẫn ở trên cùng; top âm bằng đệm của .modal để không hở khe cho sản phẩm lọt lên trên. */
  .mc-search {
    position: sticky;
    top: -22px;
    z-index: 1;
    padding: 10px 0 6px;
    background: var(--surface);
  }
  .mc-links {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  .mc-link {
    display: flex;
    align-items: center;
    gap: 12px;
    min-height: 60px;
    padding: 8px 12px;
    border: 1px solid var(--line);
    border-radius: 12px;
    color: var(--text);
    font-size: 14px;
    font-weight: 600;
    line-height: 1.35;
    text-decoration: none;
  }
  .mc-link:hover {
    border-color: var(--bo-dot);
    background: var(--bo-paper);
  }
  .mc-link > span {
    flex: 1;
    min-width: 0;
  }
  .mc-link .mc-bag {
    width: 30px;
    height: 33px;
  }
  .mc-link :global(svg:last-child) {
    flex-shrink: 0;
    color: var(--muted);
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
    margin-top: 2px;
    line-height: 1.35;
  }
  .mc-shopee .mc-bag {
    width: 32px;
    height: 35px;
  }
  .mc-affiliate {
    display: block;
    margin-top: 6px;
    font-size: 11px;
    color: var(--muted);
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
  .mc-full-list li.mc-me {
    margin: 0 -10px;
    padding-left: 10px;
    padding-right: 10px;
    border-radius: 12px;
    background: var(--bo-paper);
  }
  .mc-my-rank {
    border-top: 1px dashed var(--line);
    padding-top: 6px;
  }
  .mc-rank-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 15px;
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

  /* Hộp cuộc thi */
  .mc-modal-content {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding-top: 14px;
  }
  @media (min-width: 768px) {
    .mc-contest-detail {
      display: grid;
      grid-template-columns: minmax(0, 1.1fr) minmax(0, 1fr);
      gap: 16px;
      align-items: start;
      padding-top: 2px;
    }
    .mc-contest-detail > .mc-countdown { margin: 0; align-self: stretch; }
    .mc-contest-detail > .mc-modal-fund { align-self: stretch; padding: 12px 14px; }
    .mc-contest-detail > .mc-muted-note { grid-column: 1 / -1; }
    .mc-contest-detail > .mc-prizes { padding: 12px; gap: 8px; }
    .mc-contest-detail .mc-prizes-head small { display: none; }
    .mc-contest-detail .mc-prizes li { padding: 6px 8px; }
    .mc-contest-detail > .mc-detail-lines { padding: 14px; gap: 16px; }
    .mc-without-contest > .mc-modal-fund { align-self: start; grid-column: 1 / -1; }
  }
  .mc-modal-fund {
    display: flex;
    align-items: center;
    position: relative;
    overflow: hidden;
    gap: 14px;
    padding: 18px 16px;
    border: 1px solid var(--bo-dot);
    border-radius: 14px;
    background: var(--bo-paper);
  }
  .mc-modal-fund > div { display: flex; flex-direction: column; gap: 5px; min-width: 0; z-index: 1; }
  .mc-modal-fund > div > span {
    font-size: 13px;
  }
  .mc-modal-fund strong {
    font-size: 26px;
    color: var(--warn-text);
    font-weight: 800;
    line-height: 1.25;
  }
  .mc-modal-fund small {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.45;
  }
  .mc-fund-trophy { display: grid; place-items: center; width: 48px; height: 48px; flex: none; border-radius: 15px; background: var(--surface); border: 1px solid var(--bo-dot); font-size: 26px; z-index: 1; }
  .mc-detail-fund-mark { position: absolute; right: -6px; bottom: -22px; font-family: var(--font-hz-serif); font-size: 110px; line-height: 1; color: var(--bo-dot); opacity: .45; pointer-events: none; }
  .mc-detail-lines {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 16px;
    border: 1px solid var(--line);
    border-radius: 14px;
  }
  .mc-rules-title { font-size: 15px; margin: 0 0 2px; }
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

  @media (max-width: 767px) {
    .mc-search {
      top: -18px;
    }
    /* Điện thoại: bỏ câu phụ để nút Đăng ký/Vào thi vẫn nằm trong màn, không phải cuộn mới thấy. */
    .mc-shop-why {
      display: none;
    }
  }
  @media (max-width: 700px) {
    .mc-fund strong {
      font-size: 26px;
    }
  }
</style>
