<script lang="ts">
  import { toast } from '$lib/stores/toast.svelte';
  import Icon from './Icon.svelte';
</script>

<!-- Thông báo nổi góc dưới giữa (05 §7.10). aria-live để trình đọc màn hình đọc câu mới. -->
<div class="toast-wrap" aria-live="polite" aria-atomic="false">
  {#each toast.items as t (t.id)}
    <div class="toast" class:ok={t.kind === 'ok'} role="status">
      {#if t.kind === 'ok'}<Icon name="check" size={18} />{/if}
      <span class="grow">{t.text}</span>
      {#if t.action}
        <button
          type="button"
          class="btn btn-sm"
          onclick={() => {
            t.action?.run();
            toast.dismiss(t.id);
          }}>{t.action.label}</button
        >
      {/if}
      <button type="button" class="btn btn-bare btn-sm tclose" aria-label="Đóng thông báo" onclick={() => toast.dismiss(t.id)}>
        <Icon name="x" size={16} />
      </button>
    </div>
  {/each}
</div>

<style>
  .tclose {
    color: inherit;
    width: 30px;
    min-height: 30px;
  }
  .tclose:hover {
    background: rgba(255, 255, 255, 0.12);
    color: inherit;
  }
</style>
