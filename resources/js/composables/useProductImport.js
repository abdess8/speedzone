import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { parseAmount, parseBoolean } from '@/common/importParsers';

/**
 * State container and validation engine behind the product catalog import.
 *
 * Same three-step contract as the order import — parse, map, repair — and for
 * the same reason: a seller bringing three hundred references across from
 * another platform has to find out about the four duplicated SKUs on screen,
 * not through a server error that rejects the whole batch.
 *
 * The rules below mirror ImportProductsRequest. Where they cannot (uniqueness
 * against references already stored), the server has the last word and its
 * errors are projected back onto the table.
 */

/**
 * Product fields a file column can be mapped onto, in the order they appear in
 * the mapping list and in the review table.
 *
 * `aliases` are the header spellings seen in real exports (Shopify, WooCommerce,
 * Sage, hand-made sheets, French and English). They only seed the auto-matching
 * suggestion and every one of them stays overridable in step 2.
 */
export const PRODUCT_IMPORT_FIELDS = [
  {
    key: 'name',
    type: 'text',
    required: true,
    max: 255,
    aliases: ['nom', 'nom du produit', 'produit', 'designation', 'libelle', 'article', 'name', 'product', 'product name', 'title'],
  },
  {
    key: 'sku',
    type: 'text',
    required: false,
    max: 64,
    aliases: ['sku', 'reference', 'ref', 'code produit', 'code article', 'code', 'reference interne', 'item number'],
  },
  {
    key: 'barcode',
    type: 'text',
    required: false,
    max: 64,
    aliases: ['code barre', 'code barres', 'codebarre', 'barcode', 'ean', 'ean13', 'upc', 'gtin', 'bar code'],
  },
  {
    key: 'category',
    type: 'text',
    required: false,
    max: 120,
    aliases: ['categorie', 'category', 'famille', 'rayon', 'type', 'collection', 'product type'],
  },
  {
    key: 'unit_price',
    type: 'amount',
    required: true,
    aliases: ['prix', 'prix de vente', 'prix unitaire', 'prix ttc', 'pv', 'price', 'selling price', 'unit price', 'retail price'],
  },
  {
    key: 'cost_price',
    type: 'amount',
    required: false,
    aliases: ['cout', 'cout achat', 'prix achat', "prix d achat", 'pa', 'cost', 'cost price', 'purchase price', 'wholesale price'],
  },
  {
    key: 'stock_quantity',
    type: 'integer',
    required: false,
    aliases: ['stock', 'quantite', 'qte', 'stock initial', 'quantity', 'qty', 'inventory', 'on hand', 'stock disponible'],
  },
  {
    key: 'is_fragile',
    type: 'boolean',
    required: false,
    aliases: ['fragile', 'produit fragile', 'is fragile', 'fragilite'],
  },
  {
    key: 'weight_grams',
    type: 'integer',
    required: false,
    aliases: ['poids', 'poids g', 'poids grammes', 'weight', 'weight g', 'grams', 'gramme'],
  },
  {
    key: 'description',
    type: 'text',
    required: false,
    max: 5000,
    aliases: ['description', 'descriptif', 'detail', 'details', 'body', 'body html', 'notes'],
  },
];

const BOOLEAN_FIELDS = PRODUCT_IMPORT_FIELDS.filter((field) => field.type === 'boolean').map((f) => f.key);
const INTEGER_FIELDS = PRODUCT_IMPORT_FIELDS.filter((field) => field.type === 'integer').map((f) => f.key);
const AMOUNT_FIELDS = PRODUCT_IMPORT_FIELDS.filter((field) => field.type === 'amount').map((f) => f.key);

/** Single example row written into the downloadable template. */
export const PRODUCT_TEMPLATE_EXAMPLES = {
  name: 'T-shirt coton bio — Taille M',
  sku: 'TSH-BIO-M',
  barcode: '3760123456789',
  category: 'Vêtements',
  unit_price: '149.90',
  cost_price: '62.00',
  stock_quantity: '40',
  is_fragile: 'Non',
  weight_grams: '180',
  description: 'Coton biologique certifié, coupe droite',
};

/** Mirrors the `sku` regex in StoreProductRequest. */
const SKU_PATTERN = /^[A-Za-z0-9._\-/]+$/;

const MAX_PRICE = 99999999.99;
const MAX_QUANTITY = 1000000;

/** Strip case, accents and punctuation so "Référence", "reference" and "REF_" compare equal. */
function normalizeKey(value) {
  return String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '');
}

function isBlank(value) {
  return value === null || value === undefined || String(value).trim() === '';
}

/**
 * Parse a whole number written by a human: "1 240", "1.240" and "1240 pcs" all
 * mean the same thing to the person who typed them.
 */
export function parseInteger(raw) {
  if (typeof raw === 'number') {
    return Number.isInteger(raw) ? raw : Math.round(raw);
  }

  const text = String(raw ?? '').replace(/[^\d-]/g, '');

  if (text === '' || text === '-') {
    return null;
  }

  const value = Number(text);

  return Number.isFinite(value) ? Math.trunc(value) : null;
}

/**
 * Score how well a file header matches a product field.
 *
 * Exact matches outrank prefixes, which outrank substrings, and a longer alias
 * wins over a shorter one — without that tie-break "Prix d'achat" is claimed by
 * the `prix` alias of the selling price.
 */
function matchScore(header, candidates) {
  const target = normalizeKey(header);

  if (target === '') {
    return 0;
  }

  let best = 0;

  for (const candidate of candidates) {
    const alias = normalizeKey(candidate);

    if (alias === '') {
      continue;
    }

    let score = 0;

    if (target === alias) {
      score = 100;
    } else if (alias.length >= 3 && (target.startsWith(alias) || alias.startsWith(target))) {
      score = 60;
    } else if (alias.length >= 4 && target.includes(alias)) {
      score = 30;
    }

    if (score > 0) {
      best = Math.max(best, score + alias.length);
    }
  }

  return best;
}

export function useProductImport(props) {
  const { t } = useI18n();

  /* ---------------------------------------------------------------- state */

  const step = ref(1);
  const file = ref(null);
  const headers = ref([]);
  const rawRows = ref([]);
  /** field key → header index, or null when unmapped. */
  const mapping = ref({});
  const rows = ref([]);
  /** row id → { field: message } */
  const errors = ref({});
  /** Set once the review table has been analysed at least once. */
  const checked = ref(false);
  /** True as soon as a cell is edited or a row removed, until "Verify" runs. */
  const dirty = ref(false);

  let nextRowId = 1;

  const maxRows = computed(() => Number(props.maxRows ?? 1000));

  /** Categories already in use, offered as suggestions in the review table. */
  const categories = computed(() => props.categories ?? []);

  /* ----------------------------------------------------------- step 2: map */

  /**
   * Pre-select the file column for each product field.
   *
   * Pairs are scored globally then assigned greedily, so the strongest match
   * wins the column instead of whichever field happened to be checked first.
   */
  function autoMap() {
    const pairs = [];

    headers.value.forEach((header, index) => {
      for (const field of PRODUCT_IMPORT_FIELDS) {
        const score = matchScore(header, [
          t(`stock.products.import.fields.${field.key}`),
          ...field.aliases,
        ]);

        if (score > 0) {
          pairs.push({ index, key: field.key, score });
        }
      }
    });

    pairs.sort((a, b) => b.score - a.score);

    const takenHeaders = new Set();
    const result = Object.fromEntries(PRODUCT_IMPORT_FIELDS.map((field) => [field.key, null]));

    for (const pair of pairs) {
      if (result[pair.key] === null && !takenHeaders.has(pair.index)) {
        result[pair.key] = pair.index;
        takenHeaders.add(pair.index);
      }
    }

    mapping.value = result;
  }

  const unmappedRequiredFields = computed(() =>
    PRODUCT_IMPORT_FIELDS.filter((field) => field.required && mapping.value[field.key] == null)
  );

  const mappingIsValid = computed(() => unmappedRequiredFields.value.length === 0);

  /* ------------------------------------------------ step 3: row extraction */

  function cellAt(rawRow, fieldKey) {
    const index = mapping.value[fieldKey];

    return index === null || index === undefined ? '' : (rawRow[index] ?? '');
  }

  /**
   * Turn the mapped raw rows into product payloads.
   *
   * A value that cannot be read (an unparseable price, an unknown truthiness
   * marker) is left on screen exactly as the file wrote it, and kept on `_raw`,
   * so the seller can see what his spreadsheet actually said.
   */
  function buildRows() {
    nextRowId = 1;

    rows.value = rawRows.value.map((rawRow, position) => {
      const raw = {};
      const row = {
        _id: nextRowId++,
        // +2: the header occupies line 1 and spreadsheet lines are 1-based.
        _line: position + 2,
        _raw: raw,
      };

      for (const field of PRODUCT_IMPORT_FIELDS) {
        raw[field.key] = String(cellAt(rawRow, field.key) ?? '').trim();
      }

      row.name = raw.name;
      row.sku = raw.sku;
      row.barcode = raw.barcode;
      row.category = raw.category;
      row.description = raw.description;

      for (const key of AMOUNT_FIELDS) {
        const amount = parseAmount(raw[key]);
        row[key] = amount === null ? raw[key] : String(amount);
      }

      for (const key of INTEGER_FIELDS) {
        const value = parseInteger(raw[key]);
        row[key] = value === null ? raw[key] : String(value);
      }

      for (const key of BOOLEAN_FIELDS) {
        row[key] = parseBoolean(raw[key]);
      }

      return row;
    });
  }

  /* ------------------------------------------------------------ validation */

  /**
   * Validate a single row against the ImportProductsRequest rules.
   *
   * @returns {Record<string, string>} field key → human readable message
   */
  function validateRow(row) {
    const rowErrors = {};

    for (const field of PRODUCT_IMPORT_FIELDS) {
      const value = row[field.key];

      if (field.type !== 'text') {
        continue;
      }

      if (field.required && isBlank(value)) {
        rowErrors[field.key] = t('stock.products.import.errors.required');
      } else if (!isBlank(value) && String(value).length > field.max) {
        rowErrors[field.key] = t('stock.products.import.errors.too_long', { max: field.max });
      }
    }

    if (!isBlank(row.sku) && !SKU_PATTERN.test(String(row.sku).trim())) {
      rowErrors.sku = t('stock.products.import.errors.sku_format');
    }

    if (isBlank(row.unit_price)) {
      rowErrors.unit_price = t('stock.products.import.errors.required');
    }

    for (const key of AMOUNT_FIELDS) {
      if (isBlank(row[key])) {
        continue;
      }

      const amount = parseAmount(row[key]);

      if (amount === null) {
        rowErrors[key] = t('stock.products.import.errors.price');
      } else if (amount < 0) {
        rowErrors[key] = t('stock.products.import.errors.price_negative');
      } else if (amount > MAX_PRICE) {
        rowErrors[key] = t('stock.products.import.errors.price_too_large');
      }
    }

    for (const key of INTEGER_FIELDS) {
      if (isBlank(row[key])) {
        continue;
      }

      const value = parseInteger(row[key]);

      if (value === null) {
        rowErrors[key] = t('stock.products.import.errors.integer');
      } else if (value < 0) {
        rowErrors[key] = t('stock.products.import.errors.price_negative');
      } else if (value > MAX_QUANTITY) {
        rowErrors[key] = t('stock.products.import.errors.price_too_large');
      }
    }

    for (const key of BOOLEAN_FIELDS) {
      if (row[key] === null) {
        rowErrors[key] = t('stock.products.import.errors.boolean', { value: row._raw[key] });
      }
    }

    // Uniqueness inside the file: the server rejects the batch on the first
    // collision, so it is worth pointing at both offending rows here.
    if (!isBlank(row.sku) && duplicateSkuIds.value.has(row._id)) {
      rowErrors.sku = t('stock.products.import.errors.sku_duplicate');
    }

    if (!isBlank(row.barcode) && duplicateBarcodeIds.value.has(row._id)) {
      rowErrors.barcode = t('stock.products.import.errors.barcode_duplicate');
    }

    return rowErrors;
  }

  /** Re-validate one row without touching the "needs verifying" state. */
  function revalidateRow(rowId) {
    const row = rows.value.find((candidate) => candidate._id === rowId);

    if (!row) {
      return;
    }

    const rowErrors = validateRow(row);
    const next = { ...errors.value };

    if (Object.keys(rowErrors).length === 0) {
      delete next[rowId];
    } else {
      next[rowId] = rowErrors;
    }

    errors.value = next;
  }

  /** Run the full validation pass behind the "Verify" button. */
  function validateAll() {
    const next = {};

    for (const row of rows.value) {
      const rowErrors = validateRow(row);

      if (Object.keys(rowErrors).length > 0) {
        next[row._id] = rowErrors;
      }
    }

    errors.value = next;
    checked.value = true;
    dirty.value = false;

    return Object.keys(next).length === 0;
  }

  /* --------------------------------------------------------- row edition */

  function touch(rowId) {
    dirty.value = true;
    revalidateRow(rowId);
  }

  function removeRow(rowId) {
    rows.value = rows.value.filter((row) => row._id !== rowId);

    const next = { ...errors.value };
    delete next[rowId];
    errors.value = next;

    dirty.value = true;
  }

  function removeInvalidRows() {
    const invalid = new Set(Object.keys(errors.value).map(Number));

    rows.value = rows.value.filter((row) => !invalid.has(row._id));
    errors.value = {};
    dirty.value = true;
  }

  /* ---------------------------------------------------------- derived state */

  /** Row ids sharing a non-empty value in `field` with another row. */
  function duplicatesOn(field) {
    const seen = new Map();
    const duplicates = new Set();

    for (const row of rows.value) {
      const key = normalizeKey(row[field]);

      if (key === '') {
        continue;
      }

      if (seen.has(key)) {
        duplicates.add(seen.get(key));
        duplicates.add(row._id);
      } else {
        seen.set(key, row._id);
      }
    }

    return duplicates;
  }

  const duplicateSkuIds = computed(() => duplicatesOn('sku'));
  const duplicateBarcodeIds = computed(() => duplicatesOn('barcode'));

  const invalidRowIds = computed(() => new Set(Object.keys(errors.value).map(Number)));

  const invalidRowCount = computed(() => invalidRowIds.value.size);

  const errorCount = computed(() =>
    Object.values(errors.value).reduce((total, rowErrors) => total + Object.keys(rowErrors).length, 0)
  );

  /** Retail value of the batch, so the seller can sanity-check the magnitude. */
  const totalValue = computed(() =>
    rows.value.reduce((total, row) => {
      const price = parseAmount(row.unit_price) ?? 0;
      const quantity = parseInteger(row.stock_quantity) ?? 0;

      return total + price * quantity;
    }, 0)
  );

  const totalUnits = computed(() =>
    rows.value.reduce((total, row) => total + (parseInteger(row.stock_quantity) ?? 0), 0)
  );

  /**
   * "Verify" stays available while anything is unconfirmed; "Save" only lights
   * up on a list that was validated after the last edit and came back clean.
   */
  const canVerify = computed(() => rows.value.length > 0 && (dirty.value || errorCount.value > 0));

  const canSave = computed(
    () => checked.value && !dirty.value && errorCount.value === 0 && rows.value.length > 0
  );

  /**
   * Project server side validation errors back onto the table.
   *
   * Keys arrive as `products.12.sku`, indexed by position in the submitted
   * payload, which is the position of the row on screen.
   */
  function applyServerErrors(serverErrors) {
    const next = {};

    for (const [key, message] of Object.entries(serverErrors ?? {})) {
      const match = /^products\.(\d+)\.([\w_]+)$/.exec(key);
      const row = match ? rows.value[Number(match[1])] : null;

      if (!row) {
        continue;
      }

      next[row._id] = {
        ...(next[row._id] ?? {}),
        [match[2]]: Array.isArray(message) ? message[0] : message,
      };
    }

    errors.value = next;
    checked.value = true;
    dirty.value = false;

    return Object.keys(next).length;
  }

  /* ------------------------------------------------------------- submission */

  function payload() {
    return rows.value.map((row) => ({
      name: String(row.name ?? '').trim(),
      sku: isBlank(row.sku) ? null : String(row.sku).trim(),
      barcode: isBlank(row.barcode) ? null : String(row.barcode).trim(),
      category: isBlank(row.category) ? null : String(row.category).trim(),
      description: isBlank(row.description) ? null : String(row.description).trim(),
      unit_price: parseAmount(row.unit_price) ?? 0,
      cost_price: isBlank(row.cost_price) ? null : parseAmount(row.cost_price),
      stock_quantity: parseInteger(row.stock_quantity) ?? 0,
      weight_grams: isBlank(row.weight_grams) ? null : parseInteger(row.weight_grams),
      is_fragile: row.is_fragile === true,
    }));
  }

  /* ------------------------------------------------------------ navigation */

  function reset() {
    step.value = 1;
    file.value = null;
    headers.value = [];
    rawRows.value = [];
    mapping.value = {};
    rows.value = [];
    errors.value = {};
    checked.value = false;
    dirty.value = false;
  }

  return {
    // state
    step,
    file,
    headers,
    rawRows,
    mapping,
    rows,
    errors,
    checked,
    dirty,
    // reference data
    categories,
    maxRows,
    // derived
    mappingIsValid,
    unmappedRequiredFields,
    invalidRowIds,
    invalidRowCount,
    errorCount,
    duplicateSkuIds,
    duplicateBarcodeIds,
    totalValue,
    totalUnits,
    canVerify,
    canSave,
    // actions
    autoMap,
    buildRows,
    validateAll,
    revalidateRow,
    applyServerErrors,
    touch,
    removeRow,
    removeInvalidRows,
    payload,
    reset,
  };
}
