/**
 * Value parsers shared by the bulk import wizards.
 *
 * Everything here exists because spreadsheets are written by people: an amount
 * arrives as "1 234,50" or "1,234.50" depending on the locale of whoever
 * exported it, and a yes/no column contains "Oui", "1", "TRUE" and "x" in the
 * same file. Guessing is the whole job — the alternative is rejecting a valid
 * catalog over a comma.
 */

const TRUE_TOKENS = ['1', 'oui', 'o', 'yes', 'y', 'true', 'vrai', 'x', 'v'];
const FALSE_TOKENS = ['0', 'non', 'n', 'no', 'false', 'faux', '-'];

/** Strip case, accents and punctuation so "Prénom", "prenom" and "PRE_NOM" compare equal. */
export function normalizeKey(value) {
  return String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLocaleLowerCase('fr')
    .replace(/[^a-z0-9]/g, '');
}

/**
 * Resolve a spreadsheet value against named records, ignoring case.
 *
 * "TANGER", "Tanger", "tanger" and "tangER" are the same token. When nothing
 * equals the token exactly, a unique prefix still wins ("Tanger" → "TANGER VILLE"
 * if that is the closest city) and a unique substring is the last resort
 * ("maarif" → "CASA - MAARIF"). Ambiguous contains-matches are left unresolved
 * rather than guessed — "ville" matching every "* VILLE" city would be worse
 * than asking the seller to pick.
 *
 * @template T
 * @param {T[]} records
 * @param {unknown} raw
 * @param {(record: T) => Array<string|null|undefined>} namesOf
 * @returns {T|null}
 */
export function findBestNamedMatch(records, raw, namesOf) {
  const needle = normalizeKey(raw);

  if (needle === '' || !Array.isArray(records) || records.length === 0) {
    return null;
  }

  const indexed = records.map((record) => ({
    record,
    keys: namesOf(record).map((name) => normalizeKey(name)).filter(Boolean),
  }));

  for (const entry of indexed) {
    if (entry.keys.includes(needle)) {
      return entry.record;
    }
  }

  if (needle.length < 4) {
    return null;
  }

  const prefixHits = [];
  const containsHits = [];

  for (const entry of indexed) {
    let prefixScore = 0;
    let contains = false;

    for (const key of entry.keys) {
      if (key.startsWith(needle)) {
        prefixScore = Math.max(prefixScore, 80 - Math.min(20, key.length - needle.length));
      } else if (needle.startsWith(key) && key.length >= 4) {
        prefixScore = Math.max(prefixScore, 50);
      } else if (key.includes(needle)) {
        contains = true;
      }
    }

    if (prefixScore > 0) {
      prefixHits.push({ record: entry.record, score: prefixScore, keyLen: Math.min(...entry.keys.map((key) => key.length)) });
    } else if (contains) {
      containsHits.push(entry.record);
    }
  }

  if (prefixHits.length === 1) {
    return prefixHits[0].record;
  }

  if (prefixHits.length > 1) {
    prefixHits.sort((a, b) => b.score - a.score || a.keyLen - b.keyLen);

    return prefixHits[0].record;
  }

  return containsHits.length === 1 ? containsHits[0] : null;
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
