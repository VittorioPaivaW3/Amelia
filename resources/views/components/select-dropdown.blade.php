@props([
    'name',
    'options' => [],
    'value' => null,
    'themeByValue' => false,
    'useOld' => true,
    'oldKey' => null,
])

@php
    $options = collect($options)->mapWithKeys(function ($option, $key) {
        $value = is_int($key) ? (string) $option : (string) $key;
        $label = is_int($key) ? (string) $option : (string) $option;
        return [$value => $label];
    })->all();

    $oldKey = $useOld ? ($oldKey ?? $name) : null;
    $currentValue = $useOld && $oldKey ? old($oldKey, $value) : $value;
    if ($currentValue === null && ! empty($options)) {
        $currentValue = array_key_first($options);
    }

    $currentValue = (string) $currentValue;
    $buttonId = $attributes->get('id') ?? $name;
@endphp

<div {{ $attributes->except('id')->merge(['class' => 'relative']) }}
    x-data="{
        open: false,
        value: @js($currentValue),
        options: @js($options),
        themeByValue: @js((bool) $themeByValue),
        get label() { return this.options[this.value] ?? this.value; },
        get themeClass() {
            return this.themeByValue ? `select-theme-${this.value}` : '';
        }
    }"
    x-modelable="value"
    :class="themeClass">
    <input type="hidden" name="{{ $name }}" x-model="value">
    <button type="button"
        id="{{ $buttonId }}"
        class="w-full inline-flex items-center justify-between rounded-lg btn-accent px-4 py-2 text-sm font-semibold shadow-sm transition focus:outline-none"
        aria-haspopup="listbox"
        :aria-expanded="open.toString()"
        @click="open = !open">
        <span class="flex items-center gap-2">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 0 1 0 1.42l-7.25 7.25a1 1 0 0 1-1.42 0l-3.5-3.5a1 1 0 0 1 1.42-1.42l2.79 2.79 6.54-6.53a1 1 0 0 1 1.42 0z" clip-rule="evenodd" />
            </svg>
            <span x-text="label"></span>
        </span>
        <span class="flex items-center border-l border-white/30 pl-2">
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.09l3.71-3.86a.75.75 0 1 1 1.08 1.04l-4.25 4.42a.75.75 0 0 1-1.08 0L5.21 8.27a.75.75 0 0 1 .02-1.06z" clip-rule="evenodd" />
            </svg>
        </span>
    </button>

    <div x-show="open"
        x-transition
        x-cloak
        @click.outside="open = false"
        class="absolute z-50 mt-2 w-full rounded-xl bg-white shadow-xl ring-1 ring-black/10 dark:bg-gray-900">
        <template x-for="(label, optionValue) in options" :key="optionValue">
            <button type="button"
                class="dropdown-option w-full px-4 py-3 text-left text-sm flex items-center justify-between gap-3 transition text-gray-900 dark:text-gray-100"
                :class="value == optionValue ? 'is-selected' : ''"
                @click="value = optionValue; open = false">
                <span x-text="label"></span>
                <span x-show="value == optionValue" class="text-base">&#10003;</span>
            </button>
        </template>
    </div>
</div>
