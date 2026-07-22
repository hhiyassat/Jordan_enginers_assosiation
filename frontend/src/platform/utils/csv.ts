/**
 * CSV export helper — JORD-86 (frontend polish).
 *
 * Runs entirely in the browser: build the CSV in memory, create a
 * blob, trigger a download via a hidden <a>. No backend endpoint,
 * so the exported set is exactly what the admin currently sees
 * (filtered + sorted) rather than the full server-side collection.
 * This matches the "export what I'm looking at" expectation people
 * have from admin grids.
 *
 * We ship a BOM at the top so Excel opens Arabic content without
 * mojibake on Windows locales.
 */

export interface CsvColumn<T> {
  header: string;
  /** Value extractor. Return `null | undefined` for empty cell. */
  get: (row: T) => string | number | null | undefined;
}

function escape(value: string | number | null | undefined): string {
  if (value === null || value === undefined) return '';
  const s = String(value);
  // Excel-safe: quote if the cell contains comma / quote / newline.
  if (/[",\n\r]/.test(s)) return `"${s.replace(/"/g, '""')}"`;
  return s;
}

export function toCsv<T>(rows: T[], columns: CsvColumn<T>[]): string {
  const head = columns.map(c => escape(c.header)).join(',');
  const body = rows.map(r => columns.map(c => escape(c.get(r))).join(',')).join('\n');
  return `${head}\n${body}`;
}

export function downloadCsv<T>(filename: string, rows: T[], columns: CsvColumn<T>[]): void {
  const csv = toCsv(rows, columns);
  // ﻿ BOM keeps Excel happy with UTF-8 Arabic.
  const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename.endsWith('.csv') ? filename : `${filename}.csv`;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}
