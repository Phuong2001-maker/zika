<script lang="ts">
  import { marked } from 'marked';
  import DOMPurify from 'dompurify';

  // Ngoại lệ DUY NHẤT được dùng {@html} (02 §6.5): markdown do admin soạn,
  // đã qua DOMPurify trước khi chèn. Dùng cho Chính sách riêng tư / Điều khoản.
  let { source }: { source: string } = $props();
  const html = $derived(DOMPurify.sanitize(marked.parse(source ?? '', { async: false }) as string));
</script>

<div class="md">{@html html}</div>

<style>
  .md :global(h1) {
    font-size: 28px;
    margin: 0 0 14px;
  }
  .md :global(h2) {
    font-size: 20px;
    margin: 22px 0 10px;
  }
  .md :global(p),
  .md :global(li) {
    font-size: 15.5px;
    line-height: 1.65;
    color: var(--text-2);
  }
  .md :global(p) {
    margin: 0 0 12px;
  }
  .md :global(ul) {
    list-style: disc;
    padding-left: 22px;
    margin: 0 0 12px;
  }
  .md :global(ol) {
    list-style: decimal;
    padding-left: 22px;
    margin: 0 0 12px;
  }
</style>
