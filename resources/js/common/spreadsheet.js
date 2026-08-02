/**
 * Spreadsheet reading helpers shared by the bulk import wizard.
 *
 * Both parsers are loaded on demand: SheetJS alone weighs more than the whole
 * order module, and the vast majority of sessions never open an import screen.
 */

export const ACCEPTED_EXTENSIONS = ['.csv', '.xlsx', '.xls'];
export const ACCEPTED_MIME = [
  'text/csv',
  'application/vnd.ms-excel',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];

/** Largest file we accept, in bytes. */
export const MAX_FILE_SIZE = 5 * 1024 * 1024;

export function extensionOf(fileName) {
  const dot = String(fileName ?? '').lastIndexOf('.');

  return dot === -1 ? '' : fileName.slice(dot).toLowerCase();
}

export function isSupportedFile(file) {
  return !!file && ACCEPTED_EXTENSIONS.includes(extensionOf(file.name));
}

/**
 * Decode a CSV buffer.
 *
 * Excel on Windows still writes CSV as windows-1252, which turns every accented
 * character into a replacement glyph when decoded as UTF-8. Strict decoding
 * tells the two apart without guessing.
 */
function decodeCsv(buffer) {
  try {
    return new TextDecoder('utf-8', { fatal: true }).decode(buffer);
  } catch {
    return new TextDecoder('windows-1252').decode(buffer);
  }
}

async function readCsv(file) {
  const [{ default: Papa }, buffer] = await Promise.all([
    import('papaparse'),
    file.arrayBuffer(),
  ]);

  const { data } = Papa.parse(decodeCsv(buffer).replace(/^\uFEFF/, ''), {
    header: false,
    skipEmptyLines: 'greedy',
  });

  return data;
}

async function readWorkbook(file) {
  const [XLSX, buffer] = await Promise.all([import('xlsx'), file.arrayBuffer()]);

  const workbook = XLSX.read(buffer, { type: 'array' });
  const sheet = workbook.Sheets[workbook.SheetNames[0]];

  if (!sheet) {
    return [];
  }

  // `raw: false` keeps cells as the strings the user sees in Excel, which
  // matters for phone numbers: read raw they arrive as 612345678 and lose the
  // leading zero long before validation can normalise them.
  return XLSX.utils.sheet_to_json(sheet, {
    header: 1,
    raw: false,
    defval: '',
    blankrows: false,
  });
}

/**
 * Read a CSV/XLS/XLSX file into a header row and value rows.
 *
 * Rows stay positional arrays rather than objects keyed by header: files
 * exported from marketplaces routinely repeat a column name, and an object
 * would silently drop one of them.
 *
 * @param {File} file
 * @returns {Promise<{headers: string[], rows: string[][]}>}
 */
export async function readSpreadsheet(file) {
  const matrix = extensionOf(file.name) === '.csv' ? await readCsv(file) : await readWorkbook(file);

  const nonEmpty = matrix.filter((row) =>
    Array.isArray(row) && row.some((cell) => String(cell ?? '').trim() !== '')
  );

  if (nonEmpty.length === 0) {
    return { headers: [], rows: [] };
  }

  const [headerRow, ...rest] = nonEmpty;
  const headers = headerRow.map((cell, index) => {
    const label = String(cell ?? '').trim();

    return label === '' ? `Colonne ${index + 1}` : label;
  });

  const rows = rest.map((row) => headers.map((_, index) => String(row[index] ?? '').trim()));

  return { headers, rows };
}

/**
 * Build and download the example workbook.
 *
 * @param {Array<{header: string, example: string}>} columns
 * @param {string} fileName
 */
export async function downloadTemplate(columns, fileName = 'template_commandes.xlsx') {
  const XLSX = await import('xlsx');

  const sheet = XLSX.utils.aoa_to_sheet([
    columns.map((column) => column.header),
    columns.map((column) => column.example),
  ]);

  sheet['!cols'] = columns.map((column) => ({
    wch: Math.max(14, column.header.length + 2),
  }));

  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, sheet, 'Commandes');
  XLSX.writeFile(workbook, fileName);
}
