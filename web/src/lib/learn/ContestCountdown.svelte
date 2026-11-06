<script lang="ts">
  // Đồng hồ đếm ngược ngày, giờ, phút, giây. Tính từ số giây server trả về nên đồng hồ máy người học chạy sai cũng không lệch.
  let { seconds, active = false, unified = false, onend }: { seconds: number; active?: boolean; unified?: boolean; onend?: () => void } = $props();

  let now = $state(Date.now());
  const endAt = $derived(Date.now() + seconds * 1000);
  const left = $derived(Math.max(0, Math.ceil((endAt - now) / 1000)));
  const units = $derived.by(() => {
    const days = Math.floor(left / 86400);
    const parts = [
      { key: 'gio', label: 'Giờ', value: Math.floor((left % 86400) / 3600) },
      { key: 'phut', label: 'Phút', value: Math.floor((left % 3600) / 60) },
      { key: 'giay', label: 'Giây', value: left % 60 },
    ];
    return days > 0 ? [{ key: 'ngay', label: 'Ngày', value: days }, ...parts] : parts;
  });
  const spoken = $derived(units.map((u) => `${u.value} ${u.label.toLowerCase()}`).join(' '));
  const pad = (n: number): string => String(n).padStart(2, '0');

  $effect(() => {
    const timer = setInterval(() => (now = Date.now()), 1000);
    return () => clearInterval(timer);
  });

  let endedFor = -1;
  $effect(() => {
    if (left === 0 && endedFor !== seconds) {
      endedFor = seconds;
      onend?.();
    }
  });
</script>

<div class="cd" class:cd-unified={unified} class:cd-live={active} class:cd-urgent={active && left <= 300} role="timer" aria-label={spoken}>
  {#each units as u (u.key)}
    <div class="cd-unit">
      <b class="bignum">{pad(u.value)}</b>
      <span>{u.label}</span>
    </div>
  {/each}
</div>
{#if active}
  <p class="cd-note">{left === 0 ? 'Đã hết thời gian thi' : left <= 300 ? 'Sắp hết giờ · Cuộc thi sẽ đóng khi đồng hồ về 0' : 'Cuộc thi đang mở · Đồng hồ đếm đến lúc kết thúc'}</p>
{/if}

<style>
  .cd {
    display: flex;
    gap: 8px;
  }
  .cd-unit {
    flex: 1 1 0;
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    padding: 10px 4px 8px;
    border: 1px solid var(--line);
    border-radius: 12px;
    background: var(--surface);
  }
  .cd-unit b {
    font-size: 26px;
    color: var(--son-text);
  }
  .cd-unit span {
    font-size: 12px;
    color: var(--muted);
  }
  .cd-live .cd-unit {
    background: #ecfdf5;
    border-color: #a7e3c4;
    box-shadow: inset 0 2px 0 #22a766;
    padding-top: 14px;
  }
  .cd-live .cd-unit b {
    color: #087443;
    font-size: clamp(30px, 6vw, 38px);
    font-variant-numeric: tabular-nums;
    line-height: 1.15;
  }
  .cd-live .cd-unit span { color: #38634e; }
  .cd-urgent .cd-unit {
    background: #fff1f2;
    border-color: #f6b6bd;
    box-shadow: inset 0 2px 0 #e64555;
  }
  .cd-urgent .cd-unit b { color: #be2336; }
  .cd-urgent .cd-unit span { color: #87434b; }
  .cd-note {
    margin: 8px 0 0;
    font-size: 12px;
    line-height: 1.5;
    color: var(--muted);
  }
  .cd-unified { gap: 0; }
  .cd-unified .cd-unit {
    position: relative;
    border: 0;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    padding: 0 4px;
    gap: 3px;
  }
  .cd-unified .cd-unit b { font-size: 30px; line-height: 1.15; font-variant-numeric: tabular-nums; }
  .cd-unified .cd-unit + .cd-unit::before {
    content: ':';
    position: absolute;
    left: -3px;
    top: 1px;
    font-size: 25px;
    line-height: 1.15;
    color: var(--muted);
    opacity: .45;
  }
  :global(html[data-theme='dark']) .cd-unit b {
    color: var(--bad-text);
  }
  :global(html[data-theme='dark']) .cd-live .cd-unit b { color: #087443; }
  :global(html[data-theme='dark']) .cd-urgent .cd-unit b { color: #be2336; }
</style>
