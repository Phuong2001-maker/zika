<script lang="ts">
  import Modal from '$lib/components/Modal.svelte';
  import Icon from '$lib/components/Icon.svelte';

  let { open = $bindable(false), title, prize, amount, onstatus }: { open?: boolean; title: string; prize: string; amount: string; onstatus?: (status: 'pending' | 'paid' | 'unclaimed') => void } = $props();
  // Bản duyệt giao diện: thông tin chỉ ở bộ nhớ, chưa gửi hoặc lưu tài khoản ngân hàng.
  let step = $state<'form' | 'review' | 'pending' | 'paid'>('form');
  let bank = $state('');
  let account = $state('');
  let holder = $state('');
  let error = $state('');
  function review(e: SubmitEvent): void {
    e.preventDefault();
    error = '';
    if (!bank.trim() || !/^\d{6,20}$/.test(account.trim()) || !holder.trim()) {
      error = 'Điền đủ ngân hàng, tên chủ tài khoản và số tài khoản gồm 6–20 chữ số.';
      return;
    }
    step = 'review';
  }
</script>

{#if open}
  <Modal title={step === 'form' ? 'Thông tin nhận thưởng' : step === 'review' ? 'Kiểm tra thông tin' : 'Phần thưởng của bạn'} mobileFullscreen width={520} onclose={() => (open = false)}>
    <div class="pc-content">
      <div class="pc-award"><span aria-hidden="true">🏅</span><div><small>{title} · {prize}</small><strong>{amount}</strong><p>Nhận thưởng qua chuyển khoản ngân hàng</p></div></div>
      <p class="pc-preview">Bản xem thử giao diện · Chưa gửi thông tin hoặc thực hiện chuyển khoản.</p>
      {#if step === 'form'}
        <form id="prize-claim-form" onsubmit={review} class="pc-form">
          <label>Ngân hàng<input class="inp" bind:value={bank} required maxlength="100" placeholder="Ví dụ: Vietcombank" autocomplete="off" /></label>
          <label>Số tài khoản<input class="inp" bind:value={account} required inputmode="numeric" pattern={'[0-9]{6,20}'} maxlength="20" placeholder="Nhập số tài khoản, không phải số thẻ" autocomplete="off" /></label>
          <label>Tên chủ tài khoản<input class="inp" bind:value={holder} required maxlength="100" placeholder="Ví dụ: NGUYEN MINH ANH" autocomplete="off" /></label>
          <p class="pc-hint"><Icon name="lock" size={15} /> Thông tin nhận thưởng không xuất hiện trên bảng xếp hạng.</p>
          {#if error}<p class="err" role="alert">{error}</p>{/if}
        </form>
      {:else}
        {#if step === 'pending' || step === 'paid'}
          <div class="pc-status" role="status"><Icon name="check" size={20} /><div><b>{step === 'paid' ? 'Đã chuyển thưởng' : 'Đã gửi thông tin nhận thưởng'}</b><p>{step === 'paid' ? 'Kiểm tra tài khoản ngân hàng của bạn nhé.' : 'Chờ quản trị viên chuyển khoản. Bạn có thể xem lại thông tin bên dưới.'}</p></div></div>
        {:else}<p class="pc-hint">Kiểm tra kỹ để phần thưởng được chuyển đúng tài khoản của bạn.</p>{/if}
        <dl class="pc-details"><div><dt>Ngân hàng</dt><dd>{bank}</dd></div><div><dt>Số tài khoản</dt><dd class="num">{account}</dd></div><div><dt>Chủ tài khoản</dt><dd>{holder.trim().toLocaleUpperCase('vi-VN')}</dd></div><div><dt>Số tiền thưởng</dt><dd>{amount}</dd></div><div><dt>Trạng thái</dt><dd>{step === 'paid' ? 'Đã chuyển thưởng' : step === 'pending' ? 'Chờ chuyển khoản' : 'Chưa gửi'}</dd></div></dl>
      {/if}
    </div>
    {#snippet actions()}
      {#if step === 'form'}<button type="button" class="btn" onclick={() => (open = false)}>Để sau</button><button type="submit" form="prize-claim-form" class="btn btn-son">Tiếp tục</button>
      {:else if step === 'review'}<button type="button" class="btn" onclick={() => (step = 'form')}>Sửa thông tin</button><button type="button" class="btn btn-son" onclick={() => { step = 'pending'; onstatus?.('pending'); }}>Xác nhận thông tin</button>
      {:else}<button type="button" class="btn" onclick={() => { const next = step === 'pending' ? 'paid' : 'unclaimed'; step = next === 'paid' ? 'paid' : 'form'; onstatus?.(next); }}>{step === 'pending' ? 'Xem thử đã chuyển thưởng' : 'Xem lại mẫu nhập'}</button><button type="button" class="btn btn-son" onclick={() => (open = false)}>Đóng</button>{/if}
    {/snippet}
  </Modal>
{/if}

<style>
  .pc-content { display: flex; flex-direction: column; gap: 16px; }
  .pc-award { display: flex; align-items: center; gap: 14px; padding: 18px; border: 1px solid var(--bo-dot); border-radius: 16px; background: var(--bo-paper); }
  .pc-award > span { font-size: 36px; }
  .pc-award small { color: var(--muted); font-size: 12px; }
  .pc-award strong { display: block; font-size: 28px; color: var(--warn-text); margin: 4px 0; }
  .pc-award p, .pc-preview, .pc-hint { font-size: 12.5px; line-height: 1.6; color: var(--muted); }
  .pc-preview { padding: 10px 12px; border-radius: 10px; background: var(--surface-2); }
  .pc-form { display: flex; flex-direction: column; gap: 16px; }
  .pc-form label { display: flex; flex-direction: column; gap: 7px; font-size: 13px; font-weight: 600; }
  .pc-form input { font-weight: 400; }
  .pc-hint { display: flex; align-items: flex-start; gap: 7px; }
  .pc-hint :global(svg) { flex: none; margin-top: 2px; }
  .pc-status { display: flex; gap: 10px; padding: 14px; border-radius: 12px; background: var(--lv1); border: 1px solid var(--line); }
  .pc-status :global(svg) { flex: none; color: var(--son-text); }
  .pc-status b { font-size: 14px; }
  .pc-status p { font-size: 12.5px; color: var(--muted); line-height: 1.6; margin-top: 4px; }
  .pc-details { margin: 0; border: 1px solid var(--line); border-radius: 14px; padding: 4px 14px; }
  .pc-details > div { padding: 12px 0; display: flex; justify-content: space-between; gap: 16px; font-size: 13px; }
  .pc-details > div + div { border-top: 1px solid var(--line); }
  .pc-details dt { color: var(--muted); flex: none; }
  .pc-details dd { margin: 0; text-align: right; font-weight: 600; overflow-wrap: anywhere; }
</style>
