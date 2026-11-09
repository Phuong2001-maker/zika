<script lang="ts">
  import { page } from '$app/state';
  import SoanDeHsk from '$lib/admin/SoanDeHsk.svelte';

  // Soạn đề cuộc thi (tab Đề thi): cùng màn soạn với đề Thi thử HSK 3.0 (10/11), nên đề HSK 3.0 soạn thế nào thì
  // đề thi soạn y vậy, chép qua lại không phải đổi dạng câu.
  const deId = $derived(Number(page.params.id));
</script>

<SoanDeHsk {deId} cuocThi />
