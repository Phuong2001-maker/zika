<script lang="ts">
  // Chữ Hán có pinyin in trên đầu từng từ như đề giấy HSK 1–2. `py` là bảng chuỗi → [[từ, pinyin], …] API gửi kèm
  // lượt thi (PinyinDe::choDe); chuỗi không có trong bảng (đề cấp cao, câu vừa sửa) thì hiện chữ trơn.
  let { text, py }: { text: string; py?: Record<string, [string, string][]> | null } = $props();
  const tok = $derived(py?.[text] ?? null);
</script>

{#if tok}{#each tok as [chu, am], i (i)}{#if am}<ruby class="rb">{chu}<rt>{am}</rt></ruby>{:else}<span class="rb-dau">{chu}</span>{/if}{/each}{:else}{text}{/if}

<style>
  /* Mỗi từ một khối chữ + pinyin, cách nhau một chút để pinyin hai từ không dính (đã thử ở bản mẫu 15/11). */
  .rb {
    ruby-align: center;
    margin: 0 0.18em;
  }
  .rb:first-child {
    margin-left: 0;
  }
  .rb rt {
    font-family: var(--font-ui);
    font-size: 0.5em;
    font-weight: 500;
    letter-spacing: 0;
    color: var(--text-2);
  }
  .rb-dau {
    white-space: pre-wrap;
  }
</style>
