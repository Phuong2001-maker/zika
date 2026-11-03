<script lang="ts">
  // Chữ Hán đặt trong ô chữ điền (田字格) như vở tập viết: mỗi chữ một ô, kẻ nét đứt chữ thập.
  // Nhìn là biết web học chữ Hán, và ô kẻ giúp thấy bố cục trên–dưới, trái–phải của chữ.
  // Từ dài hơn 4 chữ (hoặc có ký tự không phải chữ Hán) thì in thường — ô sẽ nhỏ quá, khó đọc.
  let { text, size = 96 }: { text: string; size?: number } = $props();

  const chu = $derived([...text]);
  const dungO = $derived(chu.length > 0 && chu.length <= 4 && chu.every((c) => /\p{Script=Han}/u.test(c)));
  // Càng nhiều chữ ô càng nhỏ để cả từ vừa một hàng trên thẻ điện thoại.
  const o = $derived(Math.round(size * ([1, 1, 1, 0.88, 0.74][chu.length] ?? 0.74)));
</script>

{#if dungO}
  <span class="cdien-hang" lang="zh" style="--o: {o}px">
    {#each chu as c, k (k)}<span class="cdien-o hz">{c}</span>{/each}
  </span>
{:else}
  <span class="hz" lang="zh" style="font-size: {Math.round(size * 0.8)}px; line-height: 1.15">{text}</span>
{/if}

<style>
  .cdien-hang {
    display: inline-flex;
    gap: 6px;
  }
  .cdien-o {
    --c: calc(var(--o) * var(--hz-scale, 1));
    --cdien-net: color-mix(in srgb, var(--son) 38%, transparent);
    display: grid;
    place-items: center;
    width: var(--c);
    height: var(--c);
    border: 1.5px solid var(--son-line);
    border-radius: 6px;
    /* Hai đường nét đứt chữ thập; dùng gradient để màu theo biến (sáng/tối) thay vì ảnh SVG cố định. */
    background:
      linear-gradient(to right, var(--cdien-net) 55%, transparent 55%) center / 9px 1px repeat-x,
      linear-gradient(to bottom, var(--cdien-net) 55%, transparent 55%) center / 1px 9px repeat-y,
      var(--surface);
    font-size: calc(var(--c) * 0.74);
    font-weight: 500;
    line-height: 1;
    color: var(--text);
  }
</style>
