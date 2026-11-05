<script lang="ts">
  import { onMount } from 'svelte';
  import { page } from '$app/state';
  import { toast } from '$lib/stores/toast.svelte';
  import { media } from '$lib/stores/media.svelte';
  import { buddyShownOn } from './shopee-buddy';

  // Chữ trôi theo hình làm người đọc hoa mắt, nhưng giấu chữ thì không ai biết bấm để làm gì.
  // Nên bé đứng nói là chính, mỗi lần chỉ đi một quãng ngắn, và chữ chỉ hiện khi đã đứng yên.
  const FIGURE = 84;
  const WALK_SPEED = 36;
  const HOP_LENGTH = 240;
  const REST_SECONDS = 9;
  const FIRST_REST_SECONDS = 12;
  // Đủ chỗ cho lời nhắn nằm phía trên hình mà không đè thanh đầu trang.
  const MIN_TOP = 190;

  let hidden = $state(true);
  let leaving = $state(false);
  let hovering = $state(false);
  let covered = $state(false);
  // Giữ component sống khi sang màn làm bài rồi quay lại, để bé đã ẩn thì vẫn ẩn.
  const blocked = $derived(covered || !buddyShownOn(page.url.pathname));
  let ready = $state(false);
  let reducedMotion = $state(false);
  let x = $state(16);
  let y = $state(MIN_TOP);
  let viewportWidth = $state(1200);
  let phase = $state<'walk' | 'rest' | 'quiet'>('rest');
  let facing = $state(-1);
  let lookX = $state(0);
  let lookY = $state(0);

  const speaking = $derived((phase === 'rest' || hovering) && !leaving && !blocked);
  const walking = $derived(phase === 'walk' && !hovering && !blocked && !leaving);
  const onRightSide = $derived(x + FIGURE / 2 > viewportWidth / 2);

  let exitTimer: ReturnType<typeof setTimeout> | undefined;
  let stops: [number, number][] = [];
  let stopIndex = 0;
  let direction = -1;
  let speed = 0;
  let targetX = 16;
  let targetY = MIN_TOP;
  let restLeft = FIRST_REST_SECONDS;
  let pointerX: number | null = null;
  let pointerY = 0;

  /** Đường đi hình chữ U men theo cạnh màn hình, để bé không đi chéo qua nội dung đang học. */
  function buildStops(): [number, number][] {
    const left = 12;
    const right = Math.max(left, window.innerWidth - FIGURE - 12);
    // Điện thoại có thanh nút dính đáy màn hình (.mbar) nên phải đi cao hơn để không bị che.
    const bottom = Math.max(MIN_TOP, window.innerHeight - FIGURE - (media.isMobile ? 84 : 16));
    const middle = Math.min(bottom, Math.max(MIN_TOP, Math.round(window.innerHeight * 0.5)));
    const corners: [number, number][] = [
      [left, middle],
      [left, bottom],
      [right, bottom],
      [right, middle],
    ];
    const points: [number, number][] = [corners[0]];
    for (let i = 1; i < corners.length; i++) {
      const [fromX, fromY] = corners[i - 1];
      const [toX, toY] = corners[i];
      const length = Math.hypot(toX - fromX, toY - fromY);
      const hops = Math.ceil(length / HOP_LENGTH);
      for (let hop = 1; hop <= hops; hop++) {
        points.push([Math.round(fromX + ((toX - fromX) * hop) / hops), Math.round(fromY + ((toY - fromY) * hop) / hops)]);
      }
    }
    return points;
  }

  function nearestStop(toX: number, toY: number): number {
    let best = 0;
    stops.forEach(([stopX, stopY], index) => {
      if (Math.hypot(stopX - toX, stopY - toY) < Math.hypot(stops[best][0] - toX, stops[best][1] - toY)) best = index;
    });
    return best;
  }

  function walkToNextStop(): void {
    if (stops.length < 2) return;
    if (stopIndex + direction < 0 || stopIndex + direction >= stops.length) direction = -direction;
    stopIndex += direction;
    [targetX, targetY] = stops[stopIndex];
    if (Math.abs(targetX - x) > 1) facing = targetX < x ? -1 : 1;
    phase = 'walk';
  }

  function hide(): void {
    if (leaving) return;
    leaving = true;
    exitTimer = setTimeout(() => {
      hidden = true;
      toast.show('Bé túi đi nghỉ rồi nhé! Đây là bản xem trước, chưa mở liên kết Shopee thật.', 'info', 5000);
    }, 500);
  }

  onMount(() => {
    const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
    reducedMotion = preference.matches;
    const changePreference = (): void => {
      reducedMotion = preference.matches;
    };
    preference.addEventListener('change', changePreference);

    // Trong giai đoạn duyệt giao diện, tải lại trang là hiện bé để thử tiếp.
    hidden = false;
    viewportWidth = window.innerWidth;
    stops = buildStops();
    stopIndex = nearestStop(window.innerWidth, window.innerHeight);
    [x, y] = stops[stopIndex];
    targetX = x;
    targetY = y;
    ready = true;

    function trackPointer(event: PointerEvent): void {
      if (event.pointerType !== 'mouse') return;
      pointerX = event.clientX;
      pointerY = event.clientY;
    }
    window.addEventListener('pointermove', trackPointer, { passive: true });

    let frame = 0;
    let previous = 0;
    function animate(now: number): void {
      const dt = previous ? Math.min((now - previous) / 1000, 0.04) : 0;
      previous = now;
      if (hidden) return;
      const paused = hovering || blocked || leaving || document.hidden;

      if (phase === 'walk') {
        const dx = targetX - x;
        const dy = targetY - y;
        const distance = Math.hypot(dx, dy);
        if (reducedMotion || distance < 1) {
          // Đậu đúng số nguyên: toạ độ lẻ làm chữ lời nhắn bị nhoè.
          x = reducedMotion ? Math.round(x) : targetX;
          y = reducedMotion ? Math.round(y) : targetY;
          speed = 0;
          restLeft = REST_SECONDS;
          phase = 'rest';
        } else {
          const wanted = paused ? 0 : Math.min(WALK_SPEED, 10 + distance * 1.5);
          speed += (wanted - speed) * (1 - Math.exp(-(paused ? 15 : 4) * dt));
          const step = Math.min(speed * dt, distance);
          x += (dx / distance) * step;
          y += (dy / distance) * step;
          if (paused && speed < 0.5) {
            x = Math.round(x);
            y = Math.round(y);
          }
        }
      } else if (phase === 'rest' && !paused) {
        restLeft -= dt;
        if (restLeft <= 0) {
          if (reducedMotion) phase = 'quiet';
          else walkToNextStop();
        }
      } else if (phase === 'quiet' && !reducedMotion) {
        walkToNextStop();
      }

      // Mắt nhìn theo con trỏ; chưa có con trỏ thì nhìn về hướng đang đi.
      let wantLookX = walking ? 1.6 : 0;
      let wantLookY = 0;
      if (pointerX !== null && !reducedMotion) {
        wantLookX = Math.max(-1, Math.min(1, (pointerX - (x + FIGURE / 2)) / 140)) * 2.4 * facing;
        wantLookY = Math.max(-1, Math.min(1, (pointerY - (y + FIGURE / 2)) / 140)) * 1.8;
      }
      const lookBlend = 1 - Math.exp(-8 * dt);
      lookX += (wantLookX - lookX) * lookBlend;
      lookY += (wantLookY - lookY) * lookBlend;

      frame = requestAnimationFrame(animate);
    }
    frame = requestAnimationFrame(animate);

    function resize(): void {
      viewportWidth = window.innerWidth;
      stops = buildStops();
      stopIndex = nearestStop(x, y);
      [targetX, targetY] = stops[stopIndex];
      if (Math.hypot(targetX - x, targetY - y) >= 1) phase = 'walk';
    }
    window.addEventListener('resize', resize);

    const interval = setInterval(() => {
      covered = !!document.querySelector('[role="dialog"], input:focus, textarea:focus, [contenteditable="true"]:focus');
    }, 250);

    return () => {
      cancelAnimationFrame(frame);
      window.removeEventListener('resize', resize);
      window.removeEventListener('pointermove', trackPointer);
      clearInterval(interval);
      clearTimeout(exitTimer);
      preference.removeEventListener('change', changePreference);
    };
  });
</script>

{#if ready && !hidden}
  <aside
    class="sb-roamer"
    class:sb-leaving={leaving}
    class:sb-blocked={blocked}
    class:sb-walking={walking}
    class:sb-happy={hovering}
    style:transform="translate3d({x}px, {y}px, 0)"
    aria-label="Bé túi Shopee"
    onmouseenter={() => (hovering = true)}
    onmouseleave={() => (hovering = false)}
    onfocusin={() => (hovering = true)}
    onfocusout={() => (hovering = false)}
  >
    <div class="sb-speech" class:sb-speaking={speaking} class:sb-from-right={onRightSide}>
      <button type="button" class="sb-message" onclick={hide}>
        <b>Chạm tớ, tớ ẩn nhé!</b>
        <span>Chạm là góp quỹ thưởng rồi, không cần mua gì nhé 🧡</span>
        <small>Liên kết tiếp thị</small>
      </button>
    </div>

    <button
      type="button"
      class="sb-character"
      aria-label="Chạm để ẩn bé túi Shopee, thao tác minh họa chưa mở liên kết thật"
      onclick={hide}
    >
      <!-- Lúc đi không có chữ, dấu tay nhỏ này vẫn báo được là bấm vào bé được. -->
      <span class="sb-tap-hint" class:sb-tap-off={speaking} aria-hidden="true">👆</span>
      <span class="sb-figure" class:sb-facing-left={facing < 0}>
        <svg viewBox="0 0 120 120" fill="none" aria-hidden="true">
          <defs>
            <linearGradient id="sb-bag-fill" x1="60" y1="30" x2="60" y2="106" gradientUnits="userSpaceOnUse">
              <stop stop-color="#FFA768" />
              <stop offset="1" stop-color="#F2693A" />
            </linearGradient>
          </defs>

          <ellipse class="sb-shadow" cx="60" cy="112" rx="30" ry="4.5" fill="#B4532A" fill-opacity=".18" />

          <g class="sb-body">
            <ellipse class="sb-foot sb-foot-left" cx="44" cy="106" rx="9" ry="5.5" fill="#D9592C" />
            <ellipse class="sb-foot sb-foot-right" cx="76" cy="106" rx="9" ry="5.5" fill="#D9592C" />

            <path d="M42 40C42 12 78 12 78 40" stroke="#E5602F" stroke-width="6.5" stroke-linecap="round" />

            <path class="sb-arm sb-arm-left" d="M27 70Q14 73 11 60" stroke="#E5602F" stroke-width="6.5" stroke-linecap="round" />
            <path class="sb-arm sb-arm-right" d="M93 70Q106 73 109 58" stroke="#E5602F" stroke-width="6.5" stroke-linecap="round" />

            <path
              d="M32 34H88C91 34 93.5 36 94 39L100 88C101.500 99 94 106 84 106H36C26 106 18.500 99 20 88L26 39C26.500 36 29 34 32 34Z"
              fill="url(#sb-bag-fill)"
            />
            <path d="M31 42H89" stroke="#FFD2B0" stroke-width="3.500" stroke-linecap="round" />
            <path d="M29 54Q27 68 26 80" stroke="#fff" stroke-opacity=".38" stroke-width="4" stroke-linecap="round" />

            <g class="sb-charm">
              <path d="M88 40L95 50" stroke="#C94F24" stroke-width="2" stroke-linecap="round" />
              <path d="M96 60C89 55 90 49 94 49.500C95.500 49.700 96 51 96 51C96 51 96.800 49.800 98.300 50C102 50.500 102 56.500 96 60Z" fill="#FFD36E" />
            </g>

            <ellipse cx="33" cy="76" rx="7.500" ry="4.500" fill="#FF8E9E" fill-opacity=".75" />
            <ellipse cx="87" cy="76" rx="7.500" ry="4.500" fill="#FF8E9E" fill-opacity=".75" />

            <g class="sb-eyes-open" style:--sb-look-x="{lookX}px" style:--sb-look-y="{lookY}px">
              <g class="sb-eye">
                <ellipse cx="45" cy="63" rx="7" ry="8.500" fill="#46291F" />
                <circle cx="47.500" cy="59.500" r="2.700" fill="#fff" />
                <circle cx="42.500" cy="66.500" r="1.300" fill="#fff" />
              </g>
              <g class="sb-eye">
                <ellipse cx="75" cy="63" rx="7" ry="8.500" fill="#46291F" />
                <circle cx="77.500" cy="59.500" r="2.700" fill="#fff" />
                <circle cx="72.500" cy="66.500" r="1.300" fill="#fff" />
              </g>
            </g>
            <g class="sb-eyes-happy" stroke="#46291F" stroke-width="4" stroke-linecap="round">
              <path d="M37 65Q45 54 53 65" />
              <path d="M67 65Q75 54 83 65" />
            </g>

            <path class="sb-mouth" d="M53 74Q60 82 67 74" stroke="#46291F" stroke-width="3" stroke-linecap="round" />
            <g class="sb-mouth-open">
              <path d="M51 72H69C69 82 64 86 60 86C56 86 51 82 51 72Z" fill="#7A2E1F" />
              <ellipse cx="60" cy="82" rx="5" ry="3.500" fill="#FF8E9E" />
            </g>
          </g>

          <path class="sb-sparkle" d="M104 16L106.500 22.500L113 25L106.500 27.500L104 34L101.500 27.500L95 25L101.500 22.500Z" fill="#FFD36E" />

          <g transform="translate(18 26)">
            <path class="sb-heart" d="M0 4C-6 -1 -3 -7 0 -3C3 -7 6 -1 0 4Z" fill="#FF6F8A" />
          </g>
          <g transform="translate(92 8)">
            <path class="sb-heart sb-heart-late" d="M0 4C-6 -1 -3 -7 0 -3C3 -7 6 -1 0 4Z" fill="#FF9DB0" />
          </g>
        </svg>
      </span>
    </button>
  </aside>
{/if}

<style>
  .sb-roamer {
    position: fixed;
    left: 0;
    top: 0;
    width: 84px;
    height: 84px;
    z-index: 19;
    will-change: transform;
    pointer-events: none;
  }
  .sb-blocked {
    opacity: 0;
    visibility: hidden;
  }

  /* Lời nhắn */
  .sb-speech {
    position: absolute;
    left: 8px;
    /* Chồng nhẹ lên hình để con trỏ đi từ bé sang lời nhắn không bị rớt trạng thái rê chuột. */
    bottom: calc(100% - 6px);
    width: 224px;
    border: 1px solid var(--bo-dot);
    border-radius: 16px;
    background: var(--surface);
    box-shadow: var(--sh-md);
    transform-origin: 28px 100%;
    opacity: 0;
    visibility: hidden;
    transform: translateY(8px) scale(0.92);
    transition:
      opacity 0.18s ease,
      transform 0.22s cubic-bezier(0.34, 1.56, 0.64, 1),
      visibility 0.18s;
  }
  .sb-speech::after {
    content: '';
    position: absolute;
    left: 24px;
    bottom: -6px;
    width: 11px;
    height: 11px;
    background: var(--surface);
    border-right: 1px solid var(--bo-dot);
    border-bottom: 1px solid var(--bo-dot);
    transform: rotate(45deg);
  }
  .sb-from-right {
    left: auto;
    right: 8px;
    transform-origin: calc(100% - 28px) 100%;
  }
  .sb-from-right::after {
    left: auto;
    right: 24px;
  }
  .sb-speaking {
    opacity: 1;
    visibility: visible;
    transform: none;
    pointer-events: auto;
  }
  .sb-message {
    display: flex;
    flex-direction: column;
    gap: 3px;
    width: 100%;
    padding: 11px 14px 10px;
    border: 0;
    border-radius: 16px;
    background: transparent;
    color: var(--text);
    font: inherit;
    text-align: left;
    cursor: pointer;
  }
  .sb-message b {
    font-size: 14px;
    line-height: 1.3;
    color: var(--warn-text);
  }
  .sb-message span {
    font-size: 13px;
    line-height: 1.4;
  }
  .sb-message small {
    margin-top: 1px;
    color: var(--muted);
    font-size: 11px;
  }

  /* Hình */
  .sb-character {
    position: relative;
    display: block;
    width: 100%;
    height: 100%;
    padding: 0;
    border: 0;
    border-radius: 18px;
    background: transparent;
    cursor: pointer;
    pointer-events: auto;
    -webkit-tap-highlight-color: transparent;
  }
  .sb-figure {
    display: block;
    width: 100%;
    height: 100%;
    transition: transform 0.25s ease;
    filter: drop-shadow(0 3px 4px #ad583022);
  }
  .sb-facing-left {
    transform: scaleX(-1);
  }
  .sb-figure svg {
    display: block;
    width: 100%;
    height: 100%;
    overflow: visible;
  }
  .sb-tap-hint {
    position: absolute;
    z-index: 1;
    top: -6px;
    right: -4px;
    display: grid;
    place-items: center;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 1px solid var(--line);
    background: var(--surface);
    box-shadow: var(--sh-sm);
    font-size: 12px;
    line-height: 1;
    transition: opacity 0.15s;
    animation: sb-tap 1.4s ease-in-out infinite;
  }
  .sb-tap-off {
    opacity: 0;
  }

  /* Đứng yên: thở nhẹ, vẫy tay chào. */
  .sb-body {
    transform-origin: 60px 106px;
    animation: sb-breathe 2.4s ease-in-out infinite;
  }
  .sb-arm-left {
    transform-origin: 27px 70px;
  }
  .sb-arm-right {
    transform-origin: 93px 70px;
    animation: sb-wave 0.7s ease-in-out infinite alternate;
  }
  .sb-charm {
    transform-origin: 88px 40px;
    animation: sb-swing 1.8s ease-in-out infinite alternate;
  }
  .sb-foot-left {
    transform-origin: 44px 106px;
  }
  .sb-foot-right {
    transform-origin: 76px 106px;
  }
  .sb-eyes-open {
    transform: translate(var(--sb-look-x, 0), var(--sb-look-y, 0));
  }
  .sb-eye {
    transform-box: fill-box;
    transform-origin: center;
    animation: sb-blink 4.4s ease-in-out infinite;
  }
  .sb-eye + .sb-eye {
    animation-delay: 0.04s;
  }
  .sb-sparkle {
    transform-box: fill-box;
    transform-origin: center;
    animation: sb-twinkle 2.2s ease-in-out infinite;
  }
  .sb-eyes-happy,
  .sb-mouth-open,
  .sb-heart {
    display: none;
  }

  /* Đang đi: lạch bạch biên độ nhỏ, chân bước, tay đánh nhẹ. */
  .sb-walking .sb-body {
    animation: sb-waddle 0.62s ease-in-out infinite alternate;
  }
  .sb-walking .sb-foot-left {
    animation: sb-step 0.62s ease-in-out infinite alternate;
  }
  .sb-walking .sb-foot-right {
    animation: sb-step 0.62s ease-in-out infinite alternate-reverse;
  }
  .sb-walking .sb-arm-right,
  .sb-walking .sb-arm-left {
    animation: sb-arm-swing 0.62s ease-in-out infinite alternate;
  }
  .sb-walking .sb-arm-left {
    animation-direction: alternate-reverse;
  }

  /* Rê chuột vào: cười tít mắt, nhảy nhẹ, thả tim. */
  .sb-happy .sb-body {
    animation: sb-hop 0.6s ease-in-out infinite;
  }
  .sb-happy .sb-eyes-open,
  .sb-happy .sb-mouth {
    display: none;
  }
  .sb-happy .sb-eyes-happy,
  .sb-happy .sb-mouth-open {
    display: inline;
  }
  .sb-happy .sb-heart {
    display: inline;
    transform-box: fill-box;
    transform-origin: center;
    animation: sb-float 1.3s ease-out infinite;
  }
  .sb-happy .sb-heart-late {
    animation-delay: 0.45s;
  }

  .sb-leaving .sb-speech {
    opacity: 0;
    visibility: hidden;
  }
  .sb-leaving .sb-character {
    animation: sb-bye 0.5s cubic-bezier(0.36, -0.3, 0.7, 0.4) forwards;
  }

  @keyframes sb-breathe {
    0%,
    100% {
      transform: scale(1, 1);
    }
    50% {
      transform: scale(1.015, 0.975);
    }
  }
  @keyframes sb-waddle {
    from {
      transform: translateY(0) rotate(-3.5deg);
    }
    to {
      transform: translateY(-2px) rotate(3.5deg);
    }
  }
  @keyframes sb-hop {
    0%,
    100% {
      transform: translateY(0) scale(1.03, 0.97);
    }
    50% {
      transform: translateY(-6px) scale(0.98, 1.03);
    }
  }
  @keyframes sb-wave {
    from {
      transform: rotate(-6deg);
    }
    to {
      transform: rotate(16deg);
    }
  }
  @keyframes sb-arm-swing {
    from {
      transform: rotate(-8deg);
    }
    to {
      transform: rotate(8deg);
    }
  }
  @keyframes sb-swing {
    from {
      transform: rotate(-9deg);
    }
    to {
      transform: rotate(9deg);
    }
  }
  @keyframes sb-step {
    from {
      transform: translateY(0);
    }
    to {
      transform: translateY(-3px);
    }
  }
  @keyframes sb-blink {
    0%,
    93%,
    100% {
      transform: scaleY(1);
    }
    96.500% {
      transform: scaleY(0.08);
    }
  }
  @keyframes sb-twinkle {
    0%,
    100% {
      opacity: 0.35;
      transform: scale(0.7) rotate(0deg);
    }
    50% {
      opacity: 1;
      transform: scale(1) rotate(25deg);
    }
  }
  @keyframes sb-float {
    from {
      opacity: 0;
      transform: translateY(6px) scale(0.6);
    }
    30% {
      opacity: 1;
    }
    to {
      opacity: 0;
      transform: translateY(-20px) scale(1.15);
    }
  }
  @keyframes sb-tap {
    0%,
    100% {
      transform: translateY(0);
    }
    50% {
      transform: translateY(2px);
    }
  }
  @keyframes sb-bye {
    30% {
      transform: translateY(6px) scale(1.08, 0.88);
    }
    to {
      opacity: 0;
      transform: translateY(-46px) scale(0.5) rotate(12deg);
    }
  }

  @media (prefers-reduced-motion: reduce) {
    .sb-body,
    .sb-arm-left,
    .sb-arm-right,
    .sb-charm,
    .sb-eye,
    .sb-sparkle,
    .sb-foot-left,
    .sb-foot-right,
    .sb-tap-hint,
    .sb-happy .sb-heart {
      animation: none;
    }
    .sb-speech,
    .sb-figure {
      transition: none;
    }
    .sb-leaving .sb-character {
      animation: none;
      opacity: 0;
    }
  }
</style>
