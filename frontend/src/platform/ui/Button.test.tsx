import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Button } from './Button';

describe('Button', () => {
  it('renders as a real <button> with type=button by default', () => {
    render(<Button>Save</Button>);
    const btn = screen.getByRole('button', { name: /save/i });
    expect(btn.tagName).toBe('BUTTON');
    expect(btn).toHaveAttribute('type', 'button');
  });

  it('respects an explicit `type` prop', () => {
    render(<Button type="submit">Go</Button>);
    expect(screen.getByRole('button')).toHaveAttribute('type', 'submit');
  });

  it('fires onClick and forwards native events', async () => {
    const onClick = vi.fn();
    render(<Button onClick={onClick}>Tap</Button>);
    await userEvent.click(screen.getByRole('button'));
    expect(onClick).toHaveBeenCalledTimes(1);
  });

  it('disables when `disabled` or `loading` is true', async () => {
    const onClick = vi.fn();
    const { rerender } = render(<Button onClick={onClick} disabled>X</Button>);
    expect(screen.getByRole('button')).toBeDisabled();
    await userEvent.click(screen.getByRole('button'));
    expect(onClick).not.toHaveBeenCalled();

    rerender(<Button onClick={onClick} loading>X</Button>);
    expect(screen.getByRole('button')).toBeDisabled();
    expect(screen.getByRole('button')).toHaveAttribute('aria-busy', 'true');
  });

  it('renders loading spinner instead of children when loading', () => {
    render(<Button loading>Save Me</Button>);
    // Children are suppressed, only the SVG spinner appears.
    expect(screen.queryByText('Save Me')).not.toBeInTheDocument();
    // Spinner is aria-hidden svg — check its presence via role/tag.
    expect(document.querySelector('svg')).toBeInTheDocument();
  });
});
