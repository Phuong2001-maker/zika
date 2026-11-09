<script lang="ts">
  import { page } from '$app/state';
  import SoanDeHsk from '$lib/admin/SoanDeHsk.svelte';

  // Soạn một đề Thi thử HSK 3.0. Màn soạn dùng chung với đề cuộc thi (tab Đề thi).
  const deId = $derived(Number(page.params.id));
</script>

<SoanDeHsk {deId} />
