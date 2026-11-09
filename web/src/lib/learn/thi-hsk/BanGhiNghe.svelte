<script lang="ts">
  import { onDestroy, onMount } from 'svelte';
  import { layBanGhi } from './ghi-am';

  // Nghe lại bản ghi của một câu nói ở màn kết quả. Bản ghi nằm trong máy người học; máy khác hay quá 7 ngày thì không còn.
  let { attemptId, qid }: { attemptId: number; qid: number } = $props();
  let url = $state('');
  let xong = $state(false);

  onMount(async () => {
    const blob = await layBanGhi(attemptId, qid);
    if (blob) url = URL.createObjectURL(blob);
    xong = true;
  });
  onDestroy(() => {
    if (url) URL.revokeObjectURL(url);
  });
</script>

{#if url}
  <audio class="bg-nghe" controls src={url}></audio>
{:else if xong}
  <span class="muted" style="font-size: 13px">Không còn bản ghi trên máy này.</span>
{/if}

<style>
  .bg-nghe { width: min(320px, 100%); height: 40px; }
</style>
