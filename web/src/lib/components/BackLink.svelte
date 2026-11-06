<script lang="ts">
  import Icon from './Icon.svelte';

  // Nút quay lại dùng chung cho mọi màn học. Luôn kèm chữ: mũi tên trần thì người học
  // không đoán được nó dẫn về đâu, mà đây là đường thoát duy nhất của màn đang làm bài.
  let { href, label }: { href: string; label: string } = $props();
</script>

<a {href} class="ve btn btn-sm"><Icon name="chev" size={16} style="transform: rotate(180deg)" /> {label}</a>

<style>
  .ve {
    align-self: flex-start;
    padding: 0 12px 0 9px;
    border-radius: 999px;
    font-weight: 700;
  }
</style>
