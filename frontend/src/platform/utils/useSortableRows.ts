import { useMemo, useState } from 'react';

/**
 * Sortable-column state + memoized sorted rows — JORD-86.
 *
 * Keeps the sort key + direction in local component state so the
 * whole page doesn't need to hoist it to a store. Callers pass a
 * per-key extractor map so the hook stays type-safe without a
 * runtime shape.
 */
export type SortDir = 'asc' | 'desc' | null;

export interface SortableColumn<T, K extends string> {
  key: K;
  get: (row: T) => string | number | Date | null | undefined;
}

export function useSortableRows<T, K extends string>(
  rows: T[],
  columns: Array<SortableColumn<T, K>>,
  initialKey?: K,
  initialDir: SortDir = 'asc',
) {
  const [sortKey, setSortKey] = useState<K | null>(initialKey ?? null);
  const [sortDir, setSortDir] = useState<SortDir>(initialKey ? initialDir : null);

  const toggle = (key: K) => {
    if (sortKey !== key) { setSortKey(key); setSortDir('asc'); return; }
    // asc → desc → off (unsorted)
    if (sortDir === 'asc')  { setSortDir('desc'); return; }
    if (sortDir === 'desc') { setSortKey(null); setSortDir(null); return; }
    setSortDir('asc');
  };

  const sorted = useMemo(() => {
    if (!sortKey || !sortDir) return rows;
    const col = columns.find(c => c.key === sortKey);
    if (!col) return rows;
    const dir = sortDir === 'asc' ? 1 : -1;
    return [...rows].sort((a, b) => {
      const av = col.get(a);
      const bv = col.get(b);
      if (av == null && bv == null) return 0;
      if (av == null) return  1 * dir;
      if (bv == null) return -1 * dir;
      if (av instanceof Date && bv instanceof Date) {
        return (av.getTime() - bv.getTime()) * dir;
      }
      if (typeof av === 'number' && typeof bv === 'number') {
        return (av - bv) * dir;
      }
      return String(av).localeCompare(String(bv)) * dir;
    });
  }, [rows, columns, sortKey, sortDir]);

  return { sorted, sortKey, sortDir, toggle };
}
