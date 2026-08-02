import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * State container and validation engine behind the bulk order import wizard.
 *
 * The three steps share one store: step 1 parses the workbook into raw rows,
 * step 2 maps file headers onto order fields, step 3 validates and repairs the
 * resulting payloads. The rules implemented here mirror StoreOrderRequest, so a
 * list that turns green on screen is a list the server accepts — the point of
 * the wizard is that a seller finds out about a bad phone number before he
 * uploads four hundred of them, not after.
 */

/** Rows above this are rejected: a single Inertia request has to carry them. */
export const MAX_IMPORT_ROWS = 1000;

/**
 * A sector is optional in the file only in the sense that the seller may leave
 * it blank and pick it in the review table; the column itself is required by
 * StoreOrderRequest, so it can never reach the server empty.
 */
const SECTOR_REQUIRED = true;

/**
 * Order fields a file column can be mapped onto, in the order they appear in
 * the mapping list and in the review table.
 *
 * `aliases` are the header spellings seen in the wild (Shopify, WooCommerce and
 * hand-made sheets, French and English); they only drive the auto-matching
 * suggestion, and every one of them stays overridable in step 2.
 */
export const IMPORT_FIELDS = [
  {
    key: 'customer_first_name',
    type: 'text',
    required: true,
    max: 255,
    aliases: ['prenom', 'prenom client', 'client prenom', 'first name', 'firstname', 'given name'],
  },
  {
    key: 'customer_last_name',
    type: 'text',
    required: true,
    max: 255,
    aliases: ['nom', 'nom client', 'nom de famille', 'last name', 'lastname', 'surname', 'family name'],
  },
  {
    key: 'customer_phone',
    type: 'phone',
    required: true,
    max: 50,
    aliases: ['telephone', 'tel', 'gsm', 'phone', 'mobile', 'numero', 'num tel', 'contact', 'phone number'],
  },
  {
    key: 'city_id',
    type: 'city',
    required: true,
    aliases: ['ville', 'city', 'ville de livraison', 'destination', 'town'],
  },
  {
    key: 'sector_id',
    type: 'sector',
    required: SECTOR_REQUIRED,
    aliases: ['secteur', 'sector', 'zone', 'quartier', 'district', 'area'],
  },
  {
    key: 'customer_address',
    type: 'text',
    required: true,
    max: 1000,
    aliases: ['adresse', 'address', 'adresse de livraison', 'shipping address', 'street'],
  },
  {
    key: 'payment_method',
    type: 'payment',
    required: false,
    aliases: ['mode de paiement', 'paiement', 'payment', 'payment method', 'mode paiement', 'type de paiement'],
  },
  {
    key: 'order_amount',
    type: 'amount',
    required: true,
    aliases: ['montant', 'montant commande', 'montant de commande', 'prix', 'amount', 'order amount', 'total', 'crbt', 'cod'],
  },
  {
    key: 'notes',
    type: 'text',
    required: false,
    max: 2000,
    aliases: ['notes', 'note', 'remarque', 'commentaire', 'comment', 'observation'],
  },
  {
    key: 'is_fragile',
    type: 'boolean',
    required: false,
    aliases: ['fragile', 'colis fragile', 'is fragile'],
  },
  {
    key: 'can_be_opened',
    type: 'boolean',
    required: false,
    aliases: ['ouverture', 'ouverture autorisee', 'autoriser ouverture', 'ouvrir', 'can be opened', 'open allowed'],
  },
  {
    key: 'option_exchange',
    type: 'boolean',
    required: false,
    aliases: ['echange', 'option echange', 'option d echange', 'exchange', 'exchange option'],
  },
];

const BOOLEAN_FIELDS = IMPORT_FIELDS.filter((field) => field.type === 'boolean').map((f) => f.key);

/** Single example row written into the downloadable template. */
export const TEMPLATE_EXAMPLES = {
  customer_first_name: 'Yasmine',
  customer_last_name: 'El Amrani',
  customer_phone: '0612345678',
  city_id: 'Casablanca',
  sector_id: 'Maarif',
  customer_address: '12 rue des Fleurs, appartement 4',
  payment_method: 'Cash',
  order_amount: '450',
  notes: 'Livrer avant 18h',
  is_fragile: 'Oui',
  can_be_opened: 'Non',
  option_exchange: 'Non',
};

const TRUE_TOKENS = ['1', 'oui', 'o', 'yes', 'y', 'true', 'vrai', 'x', 'v'];
const FALSE_TOKENS = ['0', 'non', 'n', 'no', 'false', 'faux', '-'];

const CASH_TOKENS = ['cash', 'espece', 'especes', 'comptant', 'liquide', 'cod', 'crbt', 'contre remboursement', 'a la livraison'];
const CARD_TOKENS = ['carte', 'card', 'cb', 'cmi', 'carte bancaire', 'paiement par carte', 'paye', 'paid', 'prepaye', 'prepaid', 'virement', 'online', 'en ligne'];

/**
 * Moroccan mobile and landline numbers, once punctuation and country prefix
 * have been stripped: a 9 digit national number opening with 5, 6 or 7.
 */
const NATIONAL_NUMBER = /^[5-7]\d{8}$/;

/** Strip case, accents and punctuation so "Prénom", "prenom" and "PRE_NOM" compare equal. */
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
 * Reduce a written phone number to its 9 national digits.
 *
 * Returns null when the input cannot be read as a Moroccan number.
 */
export function normalizePhone(raw) {
  let digits = String(raw ?? '').replace(/[^\d+]/g, '');

  digits = digits.replace(/^\+/, '').replace(/^00/, '');
  digits = digits.replace(/^212/, '');
  digits = digits.replace(/^0/, '');

  return NATIONAL_NUMBER.test(digits) ? digits : null;
}

/** Display form of a national number: 0612345678. */
export function formatPhone(national) {
  return `0${national}`;
}

/**
 * Parse an amount written by a human.
 *
 * Handles "1 234,50", "1,234.50", "150 MAD" and "150.00" alike: the rightmost
 * separator is the decimal one, anything else is a thousands separator.
 */
export function parseAmount(raw) {
  if (typeof raw === 'number') {
    return Number.isFinite(raw) ? raw : null;
  }

  let text = String(raw ?? '').replace(/[^\d,.-]/g, '').trim();

  if (text === '') {
    return null;
  }

  const lastComma = text.lastIndexOf(',');
  const lastDot = text.lastIndexOf('.');

  if (lastComma > -1 && lastDot > -1) {
    const decimal = lastComma > lastDot ? ',' : '.';
    const thousands = decimal === ',' ? '.' : ',';
    text = text.split(thousands).join('').replace(decimal, '.');
  } else if (lastComma > -1) {
    // "1,50" is a price, "1,500" is a thousands separator.
    text = text.length - lastComma - 1 <= 2 ? text.replace(',', '.') : text.split(',').join('');
  }

  const amount = Number(text);

  return Number.isFinite(amount) ? amount : null;
}

/** Read a spreadsheet truthiness marker. Returns null when unrecognised. */
export function parseBoolean(raw) {
  if (typeof raw === 'boolean') {
    return raw;
  }

  const token = normalizeKey(raw);

  if (token === '') {
    return false;
  }
  if (TRUE_TOKENS.some((candidate) => normalizeKey(candidate) === token)) {
    return true;
  }
  if (FALSE_TOKENS.some((candidate) => normalizeKey(candidate) === token)) {
    return false;
  }

  return null;
}

/** Map free text onto a PaymentMethod value. Returns null when unrecognised. */
export function parsePaymentMethod(raw) {
  const token = normalizeKey(raw);

  if (token === '') {
    return 'CASH';
  }
  if (token === normalizeKey('CARD_PAYMENT')) {
    return 'CARD_PAYMENT';
  }
  if (CASH_TOKENS.some((candidate) => token.includes(normalizeKey(candidate)))) {
    return 'CASH';
  }
  if (CARD_TOKENS.some((candidate) => token.includes(normalizeKey(candidate)))) {
    return 'CARD_PAYMENT';
  }

  return null;
}

/**
 * Score how well a file header matches an order field.
 *
 * Exact matches outrank prefixes, which outrank substrings, and a longer alias
 * wins over a shorter one — without that last tie-break "Prénom" is claimed by
 * the `nom` alias of the last-name field.
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

export function useOrderImport(props) {
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

  /* -------------------------------------------------- reference data index */

  const cities = computed(() => props.cities ?? []);
  const sectors = computed(() => props.sectors ?? []);

  const cityById = computed(() => new Map(cities.value.map((city) => [city.id, city])));

  const cityByName = computed(() => {
    const index = new Map();

    for (const city of cities.value) {
      for (const alias of [city.name, city.code]) {
        const key = normalizeKey(alias);

        if (key !== '' && !index.has(key)) {
          index.set(key, city);
        }
      }
    }

    return index;
  });

  const sectorById = computed(() => new Map(sectors.value.map((sector) => [sector.id, sector])));

  const sectorsByCity = computed(() => {
    const index = new Map();

    for (const sector of sectors.value) {
      if (!index.has(sector.city_id)) {
        index.set(sector.city_id, []);
      }

      index.get(sector.city_id).push(sector);
    }

    return index;
  });

  const sectorByCityAndName = computed(() => {
    const index = new Map();

    for (const sector of sectors.value) {
      const key = `${sector.city_id}:${normalizeKey(sector.name)}`;

      if (!index.has(key)) {
        index.set(key, sector);
      }
    }

    return index;
  });

  function sectorOptionsFor(cityId) {
    return sectorsByCity.value.get(Number(cityId)) ?? [];
  }

  /* ----------------------------------------------------------- step 2: map */

  /**
   * Pre-select the file column for each order field.
   *
   * Pairs are scored globally then assigned greedily, so the strongest match
   * wins the column instead of whichever field happened to be checked first.
   */
  function autoMap() {
    const pairs = [];

    headers.value.forEach((header, index) => {
      for (const field of IMPORT_FIELDS) {
        const score = matchScore(header, [t(`orders.import.fields.${field.key}`), ...field.aliases]);

        if (score > 0) {
          pairs.push({ index, key: field.key, score });
        }
      }
    });

    pairs.sort((a, b) => b.score - a.score);

    const takenHeaders = new Set();
    const result = Object.fromEntries(IMPORT_FIELDS.map((field) => [field.key, null]));

    for (const pair of pairs) {
      if (result[pair.key] === null && !takenHeaders.has(pair.index)) {
        result[pair.key] = pair.index;
        takenHeaders.add(pair.index);
      }
    }

    mapping.value = result;
  }

  const unmappedRequiredFields = computed(() =>
    IMPORT_FIELDS.filter((field) => field.required && mapping.value[field.key] == null)
  );

  /** A sector cannot be resolved without knowing the city it belongs to. */
  const mappingIsValid = computed(
    () => unmappedRequiredFields.value.filter((field) => field.key !== 'sector_id').length === 0
  );

  /* ------------------------------------------------ step 3: row extraction */

  function cellAt(rawRow, fieldKey) {
    const index = mapping.value[fieldKey];

    return index === null || index === undefined ? '' : (rawRow[index] ?? '');
  }

  /**
   * Turn the mapped raw rows into order payloads.
   *
   * Every text value that cannot be resolved against reference data (an unknown
   * city, an unreadable payment mode) is kept on `_raw` so the review table can
   * show the seller what his file actually said next to the empty picker.
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

      for (const field of IMPORT_FIELDS) {
        raw[field.key] = String(cellAt(rawRow, field.key) ?? '').trim();
      }

      row.customer_first_name = raw.customer_first_name;
      row.customer_last_name = raw.customer_last_name;
      row.customer_address = raw.customer_address;
      row.notes = raw.notes;

      const national = normalizePhone(raw.customer_phone);
      row.customer_phone = national ? formatPhone(national) : raw.customer_phone;

      const city = cityByName.value.get(normalizeKey(raw.city_id)) ?? null;
      row.city_id = city?.id ?? null;

      const sector = city
        ? sectorByCityAndName.value.get(`${city.id}:${normalizeKey(raw.sector_id)}`) ?? null
        : null;
      row.sector_id = sector?.id ?? null;

      row.payment_method = parsePaymentMethod(raw.payment_method);

      const amount = parseAmount(raw.order_amount);
      row.order_amount = amount === null ? raw.order_amount : String(amount);

      for (const key of BOOLEAN_FIELDS) {
        row[key] = parseBoolean(raw[key]);
      }

      return row;
    });
  }

  /* ------------------------------------------------------------ validation */

  /**
   * Validate a single row against the StoreOrderRequest rules.
   *
   * @returns {Record<string, string>} field key → human readable message
   */
  function validateRow(row) {
    const rowErrors = {};

    for (const field of IMPORT_FIELDS) {
      const value = row[field.key];

      if (field.type === 'text') {
        if (field.required && isBlank(value)) {
          rowErrors[field.key] = t('orders.import.errors.required');
        } else if (!isBlank(value) && String(value).length > field.max) {
          rowErrors[field.key] = t('orders.import.errors.too_long', { max: field.max });
        }
      }
    }

    if (isBlank(row.customer_phone)) {
      rowErrors.customer_phone = t('orders.import.errors.required');
    } else if (normalizePhone(row.customer_phone) === null) {
      rowErrors.customer_phone = t('orders.import.errors.phone');
    }

    if (row.city_id === null || row.city_id === '') {
      rowErrors.city_id = isBlank(row._raw.city_id)
        ? t('orders.import.errors.required')
        : t('orders.import.errors.unknown_city', { value: row._raw.city_id });
    } else if (!cityById.value.has(Number(row.city_id))) {
      rowErrors.city_id = t('orders.import.errors.unknown_city', { value: row.city_id });
    }

    if (row.sector_id === null || row.sector_id === '') {
      if (SECTOR_REQUIRED) {
        rowErrors.sector_id = isBlank(row._raw.sector_id)
          ? t('orders.import.errors.required')
          : t('orders.import.errors.unknown_sector', { value: row._raw.sector_id });
      }
    } else {
      const sector = sectorById.value.get(Number(row.sector_id));

      if (!sector) {
        rowErrors.sector_id = t('orders.import.errors.unknown_sector', { value: row.sector_id });
      } else if (sector.city_id !== Number(row.city_id)) {
        // The seller changed the city and left a sector from the previous one.
        rowErrors.sector_id = t('orders.import.errors.sector_city_mismatch');
      }
    }

    if (row.payment_method === null) {
      rowErrors.payment_method = t('orders.import.errors.unknown_payment', {
        value: row._raw.payment_method,
      });
    }

    const amount = parseAmount(row.order_amount);

    if (row.payment_method === 'CASH' && isBlank(row.order_amount)) {
      rowErrors.order_amount = t('orders.import.errors.amount_required_cash');
    } else if (!isBlank(row.order_amount) && amount === null) {
      rowErrors.order_amount = t('orders.import.errors.amount');
    } else if (amount !== null && amount < 0) {
      rowErrors.order_amount = t('orders.import.errors.amount_negative');
    } else if (amount !== null && amount > 99999999.99) {
      rowErrors.order_amount = t('orders.import.errors.amount_too_large');
    }

    for (const key of BOOLEAN_FIELDS) {
      if (row[key] === null) {
        rowErrors[key] = t('orders.import.errors.boolean', { value: row._raw[key] });
      }
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

  /**
   * Run the full validation pass behind the "Verify" button.
   *
   * Phone numbers are rewritten to their canonical form here rather than on
   * every keystroke, so the seller sees what will actually be saved without the
   * field fighting him while he types.
   */
  function validateAll() {
    const next = {};

    for (const row of rows.value) {
      const national = normalizePhone(row.customer_phone);

      if (national) {
        row.customer_phone = formatPhone(national);
      }

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

  /**
   * Record a cell edit.
   *
   * Clearing the city invalidates the sector picker below it, so the stale
   * value is dropped rather than left to fail server side.
   */
  function touch(rowId, fieldKey) {
    dirty.value = true;

    if (fieldKey === 'city_id') {
      const row = rows.value.find((candidate) => candidate._id === rowId);
      const sector = row?.sector_id ? sectorById.value.get(Number(row.sector_id)) : null;

      if (row && sector && sector.city_id !== Number(row.city_id)) {
        row.sector_id = null;
      }
    }

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

  const invalidRowIds = computed(() => new Set(Object.keys(errors.value).map(Number)));

  const invalidRowCount = computed(() => invalidRowIds.value.size);

  const errorCount = computed(() =>
    Object.values(errors.value).reduce((total, rowErrors) => total + Object.keys(rowErrors).length, 0)
  );

  /**
   * Rows sharing a phone number with another row of the same file.
   *
   * Re-ordering the same parcel twice is a real scenario, so this is a warning
   * the seller can ignore, never a blocking error.
   */
  const duplicateRowIds = computed(() => {
    const seen = new Map();
    const duplicates = new Set();

    for (const row of rows.value) {
      const national = normalizePhone(row.customer_phone);

      if (!national) {
        continue;
      }

      if (seen.has(national)) {
        duplicates.add(seen.get(national));
        duplicates.add(row._id);
      } else {
        seen.set(national, row._id);
      }
    }

    return duplicates;
  });

  const totalAmount = computed(() =>
    rows.value.reduce((total, row) => {
      if (row.payment_method !== 'CASH') {
        return total;
      }

      return total + (parseAmount(row.order_amount) ?? 0);
    }, 0)
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
   * Keys arrive as `orders.12.customer_phone`, indexed by position in the
   * submitted payload, which is the position of the row on screen. The two
   * amount columns are folded back into the single one the table shows.
   */
  function applyServerErrors(serverErrors) {
    const next = {};

    for (const [key, message] of Object.entries(serverErrors ?? {})) {
      const match = /^orders\.(\d+)\.([\w_]+)$/.exec(key);
      const row = match ? rows.value[Number(match[1])] : null;

      if (!row) {
        continue;
      }

      const field = match[2] === 'order_value' ? 'order_amount' : match[2];

      next[row._id] = {
        ...(next[row._id] ?? {}),
        [field]: Array.isArray(message) ? message[0] : message,
      };
    }

    errors.value = next;
    checked.value = true;
    dirty.value = false;

    return Object.keys(next).length;
  }

  /* ------------------------------------------------------------- submission */

  /**
   * Build the request payload.
   *
   * Amounts follow the same split as the single-order form: a cash order
   * declares what the driver collects, a card order only declares a value.
   */
  function payload() {
    return rows.value.map((row) => {
      const amount = parseAmount(row.order_amount);
      const isCash = row.payment_method === 'CASH';

      return {
        customer_first_name: row.customer_first_name,
        customer_last_name: row.customer_last_name,
        customer_phone: row.customer_phone,
        customer_address: row.customer_address,
        city_id: Number(row.city_id),
        sector_id: row.sector_id === null ? null : Number(row.sector_id),
        payment_method: row.payment_method,
        order_amount: isCash ? amount : null,
        order_value: amount,
        notes: row.notes || null,
        is_fragile: row.is_fragile === true,
        can_be_opened: row.can_be_opened === true,
        option_exchange: row.option_exchange === true,
      };
    });
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
    cities,
    sectors,
    cityById,
    sectorById,
    sectorOptionsFor,
    // derived
    mappingIsValid,
    unmappedRequiredFields,
    invalidRowIds,
    invalidRowCount,
    errorCount,
    duplicateRowIds,
    totalAmount,
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
