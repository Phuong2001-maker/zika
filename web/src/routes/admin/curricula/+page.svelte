<script lang="ts">
  import CurriculumList from '$lib/curricula/CurriculumList.svelte';
  import CurriculumAccess from '$lib/curricula/CurriculumAccess.svelte';
  let tab = $state<'books' | 'access'>('books');
</script>

<div class="tabs" style="margin-bottom: 20px" aria-label="Quản lý giáo trình">
  <button type="button" class="tab" class:on={tab === 'books'} aria-pressed={tab === 'books'} onclick={() => (tab = 'books')}>Giáo trình</button>
  <button type="button" class="tab" class:on={tab === 'access'} aria-pressed={tab === 'access'} onclick={() => (tab = 'access')}>Phân quyền học</button>
</div>
{#if tab === 'books'}
  <CurriculumList onaccess={() => (tab = 'access')} />
{:else}
  <CurriculumAccess />
{/if}
