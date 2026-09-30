<script setup>
import AuthLocaleSwitcher from '@/Components/AuthLocaleSwitcher.vue';
import MadeWithLove from '@/Components/MadeWithLove.vue';
</script>

<template>
    <div class="auth-locale-fixed">
        <AuthLocaleSwitcher />
    </div>
    <footer class="footer">
        <BContainer>
            <div class="text-center">
                <p class="mb-1 text-muted">&copy; {{ new Date().getFullYear() }} SpeedZone Express.</p>
                <MadeWithLove class="text-muted" />
            </div>
        </BContainer>
    </footer>
</template>

<style scoped>
.auth-locale-fixed {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 1050;
}
</style>
