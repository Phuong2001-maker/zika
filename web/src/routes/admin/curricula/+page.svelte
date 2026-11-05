<script lang="ts">
  import CurriculumPreview from '$lib/curricula/CurriculumPreview.svelte';
  import CurriculumAccess from '$lib/curricula/CurriculumAccess.svelte';
  let tab = $state('books');
</script>

<div class="tabs" style="margin-bottom: 24px" aria-label="Quản lý giáo trình">
  <button
    class="tab"
    class:on={tab === 'books'}
    aria-pressed={tab === 'books'}
    onclick={() => {
      tab = 'books';
    }}>Giáo trình</button
  >
  <button
    class="tab"
    class:on={tab === 'access'}
    aria-pressed={tab === 'access'}
    onclick={() => {
      tab = 'access';
    }}>Phân quyền học</button
  >
</div>
{#if tab === 'books'}
  <CurriculumPreview
    admin
    onaccess={() => {
      tab = 'access';
    }}
  />
{:else}
  <CurriculumAccess />
{/if}
