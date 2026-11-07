<script lang="ts">
  import { onMount, tick } from 'svelte';

  let text = $state('');
  let x = $state(0);
  let y = $state(0);
  let positioned = $state(false);
  let bubble = $state<HTMLDivElement>();
  const id = 'zk-shared-tooltip';

  onMount(() => {
    let target: HTMLElement | null = null;
    let timer: ReturnType<typeof setTimeout> | undefined;
    let previousDescription: string | null = null;

    // Chỉ chuyển title của DOM thực, không đụng prop title của Modal/EmptyState.
    function enhance(root: HTMLElement): void {
      const elements = [...root.querySelectorAll<HTMLElement>('[title]')];
      if (root instanceof HTMLElement && root.hasAttribute('title')) elements.push(root);
      for (const el of elements) {
        const title = el.getAttribute('title');
        if (title?.trim()) el.dataset.zkTooltip = title;
        else delete el.dataset.zkTooltip;
        el.removeAttribute('title');
      }
    }

    function hide(): void {
      clearTimeout(timer);
      if (target) {
        if (previousDescription === null) target.removeAttribute('aria-describedby');
        else target.setAttribute('aria-describedby', previousDescription);
      }
      target = null;
      text = '';
      positioned = false;
    }

    async function reveal(el: HTMLElement): Promise<void> {
      if (!el.isConnected || target !== el || !el.dataset.zkTooltip) return;
      text = el.dataset.zkTooltip;
      await tick();
      if (!bubble || target !== el) return;
      const rect = el.getBoundingClientRect();
      const tip = bubble.getBoundingClientRect();
      const padding = 8;
      x = Math.max(padding, Math.min(rect.left + rect.width / 2 - tip.width / 2, window.innerWidth - tip.width - padding));
      const above = rect.top - tip.height - 8;
      y = Math.max(padding, Math.min(above >= padding ? above : rect.bottom + 8, window.innerHeight - tip.height - padding));
      positioned = true;
    }

    function show(el: HTMLElement, delay: number): void {
      if (target === el) return;
      hide();
      target = el;
      previousDescription = el.getAttribute('aria-describedby');
      el.setAttribute('aria-describedby', [previousDescription, id].filter(Boolean).join(' '));
      timer = setTimeout(() => void reveal(el), delay);
    }

    function trigger(e: Event): HTMLElement | null {
      return e.target instanceof Element ? e.target.closest<HTMLElement>('[data-zk-tooltip]') : null;
    }
    function pointerOver(e: PointerEvent): void {
      if (e.pointerType !== 'mouse' && e.pointerType !== 'pen') return;
      const el = trigger(e);
      if (el) show(el, 350);
    }
    function pointerOut(e: PointerEvent): void {
      if (e.relatedTarget instanceof Node && bubble?.contains(e.relatedTarget)) return;
      if (target && (!(e.relatedTarget instanceof Node) || !target.contains(e.relatedTarget))) hide();
    }
    function focusIn(e: FocusEvent): void {
      const el = trigger(e);
      if (el?.matches(':focus-visible')) show(el, 0);
    }
    // Xoá phần tử đang có focus làm trình duyệt bắn focusout ngay giữa lúc Svelte dựng lại DOM; đổi $state lúc đó
    // sẽ ném state_unsafe_mutation nên phải chờ xong lượt dựng. Trong lúc chờ nếu đã sang phần tử khác thì thôi.
    function focusOut(): void {
      const luc = target;
      queueMicrotask(() => {
        if (target === luc) hide();
      });
    }
    function keyDown(e: KeyboardEvent): void {
      if (e.key === 'Escape' && target) {
        e.stopPropagation();
        hide();
      }
    }

    enhance(document.body);
    const observer = new MutationObserver((records) => {
      for (const record of records) {
        if (record.type === 'attributes' && record.target instanceof HTMLElement && record.target.hasAttribute('title')) enhance(record.target);
        for (const node of record.addedNodes) if (node instanceof HTMLElement) enhance(node);
      }
      if (target && (!target.isConnected || !target.dataset.zkTooltip)) hide();
    });
    observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['title'] });
    document.addEventListener('pointerover', pointerOver);
    document.addEventListener('pointerout', pointerOut);
    document.addEventListener('focusin', focusIn);
    document.addEventListener('focusout', focusOut);
    document.addEventListener('pointerdown', hide);
    document.addEventListener('keydown', keyDown, true);
    document.addEventListener('scroll', hide, true);
    window.addEventListener('resize', hide);
    return () => {
      observer.disconnect();
      hide();
      document.removeEventListener('pointerover', pointerOver);
      document.removeEventListener('pointerout', pointerOut);
      document.removeEventListener('focusin', focusIn);
      document.removeEventListener('focusout', focusOut);
      document.removeEventListener('pointerdown', hide);
      document.removeEventListener('keydown', keyDown, true);
      document.removeEventListener('scroll', hide, true);
      window.removeEventListener('resize', hide);
    };
  });
</script>

{#if text}
  <div bind:this={bubble} {id} class="zk-tooltip-layer" class:zk-tooltip-ready={positioned} role="tooltip" style:left="{x}px" style:top="{y}px">{text}</div>
{/if}

<style>
  .zk-tooltip-layer {
    position: fixed;
    z-index: 300;
    width: max-content;
    max-width: min(280px, calc(100vw - 16px));
    padding: 9px 12px;
    border: 1px solid var(--line-3);
    border-radius: 10px;
    background: var(--surface);
    color: var(--text-2);
    box-shadow: var(--sh-md);
    font-family: var(--font-ui);
    font-size: 13px;
    font-weight: 400;
    line-height: 1.6;
    letter-spacing: normal;
    text-align: left;
    white-space: pre-line;
    overflow-wrap: anywhere;
    pointer-events: auto;
    visibility: hidden;
    opacity: 0;
    transition: opacity .12s ease;
  }
  .zk-tooltip-ready { visibility: visible; opacity: 1; }
  @media (prefers-reduced-motion: reduce) { .zk-tooltip-layer { transition: none; } }
</style>
