<script lang="ts">
  import { page } from '$app/state';
  import ThiHskRunner from '$lib/learn/thi-hsk/ThiHskRunner.svelte';

  // ?mode=practice: luyện tập, xem đáp án từng câu; không có thì thi thật tính giờ.
  const mockId = $derived(Number(page.params.id));
  const mode = $derived(page.url.searchParams.get('mode') === 'practice' ? 'practice' : 'exam');
</script>

<svelte:head><title>Thi thử HSK 3.0 · Zìkǎ</title></svelte:head>

<!-- Đổi đề hoặc chế độ mà vẫn cùng route thì SvelteKit không dựng lại trang (bẫy số 2): key buộc dựng lại. -->
{#key `${mockId}:${mode}`}
  <ThiHskRunner {mockId} {mode} />
{/key}
