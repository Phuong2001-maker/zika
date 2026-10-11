<script lang="ts">
  import { page } from '$app/state';
  import { api } from '$lib/api/client';
  import Icon from '$lib/components/Icon.svelte';
  import Markdown from '$lib/components/Markdown.svelte';

  // [BỔ SUNG] /pages/privacy, /pages/terms (07 §13): thanh trên tối giản + nội dung markdown rộng 760px.
  const slug = $derived(page.params.slug);
  let doc = $state<{ title: string; markdown: string } | null>(null);
  let failed = $state(false);

  $effect(() => {
    const s = slug;
    doc = null;
    failed = false;
    if (s !== 'privacy' && s !== 'terms') {
      failed = true;
      return;
    }
    api
      .get<{ title: string; markdown: string }>(`/public/pages/${s}`, { quiet: true })
      .then((d) => (doc = d))
      .catch(() => (failed = true));
  });

  function goBack(e: MouseEvent) {
    if (history.length > 1) {
      e.preventDefault();
      history.back();
    }
  }
</script>

<svelte:head><title>{doc?.title ?? 'Zìkǎ'} · Zìkǎ</title></svelte:head>

<header class="topbar">
  <a href="/" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
  <span class="grow"></span>
  <a href="/" class="btn btn-sm" onclick={goBack}><Icon name="left" size={16} /> Quay lại</a>
</header>

<main style="max-width: 760px; margin: 0 auto; padding: 36px 16px 64px">
  {#if doc}
    {#if doc.markdown.trim()}
      <Markdown source={doc.markdown} />
    {:else}
      <h1 style="font-size: 28px; margin-bottom: 12px">{doc.title}</h1>
      <p class="muted">Nội dung đang được cập nhật.</p>
    {/if}
  {:else if failed}
    <div class="empty">
      <b>Không tải được dữ liệu.</b>
      <a href="/" class="btn btn-sm" style="margin-top: 8px">Về trang chủ</a>
    </div>
  {:else}
    <div class="skel" style="height: 36px; width: 60%; margin-bottom: 16px"></div>
    <div class="skel" style="height: 140px"></div>
  {/if}
</main>
