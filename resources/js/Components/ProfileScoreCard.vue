<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

/**
 * Profile completion gauge shown to vendors after they sign in.
 *
 * Reads the shared auth props rather than taking the score as a prop, so every
 * page that drops it in stays in sync with the last save without its controller
 * having to pass anything down.
 */
const page = usePage();

const completion = computed(() => page.props.auth?.user?.profile_completion ?? null);

const missing = computed(() =>
    [...(completion.value?.missing ?? [])].sort((a, b) => b.weight - a.weight)
);
</script>

<template>
    <BCard v-if="completion" no-body class="profile-score-card mb-4">
        <BCardBody>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div class="flex-shrink-0">
                    <div
                        class="avatar-md rounded-circle d-flex align-items-center justify-content-center"
                        :class="`bg-${completion.level}-subtle text-${completion.level}`"
                    >
                        <span class="fs-18 fw-semibold">{{ completion.score }}%</span>
                    </div>
                </div>

                <div class="flex-grow-1 min-width-0">
                    <h5 class="mb-1">{{ $t('profile.completion.title') }}</h5>
                    <p class="text-muted mb-2">
                        {{
                            completion.is_complete
                                ? $t('profile.completion.complete')
                                : $t('profile.completion.subtitle', {
                                      filled: completion.filled_count,
                                      total: completion.field_count,
                                  })
                        }}
                    </p>

                    <BProgress :max="100" style="height: 8px">
                        <BProgressBar :value="completion.score" :variant="completion.level" />
                    </BProgress>
                </div>

                <div class="flex-shrink-0">
                    <Link :href="route('profile.show')" class="btn btn-primary">
                        <i class="ri-user-settings-line align-bottom me-1"></i>
                        {{ $t('profile.completion.improve') }}
                    </Link>
                </div>
            </div>

            <div v-if="missing.length" class="mt-3 pt-3 border-top">
                <p class="text-muted fs-13 mb-2">{{ $t('profile.completion.missing_title') }}</p>
                <div class="d-flex flex-wrap gap-2">
                    <span
                        v-for="field in missing"
                        :key="field.key"
                        class="badge bg-light text-body border fw-normal"
                    >
                        {{ field.label }}
                        <span class="text-success ms-1">+{{ field.weight }}</span>
                    </span>
                </div>
            </div>
        </BCardBody>
    </BCard>
</template>
