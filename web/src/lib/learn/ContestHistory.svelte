<script lang="ts">
  import Modal from '$lib/components/Modal.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { contestStore, type PastContest, type Prize, type RankRow } from './contest.svelte';
  import { feedback } from '$lib/stores/feedback.svelte';
  import { clock, num } from '$lib/utils/format';
  import { session } from '$lib/stores/session.svelte';

  let { open = $bindable(false) }: { open?: boolean } = $props();
  // Cuộc thi đã kết thúc lấy từ /contest/history. Cuộc thi thật không ghi số tiền: quỹ không chụp lại theo từng
  // cuộc thi, lấy quỹ tháng hiện tại thì nói sai số đã trao. Giải minh họa (isDemo) mang sẵn tên giải và tiền.
  const history = $derived(contestStore.history);
  let selected = $state<number | null>(null);
  const item = $derived(history?.find((x) => x.id === selected) ?? null);
  const medals = ['🥇', '🥈', '🥉'];
  const prizeOf = (rank: number): Prize | null => contestStore.prizes[rank - 1] ?? null;
  const trangThai = { cho: 'Chờ thông tin nhận thưởng', 'dang-xu-ly': 'Đang xử lý', 'da-trao': 'Đã trao thưởng' };
  const phu = (c: PastContest): string => (c.isDemo ? `Giải thưởng tháng ${c.rewardMonth}` : `${c.participants} người tham gia`);

  function nhan(row: RankRow): string {
    if (!session.can('community.contests.rewards')) return `Hạng ${row.rank}`;
    if (!row.isDemo) return prizeOf(row.rank)?.label ?? `Hạng ${row.rank}`;
    return [row.prize || `Hạng ${row.rank}`, row.rewardStatus ? trangThai[row.rewardStatus] : ''].filter(Boolean).join(' · ');
  }

  $effect(() => {
    if (open) void contestStore.refreshHistory();
  });

  // Chưa có cách nhận thưởng riêng: người đạt giải gửi tài khoản ngân hàng qua Góp ý, quản trị đọc ở /admin/feedback.
  function claim(): void {
    open = false;
    selected = null;
    feedback.open();
  }
  function back(): void {
    if (item) selected = null;
    else open = false;
  }
</script>

{#if open && session.can('community.contests.history')}
  <Modal title={item ? item.title : 'Lịch sử thi & thưởng'} width={640} mobileFullscreen onclose={back}>
    <div class="ch-content">
      {#if item}
        <div class="ch-heading">
          <span class="ch-trophy" aria-hidden="true">🏆</span>
          <div><h3>{item.title}</h3><p>Kết thúc {item.endedOn} · {phu(item)}</p></div>
        </div>
        <section class="ch-result">
          <div class="ch-section-title">
            <h3>Kết quả của bạn</h3>
            {#if item.me}<span class="tag">Hạng {item.me.rank}/{item.participants}</span>{/if}
          </div>
          {#if !item.me}
            <p>Bạn không tham gia cuộc thi này.</p>
          {:else if !item.me.participated}
            <p>Bạn đã đăng ký nhưng không vào làm bài.</p>
          {:else}
            {@const giai = prizeOf(item.me.rank)}
            {#if giai && session.can('community.contests.rewards')}<b class="ch-win">{giai.label}</b>{/if}
            <p>Đúng {item.me.correct}/{item.me.total} câu · làm trong {clock(item.me.usedSec * 1000)}.</p>
            {#if giai && session.can('community.contests.rewards')}
              <p>Chưa nhận thưởng thì gửi tên ngân hàng, số tài khoản và tên chủ tài khoản qua Góp ý.</p>
              {#if feedback.available && session.can('community.feedback')}
                <button type="button" class="btn btn-son ch-claim-button" onclick={claim}>Gửi thông tin nhận thưởng</button>
              {/if}
            {/if}
          {/if}
        </section>
        <section class="ch-winners">
          <h3>Dẫn đầu</h3>
          {#if item.top.length > 0}
            <ol>
              <!-- Khoá theo vị trí: quản trị có thể nhập hai giải minh họa cùng hạng. -->
              {#each item.top as row, i (i)}
                <li>
                  <span class="ch-medal" aria-hidden="true">{medals[row.rank - 1] ?? row.rank}</span>
                  <div><b>{row.name}{row.isMe ? ' (bạn)' : ''}</b><small>{nhan(row)}</small></div>
                  {#if !row.isDemo}<strong>{row.correct}/{row.total} câu</strong>
                  {:else if row.amount}<strong>{num(row.amount)} đ</strong>{/if}
                </li>
              {/each}
            </ol>
          {:else}
            <p class="ch-intro">Không có ai nộp bài.</p>
          {/if}
        </section>
      {:else if history === null}
        <p class="ch-intro">Đang tải…</p>
      {:else if history.length === 0}
        <div class="ch-empty">
          <span aria-hidden="true">🏆</span>
          <h3>Chưa có cuộc thi nào kết thúc</h3>
          <p>Kết quả từng cuộc thi sẽ lưu ở đây ngay khi hết giờ thi.</p>
        </div>
      {:else}
        <p class="ch-intro">Nhìn lại kết quả và những người dẫn đầu qua từng cuộc thi.</p>
        <div class="ch-list">
          {#each history as row (row.id)}
            <button type="button" class="ch-card" onclick={() => (selected = row.id)}>
              <div class="ch-card-top"><span class="ch-month">Kết thúc {row.endedOn}</span><span class="ch-ended">Đã kết thúc</span></div>
              <h3>{row.title}</h3>
              <p>{phu(row)}</p>
              <div class="ch-personal">
                <Icon name={row.me?.participated ? 'flag' : 'clock'} size={15} />
                <span>{row.me?.participated ? `Bạn xếp hạng ${row.me.rank}/${row.participants}` : row.me ? 'Bạn đăng ký nhưng không làm bài' : 'Bạn không tham gia'}</span>
                <span class="ch-view">Xem kết quả <Icon name="chev" size={16} /></span>
              </div>
            </button>
          {/each}
        </div>
      {/if}
    </div>
    {#snippet actions()}<button type="button" class="btn" onclick={back}>{item ? 'Về danh sách' : 'Đóng'}</button>{/snippet}
  </Modal>
{/if}

<style>
  .ch-claim-button { display: flex; margin-top: 14px; min-height: 44px; }
  .ch-content { display: flex; flex-direction: column; gap: 16px; }
  .ch-intro { font-size: 13px; line-height: 1.6; color: var(--muted); }
  .ch-list { display: flex; flex-direction: column; gap: 14px; }
  .ch-card { width: 100%; text-align: left; font: inherit; color: var(--text); background: var(--surface); padding: 16px; border: 1px solid var(--line); border-radius: 16px; cursor: pointer; transition: border-color .15s, box-shadow .15s; }
  .ch-card:hover { border-color: var(--son-text); box-shadow: var(--sh-sm); }
  .ch-card-top, .ch-section-title { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
  .ch-month { font-size: 12px; font-weight: 600; color: var(--son-text); }
  .ch-ended { font-size: 11px; color: var(--muted); }
  .ch-card h3 { font-size: 16px; margin: 10px 0 5px; }
  .ch-card p, .ch-heading p { font-size: 12px; color: var(--muted); line-height: 1.6; }
  .ch-view { display: inline-flex; align-items: center; margin-left: auto; color: var(--text-2); min-height: 32px; }
  .ch-personal { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--line); font-size: 12px; }
  .ch-heading { display: flex; gap: 12px; align-items: center; }
  .ch-heading h3 { font-size: 17px; margin-bottom: 5px; }
  .ch-trophy { font-size: 30px; flex: none; }
  .ch-result, .ch-winners { border: 1px solid var(--line); border-radius: 14px; padding: 16px; }
  .ch-result { background: var(--lv1); }
  .ch-content h3 { font-weight: 700; }
  .ch-section-title h3, .ch-winners h3 { font-size: 14px; }
  .ch-win { display: block; font-size: 18px; margin-top: 12px; }
  .ch-result p { font-size: 13px; color: var(--muted); line-height: 1.6; margin: 8px 0 0; }
  .ch-winners ol { list-style: none; padding: 0; margin: 12px 0 0; }
  .ch-winners li { display: flex; align-items: center; gap: 10px; padding: 12px 0; }
  .ch-winners li + li { border-top: 1px solid var(--line); }
  .ch-medal { font-size: 24px; min-width: 28px; text-align: center; }
  .ch-winners li > div { flex: 1; min-width: 0; }
  .ch-winners b { font-size: 13px; }
  .ch-winners small { display: block; font-size: 12px; color: var(--muted); margin-top: 3px; }
  .ch-winners strong { color: var(--warn-text); font-size: 14px; white-space: nowrap; }
  .ch-empty { text-align: center; padding: 28px 12px; }
  .ch-empty > span { font-size: 36px; }
  .ch-empty h3 { font-size: 16px; margin: 10px 0 6px; }
  .ch-empty p { font-size: 13px; color: var(--muted); line-height: 1.6; }
</style>
