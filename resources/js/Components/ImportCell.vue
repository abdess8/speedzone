<script setup>
import { computed, nextTick, ref, useId } from 'vue';

/**
 * One editable cell of a bulk import review table.
 *
 * Free text opens on click so a 12 column grid stays readable, while pickers
 * and switches are always live: an unresolved reference is the most common
 * reason a row is red, and making the user click twice to fix it would be
 * needless.
 *
 * Shared by the order and product import wizards; the `type` values it does not
 * recognise fall back to a plain text input.
 */

const props = defineProps({
  modelValue: { type: [String, Number, Boolean], default: '' },
  type: { type: String, default: 'text' },
  error: { type: String, default: '' },
  /** @type {Array<{value: string|number, label: string}>} */
  options: { type: Array, default: () => [] },
  /** Free-text suggestions offered through a datalist, without constraining. */
  suggestions: { type: Array, default: () => [] },
  /** Original spreadsheet text, shown when the value could not be resolved. */
  raw: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  disabledHint: { type: String, default: '' },
  multiline: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue', 'change']);

const editing = ref(false);
const input = ref(null);
const listId = `import-cell-${useId()}`;

const isSelect = computed(() => ['city', 'sector', 'payment'].includes(props.type));
const isBoolean = computed(() => props.type === 'boolean');
const isNumeric = computed(() => ['amount', 'integer'].includes(props.type));

const selectedLabel = computed(() => {
  const match = props.options.find((option) => String(option.value) === String(props.modelValue));

  return match?.label ?? '';
});

const display = computed(() => {
  if (isSelect.value) {
    return selectedLabel.value;
  }

  return props.modelValue === null || props.modelValue === undefined ? '' : String(props.modelValue);
});

function update(value) {
  emit('update:modelValue', value);
  emit('change', value);
}

async function startEdit() {
  if (props.disabled || isSelect.value || isBoolean.value) {
    return;
  }

  editing.value = true;
  await nextTick();
  input.value?.focus();
  input.value?.select?.();
}

function stopEdit() {
  editing.value = false;
}
</script>

<template>
  <div class="import-cell" :class="{ 'import-cell--invalid': !!error }">
    <select
      v-if="isSelect"
      class="form-select form-select-sm import-cell__control"
      :class="{ 'is-invalid': !!error }"
      :value="modelValue ?? ''"
      :disabled="disabled"
      :title="disabled ? disabledHint : ''"
      @change="update($event.target.value === '' ? null : $event.target.value)"
    >
      <option value="">—</option>
      <option v-for="option in options" :key="option.value" :value="option.value">
        {{ option.label }}
      </option>
    </select>

    <div v-else-if="isBoolean" class="form-check form-switch mb-0 d-flex justify-content-center">
      <input
        class="form-check-input"
        type="checkbox"
        :checked="modelValue === true"
        @change="update($event.target.checked)"
      />
    </div>

    <textarea
      v-else-if="editing && multiline"
      ref="input"
      class="form-control form-control-sm import-cell__control"
      rows="2"
      :value="modelValue"
      @input="update($event.target.value)"
      @blur="stopEdit"
      @keydown.esc="stopEdit"
    ></textarea>

    <template v-else-if="editing">
      <input
        ref="input"
        class="form-control form-control-sm import-cell__control"
        :type="isNumeric ? 'number' : 'text'"
        :step="type === 'amount' ? '0.01' : type === 'integer' ? '1' : undefined"
        :inputmode="type === 'amount' ? 'decimal' : type === 'integer' ? 'numeric' : undefined"
        :list="suggestions.length ? listId : undefined"
        :value="modelValue"
        @input="update($event.target.value)"
        @blur="stopEdit"
        @keydown.enter="stopEdit"
        @keydown.esc="stopEdit"
      />
      <datalist v-if="suggestions.length" :id="listId">
        <option v-for="suggestion in suggestions" :key="suggestion" :value="suggestion"></option>
      </datalist>
    </template>

    <button
      v-else
      type="button"
      class="import-cell__value"
      :class="{ 'text-end w-100': isNumeric }"
      :title="display"
      @click="startEdit"
    >
      <span v-if="display">{{ display }}</span>
      <span v-else class="text-muted">—</span>
    </button>

    <!-- The file said something the system could not resolve: showing it keeps
         the user from having to reopen his spreadsheet to know what to fix. -->
    <small v-if="error && raw && isSelect" class="import-cell__raw" :title="raw">« {{ raw }} »</small>

    <small v-if="error" class="import-cell__error" :title="error">{{ error }}</small>
  </div>
</template>

<style scoped>
.import-cell {
  min-width: 0;
}

.import-cell__control {
  min-width: 100%;
}

.import-cell__value {
  display: block;
  width: 100%;
  padding: 0.25rem 0.4rem;
  border: 1px dashed transparent;
  border-radius: 0.25rem;
  background: transparent;
  text-align: left;
  color: inherit;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.import-cell__value:hover {
  border-color: var(--vz-border-color);
  background: var(--vz-light);
}

.import-cell--invalid .import-cell__value {
  border-color: var(--vz-danger);
  border-style: solid;
  background: rgba(var(--vz-danger-rgb), 0.08);
}

.import-cell__raw,
.import-cell__error {
  display: block;
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  font-size: 0.6875rem;
  line-height: 1.2;
}

.import-cell__raw {
  color: var(--vz-secondary-color);
}

.import-cell__error {
  margin-top: 0.125rem;
  color: var(--vz-danger);
}
</style>
