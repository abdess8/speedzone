import invoicesRead from './definitions/invoices-read.json';
import ordersCreate from './definitions/orders-create.json';
import ordersImport from './definitions/orders-import.json';
import pickupsCreate from './definitions/pickups-create.json';
import returnsRequest from './definitions/returns-request.json';
import stockCatalog from './definitions/stock-catalog.json';
import stockInventory from './definitions/stock-inventory.json';
import stockShipment from './definitions/stock-shipment.json';
import storesManage from './definitions/stores-manage.json';
import teamMember from './definitions/team-member.json';

/**
 * Registry of the interactive guides shipped with the interface.
 *
 * A guide is a JSON file rather than a component: adding one must not require
 * writing Vue, and the steps have to stay readable by whoever writes the help
 * content. What the JSON carries is *structure* — the order of the steps, what
 * they point at, what they wait for. Wording lives in the `guides` translation
 * group and is resolved from the ids, so translating a guide never touches
 * this folder.
 *
 * The audience (which roles are offered the guide) is decided server side, in
 * `App\Support\Guides\GuideCatalog` — a guide walks through screens the reader
 * has to be allowed to open, and that rule cannot be enforced here.
 *
 * ── Guide shape ────────────────────────────────────────────────────────────
 *   key    matches the key in GuideCatalog; also the i18n suffix once dashes
 *          are replaced by underscores (`orders-import` → `orders_import`)
 *   route  Ziggy route name the tour needs to be standing on
 *   steps  ordered list, see below
 *
 * ── Step shape ─────────────────────────────────────────────────────────────
 *   id            i18n suffix: `guides.tours.<guide>.<id>.{title,body,hint}`
 *   kind          `welcome` | `step` (default) | `finish`
 *   target        CSS selector of the element to spotlight; omit for a step
 *                 that is a plain centered card
 *   pendingTarget spotlighted *instead of* `target` while `require` is unmet —
 *                 typically the control the reader has to press to make the
 *                 real target appear
 *   placement     desktop tooltip side: `auto` (default) | top | bottom | left
 *                 | right. Ignored on mobile, where every step is a sheet.
 *   padding       spotlight padding in pixels (default 8)
 *   require       gate: `{ signal, equals? }`. Until the signal matches, "Next"
 *                 is disabled and the step's `hint` is shown. Without `equals`
 *                 any truthy value satisfies it.
 *   autoAdvance   move to the next step by itself when the gate becomes
 *                 satisfied *while the step is on screen*
 *   interactive   let clicks reach the spotlighted element (default true)
 *   cta           `finish` steps only: `{ route }` for the closing call to
 *                 action, labelled by `guides.tours.<guide>.<id>.cta`
 *
 * Signals are published by the pages themselves — see `useGuideSignals` — with
 * one exception worth knowing: `app.route` is published by the tour host on
 * every navigation. A step that waits on it is how a guide walks the reader
 * from a list to a form and back without either screen knowing about guides.
 */
const DEFINITIONS = [
  ordersCreate,
  ordersImport,
  pickupsCreate,
  returnsRequest,
  invoicesRead,
  stockCatalog,
  stockShipment,
  stockInventory,
  storesManage,
  teamMember,
];

const BY_KEY = new Map(DEFINITIONS.map((guide) => [guide.key, guide]));

/**
 * @param {string} key
 * @returns {object|null}
 */
export function getGuide(key) {
  return BY_KEY.get(key) ?? null;
}

export function hasGuide(key) {
  return BY_KEY.has(key);
}

/**
 * Number of steps, for the Help Center card.
 *
 * @param {string} key
 * @returns {number}
 */
export function guideStepCount(key) {
  return getGuide(key)?.steps.length ?? 0;
}

/**
 * The i18n branch holding a guide's wording.
 *
 * PHP array keys cannot carry a dash without becoming awkward to read, so the
 * catalog key and the translation key differ by exactly this substitution.
 *
 * @param {string} key
 * @returns {string}
 */
export function guideI18nKey(key) {
  return String(key).replace(/-/g, '_');
}

export default { getGuide, hasGuide, guideStepCount, guideI18nKey };
