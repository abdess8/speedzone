<script setup>
import MadeWithLove from '@/Components/MadeWithLove.vue';
</script>

<template>
    <footer class="footer">
        <BContainer>
            <div class="text-center">
                <p class="mb-1 text-muted">&copy; {{ new Date().getFullYear() }} SpeedZone Express.</p>
                <MadeWithLove class="text-muted" />
            </div>
        </BContainer>
    </footer>
</template>
