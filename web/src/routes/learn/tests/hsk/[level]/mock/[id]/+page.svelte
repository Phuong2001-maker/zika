<script lang="ts">
  import { page } from '$app/state';
  import TestRunner from '$lib/learn/TestRunner.svelte';

  // Thi thử cuối cấp — dùng chung bộ làm bài với bài kiểm tra.
  const levelId = $derived(Number(page.params.level));
  const mockTestId = $derived(Number(page.params.id));
</script>

<svelte:head><title>Thi thử HSK {levelId} · Zìkǎ</title></svelte:head>

<TestRunner {levelId} {mockTestId} backHref="/learn/tests/hsk/{levelId}/review" heading="Thi thử" />
