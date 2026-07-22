import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ConfirmDialog } from './ConfirmDialog';

/**
 * JORD-70: the in-app replacement for window.confirm(). Pins:
 *   • Only renders when `open`
 *   • Confirm button fires onConfirm
 *   • Cancel button fires onCancel
 *   • Escape fires onCancel
 *   • aria-labelledby / aria-describedby wired so screen readers
 *     announce the dialog with a real name
 *   • busy state disables both buttons
 */

describe('ConfirmDialog (JORD-70)', () => {
  it('renders nothing when open=false', () => {
    render(
      <ConfirmDialog
        open={false}
        title="delete X" message="are you sure?"
        onConfirm={vi.fn()} onCancel={vi.fn()}
      />
    );
    expect(screen.queryByTestId('confirm-dialog')).toBeNull();
  });

  it('renders title, message, and both buttons when open=true', () => {
    render(
      <ConfirmDialog
        open title="delete user" message="are you sure?"
        onConfirm={vi.fn()} onCancel={vi.fn()}
      />
    );
    expect(screen.getByTestId('confirm-dialog')).toBeInTheDocument();
    expect(screen.getByText('delete user')).toBeInTheDocument();
    expect(screen.getByText('are you sure?')).toBeInTheDocument();
    expect(screen.getByTestId('confirm-dialog-confirm')).toBeInTheDocument();
    expect(screen.getByTestId('confirm-dialog-cancel')).toBeInTheDocument();
  });

  it('fires onConfirm on the confirm button click', async () => {
    const onConfirm = vi.fn();
    render(
      <ConfirmDialog
        open title="t" message="m"
        onConfirm={onConfirm} onCancel={vi.fn()}
      />
    );
    await userEvent.click(screen.getByTestId('confirm-dialog-confirm'));
    expect(onConfirm).toHaveBeenCalledTimes(1);
  });

  it('fires onCancel on the cancel button click', async () => {
    const onCancel = vi.fn();
    render(
      <ConfirmDialog
        open title="t" message="m"
        onConfirm={vi.fn()} onCancel={onCancel}
      />
    );
    await userEvent.click(screen.getByTestId('confirm-dialog-cancel'));
    expect(onCancel).toHaveBeenCalledTimes(1);
  });

  it('fires onCancel on Escape', async () => {
    const onCancel = vi.fn();
    render(
      <ConfirmDialog
        open title="t" message="m"
        onConfirm={vi.fn()} onCancel={onCancel}
      />
    );
    await userEvent.keyboard('{Escape}');
    expect(onCancel).toHaveBeenCalledTimes(1);
  });

  it('wires aria-labelledby + aria-describedby so screen readers announce the dialog', () => {
    render(
      <ConfirmDialog
        open title="del user" message="permanent"
        onConfirm={vi.fn()} onCancel={vi.fn()}
      />
    );
    const dialog = screen.getByTestId('confirm-dialog');
    expect(dialog.getAttribute('role')).toBe('alertdialog');
    expect(dialog.getAttribute('aria-labelledby')).toBe('confirm-dialog-title');
    expect(dialog.getAttribute('aria-describedby')).toBe('confirm-dialog-body');
    expect(document.getElementById('confirm-dialog-title')?.textContent).toBe('del user');
    expect(document.getElementById('confirm-dialog-body')?.textContent).toBe('permanent');
  });

  it('disables both buttons and swallows Escape while busy=true', async () => {
    const onCancel = vi.fn();
    render(
      <ConfirmDialog
        open busy title="t" message="m"
        onConfirm={vi.fn()} onCancel={onCancel}
      />
    );
    expect(screen.getByTestId('confirm-dialog-confirm')).toBeDisabled();
    expect(screen.getByTestId('confirm-dialog-cancel')).toBeDisabled();
    await userEvent.keyboard('{Escape}');
    expect(onCancel).not.toHaveBeenCalled();
  });

  it('applies destructive styling to the confirm button when destructive=true', () => {
    render(
      <ConfirmDialog
        open destructive title="t" message="m"
        onConfirm={vi.fn()} onCancel={vi.fn()}
      />
    );
    expect(screen.getByTestId('confirm-dialog-confirm').className).toContain('bg-red-600');
  });
});
