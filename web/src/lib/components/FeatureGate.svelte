<script lang="ts">
  import type { Snippet } from 'svelte';
  import { session } from '$lib/stores/session.svelte';
  let { name, children }: { name: string; children: Snippet } = $props();
</script>
{#if session.can(name)}{@render children()}{/if}
