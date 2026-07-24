import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom/vitest';
import { ManualReferenceIcon } from './ManualReferenceIcon';

// Mock the request helper — the component uses it to load + save references.
vi.mock('../../api/http', () => ({
  request: vi.fn(),
}));

// eslint-disable-next-line @typescript-eslint/no-explicit-any
const { request } = await import('../../api/http') as any;

// Mock react-i18next to a deterministic Arabic-locale variant.
vi.mock('react-i18next', () => ({
  useTranslation: () => ({ i18n: { language: 'ar' } }),
}));

const sampleRef = {
  id: 42,
  chapter: 'الباب الأول — الفصل الثاني',
  section: 'عقد الإشراف',
  page: 27,
  rule_type: 'deadline',
  text_ar: 'يكون عقد الإشراف ملزماً لكلا الطرفين مضافاً اليها ستة أشهر.',
  text_ar_original: 'يكون عقد الإشراف ملزماً لكلا الطرفين مضافاً اليها ستة أشهر.',
  jord_ticket: 'JORD-59',
  target_type: 'rule',
  target_id: 'supervision_window_months',
  target_service_code: null,
  edited_by: null,
  edited_at: null,
  needs_reimplementation: false,
};

describe('ManualReferenceIcon', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders as a small (?) button and does NOT fetch until opened', () => {
    render(<ManualReferenceIcon referenceId={42} />);
    expect(screen.getByRole('button', { name: /الأساس النظامي/i })).toBeInTheDocument();
    expect(request).not.toHaveBeenCalled();
  });

  it('opens popover on click, fetches the reference, renders the Arabic text', async () => {
    request.mockResolvedValueOnce({ reference: sampleRef });
    render(<ManualReferenceIcon referenceId={42} />);

    fireEvent.click(screen.getByRole('button', { name: /الأساس النظامي/i }));

    await waitFor(() => {
      expect(request).toHaveBeenCalledWith('GET', '/manual-references/42');
    });
    await waitFor(() => {
      expect(screen.getByText(/عقد الإشراف ملزماً/)).toBeInTheDocument();
    });
    expect(screen.getByText(/ص\.27/)).toBeInTheDocument();
  });

  it('hides edit button when canEdit=false', async () => {
    request.mockResolvedValueOnce({ reference: sampleRef });
    render(<ManualReferenceIcon referenceId={42} canEdit={false} />);
    fireEvent.click(screen.getByRole('button', { name: /الأساس النظامي/i }));
    await screen.findByText(/يكون عقد الإشراف/);
    expect(screen.queryByTestId('manual-ref-edit-btn')).not.toBeInTheDocument();
  });

  it('shows edit button + textarea when canEdit=true and admin clicks Edit', async () => {
    request.mockResolvedValueOnce({ reference: sampleRef });
    render(<ManualReferenceIcon referenceId={42} canEdit={true} />);
    fireEvent.click(screen.getByRole('button', { name: /الأساس النظامي/i }));
    await screen.findByText(/يكون عقد الإشراف/);

    const editBtn = screen.getByTestId('manual-ref-edit-btn');
    fireEvent.click(editBtn);

    expect(screen.getByTestId('manual-ref-textarea')).toBeInTheDocument();
    expect(screen.getByTestId('manual-ref-save-btn')).toBeInTheDocument();
  });

  it('PATCHes the updated text on save and shows the returned text', async () => {
    request.mockResolvedValueOnce({ reference: sampleRef });
    const editedRef = { ...sampleRef, text_ar: 'نص جديد بعد المراجعة.', needs_reimplementation: true };
    request.mockResolvedValueOnce({ reference: editedRef });

    render(<ManualReferenceIcon referenceId={42} canEdit={true} />);
    fireEvent.click(screen.getByRole('button', { name: /الأساس النظامي/i }));
    await screen.findByText(/يكون عقد الإشراف/);

    fireEvent.click(screen.getByTestId('manual-ref-edit-btn'));
    const ta = screen.getByTestId('manual-ref-textarea') as HTMLTextAreaElement;
    fireEvent.change(ta, { target: { value: 'نص جديد بعد المراجعة.' } });
    fireEvent.click(screen.getByTestId('manual-ref-save-btn'));

    await waitFor(() => {
      expect(request).toHaveBeenLastCalledWith(
        'PATCH',
        '/admin/manual-references/42',
        { text_ar: 'نص جديد بعد المراجعة.', edit_reason: undefined },
      );
    });
    await waitFor(() => {
      expect(screen.getByText(/نص جديد بعد المراجعة/)).toBeInTheDocument();
    });
    // Pending badge now shown after edit
    expect(screen.getByText(/معلَّق/)).toBeInTheDocument();
  });

  it('disables save button when draft is too short', async () => {
    request.mockResolvedValueOnce({ reference: sampleRef });
    render(<ManualReferenceIcon referenceId={42} canEdit={true} />);
    fireEvent.click(screen.getByRole('button', { name: /الأساس النظامي/i }));
    await screen.findByText(/يكون عقد الإشراف/);
    fireEvent.click(screen.getByTestId('manual-ref-edit-btn'));

    const ta = screen.getByTestId('manual-ref-textarea') as HTMLTextAreaElement;
    fireEvent.change(ta, { target: { value: 'قصير' } });

    expect(screen.getByTestId('manual-ref-save-btn')).toBeDisabled();
  });
});
