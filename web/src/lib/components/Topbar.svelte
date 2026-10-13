<script lang="ts">
  import type { Part } from '$lib/api/types';
  import Switcher from './Switcher.svelte';
  import Avatar from './Avatar.svelte';

  // Thanh trên người học, bố cục máy tính (05 §7.1). Bản làm bài có topbar riêng trong từng màn.
  let {
    current = 'lt',
    accountCurrent = false,
  }: { current?: Part; accountCurrent?: boolean } = $props();
</script>

<header class="topbar">
  <a href="/learn/flashcards" class="logo">
    <span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ
  </a>
  <div style="margin-left: auto; display: flex; align-items: center; gap: 12px">
    <Switcher {current} />
    <Avatar size={44} current={accountCurrent} />
  </div>
</header>
