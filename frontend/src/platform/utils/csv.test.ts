import { describe, it, expect, vi } from 'vitest';
import { toCsv, downloadCsv, type CsvColumn } from './csv';

interface Row { id: number; name: string; amount: number | null; }

const cols: CsvColumn<Row>[] = [
  { header: 'ID',     get: r => r.id },
  { header: 'Name',   get: r => r.name },
  { header: 'Amount', get: r => r.amount },
];

describe('toCsv', () => {
  it('emits header + rows', () => {
    const csv = toCsv([{ id: 1, name: 'a', amount: 10 }], cols);
    expect(csv).toBe('ID,Name,Amount\n1,a,10');
  });

  it('quotes cells that contain commas or quotes', () => {
    const csv = toCsv([{ id: 2, name: 'name, with comma', amount: null }], cols);
    expect(csv).toContain('"name, with comma"');
  });

  it('escapes embedded double quotes by doubling them', () => {
    const csv = toCsv([{ id: 3, name: 'she said "hi"', amount: 0 }], cols);
    expect(csv).toContain('"she said ""hi"""');
  });

  it('renders null / undefined as empty cells (not the string "null")', () => {
    const csv = toCsv([{ id: 4, name: 'x', amount: null }], cols);
    expect(csv).toBe('ID,Name,Amount\n4,x,');
  });
});

describe('downloadCsv', () => {
  it('triggers a click on an <a> with a blob URL and the .csv extension', () => {
    const createObjectURL = vi.fn(() => 'blob:test');
    const revokeObjectURL = vi.fn();
    (globalThis.URL as unknown as { createObjectURL: typeof createObjectURL; revokeObjectURL: typeof revokeObjectURL }).createObjectURL = createObjectURL;
    (globalThis.URL as unknown as { createObjectURL: typeof createObjectURL; revokeObjectURL: typeof revokeObjectURL }).revokeObjectURL = revokeObjectURL;
    const clicks: string[] = [];
    const origCreate = document.createElement.bind(document);
    vi.spyOn(document, 'createElement').mockImplementation((tag: string) => {
      const el = origCreate(tag);
      if (tag === 'a') {
        el.click = () => clicks.push((el as HTMLAnchorElement).download);
      }
      return el;
    });
    downloadCsv('report', [{ id: 1, name: 'a', amount: 5 }], cols);
    expect(clicks).toEqual(['report.csv']);
    expect(createObjectURL).toHaveBeenCalled();
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:test');
    vi.restoreAllMocks();
  });
});
