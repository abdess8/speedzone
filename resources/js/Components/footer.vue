<template>
    <footer class="footer">
        <BContainer fluid>
            <BRow class="align-items-center g-2">
                <BCol col sm="6" class="d-flex align-items-center gap-2">
                    <img src="@assets/images/logo-footer.png" alt="OWL Delivery" class="footer-brand-logo" />
                    <span>{{ new Date().getFullYear() }} © OWL Delivery.</span>
                </BCol>
                <BCol col sm="6">
                    <div class="text-sm-end d-none d-sm-block">
                        Fast. Reliable. Modern logistics operations.
                    </div>
                </BCol>
            </BRow>
        </BContainer>
    </footer>
</template>
