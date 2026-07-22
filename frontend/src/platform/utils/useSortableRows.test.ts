import { describe, it, expect } from 'vitest';
import { renderHook, act } from '@testing-library/react';
import { useSortableRows } from './useSortableRows';

interface Row { id: number; name: string; amount: number | null; date: Date; }

const rows: Row[] = [
  { id: 1, name: 'charlie', amount: 30,   date: new Date('2026-03-01') },
  { id: 2, name: 'alpha',   amount: 10,   date: new Date('2026-01-01') },
  { id: 3, name: 'bravo',   amount: null, date: new Date('2026-02-01') },
];

const columns = [
  { key: 'name'   as const, get: (r: Row) => r.name },
  { key: 'amount' as const, get: (r: Row) => r.amount },
  { key: 'date'   as const, get: (r: Row) => r.date },
];

describe('useSortableRows', () => {
  it('returns rows in original order until a sort is set', () => {
    const { result } = renderHook(() => useSortableRows(rows, columns));
    expect(result.current.sorted.map(r => r.id)).toEqual([1, 2, 3]);
  });

  it('sorts asc on toggle, desc on second toggle, off on third', () => {
    const { result } = renderHook(() => useSortableRows(rows, columns));
    act(() => result.current.toggle('name'));
    expect(result.current.sorted.map(r => r.id)).toEqual([2, 3, 1]); // alpha, bravo, charlie
    act(() => result.current.toggle('name'));
    expect(result.current.sorted.map(r => r.id)).toEqual([1, 3, 2]); // charlie, bravo, alpha
    act(() => result.current.toggle('name'));
    expect(result.current.sorted.map(r => r.id)).toEqual([1, 2, 3]); // original
  });

  it('sorts by number and by date correctly', () => {
    const { result } = renderHook(() => useSortableRows(rows, columns));
    act(() => result.current.toggle('amount'));
    // Nulls sink to the bottom on asc: 10, 30, null.
    expect(result.current.sorted.map(r => r.id)).toEqual([2, 1, 3]);

    act(() => result.current.toggle('amount'));
    act(() => result.current.toggle('amount')); // clear
    act(() => result.current.toggle('date'));
    expect(result.current.sorted.map(r => r.id)).toEqual([2, 3, 1]); // jan, feb, mar
  });

  it('honours the initial sort key and direction', () => {
    const { result } = renderHook(() => useSortableRows(rows, columns, 'name', 'desc'));
    expect(result.current.sorted.map(r => r.id)).toEqual([1, 3, 2]);
    expect(result.current.sortKey).toBe('name');
    expect(result.current.sortDir).toBe('desc');
  });
});
