import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { DynamicForm } from './DynamicForm';
import type { ServiceSchema } from '../types';

/**
 * JORD-48a: display_order controls the render position within a
 * section without repositioning entries in the schema-array.
 * Fields without display_order keep their original array position
 * (stable secondary sort).
 */

function schema(fields: Array<{ id: string; display_order?: number }>): ServiceSchema {
  return {
    service_code: 'x', name_ar: 'x', name_en: 'x',
    fields: fields.map(f => ({
      id: f.id, label_ar: f.id, label_en: f.id,
      type: 'text', required: false,
      display_order: f.display_order,
    })),
    documents: [],
    workflow: { stages: [] },
    fee: { type: 'fixed', amount: 0, currency: 'JOD' },
  } as unknown as ServiceSchema;
}

describe('DynamicForm — JORD-48a display_order', () => {
  it('renders in schema-array order when no display_order is set', () => {
    render(
      <DynamicForm
        schema={schema([{ id: 'first' }, { id: 'second' }, { id: 'third' }])}
        values={{}}
        onChange={() => {}}
      />
    );
    const labels = Array.from(document.querySelectorAll('label')).map(l => l.textContent);
    expect(labels).toEqual(['first', 'second', 'third']);
  });

  it('sorts by display_order ascending when set', () => {
    render(
      <DynamicForm
        schema={schema([
          { id: 'third',  display_order: 3 },
          { id: 'first',  display_order: 1 },
          { id: 'second', display_order: 2 },
        ])}
        values={{}}
        onChange={() => {}}
      />
    );
    const labels = Array.from(document.querySelectorAll('label')).map(l => l.textContent);
    expect(labels).toEqual(['first', 'second', 'third']);
  });

  it('places unordered fields after ordered ones (stable secondary sort)', () => {
    render(
      <DynamicForm
        schema={schema([
          { id: 'array_first' },
          { id: 'ordered_a', display_order: 1 },
          { id: 'array_second' },
        ])}
        values={{}}
        onChange={() => {}}
      />
    );
    const labels = Array.from(document.querySelectorAll('label')).map(l => l.textContent);
    expect(labels).toEqual(['ordered_a', 'array_first', 'array_second']);
  });
});
