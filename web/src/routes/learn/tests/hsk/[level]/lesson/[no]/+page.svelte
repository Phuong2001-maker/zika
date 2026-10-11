<script lang="ts">
  import { page } from '$app/state';
  import TestRunner from '$lib/learn/TestRunner.svelte';

  // Kiểm tra · Làm bài của một bài học (07 §7).
  const levelId = $derived(Number(page.params.level));
  const lessonNo = $derived(Number(page.params.no));
</script>

<svelte:head><title>Kiểm tra · HSK {levelId} · Zìkǎ</title></svelte:head>

<TestRunner {levelId} {lessonNo} backHref="/learn/tests/hsk/{levelId}" heading="Bài {lessonNo}" />
