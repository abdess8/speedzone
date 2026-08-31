<script setup>
import { computed, ref } from "vue";
import { useI18n } from "vue-i18n";
import Layout from "@/Layouts/main.vue";
import PageHeader from "@/Components/page-header.vue";
import ProcessFlow from "@/Components/Help/ProcessFlow.vue";
import StatusMatrix from "@/Components/Help/StatusMatrix.vue";

const { t } = useI18n();

const props = defineProps({
  flows: { type: Array, default: () => [] },
  matrices: { type: Object, default: () => ({ orders: [], returns: [] }) },
  contentTypes: { type: Array, default: () => [] },
  billing: { type: Object, default: () => ({}) },
});

const tabs = computed(() => [
  { key: "flows", label: t("help.processes.tabs.flows"), icon: "ri-route-line" },
  { key: "orders", label: t("help.processes.tabs.orders"), icon: "ri-shopping-basket-2-line" },
  { key: "returns", label: t("help.processes.tabs.returns"), icon: "ri-arrow-go-back-line" },
  { key: "billing", label: t("help.processes.tabs.billing"), icon: "ri-bill-line" },
]);

const activeTab = ref("flows");

const successFlow = computed(() => props.flows.find((flow) => flow.key === "success"));
const failureFlow = computed(() => props.flows.find((flow) => flow.key === "failure"));

const billingPanels = computed(() =>
  [props.billing.seller, props.billing.driver].filter(Boolean)
);
</script>

<template>
  <Layout>
    <PageHeader :title="$t('help.processes.page_title')" :pageTitle="$t('help.title')" />

    <BCard no-body>
      <BCardBody class="pb-0">
        <p class="text-muted mb-3">{{ $t('help.processes.intro') }}</p>

        <ul class="nav nav-tabs nav-tabs-custom border-bottom-0" role="tablist">
          <li v-for="tab in tabs" :key="tab.key" class="nav-item">
            <button
              type="button"
              class="nav-link"
              :class="{ active: activeTab === tab.key }"
              :aria-selected="activeTab === tab.key"
              role="tab"
              @click="activeTab = tab.key"
            >
              <i :class="tab.icon" class="align-bottom me-1"></i>
              <span>{{ tab.label }}</span>
            </button>
          </li>
        </ul>
      </BCardBody>
    </BCard>

    <template v-if="activeTab === 'flows'">
      <BCard no-body v-if="successFlow">
        <BCardBody>
          <!-- Autoplay only on the first flow: two timers running side by side
               would each pull at the reader's attention. -->
          <ProcessFlow
            :title="successFlow.title"
            :summary="successFlow.summary"
            :tone="successFlow.tone"
            :steps="successFlow.steps"
            autoplay
          />
        </BCardBody>
      </BCard>

      <BCard no-body v-if="failureFlow">
        <BCardBody>
          <ProcessFlow
            :title="failureFlow.title"
            :summary="failureFlow.summary"
            :tone="failureFlow.tone"
            :steps="failureFlow.steps"
          />

          <div v-if="failureFlow.branch" class="branch-panel mt-4">
            <div class="branch-connector" aria-hidden="true">
              <i class="ri-corner-down-right-line"></i>
            </div>
            <ProcessFlow
              :title="failureFlow.branch.title"
              :summary="failureFlow.branch.summary"
              :tone="failureFlow.branch.tone"
              :steps="failureFlow.branch.steps"
            />
          </div>
        </BCardBody>
      </BCard>

      <BCard no-body v-if="contentTypes.length">
        <BCardHeader>
          <h5 class="card-title mb-1">{{ $t('help.processes.transfer_types.title') }}</h5>
          <p class="text-muted mb-0 fs-13">{{ $t('help.processes.transfer_types.summary') }}</p>
        </BCardHeader>
        <BCardBody>
          <BRow class="g-3">
            <BCol md="4" v-for="type in contentTypes" :key="type.value">
              <div class="type-card h-100">
                <span class="type-icon" :class="`bg-${type.color}-subtle text-${type.color}`">
                  <i :class="type.icon"></i>
                </span>
                <h6 class="mb-1 fs-14">{{ type.label }}</h6>
                <p class="text-muted mb-0 fs-13">{{ type.description }}</p>
              </div>
            </BCol>
          </BRow>
        </BCardBody>
      </BCard>
    </template>

    <BCard no-body v-else-if="activeTab === 'orders'">
      <BCardBody>
        <StatusMatrix :rows="matrices.orders" :no-permission-label="$t('help.processes.no_permission')" />
      </BCardBody>
    </BCard>

    <BCard no-body v-else-if="activeTab === 'returns'">
      <BCardBody>
        <StatusMatrix :rows="matrices.returns" :no-permission-label="$t('help.processes.no_permission')" />
      </BCardBody>
    </BCard>

    <BRow v-else class="g-3">
      <BCol lg="6" v-for="panel in billingPanels" :key="panel.title">
        <BCard no-body class="h-100">
          <BCardHeader>
            <h5 class="card-title mb-1">{{ panel.title }}</h5>
            <p class="text-muted mb-0 fs-13">{{ panel.summary }}</p>
          </BCardHeader>
          <BCardBody>
            <!-- The formula is the point of the page, so it is laid out as an
                 equation rather than buried in a paragraph. -->
            <div class="formula mb-3">
              <div
                v-for="line in panel.formula"
                :key="line.label"
                class="formula-line"
                :class="{ 'formula-total': line.sign === '=' }"
              >
                <span class="formula-sign" :class="`text-${line.tone}`">{{ line.sign ?? '' }}</span>
                <span class="formula-label">{{ line.label }}</span>
              </div>
            </div>

            <ul class="text-muted fs-13 mb-0 ps-3">
              <li v-for="note in panel.notes" :key="note" class="mb-1">{{ note }}</li>
            </ul>
          </BCardBody>
        </BCard>
      </BCol>
    </BRow>
  </Layout>
</template>

<style scoped>
.branch-panel {
  position: relative;
  padding-left: 1.5rem;
  border-left: 2px dashed var(--vz-border-color);
}

.branch-connector {
  position: absolute;
  top: -0.25rem;
  left: -0.7rem;
  color: var(--vz-secondary-color);
  font-size: 1.1rem;
}

.type-card {
  padding: 1.1rem;
  border: 1px solid var(--vz-border-color);
  border-radius: 0.6rem;
}

.type-icon {
  display: inline-flex;
  width: 2.5rem;
  height: 2.5rem;
  align-items: center;
  justify-content: center;
  margin-bottom: 0.65rem;
  border-radius: 0.6rem;
  font-size: 1.2rem;
}

.formula-line {
  display: flex;
  align-items: baseline;
  gap: 0.6rem;
  padding: 0.5rem 0;
  border-bottom: 1px solid var(--vz-border-color);
}

.formula-line:last-child {
  border-bottom: 0;
}

.formula-sign {
  width: 1rem;
  flex-shrink: 0;
  font-size: 1.05rem;
  font-weight: 700;
  text-align: center;
}

.formula-total {
  margin-top: 0.25rem;
  border-top: 2px solid var(--vz-border-color);
  font-weight: 600;
}
</style>
