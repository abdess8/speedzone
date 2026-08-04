/**
 * Builds a Postman Collection v2.1 out of the same endpoint catalogue that
 * renders the documentation page, so the two can never describe different APIs.
 *
 * The collection is meant to be run, not just read: path and body identifiers
 * are wired to collection variables, and the write endpoints capture the ids
 * they create so a full run walks the real order lifecycle end to end.
 */

import { ENDPOINTS, GROUPS } from './apiCatalog';

const SCHEMA = 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json';

/**
 * Folder order, and item order inside each folder, for the collection runner.
 *
 * The reference data comes first because an order cannot be created without a
 * city and a sector, and `orders-create` runs before everything that needs an
 * order to exist.
 */
const RUN_ORDER = {
  reference: ['user-me', 'cities-list', 'city-sectors', 'sectors-list'],
  orders: [
    'orders-create',
    'orders-list',
    'orders-show',
    'orders-track',
    'orders-tracking',
    'orders-update',
    'orders-pdf',
    'orders-delete',
  ],
  pickups: ['pickups-create', 'pickups-list', 'pickups-show'],
};

/** Path placeholders resolved through collection variables rather than literals. */
const PATH_VARIABLES = {
  order: '{{orderId}}',
  tracking_number: '{{trackingNumber}}',
  pickup_request: '{{pickupRequestId}}',
  city: '{{cityId}}',
};

/**
 * Body fields that must reference a variable instead of the documentation's
 * example value, so a created order points at a city and sector that exist.
 */
const BODY_VARIABLES = {
  city_id: '{{cityId}}',
  sector_id: '{{sectorId}}',
};

/**
 * Variables interpolated as raw JSON rather than as strings: `"{{cityId}}"`
 * would post the literal text, and the API expects an integer.
 */
const UNQUOTED_VARIABLES = ['{{cityId}}', '{{sectorId}}', '{{orderId}}'];

/**
 * Scripts that hand an identifier to the requests that come after.
 *
 * Without them the collection is a list of examples; with them a single run
 * creates an order, reads it back, edits it and books its pickup.
 */
const CAPTURES = {
  'cities-list': [['cityId', 'data[0].id']],
  'city-sectors': [['sectorId', 'data[0].id']],
  'orders-create': [
    ['orderId', 'data.id'],
    ['trackingNumber', 'data.tracking_number'],
  ],
  'pickups-create': [['pickupRequestId', 'data.id']],
};

const STATUS_TEXT = {
  200: 'OK',
  201: 'Created',
  204: 'No Content',
  401: 'Unauthorized',
  403: 'Forbidden',
  404: 'Not Found',
  422: 'Unprocessable Content',
  429: 'Too Many Requests',
  500: 'Internal Server Error',
};

const uuid = () =>
  globalThis.crypto?.randomUUID?.() ??
  'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
    const random = (Math.random() * 16) | 0;

    return (char === 'x' ? random : (random & 0x3) | 0x8).toString(16);
  });

function resolvePath(endpoint) {
  return (endpoint.pathParams ?? []).reduce((path, param) => {
    // The delete request deliberately points at its own variable, left empty so
    // that running the whole collection cannot wipe the order the other
    // requests are still working with.
    const value =
      endpoint.id === 'orders-delete' && param.name === 'order'
        ? '{{deletableOrderId}}'
        : (PATH_VARIABLES[param.name] ?? param.example ?? `{{${param.name}}}`);

    return path.replace(`{${param.name}}`, value);
  }, endpoint.path);
}

function buildUrl(endpoint, t) {
  const path = resolvePath(endpoint);
  const sample = endpoint.sampleQuery ?? {};

  // Every documented filter is listed, but only the ones the documentation
  // demonstrates are enabled — the rest sit in Postman's UI as one click away.
  const query = (endpoint.query ?? []).map((param) => ({
    key: param.name,
    value: sample[param.name] !== undefined ? String(sample[param.name]) : (param.default ?? ''),
    description: t(`api_docs.${param.desc}`),
    disabled: sample[param.name] === undefined,
  }));

  const enabled = query.filter((param) => !param.disabled);
  const queryString = enabled.length
    ? `?${enabled.map((param) => `${param.key}=${encodeURIComponent(param.value)}`).join('&')}`
    : '';

  return {
    raw: `{{baseUrl}}${path}${queryString}`,
    host: ['{{baseUrl}}'],
    path: path.replace(/^\//, '').split('/'),
    ...(query.length ? { query } : {}),
  };
}

function buildBody(endpoint) {
  if (!endpoint.request) {
    return undefined;
  }

  const payload = Object.fromEntries(
    Object.entries(endpoint.request).map(([key, value]) => {
      if (BODY_VARIABLES[key]) {
        return [key, BODY_VARIABLES[key]];
      }

      // A pickup collects the order the previous request just created.
      if (key === 'order_ids') {
        return [key, ['{{orderId}}']];
      }

      return [key, value];
    }),
  );

  const raw = UNQUOTED_VARIABLES.reduce(
    (json, variable) => json.split(`"${variable}"`).join(variable),
    JSON.stringify(payload, null, 2),
  );

  return {
    mode: 'raw',
    raw,
    options: { raw: { language: 'json' } },
  };
}

function buildHeaders(endpoint, { hasStores }) {
  const headers = [];

  if (!endpoint.binary) {
    headers.push({ key: 'Accept', value: 'application/json' });
  }

  if (endpoint.request) {
    headers.push({ key: 'Content-Type', value: 'application/json' });
  }

  headers.push({
    key: 'X-Store-Id',
    value: '{{storeId}}',
    // Sending an empty store header is harmless but noisy, so a single-store
    // account gets it switched off rather than removed.
    disabled: !hasStores,
  });

  return headers;
}

function buildRequest(endpoint, context, t, description) {
  const body = buildBody(endpoint);

  return {
    method: endpoint.method,
    header: buildHeaders(endpoint, context),
    ...(body ? { body } : {}),
    url: buildUrl(endpoint, t),
    ...(description ? { description } : {}),
  };
}

function buildTestScript(endpoint) {
  const expected = endpoint.responses?.[0]?.status ?? 200;
  const lines = [`pm.test('Status ${expected}', () => pm.response.to.have.status(${expected}));`];
  const captures = CAPTURES[endpoint.id] ?? [];

  if (captures.length) {
    lines.push(
      '',
      'if (pm.response.code < 300) {',
      ...captures.map(
        ([variable, path]) => `    pm.collectionVariables.set('${variable}', pm.response.json().${path});`,
      ),
      '}',
    );
  }

  return {
    listen: 'test',
    script: { type: 'text/javascript', exec: lines },
  };
}

function buildResponses(endpoint, request) {
  return (endpoint.responses ?? []).map((response) => ({
    name: `${response.status} ${STATUS_TEXT[response.status] ?? ''}`.trim(),
    originalRequest: request,
    status: STATUS_TEXT[response.status] ?? '',
    code: response.status,
    _postman_previewlanguage: response.raw ? 'text' : 'json',
    header: [
      { key: 'Content-Type', value: response.contentType ?? 'application/json' },
    ],
    cookie: [],
    body: response.raw ?? (response.sample === null ? '' : JSON.stringify(response.sample, null, 2)),
  }));
}

/**
 * Markdown shown on a request in Postman: what it does, then the caveats the
 * documentation page renders as callouts.
 */
function requestDescription(endpoint, t) {
  const parts = [t(`api_docs.endpoints.${endpoint.i18n}.description`)];

  if (endpoint.permission) {
    parts.push(`**${t('api_docs.labels.permission')}:** \`${endpoint.permission}\``);
  }

  (endpoint.notes ?? []).forEach((note) => parts.push(`> ${t(`api_docs.notes.${note}`)}`));

  return parts.join('\n\n');
}

function buildItem(endpoint, context, t) {
  const request = buildRequest(endpoint, context, t, requestDescription(endpoint, t));

  return {
    name: t(`api_docs.endpoints.${endpoint.i18n}.title`),
    event: [buildTestScript(endpoint)],
    request,
    response: buildResponses(endpoint, request),
  };
}

/**
 * @param {object} options
 * @param {string} options.baseUrl        origin the requests are pointed at
 * @param {string} options.token          personal token, blank when not supplied
 * @param {number|string|null} options.storeId
 * @param {{city_id: number|null, sector_id: number|null}} options.examples
 * @param {number} options.rateLimit
 * @param {(key: string, params?: object) => string} options.t
 * @returns {object} a Postman Collection v2.1 document
 */
export function buildPostmanCollection({ baseUrl, token, storeId, examples, rateLimit, t }) {
  const context = { hasStores: Boolean(storeId) };

  const folderOrder = Object.keys(RUN_ORDER);

  const folders = GROUPS.filter((group) => RUN_ORDER[group.id])
    .sort((a, b) => folderOrder.indexOf(a.id) - folderOrder.indexOf(b.id))
    .map((group) => ({
      name: t(group.labelKey),
      item: RUN_ORDER[group.id]
        .map((id) => ENDPOINTS.find((endpoint) => endpoint.id === id))
        .filter(Boolean)
        .map((endpoint) => buildItem(endpoint, context, t)),
    }));

  return {
    info: {
      _postman_id: uuid(),
      name: t('api_docs.postman.collection_name'),
      description: t('api_docs.postman.description', { limit: rateLimit }),
      schema: SCHEMA,
    },
    auth: {
      type: 'bearer',
      bearer: [{ key: 'token', value: '{{token}}', type: 'string' }],
    },
    variable: [
      { key: 'baseUrl', value: baseUrl, type: 'string' },
      { key: 'token', value: token, type: 'string' },
      { key: 'storeId', value: storeId ? String(storeId) : '', type: 'string' },
      { key: 'cityId', value: examples?.city_id ? String(examples.city_id) : '', type: 'string' },
      { key: 'sectorId', value: examples?.sector_id ? String(examples.sector_id) : '', type: 'string' },
      // Filled in by the capture scripts as the run progresses.
      { key: 'orderId', value: '', type: 'string' },
      { key: 'trackingNumber', value: '', type: 'string' },
      { key: 'pickupRequestId', value: '', type: 'string' },
      { key: 'deletableOrderId', value: '', type: 'string' },
    ],
    item: folders,
  };
}

/** Hands the collection to the browser as a file. */
export function downloadPostmanCollection(collection, filename = 'owl-delivery-api.postman_collection.json') {
  const blob = new Blob([JSON.stringify(collection, null, 2)], { type: 'application/json' });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');

  anchor.href = url;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
  URL.revokeObjectURL(url);
}
