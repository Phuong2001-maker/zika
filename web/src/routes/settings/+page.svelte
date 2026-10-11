<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '$lib/api/client';
  import type { UserSettings } from '$lib/api/types';
  import { settings } from '$lib/stores/settings.svelte';
  import { media } from '$lib/stores/media.svelte';
  import { heartbeat } from '$lib/stores/heartbeat';
  import { toast } from '$lib/stores/toast.svelte';
  import Topbar from '$lib/components/Topbar.svelte';
  import Switcher from '$lib/components/Switcher.svelte';
  import Avatar from '$lib/components/Avatar.svelte';
  import Icon from '$lib/components/Icon.svelte';
  import { speak } from '$lib/learn/speech';
  import { toneParts } from '$lib/utils/pinyin';

  // Cài đặt học (07 §9). Đổi là thấy ngay ở thẻ xem trước, bấm Lưu mới gửi lên server.
  let draft = $state<UserSettings>({ ...settings.value, dialogue: { ...settings.value.dialogue } });
  let saving = $state(false);
  let side = $state<'front' | 'back'>('front');

  onMount(() => {
    heartbeat.setActivity({ part: null, screen: 'account' });
    draft = { ...settings.value, dialogue: { ...settings.value.dialogue } };
  });

  // Áp dụng ngay để người dùng thấy cỡ chữ / phông / màu đổi thật.
  $effect(() => {
    settings.apply(draft);
  });

  async function save(): Promise<void> {
    saving = true;
    try {
      const saved = await api.put<UserSettings>('/me/settings', draft);
      settings.set(saved);
      toast.ok('Đã lưu cài đặt.');
    } catch {
      toast.show('Không lưu được, thử lại nhé.');
    } finally {
      saving = false;
    }
  }

  function reset(): void {
    draft = { ...settings.value, dialogue: { ...settings.value.dialogue } };
    toast.show('Đã bỏ thay đổi.');
  }

  const DEMO = { hanzi: '牛奶', pinyin: 'niúnǎi', hanViet: 'NGƯU NÃI', meaning: 'sữa bò' };
</script>

<svelte:head><title>Cài đặt học · Zìkǎ</title></svelte:head>

{#snippet row(label: string, hint?: string)}
  <div style="flex: 1 1 200px; min-width: 0">
    <b style="font-size: 14.5px">{label}</b>
    {#if hint}<p class="muted" style="font-size: 13px">{hint}</p>{/if}
  </div>
{/snippet}

{#snippet seg<T>(value: T, opts: { v: T; label: string }[], set: (v: T) => void)}
  <div class="seg">
    {#each opts as o (String(o.v))}
      <button type="button" class:on={value === o.v} onclick={() => set(o.v)}>{o.label}</button>
    {/each}
  </div>
{/snippet}

{#snippet preview()}
  <section class="block" style="padding: 18px; display: flex; flex-direction: column; gap: 12px; align-items: center">
    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%">
      <p class="kicker">Xem trước</p>
      {@render seg(side, [{ v: 'front' as const, label: 'Trước' }, { v: 'back' as const, label: 'Sau' }], (v) => (side = v))}
    </div>

    <div class="face-demo" class:back={side === 'back'}>
      {#if side === 'front'}
        <span class="kicker">
          {draft.cardFront === 'hanzi' ? 'Chữ Hán' : draft.cardFront === 'pinyin' ? 'Pinyin' : 'Nghĩa tiếng Việt'}
        </span>
        <span class={draft.cardFront === 'hanzi' ? 'hz' : ''} lang={draft.cardFront === 'hanzi' ? 'zh' : 'vi'}
              style="font-size: {draft.cardFront === 'hanzi' ? 48 : 26}px; font-weight: 800">
          {draft.cardFront === 'hanzi' ? DEMO.hanzi : draft.cardFront === 'pinyin' ? DEMO.pinyin : DEMO.meaning}
        </span>
      {:else}
        <span class="num" style="font-size: 18px; font-weight: 600" class:no-tone={!draft.toneColors}>
          {#each toneParts(DEMO.pinyin) as p (p.text + p.tone)}<span class="t{p.tone}">{p.text}</span>{/each}
        </span>
        <span class="hz" lang="zh" style="font-size: 48px">{DEMO.hanzi}</span>
        {#if draft.showHanViet}<span class="muted" style="font-size: 12.5px; font-weight: 700">{DEMO.hanViet}</span>{/if}
        <span style="font-size: 17px; font-weight: 600">{DEMO.meaning}</span>
      {/if}
    </div>

    <button type="button" class="btn btn-sm" onclick={() => speak(DEMO.hanzi)}><Icon name="speaker" size={15} /> Nghe thử</button>
  </section>
{/snippet}

{#snippet body(m: boolean)}
  <!-- Phát âm -->
  <section class="block card">
    <h2 class="h2">Phát âm</h2>

    <div class="line">
      {@render row('Giọng đọc')}
      {@render seg(draft.voice, [
        { v: 'xiaoxiao' as const, label: 'Xiaoxiao · nữ' },
        { v: 'yunxi' as const, label: 'Yunxi · nam' },
        { v: 'browser' as const, label: 'Giọng máy' },
      ], (v) => (draft.voice = v))}
    </div>

    <div class="line">
      {@render row('Tốc độ đọc')}
      {@render seg(draft.rate, [
        { v: 0.75, label: '0.75×' },
        { v: 1, label: '1×' },
        { v: 1.25, label: '1.25×' },
      ], (v) => (draft.rate = v))}
    </div>

    <div class="line">
      {@render row('Tự đọc khi lật thẻ')}
      <label class="sw"><input type="checkbox" bind:checked={draft.autoSpeak} /><span></span></label>
    </div>
  </section>

  <!-- Lật thẻ -->
  <section class="block card">
    <h2 class="h2">Lật thẻ</h2>

    <div class="line">
      {@render row('Mặt trước của thẻ')}
      {@render seg(draft.cardFront, [
        { v: 'vi' as const, label: 'Nghĩa' },
        { v: 'hanzi' as const, label: 'Chữ Hán' },
        { v: 'pinyin' as const, label: 'Pinyin' },
      ], (v) => (draft.cardFront = v))}
    </div>

    <div class="line">
      {@render row('Trộn thứ tự thẻ', 'Mỗi lần học lại một thứ tự khác.')}
      <label class="sw"><input type="checkbox" bind:checked={draft.shuffle} /><span></span></label>
    </div>

    <div class="line">
      {@render row('Hiện câu ví dụ sau khi lật')}
      <label class="sw"><input type="checkbox" bind:checked={draft.showExamples} /><span></span></label>
    </div>
  </section>

  <!-- Hiển thị chữ -->
  <section class="block card">
    <h2 class="h2">Hiển thị chữ</h2>

    <div class="line">
      {@render row('Kiểu chữ Hán')}
      {@render seg(draft.hanziFont, [
        { v: 'sans' as const, label: 'Không chân' },
        { v: 'serif' as const, label: 'Có chân' },
        { v: 'kai' as const, label: 'Viết tay' },
      ], (v) => (draft.hanziFont = v))}
    </div>

    <div class="line">
      {@render row('Cỡ chữ')}
      {@render seg(draft.fontSize, [
        { v: 's' as const, label: 'Nhỏ' },
        { v: 'm' as const, label: 'Vừa' },
        { v: 'l' as const, label: 'Lớn' },
      ], (v) => (draft.fontSize = v))}
    </div>

    <div class="line">
      {@render row('Pinyin tô màu theo thanh điệu')}
      <label class="sw"><input type="checkbox" bind:checked={draft.toneColors} /><span></span></label>
    </div>

    <div class="line">
      {@render row('Hiện âm Hán Việt')}
      <label class="sw"><input type="checkbox" bind:checked={draft.showHanViet} /><span></span></label>
    </div>
  </section>

  <!-- Khác -->
  <section class="block card">
    <h2 class="h2">Khác</h2>

    <div class="line">
      {@render row('Giao diện')}
      {@render seg(draft.theme, [
        { v: 'light' as const, label: 'Sáng' },
        { v: 'dark' as const, label: 'Tối' },
        { v: 'system' as const, label: 'Theo máy' },
      ], (v) => (draft.theme = v))}
    </div>

    <div class="line">
      {@render row('Nhắc học mỗi ngày')}
      {@render seg(draft.remindAt ?? 'off', [
        { v: 'off', label: 'Tắt' },
        { v: '20:00', label: '20:00' },
        { v: '07:00', label: '07:00' },
      ], (v) => (draft.remindAt = v === 'off' ? null : v))}
    </div>

    <div class="line">
      {@render row('Âm báo khi bài kiểm tra còn 1 phút')}
      <label class="sw"><input type="checkbox" bind:checked={draft.testBeep} /><span></span></label>
    </div>

    <div class="line">
      {@render row('Hội thoại: hiện pinyin')}
      <label class="sw"><input type="checkbox" bind:checked={draft.dialogue.showPinyin} /><span></span></label>
    </div>

    <div class="line">
      {@render row('Hội thoại: hiện nghĩa tiếng Việt')}
      <label class="sw"><input type="checkbox" bind:checked={draft.dialogue.showVi} /><span></span></label>
    </div>
  </section>
{/snippet}

{#if media.isMobile}
  <div class="mscreen">
    <header class="mtop">
      <a href="/account" class="btn btn-bare" aria-label="Về Tài khoản"><Icon name="left" size={20} /></a>
      <b style="flex: 1; font-size: 16px">Cài đặt học</b>
    </header>
    <main class="mcontent" style="display: flex; flex-direction: column; gap: 16px">
      {@render preview()}
      {@render body(true)}
    </main>
    <div class="mbar">
      <button type="button" class="btn grow" onclick={reset}>Bỏ thay đổi</button>
      <button type="button" class="btn btn-son grow" disabled={saving} onclick={save}>{saving ? 'Đang lưu…' : 'Lưu cài đặt'}</button>
    </div>
  </div>
{:else}
  <header class="topbar">
    <a href="/learn/flashcards" class="logo"><span class="seal" lang="zh" style="width: 34px; height: 34px; font-size: 19px">字</span>Zìkǎ</a>
    <div style="margin-left: auto; display: flex; align-items: center; gap: 12px">
      <a href="/account" class="btn btn-sm">Tài khoản</a>
      <Switcher current="lt" />
      <Avatar size={44} accountCurrent />
    </div>
  </header>

  <main class="page" style="display: flex; flex-direction: column; gap: 20px">
    <div style="display: flex; align-items: flex-end; justify-content: space-between; gap: 16px; flex-wrap: wrap">
      <div>
        <h1 style="font-size: 28px">Cài đặt học</h1>
        <p class="muted" style="font-size: 14px">Đổi cách thẻ hiện ra và cách web đọc chữ Hán.</p>
      </div>
      <div style="display: flex; gap: 10px">
        <button type="button" class="btn btn-sm" onclick={reset}>Bỏ thay đổi</button>
        <button type="button" class="btn btn-son btn-sm" disabled={saving} onclick={save}>{saving ? 'Đang lưu…' : 'Lưu cài đặt'}</button>
      </div>
    </div>

    <div style="display: flex; flex-wrap: wrap; gap: 22px; align-items: flex-start">
      <div style="flex: 999 1 520px; min-width: 0; display: flex; flex-direction: column; gap: 16px">{@render body(false)}</div>
      <aside style="flex: 1 1 280px; min-width: 0; position: sticky; top: 20px">{@render preview()}</aside>
    </div>
  </main>
{/if}

<style>
  .card {
    padding: 18px 22px;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  .line {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    padding: 6px 0;
    border-top: 1px solid var(--line-2);
  }
  .line:first-of-type {
    border-top: 0;
  }
  .face-demo {
    width: 100%;
    min-height: 190px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 20px;
    border: 1px solid var(--line);
    border-radius: var(--r-lg);
    background: var(--troi);
    box-shadow: var(--sh-md);
    text-align: center;
  }
  .face-demo.back {
    background: var(--surface);
  }
</style>
