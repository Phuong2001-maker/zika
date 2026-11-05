<script lang="ts">
  // Đồng hồ đếm ngược ngày, giờ, phút, giây. Tính từ số giây server trả về nên đồng hồ máy người học chạy sai cũng không lệch.
  let { seconds, onend }: { seconds: number; onend?: () => void } = $props();

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

<div class="cd" role="timer" aria-label={spoken}>
  {#each units as u (u.key)}
    <div class="cd-unit">
      <b class="bignum">{pad(u.value)}</b>
      <span>{u.label}</span>
    </div>
  {/each}
</div>

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
  :global(html[data-theme='dark']) .cd-unit b {
    color: var(--bad-text);
  }
</style>
