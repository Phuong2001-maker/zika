<script lang="ts">
  import CurriculumList from '$lib/curricula/CurriculumList.svelte';
  import CurriculumAccess from '$lib/curricula/CurriculumAccess.svelte';
  import ContentVisibility from '$lib/curricula/ContentVisibility.svelte';
  import { page } from '$app/state';
  let tab = $state<'books' | 'access' | 'visibility'>(page.url.searchParams.get('tab') === 'visibility' ? 'visibility' : 'books');
</script>

<div class="tabs" style="margin-bottom: 20px" aria-label="Quản lý giáo trình">
  <button type="button" class="tab" class:on={tab === 'books'} aria-pressed={tab === 'books'} onclick={() => (tab = 'books')}>Giáo trình</button>
  <button type="button" class="tab" class:on={tab === 'access'} aria-pressed={tab === 'access'} onclick={() => (tab = 'access')}>Phân quyền học</button>
  <button type="button" class="tab" class:on={tab === 'visibility'} aria-pressed={tab === 'visibility'} onclick={() => (tab = 'visibility')}>Ẩn/hiện nội dung</button>
</div>
{#if tab === 'books'}
  <CurriculumList onaccess={() => (tab = 'access')} />
{:else if tab === 'access'}
  <CurriculumAccess />
{:else}
  <ContentVisibility curriculum={Number(page.url.searchParams.get('curriculum')) || 0} level={Number(page.url.searchParams.get('level')) || 1} />
{/if}
