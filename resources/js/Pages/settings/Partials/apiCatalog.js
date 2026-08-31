/**
 * The endpoint catalogue backing the API documentation page.
 *
 * Every entry mirrors a route actually registered in `routes/api.php`, and the
 * response samples mirror what the matching API resource emits — including
 * which relations the controller eager-loads, since anything it leaves out is
 * absent from the payload rather than null.
 *
 * Translatable strings are stored as keys under the `api_docs` group; the page
 * resolves them through vue-i18n so the catalogue itself stays language-neutral.
 */

const SELLER = {
  id: 42,
  name: 'Atlas Concept Store',
  email: 'contact@atlas-concept.ma',
  phone: '0522334455',
  profile_photo_url: 'https://ui-avatars.com/api/?name=Atlas+Concept+Store',
  photo_url: null,
  has_profile_photo: false,
  role: null,
  role_label: null,
  city_id: 3,
};

const ORDER_LIST_ITEM = {
  id: 1841,
  tracking_number: 'SPD-2026-583920',
  order_number: 'SPD-2026-583920',
  tracking_url: 'https://app.speedzone.ma/orders/SPD-2026-583920',
  status: 'OUT_FOR_DELIVERY',
  status_label: 'Out for delivery',
  status_color: 'warning',
  customer: {
    first_name: 'Fatima Zohra',
    last_name: 'Bennani',
    full_name: 'Fatima Zohra Bennani',
    phone: '0612345678',
    address: '15 Rue de Fès, Résidence Al Manar, Apt 4',
  },
  city: { id: 7, name: 'Tanger', region: 'Tanger-Tétouan-Al Hoceïma' },
  city_id: 7,
  sector: { id: 21, name: 'Centre Ville', delivery_price: 25 },
  sector_id: 21,
  seller: SELLER,
  seller_id: 42,
  store: {
    id: 12,
    name: 'Atlas Concept — Casablanca',
    category: 'Fashion',
    logo_url: 'https://app.speedzone.ma/storage/stores/12/logo.png',
  },
  store_id: 12,
  partner_id: null,
  external_tracking_code: null,
  partner_sync_error: null,
  is_partner_delivery: false,
  driver_id: 88,
  assigned_at: '2026-08-01T07:02:44+00:00',
  delivered_at: null,
  returned_at: null,
  invoice_id: null,
  is_returned: false,
  payment_method: 'CASH',
  payment_method_label: 'Cash on delivery',
  payment_method_display: '💵 Cash on delivery',
  payment_method_icon: 'ri-money-dollar-box-fill',
  payment_method_emoji: '💵',
  payment_method_color: 'success',
  cash_collection_required: true,
  order_amount: 250,
  order_value: null,
  amount_to_collect: 250,
  delivery_price: 25,
  delivery_included: false,
  total_amount: 275,
  notes: 'Call before delivery',
  is_fragile: false,
  can_be_opened: true,
  option_exchange: false,
  created_at: '2026-07-31T14:18:03+00:00',
  updated_at: '2026-08-01T07:02:44+00:00',
};

const CREATED_ORDER = {
  ...ORDER_LIST_ITEM,
  id: 1842,
  tracking_number: 'SPD-2026-771048',
  order_number: 'SPD-2026-771048',
  tracking_url: 'https://app.speedzone.ma/orders/SPD-2026-771048',
  status: 'CREATED',
  status_label: 'Created',
  status_color: 'secondary',
  store: undefined,
  driver_id: null,
  assigned_at: null,
  created_at: '2026-08-01T09:24:11+00:00',
  updated_at: '2026-08-01T09:24:11+00:00',
};

// The store relation is not eager-loaded on create, so it never reaches the
// payload. Dropping the key keeps the sample honest.
delete CREATED_ORDER.store;

const STATUS_TIMELINE = [
  {
    id: 5120,
    status: 'CREATED',
    status_label: 'Created',
    status_color: 'secondary',
    status_icon: 'ri-file-add-line',
    comment: 'Order created.',
    is_system: false,
    created_at: '2026-07-31T14:18:03+00:00',
    user: SELLER,
  },
  {
    id: 5131,
    status: 'PICKED_UP',
    status_label: 'Picked up',
    status_color: 'primary',
    status_icon: 'ri-hand-coin-line',
    comment: null,
    is_system: false,
    created_at: '2026-08-01T06:41:20+00:00',
    user: { ...SELLER, id: 88, name: 'Youssef Amrani', role: 'Driver', role_label: 'Driver' },
  },
];

const PICKUP_REQUEST = {
  id: 304,
  reference: 'PU-2026-000304',
  status: 'WAITING_FOR_PICKUP',
  status_label: 'Waiting for pickup',
  status_color: 'info',
  pickup_address: '22 Boulevard Zerktouni, Casablanca',
  number_of_packages: 3,
  total_orders_amount: 740,
  notes: 'Ready from 2pm',
  created_by: 42,
  assigned_to: null,
  created_at: '2026-08-01T09:31:57+00:00',
  updated_at: '2026-08-01T09:31:57+00:00',
};

const PAGINATION = (path, total, perPage = 25) => ({
  links: {
    first: `${path}?page=1`,
    last: `${path}?page=${Math.max(1, Math.ceil(total / perPage))}`,
    prev: null,
    next: total > perPage ? `${path}?page=2` : null,
  },
  meta: {
    current_page: 1,
    from: 1,
    last_page: Math.max(1, Math.ceil(total / perPage)),
    path,
    per_page: perPage,
    to: Math.min(perPage, total),
    total,
  },
});

const UNAUTHORIZED = { message: 'Unauthenticated.' };

const FORBIDDEN = { message: 'This action is unauthorized.' };

const NOT_FOUND = { message: 'No query results for model [App\\Models\\Order] 9999.' };

const VALIDATION_ERROR = {
  message: 'The customer phone field is required. (and 1 more error)',
  errors: {
    customer_phone: ['The customer phone field is required.'],
    sector_id: ['The selected sector does not belong to the chosen city or is inactive.'],
  },
};

const TOO_MANY_REQUESTS = { message: 'Too Many Attempts.' };

/** Query parameters shared by every paginated collection. */
const PAGINATION_PARAMS = [
  { name: 'page', type: 'integer', desc: 'filters.page', default: '1' },
  { name: 'per_page', type: 'integer', desc: 'filters.per_page', default: '25' },
];

export const GROUPS = [
  { id: 'getting-started', labelKey: 'api_docs.nav.getting_started', icon: 'ri-rocket-2-line' },
  { id: 'orders', labelKey: 'api_docs.nav.orders', icon: 'ri-shopping-bag-3-line' },
  { id: 'pickups', labelKey: 'api_docs.nav.pickups', icon: 'ri-hand-heart-line' },
  { id: 'reference', labelKey: 'api_docs.nav.reference', icon: 'ri-book-2-line' },
];

/** Narrative sections, rendered before the endpoint reference of their group. */
export const SECTIONS = [
  { id: 'introduction', group: 'getting-started', icon: 'ri-compass-3-line' },
  { id: 'authentication', group: 'getting-started', icon: 'ri-key-2-line' },
  { id: 'stores', group: 'getting-started', icon: 'ri-store-2-line' },
  { id: 'errors', group: 'getting-started', icon: 'ri-error-warning-line' },
  { id: 'rate_limits', group: 'getting-started', icon: 'ri-speed-up-line' },
  // Grouped with the primer rather than with the order endpoints: ApiGuide
  // renders every narrative section up front, and the nav has to follow the
  // reading order of the page.
  { id: 'statuses', group: 'getting-started', icon: 'ri-flow-chart' },
];

export const ENDPOINTS = [
  {
    id: 'orders-list',
    group: 'orders',
    i18n: 'orders_list',
    method: 'GET',
    path: '/api/orders',
    permission: 'orders.read.own',
    sampleQuery: { status: 'OUT_FOR_DELIVERY', per_page: 25 },
    query: [
      ...PAGINATION_PARAMS,
      { name: 'tracking_number', type: 'string', desc: 'filters.tracking_number' },
      { name: 'customer_name', type: 'string', desc: 'filters.customer_name' },
      { name: 'customer_phone', type: 'string', desc: 'filters.customer_phone' },
      { name: 'status', type: 'string | string[]', desc: 'filters.status' },
      { name: 'status_group', type: 'string', desc: 'filters.status_group' },
      { name: 'payment_method', type: 'string | string[]', desc: 'filters.payment_method' },
      { name: 'city_id', type: 'integer', desc: 'filters.city_id' },
      { name: 'sector_id', type: 'integer', desc: 'filters.sector_id' },
      { name: 'created_from', type: 'date', desc: 'filters.created_from' },
      { name: 'created_to', type: 'date', desc: 'filters.created_to' },
      { name: 'delivery_from', type: 'date', desc: 'filters.delivery_from' },
      { name: 'delivery_to', type: 'date', desc: 'filters.delivery_to' },
      { name: 'is_fragile', type: 'boolean', desc: 'filters.is_fragile' },
      { name: 'can_be_opened', type: 'boolean', desc: 'filters.can_be_opened' },
      { name: 'sort', type: 'string', desc: 'filters.sort', default: 'created_at' },
      { name: 'direction', type: 'string', desc: 'filters.direction', default: 'desc' },
    ],
    notes: ['orders_list_partner'],
    responses: [
      { status: 200, sample: { data: [ORDER_LIST_ITEM], ...PAGINATION('https://app.speedzone.ma/api/orders', 137) } },
      { status: 401, sample: UNAUTHORIZED },
      { status: 429, sample: TOO_MANY_REQUESTS },
    ],
  },

  {
    id: 'orders-create',
    group: 'orders',
    i18n: 'orders_create',
    method: 'POST',
    path: '/api/orders',
    permission: 'orders.create',
    body: [
      { name: 'customer_first_name', type: 'string', required: true, desc: 'fields.customer_first_name', rule: 'max:255' },
      { name: 'customer_last_name', type: 'string', required: true, desc: 'fields.customer_last_name', rule: 'max:255' },
      { name: 'customer_phone', type: 'string', required: true, desc: 'fields.customer_phone', rule: 'max:50' },
      { name: 'customer_address', type: 'string', required: true, desc: 'fields.customer_address', rule: 'max:1000' },
      { name: 'city_id', type: 'integer', required: true, desc: 'fields.city_id' },
      { name: 'sector_id', type: 'integer', required: true, desc: 'fields.sector_id' },
      { name: 'payment_method', type: 'string', required: true, desc: 'fields.payment_method', default: 'CASH' },
      { name: 'order_amount', type: 'number', required: 'conditional', desc: 'fields.order_amount' },
      { name: 'order_value', type: 'number', desc: 'fields.order_value' },
      { name: 'delivery_price', type: 'number', desc: 'fields.delivery_price' },
      { name: 'delivery_included', type: 'boolean', desc: 'fields.delivery_included', default: 'false' },
      { name: 'notes', type: 'string', desc: 'fields.notes', rule: 'max:2000' },
      { name: 'is_fragile', type: 'boolean', desc: 'fields.is_fragile', default: 'false' },
      { name: 'can_be_opened', type: 'boolean', desc: 'fields.can_be_opened', default: 'false' },
      { name: 'option_exchange', type: 'boolean', desc: 'fields.option_exchange', default: 'false' },
    ],
    request: {
      customer_first_name: 'Fatima Zohra',
      customer_last_name: 'Bennani',
      customer_phone: '0612345678',
      customer_address: '15 Rue de Fès, Résidence Al Manar, Apt 4',
      city_id: 7,
      sector_id: 21,
      payment_method: 'CASH',
      order_amount: 250,
      delivery_included: false,
      notes: 'Call before delivery',
      is_fragile: false,
      can_be_opened: true,
      option_exchange: false,
    },
    notes: ['orders_create_amount', 'orders_create_delivery_included', 'orders_create_sector'],
    responses: [
      { status: 201, sample: { data: CREATED_ORDER } },
      { status: 422, sample: VALIDATION_ERROR },
      { status: 403, sample: FORBIDDEN },
    ],
  },

  {
    id: 'orders-show',
    group: 'orders',
    i18n: 'orders_show',
    method: 'GET',
    path: '/api/orders/{order}',
    permission: 'orders.read.own',
    pathParams: [{ name: 'order', type: 'integer', required: true, desc: 'filters.order_id', example: '1841' }],
    responses: [
      {
        status: 200,
        sample: {
          data: {
            ...ORDER_LIST_ITEM,
            pickup_request: {
              id: 304,
              reference: 'PU-2026-000304',
              status: 'PICKED_UP',
              status_label: 'Picked up',
              status_color: 'primary',
              pickup_address: '22 Boulevard Zerktouni, Casablanca',
              created_at: '2026-07-31T16:02:11+00:00',
              created_by: SELLER,
              assigned_driver: null,
            },
            active_transfer: null,
            status_history: STATUS_TIMELINE,
            change_history: [],
          },
        },
      },
      { status: 403, sample: FORBIDDEN },
      { status: 404, sample: NOT_FOUND },
    ],
  },

  {
    id: 'orders-track',
    group: 'orders',
    i18n: 'orders_track',
    method: 'GET',
    path: '/api/orders/track/{tracking_number}',
    permission: 'orders.read.own',
    pathParams: [
      { name: 'tracking_number', type: 'string', required: true, desc: 'filters.tracking_path', example: 'SPD-2026-583920' },
    ],
    responses: [
      { status: 200, sample: { data: { ...ORDER_LIST_ITEM, status_history: STATUS_TIMELINE } } },
      { status: 404, sample: { message: 'No query results for model [App\\Models\\Order].' } },
    ],
  },

  {
    id: 'orders-update',
    group: 'orders',
    i18n: 'orders_update',
    method: 'PUT',
    path: '/api/orders/{order}',
    permission: 'orders.update.own',
    pathParams: [{ name: 'order', type: 'integer', required: true, desc: 'filters.order_id', example: '1842' }],
    body: [
      { name: 'customer_first_name', type: 'string', desc: 'fields.customer_first_name' },
      { name: 'customer_last_name', type: 'string', desc: 'fields.customer_last_name' },
      { name: 'customer_phone', type: 'string', desc: 'fields.customer_phone' },
      { name: 'customer_address', type: 'string', desc: 'fields.customer_address' },
      { name: 'city_id', type: 'integer', desc: 'fields.city_id' },
      { name: 'sector_id', type: 'integer', desc: 'fields.sector_id' },
      { name: 'payment_method', type: 'string', desc: 'fields.payment_method' },
      { name: 'order_amount', type: 'number', desc: 'fields.order_amount' },
      { name: 'order_value', type: 'number', desc: 'fields.order_value' },
      { name: 'delivery_price', type: 'number', desc: 'fields.delivery_price' },
      { name: 'delivery_included', type: 'boolean', desc: 'fields.delivery_included' },
      { name: 'notes', type: 'string', desc: 'fields.notes' },
      { name: 'is_fragile', type: 'boolean', desc: 'fields.is_fragile' },
      { name: 'can_be_opened', type: 'boolean', desc: 'fields.can_be_opened' },
      { name: 'option_exchange', type: 'boolean', desc: 'fields.option_exchange' },
    ],
    request: {
      customer_phone: '0698765432',
      customer_address: '8 Avenue Mohammed V, Appartement 12',
      notes: 'Leave with the concierge',
    },
    notes: ['orders_update_status', 'orders_update_sector'],
    responses: [
      { status: 200, sample: { data: { ...CREATED_ORDER, customer: { ...CREATED_ORDER.customer, phone: '0698765432', address: '8 Avenue Mohammed V, Appartement 12' }, notes: 'Leave with the concierge' } } },
      { status: 403, sample: FORBIDDEN },
      { status: 422, sample: { message: 'The selected sector does not belong to the chosen city.', errors: { sector_id: ['The selected sector does not belong to the chosen city.'] } } },
    ],
  },

  {
    id: 'orders-delete',
    group: 'orders',
    i18n: 'orders_delete',
    method: 'DELETE',
    path: '/api/orders/{order}',
    permission: 'orders.delete.own',
    pathParams: [{ name: 'order', type: 'integer', required: true, desc: 'filters.order_id', example: '1842' }],
    notes: ['orders_delete_scope'],
    responses: [
      { status: 204, sample: null },
      { status: 403, sample: FORBIDDEN },
      { status: 404, sample: NOT_FOUND },
    ],
  },

  {
    id: 'orders-tracking',
    group: 'orders',
    i18n: 'orders_tracking',
    method: 'GET',
    path: '/api/orders/{order}/tracking',
    permission: 'orders.read.own',
    pathParams: [{ name: 'order', type: 'integer', required: true, desc: 'filters.order_id', example: '1841' }],
    responses: [
      { status: 200, sample: { data: STATUS_TIMELINE } },
      { status: 403, sample: FORBIDDEN },
    ],
  },

  {
    id: 'orders-pdf',
    group: 'orders',
    i18n: 'orders_pdf',
    method: 'GET',
    path: '/api/orders/{order}/pdf',
    permission: 'orders.print',
    binary: true,
    pathParams: [{ name: 'order', type: 'integer', required: true, desc: 'filters.order_id', example: '1841' }],
    notes: ['pdf_accept'],
    responses: [
      { status: 200, contentType: 'application/pdf', raw: '%PDF-1.7\n…binary…' },
      { status: 403, sample: FORBIDDEN },
    ],
  },

  {
    id: 'pickups-list',
    group: 'pickups',
    i18n: 'pickups_list',
    method: 'GET',
    path: '/api/pickup-requests',
    permission: 'pickup_requests.read.own',
    query: [
      ...PAGINATION_PARAMS,
      { name: 'status', type: 'string', desc: 'filters.pickup_status' },
    ],
    responses: [
      { status: 200, sample: { data: [PICKUP_REQUEST], ...PAGINATION('https://app.speedzone.ma/api/pickup-requests', 12) } },
      { status: 401, sample: UNAUTHORIZED },
    ],
  },

  {
    id: 'pickups-create',
    group: 'pickups',
    i18n: 'pickups_create',
    method: 'POST',
    path: '/api/pickup-requests',
    permission: 'pickup_requests.create',
    body: [
      { name: 'order_ids', type: 'integer[]', required: true, desc: 'fields.order_ids', rule: 'min:1' },
      { name: 'pickup_address', type: 'string', required: true, desc: 'fields.pickup_address' },
      { name: 'notes', type: 'string', desc: 'fields.pickup_notes', rule: 'max:2000' },
    ],
    request: {
      order_ids: [1842, 1843, 1844],
      pickup_address: '22 Boulevard Zerktouni, Casablanca',
      notes: 'Ready from 2pm',
    },
    notes: ['pickups_create_address'],
    responses: [
      { status: 201, sample: { data: PICKUP_REQUEST } },
      {
        status: 422,
        sample: {
          message: 'One or more selected orders are invalid or not eligible for pickup.',
          errors: { 'order_ids.0': ['One or more selected orders are invalid or not eligible for pickup.'] },
        },
      },
    ],
  },

  {
    id: 'pickups-show',
    group: 'pickups',
    i18n: 'pickups_show',
    method: 'GET',
    path: '/api/pickup-requests/{pickup_request}',
    permission: 'pickup_requests.read.own',
    pathParams: [
      { name: 'pickup_request', type: 'integer', required: true, desc: 'filters.pickup_id', example: '304' },
    ],
    responses: [
      {
        status: 200,
        sample: {
          data: {
            ...PICKUP_REQUEST,
            creator: SELLER,
            assignee: null,
            orders: [ORDER_LIST_ITEM],
            status_history: [],
          },
        },
      },
      { status: 403, sample: FORBIDDEN },
    ],
  },

  {
    id: 'cities-list',
    group: 'reference',
    i18n: 'cities_list',
    method: 'GET',
    path: '/api/cities',
    permission: 'cities.read',
    query: [
      ...PAGINATION_PARAMS,
      { name: 'search', type: 'string', desc: 'filters.city_search' },
    ],
    responses: [
      {
        status: 200,
        sample: {
          data: [
            { id: 7, name: 'Tanger', code: 'TNG', region: 'Tanger-Tétouan-Al Hoceïma', is_active: true, created_at: '2026-01-12T10:00:00+00:00', updated_at: '2026-01-12T10:00:00+00:00' },
            { id: 3, name: 'Casablanca', code: 'CAS', region: 'Casablanca-Settat', is_active: true, created_at: '2026-01-12T10:00:00+00:00', updated_at: '2026-01-12T10:00:00+00:00' },
          ],
          ...PAGINATION('https://app.speedzone.ma/api/cities', 48),
        },
      },
    ],
  },

  {
    id: 'city-sectors',
    group: 'reference',
    i18n: 'city_sectors',
    method: 'GET',
    path: '/api/cities/{city}/sectors',
    permission: 'cities.read',
    pathParams: [{ name: 'city', type: 'integer', required: true, desc: 'filters.city_path', example: '7' }],
    responses: [
      {
        status: 200,
        sample: {
          data: [
            { id: 21, city_id: 7, name: 'Centre Ville', delivery_price: 25, return_price: 15, is_active: true, created_at: '2026-01-12T10:00:00+00:00', updated_at: '2026-01-12T10:00:00+00:00' },
            { id: 22, city_id: 7, name: 'Malabata', delivery_price: 30, return_price: 18, is_active: true, created_at: '2026-01-12T10:00:00+00:00', updated_at: '2026-01-12T10:00:00+00:00' },
          ],
        },
      },
    ],
  },

  {
    id: 'sectors-list',
    group: 'reference',
    i18n: 'sectors_list',
    method: 'GET',
    path: '/api/sectors',
    permission: 'sectors.read',
    query: [
      ...PAGINATION_PARAMS,
      { name: 'city_id', type: 'integer', desc: 'filters.city_id' },
    ],
    responses: [
      {
        status: 200,
        sample: {
          data: [
            { id: 21, city_id: 7, name: 'Centre Ville', delivery_price: 25, return_price: 15, is_active: true, created_at: '2026-01-12T10:00:00+00:00', updated_at: '2026-01-12T10:00:00+00:00' },
          ],
          ...PAGINATION('https://app.speedzone.ma/api/sectors', 214),
        },
      },
    ],
  },

  {
    id: 'user-me',
    group: 'reference',
    i18n: 'user_me',
    method: 'GET',
    path: '/api/user',
    responses: [
      {
        status: 200,
        sample: {
          id: 42,
          first_name: 'Karim',
          last_name: 'El Alaoui',
          name: 'Atlas Concept Store',
          email: 'contact@atlas-concept.ma',
          phone_number: '0522334455',
          city_id: 3,
          created_at: '2026-01-20T08:11:04.000000Z',
          updated_at: '2026-07-30T18:44:29.000000Z',
        },
      },
      { status: 401, sample: UNAUTHORIZED },
    ],
  },
];

/** Endpoints of a group, in catalogue order. */
export function endpointsOf(groupId) {
  return ENDPOINTS.filter((endpoint) => endpoint.group === groupId);
}

/** Narrative sections of a group, in catalogue order. */
export function sectionsOf(groupId) {
  return SECTIONS.filter((section) => section.group === groupId);
}

const METHOD_VARIANTS = {
  GET: 'success',
  POST: 'primary',
  PUT: 'warning',
  PATCH: 'warning',
  DELETE: 'danger',
};

export function methodVariant(method) {
  return METHOD_VARIANTS[method] ?? 'secondary';
}

/** Substitutes `{placeholders}` with the example values declared on the endpoint. */
function resolvePath(endpoint) {
  return (endpoint.pathParams ?? []).reduce(
    (path, param) => path.replace(`{${param.name}}`, param.example ?? `{${param.name}}`),
    endpoint.path,
  );
}

function fullUrl(endpoint, baseUrl) {
  const query = endpoint.sampleQuery ? `?${new URLSearchParams(endpoint.sampleQuery)}` : '';

  return `${baseUrl}${resolvePath(endpoint)}${query}`;
}

/**
 * Request headers for a sample call, in the order they should be read:
 * credentials, negotiation, then store targeting.
 */
function sampleHeaders(endpoint, { token, storeId }) {
  const headers = [['Authorization', `Bearer ${token}`]];

  if (!endpoint.binary) {
    headers.push(['Accept', 'application/json']);
  }

  if (endpoint.request) {
    headers.push(['Content-Type', 'application/json']);
  }

  if (storeId) {
    headers.push(['X-Store-Id', String(storeId)]);
  }

  return headers;
}

/** Renders a JSON-ish value as a PHP literal, so the PHP sample reads natively. */
function toPhpLiteral(value, depth = 1) {
  const pad = '    '.repeat(depth);
  const closingPad = '    '.repeat(depth - 1);

  if (value === null) return 'null';
  if (typeof value === 'boolean') return value ? 'true' : 'false';
  if (typeof value === 'number') return String(value);
  if (typeof value === 'string') return `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;

  if (Array.isArray(value)) {
    const items = value.map((item) => `${pad}${toPhpLiteral(item, depth + 1)},`);

    return `[\n${items.join('\n')}\n${closingPad}]`;
  }

  const entries = Object.entries(value).map(
    ([key, item]) => `${pad}'${key}' => ${toPhpLiteral(item, depth + 1)},`,
  );

  return `[\n${entries.join('\n')}\n${closingPad}]`;
}

function curlSample(endpoint, options) {
  const lines = [`curl --request ${endpoint.method} \\`, `  --url '${fullUrl(endpoint, options.baseUrl)}' \\`];

  sampleHeaders(endpoint, options).forEach(([name, value]) => {
    lines.push(`  --header '${name}: ${value}' \\`);
  });

  if (endpoint.request) {
    lines.push(`  --data '${JSON.stringify(endpoint.request, null, 2)}'`);
  } else if (endpoint.binary) {
    lines.push(`  --output label.pdf`);
  }

  // Strip the trailing continuation left by the last header when there is no body.
  return lines.join('\n').replace(/ \\$/, '');
}

function javascriptSample(endpoint, options) {
  const headers = sampleHeaders(endpoint, options)
    .map(([name, value]) => `    ${/^[A-Za-z]+$/.test(name) ? name : `'${name}'`}: '${value}',`)
    .join('\n');

  const body = endpoint.request
    ? `  body: JSON.stringify(${JSON.stringify(endpoint.request, null, 2).replace(/\n/g, '\n  ')}),\n`
    : '';

  const tail = endpoint.binary
    ? "const blob = await response.blob();"
    : endpoint.method === 'DELETE'
      ? 'if (!response.ok) throw new Error(await response.text());'
      : 'const { data } = await response.json();';

  return `const response = await fetch('${fullUrl(endpoint, options.baseUrl)}', {
  method: '${endpoint.method}',
  headers: {
${headers}
  },
${body}});

${tail}`;
}

function phpSample(endpoint, options) {
  const headers = sampleHeaders(endpoint, options)
    .map(([name, value]) => `        '${name}: ${value}',`)
    .join('\n');

  const body = endpoint.request
    ? `\n    CURLOPT_POSTFIELDS     => json_encode(${toPhpLiteral(endpoint.request, 2)}),`
    : '';

  const tail = endpoint.binary
    ? "file_put_contents('label.pdf', $response);"
    : "$payload = json_decode($response, true);";

  return `<?php

$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL            => '${fullUrl(endpoint, options.baseUrl)}',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => '${endpoint.method}',
    CURLOPT_HTTPHEADER     => [
${headers}
    ],${body}
]);

$response = curl_exec($curl);
$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

${tail}`;
}

export const LANGUAGES = [
  { id: 'curl', label: 'cURL', highlight: 'bash', build: curlSample },
  { id: 'javascript', label: 'JavaScript', highlight: 'javascript', build: javascriptSample },
  { id: 'php', label: 'PHP', highlight: 'php', build: phpSample },
];

/**
 * Ready-to-paste snippets for one endpoint, keyed by language id.
 *
 * @param {object} endpoint
 * @param {{baseUrl: string, token: string, storeId: number|string|null}} options
 */
export function buildSamples(endpoint, options) {
  return Object.fromEntries(
    LANGUAGES.map((language) => [language.id, language.build(endpoint, options)]),
  );
}
