import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { DocumentPreviewCard } from './DocumentPreviewCard';
import type { SchemaDocument } from '../types';

function doc(overrides: Partial<SchemaDocument> = {}): SchemaDocument {
  return {
    id: 'demolition_drawings',
    label_ar: 'مخططات الهدم',
    label_en: 'Demolition drawings',
    required: true,
    accept: ['pdf'],
    max_size_mb: 5,
    ...overrides,
  } as SchemaDocument;
}

describe('DocumentPreviewCard', () => {
  it('renders the document label and required badge for a required doc', () => {
    render(<DocumentPreviewCard doc={doc({ required: true })} />);
    expect(screen.getByText('مخططات الهدم')).toBeInTheDocument();
    expect(screen.getByText('إلزامي')).toBeInTheDocument();
  });

  it('shows accepted formats and size limit', () => {
    render(<DocumentPreviewCard doc={doc({ accept: ['pdf', 'dwg'], max_size_mb: 20 })} />);
    // Both accept list and max size are baked into one line
    expect(screen.getByText(/pdf, dwg.*20MB/)).toBeInTheDocument();
  });

  it('marks the upload button as disabled with the "معاينة" badge', () => {
    render(<DocumentPreviewCard doc={doc()} />);
    // The Arabic معاينة badge signals this is a preview surface.
    expect(screen.getByText('معاينة')).toBeInTheDocument();
    // The upload button exists but must be disabled — admins can't
    // accidentally attach a file to the schema editor.
    const button = screen.getByRole('button', { name: 'رفع الملف' });
    expect(button).toBeDisabled();
    expect(button).toHaveAttribute('aria-disabled', 'true');
  });

  it('omits the required badge for optional documents', () => {
    render(<DocumentPreviewCard doc={doc({ required: false })} />);
    expect(screen.queryByText('إلزامي')).toBeNull();
  });

  it('shows a "شرطي" badge when the document is conditional', () => {
    render(<DocumentPreviewCard doc={doc({ conditional: { field: 'x', value: 'y' } })} />);
    expect(screen.getByText('شرطي')).toBeInTheDocument();
  });
});
