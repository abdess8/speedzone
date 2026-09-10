<script setup>
import MadeWithLove from '@/Components/MadeWithLove.vue';
</script>

<template>
    <footer class="footer">
        <BContainer fluid>
            <BRow class="align-items-center g-2">
                <BCol col sm="6" class="d-flex align-items-center gap-2">
                    <img src="@assets/images/logo-footer.png" alt="SpeedZone Express" class="footer-brand-logo" />
                    <span>{{ new Date().getFullYear() }} © SpeedZone Express.</span>
                </BCol>
                <BCol col sm="6">
                    <div class="text-sm-end">
                        <MadeWithLove />
                    </div>
                </BCol>
            </BRow>
        </BContainer>
    </footer>
</template>
