<script setup lang="ts">
// The PromptEdit Studio mark: a prompt chevron whose tip becomes a spark. Colours are fixed brand
// values (lime on ink), so it reads the same on light and dark surfaces.
</script>

<template>
    <svg
        viewBox="0 0 64 64"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        aria-hidden="true"
    >
        <rect width="64" height="64" rx="15" fill="#262626" />
        <path
            d="M15.5 19.5 29 32 15.5 44.5"
            stroke="#9ae600"
            stroke-width="6"
            stroke-linecap="round"
            stroke-linejoin="round"
        />
        <path
            d="M42 18.5c1.2 7.6 3.9 10.3 11.5 13.5-7.6 3.2-10.3 5.9-11.5 13.5-1.2-7.6-3.9-10.3-11.5-13.5 7.6-3.2 10.3-5.9 11.5-13.5Z"
            fill="#9ae600"
        />
    </svg>
</template>
