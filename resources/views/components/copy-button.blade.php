@props(['text', 'label' => 'Salin', 'copiedLabel' => 'Tersalin', 'icon' => 'copy'])

<x-secondary-button size="xs" type="button"
    x-data="{ copied: false }"
    data-copy-text="{{ $text }}"
    x-on:click="window.parafCopy($el.dataset.copyText).then(() => { copied = true; setTimeout(() => copied = false, 1800) })"
    {{ $attributes }}>
    <i data-lucide="{{ $icon }}" class="w-3.5 h-3.5" x-show="!copied"></i>
    <i data-lucide="check" class="w-3.5 h-3.5 text-emerald-400" x-show="copied" x-cloak></i>
    <span x-text="copied ? $el.dataset.copiedLabel : $el.dataset.label" data-label="{{ $label }}" data-copied-label="{{ $copiedLabel }}">{{ $label }}</span>
</x-secondary-button>
