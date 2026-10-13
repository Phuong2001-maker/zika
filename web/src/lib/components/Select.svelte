<script lang="ts">
  import Icon from './Icon.svelte';

  // Ô chọn tự dựng, thay cho <select> của trình duyệt.
  // Lý do: danh sách bung ra của <select> do hệ điều hành vẽ, CSS không đụng được,
  // nên trông lạc hẳn với phần còn lại của app (05 §2).
  export type Opt = { v: unknown; label: string };

  let {
    value = $bindable(),
    options,
    id = undefined,
    ariaLabel = undefined,
    onchange = undefined,
  }: {
    value: unknown;
    options: Opt[];
    id?: string;
    ariaLabel?: string;
    onchange?: () => void;
  } = $props();

  let open = $state(false);
  let active = $state(0);
  /** Toạ độ panel, tính theo nút. Dùng position: fixed để không bị hộp thoại (overflow: auto) cắt mất. */
  let pos = $state<{ top: number; left: number; minW: number } | null>(null);
  let root: HTMLDivElement | undefined = $state();
  let list: HTMLUListElement | undefined = $state();

  const selected = $derived(options.findIndex((o) => o.v === value));
  const label = $derived(selected >= 0 ? options[selected].label : '');
  const uid = $derived(id ?? `sel-${Math.random().toString(36).slice(2, 8)}`);

  function show(): void {
    active = selected >= 0 ? selected : 0;
    open = true;
    // Chờ panel dựng xong rồi mới chuyển con trỏ bàn phím vào.
    queueMicrotask(() => list?.focus());
  }

  function hide(back = true): void {
    if (!open) return;
    open = false;
    pos = null;
    if (back) queueMicrotask(() => root?.querySelector('button')?.focus());
  }

  function pick(i: number): void {
    const next = options[i]?.v;
    hide();
    if (next === value) return;
    value = next;
    onchange?.();
  }

  function onKey(e: KeyboardEvent): void {
    if (!open) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        show();
      }
      return;
    }
    if (e.key === 'Escape') {
      e.preventDefault();
      hide();
    } else if (e.key === 'ArrowDown') {
      e.preventDefault();
      active = (active + 1) % options.length;
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      active = (active - 1 + options.length) % options.length;
    } else if (e.key === 'Home') {
      e.preventDefault();
      active = 0;
    } else if (e.key === 'End') {
      e.preventDefault();
      active = options.length - 1;
    } else if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      pick(active);
    } else if (e.key === 'Tab') {
      hide(false);
    }
  }

  // Bấm ra ngoài thì đóng, giống bảng Chuyển nhanh.
  $effect(() => {
    if (!open) return;
    const onDoc = (e: MouseEvent) => {
      if (root && !root.contains(e.target as Node)) hide(false);
    };
    document.addEventListener('mousedown', onDoc);
    return () => document.removeEventListener('mousedown', onDoc);
  });

  // Đặt panel ngay dưới nút; nếu dưới không đủ chỗ thì bung lên trên.
  $effect(() => {
    if (!open || !list || !root) return;
    const btn = root.querySelector('button');
    if (!btn) return;
    const r = btn.getBoundingClientRect();
    const h = list.offsetHeight;
    const duoi = window.innerHeight - r.bottom - 8;
    pos = {
      top: h <= duoi ? r.bottom + 6 : Math.max(8, r.top - h - 6),
      left: r.left,
      minW: r.width,
    };
  });

  // Cuộn hay đổi cỡ cửa sổ thì đóng, vì toạ độ đã tính cứng lúc mở.
  $effect(() => {
    if (!open) return;
    const dong = () => hide(false);
    window.addEventListener('scroll', dong, true);
    window.addEventListener('resize', dong);
    return () => {
      window.removeEventListener('scroll', dong, true);
      window.removeEventListener('resize', dong);
    };
  });

  // Giữ mục đang trỏ luôn nằm trong tầm nhìn khi đi bằng bàn phím.
  $effect(() => {
    if (!open) return;
    list?.querySelector<HTMLElement>(`#${uid}-o${active}`)?.scrollIntoView({ block: 'nearest' });
  });
</script>

<div class="sel2" bind:this={root} onkeydown={onKey} role="presentation">
  <button
    type="button"
    id={id}
    class="inp btn-sel"
    class:on={open}
    aria-haspopup="listbox"
    aria-expanded={open}
    aria-label={ariaLabel}
    onclick={() => (open ? hide() : show())}
  >
    <span class="val">{label}</span>
    <Icon name="down" size={18} />
  </button>

  {#if open}
    <ul
      class="block-sm panel"
      role="listbox"
      tabindex="-1"
      aria-activedescendant="{uid}-o{active}"
      aria-label={ariaLabel}
      bind:this={list}
      style:top={pos ? `${pos.top}px` : undefined}
      style:left={pos ? `${pos.left}px` : undefined}
      style:min-width={pos ? `${pos.minW}px` : undefined}
      style:visibility={pos ? 'visible' : 'hidden'}
    >
      {#each options as o, i (i)}
        <!-- Bàn phím xử lý ở <ul> qua aria-activedescendant — đúng mẫu listbox của ARIA,
             nên từng <li> không cần onkeydown riêng. -->
        <!-- svelte-ignore a11y_click_events_have_key_events -->
        <li
          id="{uid}-o{i}"
          role="option"
          aria-selected={i === selected}
          class="sopt"
          class:cur={i === active}
          class:on={i === selected}
          onclick={() => pick(i)}
          onmouseenter={() => (active = i)}
        >
          <span class="txt">{o.label}</span>
          {#if i === selected}<Icon name="check" size={16} />{/if}
        </li>
      {/each}
    </ul>
  {/if}
</div>

<style>
  .sel2 {
    position: relative;
    display: block;
    width: 100%;
  }
  .btn-sel {
    display: flex;
    align-items: center;
    gap: 8px;
    text-align: left;
    cursor: pointer;
  }
  .btn-sel .val {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .btn-sel :global(svg) {
    flex: none;
    color: var(--text-2);
    transition: transform 0.15s ease;
  }
  .btn-sel.on {
    border-color: var(--son);
    box-shadow: var(--ring);
  }
  .btn-sel.on :global(svg) {
    transform: rotate(180deg);
  }

  .panel {
    /* fixed, không phải absolute: hộp thoại có overflow: auto sẽ cắt mất panel. */
    position: fixed;
    /* Rộng ít nhất bằng ô, nhưng giãn theo nhãn dài nhất để không bị cắt chữ. */
    width: max-content;
    max-width: min(340px, calc(100vw - 32px));
    z-index: 90;
    max-height: 288px;
    overflow-y: auto;
    padding: 6px;
    margin: 0;
    list-style: none;
    outline: none;
    animation: drop 0.12s ease;
  }
  @keyframes drop {
    from {
      opacity: 0;
      transform: translateY(-4px);
    }
    to {
      opacity: 1;
      transform: none;
    }
  }
  @media (prefers-reduced-motion: reduce) {
    .panel {
      animation: none;
    }
  }

  .sopt {
    display: flex;
    align-items: center;
    gap: 8px;
    min-height: 38px;
    padding: 0 10px;
    border-radius: 9px;
    font-size: 14.5px;
    color: var(--text);
    cursor: pointer;
  }
  .sopt .txt {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
  .sopt :global(svg) {
    flex: none;
    color: var(--son);
  }
  .sopt.cur {
    background: var(--ground);
  }
  .sopt.on {
    font-weight: 700;
  }
  .sopt.on.cur {
    background: var(--son-soft);
  }
</style>
