import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import type { ServiceDefinition } from '../../../types';

// Mock the api client. chatUpdateSchema returns a schema that adds a
// document; updateService is a spy we assert on to prove the two-in-one
// button actually persists to the backend.
const mockGetService     = vi.fn();
const mockUpdateService  = vi.fn();
const mockChatSchema     = vi.fn();
vi.mock('../../../api/client', () => ({
  adminApi: {
    getService:          (...a: unknown[]) => mockGetService(...a),
    updateService:       (...a: unknown[]) => mockUpdateService(...a),
    updateServiceStatus: vi.fn(),
    chatUpdateSchema:    (...a: unknown[]) => mockChatSchema(...a),
  },
}));

import { EditService } from './EditService';

const baseSchema = {
  service_code: 'DRW-P-004',
  name_ar: 'مخططات الهدم',
  name_en: 'Demolition Drawings',
  workflow: { stages: [{ id: 'review', label_ar: 'مراجعة', label_en: 'Review', role: 'staff', sla_hours: 24, actions: ['approve', 'reject'] }] },
  fee: { type: 'fixed', amount: 0, currency: 'JOD' },
  sections: [],
  fields: [],
  documents: [],
};

const updatedSchemaWithDoc = {
  ...baseSchema,
  documents: [
    { id: 'demolition_drawings', label_ar: 'مخططات الهدم', label_en: 'Demolition drawings', required: true, accept: ['pdf'], max_size_mb: 5 },
  ],
};

function service(overrides: Partial<ServiceDefinition> = {}): ServiceDefinition {
  return {
    id: 22, code: 'DRW-P-004', name_ar: 'مخططات الهدم', name_en: 'Demolition Drawings',
    currency: 'JOD', status: 'active', is_locked: false,
    schema: baseSchema, ...overrides,
  } as unknown as ServiceDefinition;
}

beforeEach(() => {
  mockGetService.mockReset();
  mockUpdateService.mockReset();
  mockChatSchema.mockReset();
  // jsdom doesn't implement scrollIntoView; the AI panel scrolls to
  // the bottom on every new message, which crashes without this stub.
  window.HTMLElement.prototype.scrollIntoView = () => {};
});

function renderEdit() {
  return render(
    <MemoryRouter initialEntries={['/admin/services/22/edit']}>
      <Routes>
        <Route path="/admin/services/:id/edit" element={<EditService />} />
      </Routes>
    </MemoryRouter>
  );
}

async function goToAiTab() {
  await userEvent.click(await screen.findByRole('button', { name: /مساعد الذكاء الاصطناعي/ }));
}

async function sendChat(prompt: string) {
  // The chat textarea has placeholder text with the word 'AI' — grabbing
  // by role='textbox' is enough since there's only one on this tab.
  const box = screen.getAllByRole('textbox').find(el => el.tagName === 'TEXTAREA');
  if (!box) throw new Error('chat textarea not found');
  await userEvent.type(box, prompt);
  await userEvent.click(screen.getByRole('button', { name: /إرسال/ }));
}

describe('EditService — apply-and-save from the AI chat', () => {
  it('persists the AI-updated schema via updateService and confirms in the panel', async () => {
    mockGetService.mockResolvedValue({ service: service({ is_locked: false }) });
    mockChatSchema.mockResolvedValue({
      updated_schema: updatedSchemaWithDoc,
      explanation:    'Restricted the demolition_drawings document upload to accept PDF files only',
      changes:        ['Restricted the demolition_drawings document upload to accept PDF files only'],
      tokens_used:    100,
    });
    // Cast: the literal narrows role: 'staff' to plain string; the
    // schema shape is otherwise identical to ServiceSchema.
    mockUpdateService.mockResolvedValue({ service: service({ schema: updatedSchemaWithDoc as unknown as ServiceDefinition['schema'] }) });

    renderEdit();
    await waitFor(() => expect(screen.getByText('مخططات الهدم')).toBeInTheDocument());
    await goToAiTab();
    await sendChat('add pdf-only demolition_drawings upload');
    await waitFor(() => expect(screen.getByRole('button', { name: /تطبيق التغييرات وحفظها/ })).toBeInTheDocument());

    await userEvent.click(screen.getByRole('button', { name: /تطبيق التغييرات وحفظها/ }));

    // Regression pin: the button MUST call updateService, not just apply
    // to the local buffer. Prior behaviour required a second click on the
    // JSON tab that admins kept forgetting.
    await waitFor(() => expect(mockUpdateService).toHaveBeenCalledTimes(1));
    const [id, body] = mockUpdateService.mock.calls[0];
    expect(id).toBe(22);
    expect((body as { schema: Record<string, unknown> }).schema).toMatchObject({ documents: [{ id: 'demolition_drawings' }] });

    // Success confirmation swaps the button label.
    await waitFor(() => expect(screen.getByRole('button', { name: /تم التطبيق والحفظ/ })).toBeInTheDocument());
  });

  it('disables apply-and-save with a lock hint when the service is locked', async () => {
    mockGetService.mockResolvedValue({ service: service({ is_locked: true }) });
    mockChatSchema.mockResolvedValue({
      updated_schema: updatedSchemaWithDoc, explanation: 'x', changes: [], tokens_used: 1,
    });

    renderEdit();
    await waitFor(() => expect(screen.getByText('مخططات الهدم')).toBeInTheDocument());
    await goToAiTab();
    await sendChat('anything');
    const button = await screen.findByRole('button', { name: /الخدمة مقفلة/ });
    expect(button).toBeDisabled();
    // updateService MUST NOT fire while locked.
    await userEvent.click(button).catch(() => {});
    expect(mockUpdateService).not.toHaveBeenCalled();
  });

  it('surfaces a save-side 422 error inline instead of dropping it', async () => {
    mockGetService.mockResolvedValue({ service: service({ is_locked: false }) });
    mockChatSchema.mockResolvedValue({
      updated_schema: updatedSchemaWithDoc, explanation: 'x', changes: [], tokens_used: 1,
    });
    const apiErr = Object.assign(new Error('validation failed'), {
      errors: { 'schema.workflow.stages[0].actions': ['قيمة غير مسموح بها'] },
    });
    mockUpdateService.mockRejectedValue(apiErr);

    renderEdit();
    await waitFor(() => expect(screen.getByText('مخططات الهدم')).toBeInTheDocument());
    await goToAiTab();
    await sendChat('add doc');
    await userEvent.click(await screen.findByRole('button', { name: /تطبيق التغييرات وحفظها/ }));

    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent(/قيمة غير مسموح بها/));
    // Button stays available so the admin can retry after fixing.
    expect(screen.getByRole('button', { name: /تطبيق التغييرات وحفظها/ })).toBeInTheDocument();
  });
});
