import { ArrowDown, ArrowUp, ArrowUpDown } from 'lucide-react';
import type { SortDir } from './useSortableRows';

/**
 * SortHeader — clickable <th> that shows the current sort direction
 * and cycles asc → desc → off on click. Shared across admin tables
 * (JORD-86) so every page presents the same sort affordance.
 */
export function SortHeader<K extends string>(props: {
  label:    string;
  k:        K;
  sortKey:  string | null;
  sortDir:  SortDir;
  onToggle: (k: K) => void;
  align?:   'start' | 'end';
  className?: string;
}) {
  const active = props.sortKey === props.k && props.sortDir !== null;
  const Icon = !active
    ? ArrowUpDown
    : props.sortDir === 'asc' ? ArrowUp : ArrowDown;
  return (
    <th
      scope="col"
      className={`px-5 py-2 text-${props.align ?? 'start'} select-none ${props.className ?? ''}`}
      data-testid={`sort-header-${props.k}`}
    >
      <button
        type="button"
        onClick={() => props.onToggle(props.k)}
        aria-sort={
          !active ? 'none'
          : props.sortDir === 'asc' ? 'ascending' : 'descending'
        }
        className={`inline-flex items-center gap-1 text-xs uppercase font-semibold ${
          active ? 'text-jea-primary' : 'text-gray-600 hover:text-gray-800'
        }`}
      >
        {props.label}
        <Icon size={11} aria-hidden="true" className="opacity-70" />
      </button>
    </th>
  );
}
