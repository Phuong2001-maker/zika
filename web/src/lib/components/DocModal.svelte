<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '$lib/api/client';
  import Modal from './Modal.svelte';
  import Markdown from './Markdown.svelte';

  // Hộp thoại hiện Chính sách riêng tư / Điều khoản (link trong form đăng ký, 07 §2.1).
  let { slug, onclose }: { slug: 'privacy' | 'terms'; onclose: () => void } = $props();

  let doc = $state<{ title: string; markdown: string } | null>(null);
  let failed = $state(false);

  onMount(async () => {
    try {
      doc = await api.get<{ title: string; markdown: string }>(`/public/pages/${slug}`, { quiet: true });
    } catch {
      failed = true;
    }
  });
</script>

<Modal title={doc?.title ?? (slug === 'privacy' ? 'Chính sách riêng tư' : 'Điều khoản sử dụng')} {onclose} width={640}>
  {#if doc}
    {#if doc.markdown.trim()}
      <Markdown source={doc.markdown} />
    {:else}
      <p class="muted">Nội dung đang được cập nhật.</p>
    {/if}
  {:else if failed}
    <p class="muted">Không tải được dữ liệu.</p>
  {:else}
    <div class="skel" style="height: 120px"></div>
  {/if}
  {#snippet actions()}
    <button type="button" class="btn" onclick={onclose}>Đóng</button>
  {/snippet}
</Modal>
