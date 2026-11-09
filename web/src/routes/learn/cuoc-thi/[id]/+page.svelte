<script lang="ts">
  import { page } from '$app/state';
  import ThiHskRunner from '$lib/learn/thi-hsk/ThiHskRunner.svelte';

  // Cuộc thi có đề soạn theo khung HSK 3.0 (tab Đề thi): làm ở màn thi HSK 3.0, luôn thi thật. Server tự gắn lượt với
  // cuộc thi đang mở, kiểm đăng ký, mỗi người một lượt; hết giờ cuộc thi thì tự nộp.
  const mockId = $derived(Number(page.params.id));
</script>

<svelte:head><title>Cuộc thi · Zìkǎ</title></svelte:head>

{#key mockId}
  <ThiHskRunner {mockId} mode="exam" cuocThi />
{/key}
