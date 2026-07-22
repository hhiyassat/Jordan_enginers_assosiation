import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
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

/**
 * JORD-54: the inline required/optional toggle. Consumers that pass
 * onToggleRequired get an interactive checkbox; consumers that don't
 * (NewService creation preview, applicant view) still get the read-only
 * badge behavior above.
 */
describe('DocumentPreviewCard — inline required toggle (JORD-54)', () => {
  it('renders no toggle when onToggleRequired is not passed', () => {
    render(<DocumentPreviewCard doc={doc()} />);
    expect(screen.queryByRole('checkbox')).toBeNull();
  });

  it('renders a checked checkbox when doc.required=true and onToggleRequired is passed', () => {
    const spy = vi.fn();
    render(<DocumentPreviewCard doc={doc({ required: true })} onToggleRequired={spy} />);
    const box = screen.getByRole('checkbox');
    expect(box).toBeChecked();
    // Wording confirms the enforcement contract so admins know what
    // switching this flag actually does.
    expect(screen.getByText(/يمنع تجاوز المرحلة/)).toBeInTheDocument();
  });

  it('renders an unchecked checkbox when doc.required=false and onToggleRequired is passed', () => {
    const spy = vi.fn();
    render(<DocumentPreviewCard doc={doc({ required: false })} onToggleRequired={spy} />);
    const box = screen.getByRole('checkbox');
    expect(box).not.toBeChecked();
    expect(screen.getByText(/اختياري/)).toBeInTheDocument();
  });

  it('calls onToggleRequired with (docId, next) when the box is clicked', async () => {
    const spy = vi.fn();
    render(<DocumentPreviewCard doc={doc({ id: 'commercial_register', required: false })} onToggleRequired={spy} />);
    await userEvent.click(screen.getByRole('checkbox'));
    expect(spy).toHaveBeenCalledWith('commercial_register', true);
  });

  it('swaps the badge for the interactive toggle in editable mode (no duplicate UI)', () => {
    // If both the plain "إلزامي" badge and the toggle rendered
    // simultaneously the admin would see conflicting UI when the flag
    // changes. Only one should be visible in editable mode.
    render(<DocumentPreviewCard doc={doc({ required: true })} onToggleRequired={() => {}} />);
    const badges = screen.queryAllByText('إلزامي');
    expect(badges).toHaveLength(0);
  });
});
