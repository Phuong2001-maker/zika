<script lang="ts">
  import Modal from '$lib/components/Modal.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import PrizeClaim from './PrizeClaim.svelte';

  let { open = $bindable(false) }: { open?: boolean } = $props();
  const money = (n: number): string => n.toLocaleString('vi-VN') + ' ₫';
  // Dữ liệu duyệt giao diện: giải và quỹ là bản chụp riêng từng cuộc thi, không dùng quỹ tháng hiện tại.
  const history = [
    { id: 10, month: '10/2026', date: '31/10/2026', title: 'Thử sức HSK · Tháng 10', fund: 1200000, people: 32, rank: 2, prize: 'Giải Nhì', amount: 300000, status: 'unclaimed', winners: ['Nguyễn Thảo Linh', 'Bạn', 'Lê Hoàng Nam'], amounts: [600000, 300000, 180000] },
    { id: 9, month: '09/2026', date: '30/09/2026', title: 'Chinh phục đề thi · Tháng 9', fund: 1000000, people: 28, rank: 3, prize: 'Giải Ba', amount: 150000, status: 'paid', winners: ['Trần Minh Anh', 'Phạm Ngọc Mai', 'Bạn'], amounts: [500000, 250000, 150000] },
    { id: 8, month: '08/2026', date: '31/08/2026', title: 'Cùng tiến bộ · Tháng 8', fund: 800000, people: 24, rank: 12, prize: '', amount: 0, status: 'none', winners: ['Lê Hoàng Nam', 'Nguyễn Thảo Linh', 'Phạm Ngọc Mai'], amounts: [400000, 200000, 120000] },
  ];
  let selected = $state<number | null>(null);
  const item = $derived(history.find((x) => x.id === selected));
  let claimOpen = $state(false);
  type ClaimStatus = 'unclaimed' | 'pending' | 'paid';
  let claimStatuses = $state<Record<number, ClaimStatus>>({});
  const statusOf = (row: typeof history[number]): string => claimStatuses[row.id] ?? row.status;
  const statusLabel = (status: string): string => status === 'paid' ? 'Đã chuyển thưởng' : status === 'pending' ? 'Chờ chuyển khoản' : 'Chưa gửi thông tin';
  const medals = ['🥇', '🥈', '🥉'];
</script>

{#if open && !claimOpen}
  <Modal title={item ? `Kết quả tháng ${item.month}` : 'Lịch sử thi & thưởng'} width={640} mobileFullscreen onclose={() => item ? (selected = null) : (open = false)}>
    <div class="ch-content">
      <p class="ch-preview">Dữ liệu minh hoạ để duyệt giao diện · Chưa kết nối lịch sử thực tế.</p>
      {#if item}
        <div class="ch-heading"><span class="ch-trophy" aria-hidden="true">🏆</span><div><h3>{item.title}</h3><p>Đã tổ chức {item.date} · {item.people} người tham gia</p></div></div>
        <div class="ch-fund"><span>Quỹ thưởng tháng {item.month}</span><strong>{money(item.fund)}</strong><small>Số tiền của cuộc thi này, tách biệt với quỹ tháng hiện tại.</small></div>
        <section class="ch-result"><div class="ch-section-title"><h3>Kết quả của bạn</h3><span class="tag">Hạng {item.rank}/{item.people}</span></div>
          {#if item.amount > 0}<b class="ch-win">{item.prize} · {money(item.amount)}</b><p>{statusOf(item) === 'paid' ? 'Đã chuyển thưởng qua ngân hàng.' : statusOf(item) === 'pending' ? 'Đã gửi thông tin ngân hàng, đang chờ quản trị viên chuyển thưởng.' : 'Chúc mừng bạn! Cung cấp tài khoản ngân hàng để nhận phần thưởng.'}</p>
            <span class="tag" class:tag-ok={statusOf(item) === 'paid'} class:tag-warn={statusOf(item) !== 'paid'}>{statusLabel(statusOf(item))}</span>
            {#if statusOf(item) === 'unclaimed'}<button type="button" class="btn btn-son ch-claim-button" onclick={() => (claimOpen = true)}>Nhận thưởng</button>{/if}
          {:else}<p>Bạn đã hoàn thành cuộc thi. Cùng cố gắng ở cuộc thi tiếp theo nhé!</p>{/if}
        </section>
        <section class="ch-winners"><h3>Những gương mặt đạt giải</h3><ol>{#each item.winners as name, i (i)}<li><span class="ch-medal" aria-hidden="true">{medals[i]}</span><div><b>{name}</b><small>{['Giải Nhất', 'Giải Nhì', 'Giải Ba'][i]}</small></div><strong>{money(item.amounts[i])}</strong></li>{/each}</ol></section>
      {:else}
        <p class="ch-intro">Nhìn lại kết quả, những người đạt giải và phần thưởng của bạn qua từng tháng.</p>
        <div class="ch-list">{#each history as row (row.id)}
          <button type="button" class="ch-card" onclick={() => (selected = row.id)}>
            <div class="ch-card-top"><span class="ch-month">Tháng {row.month}</span><span class="ch-ended">Đã kết thúc</span></div>
            <h3>{row.title}</h3><p>{row.date} · {row.people} người tham gia</p>
            <div class="ch-card-bottom"><span>Quỹ thưởng <b>{money(row.fund)}</b></span><span class="ch-view">Xem kết quả <Icon name="chev" size={16} /></span></div>
            <div class="ch-personal"><Icon name={row.amount > 0 ? 'check' : 'flag'} size={15} /><span>{row.amount > 0 ? `${row.prize} · ${money(row.amount)}` : `Bạn xếp hạng ${row.rank}`}</span>{#if row.amount > 0}<small class="tag" class:tag-ok={statusOf(row) === 'paid'} class:tag-warn={statusOf(row) !== 'paid'}>{statusLabel(statusOf(row))}</small>{/if}</div>
          </button>
        {/each}</div>
      {/if}
    </div>
    {#snippet actions()}<button type="button" class="btn" onclick={() => item ? (selected = null) : (open = false)}>{item ? 'Về danh sách' : 'Đóng'}</button>{/snippet}
  </Modal>
{/if}

{#key selected}
  <PrizeClaim bind:open={claimOpen} title={item ? `Cuộc thi giải đề tháng ${item.month}` : ''} prize={item?.prize ?? ''} amount={money(item?.amount ?? 0)} onstatus={(status) => { if (item) claimStatuses[item.id] = status; }} />
{/key}

<style>
  .ch-claim-button { display: flex; margin-top: 14px; min-height: 44px; }
  .ch-content { display: flex; flex-direction: column; gap: 16px; }
  .ch-preview { color: var(--muted); font-size: 12px; line-height: 1.6; padding: 9px 12px; background: var(--surface-2); border-radius: 10px; }
  .ch-intro { font-size: 13px; line-height: 1.6; color: var(--muted); }
  .ch-list { display: flex; flex-direction: column; gap: 14px; }
  .ch-card { width: 100%; text-align: left; font: inherit; color: var(--text); background: var(--surface); padding: 16px; border: 1px solid var(--line); border-radius: 16px; cursor: pointer; transition: border-color .15s, box-shadow .15s; }
  .ch-card:hover { border-color: var(--son-text); box-shadow: var(--sh-sm); }
  .ch-card-top, .ch-card-bottom, .ch-section-title { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
  .ch-month { font-size: 12px; font-weight: 600; color: var(--son-text); }
  .ch-ended { font-size: 11px; color: var(--muted); }
  .ch-card h3 { font-size: 16px; margin: 10px 0 5px; }
  .ch-card p, .ch-heading p { font-size: 12px; color: var(--muted); line-height: 1.6; }
  .ch-card-bottom { margin-top: 14px; font-size: 12px; }
  .ch-card-bottom b { margin-left: 5px; color: var(--warn-text); font-size: 15px; }
  .ch-view { display: inline-flex; align-items: center; color: var(--text-2); min-height: 32px; }
  .ch-personal { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--line); font-size: 12px; }
  .ch-personal small { margin-left: auto; color: var(--muted); font-size: 11px; }
  .ch-heading { display: flex; gap: 12px; align-items: center; }
  .ch-heading h3 { font-size: 17px; margin-bottom: 5px; }
  .ch-trophy { font-size: 30px; flex: none; }
  .ch-fund { border: 1px solid var(--bo-dot); border-radius: 14px; background: var(--bo-paper); padding: 16px; display: flex; flex-direction: column; gap: 5px; }
  .ch-fund > span { font-size: 13px; }
  .ch-fund strong { color: var(--warn-text); font-size: 28px; }
  .ch-fund small { color: var(--muted); font-size: 12px; line-height: 1.5; }
  .ch-result, .ch-winners { border: 1px solid var(--line); border-radius: 14px; padding: 16px; }
  .ch-result { background: var(--lv1); }
  .ch-content h3 { font-weight: 700; }
  .ch-section-title h3, .ch-winners h3 { font-size: 14px; }
  .ch-win { display: block; font-size: 18px; margin-top: 12px; }
  .ch-result p { font-size: 13px; color: var(--muted); line-height: 1.6; margin: 8px 0 12px; }
  .ch-winners ol { list-style: none; padding: 0; margin: 12px 0 0; }
  .ch-winners li { display: flex; align-items: center; gap: 10px; padding: 12px 0; }
  .ch-winners li + li { border-top: 1px solid var(--line); }
  .ch-medal { font-size: 24px; }
  .ch-winners li > div { flex: 1; min-width: 0; }
  .ch-winners b { font-size: 13px; }
  .ch-winners small { display: block; font-size: 12px; color: var(--muted); margin-top: 3px; }
  .ch-winners strong { color: var(--warn-text); font-size: 14px; white-space: nowrap; }
</style>
