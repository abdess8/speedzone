<template>
    <a
        class="sz-made-with"
        href="https://oowlmedia.com/"
        target="_blank"
        rel="noopener noreferrer"
    >
        Made with
        <span class="sz-made-with__heart" aria-hidden="true">♥</span>
        love by oowlmedia
    </a>
</template>

<style scoped>
.sz-made-with {
    display: inline-flex;
    align-items: center;
    gap: 0.28rem;
    color: inherit;
    font-size: 0.85rem;
    font-weight: 600;
    text-decoration: none;
    white-space: nowrap;
    transition: color 0.2s ease, opacity 0.2s ease;
}

.sz-made-with:hover {
    color: #e11d48;
    text-decoration: none;
}

.sz-made-with__heart {
    color: #e11d48;
    font-size: 0.95em;
    line-height: 1;
}
</style>
