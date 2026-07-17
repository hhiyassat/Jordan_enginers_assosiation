import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { PhaseBadge } from './PhaseBadge';

describe('PhaseBadge', () => {
  it.each([
    [1, 'bg-emerald-500', 'المرحلة الأولى'],
    [2, 'bg-orange-500',  'المرحلة الثانية'],
    [3, 'bg-red-500',     'المرحلة الثالثة'],
    [4, 'bg-blue-500',    'المرحلة الرابعة'],
    [5, 'bg-purple-500',  'المرحلة الخامسة'],
  ] as const)(
    'phase %s renders with %s color class and correct aria-label',
    (phase, colorCls, arLabel) => {
      const { container } = render(<PhaseBadge phase={phase} />);
      const el = container.firstChild as HTMLElement;
      expect(el).toBeInTheDocument();
      expect(el.className).toContain(colorCls);
      expect(el).toHaveAttribute('aria-label', expect.stringContaining(arLabel));
      expect(el).toHaveAttribute('aria-label', expect.stringContaining(`Phase ${phase}`));
    }
  );

  it.each([null, undefined, 0, 6, -1, NaN] as const)(
    'renders nothing for invalid phase %p',
    (phase) => {
      const { container } = render(<PhaseBadge phase={phase as unknown as number} />);
      expect(container.firstChild).toBeNull();
    }
  );

  it('pill variant shows the phase number and both AR/EN labels', () => {
    render(<PhaseBadge phase={3} variant="pill" />);
    // Arabic label م3 and English label P3 are both present in the pill.
    expect(screen.getByText('م3')).toBeInTheDocument();
    expect(screen.getByText('P3')).toBeInTheDocument();
  });

  it('dot variant is a small colored circle with role=img', () => {
    const { container } = render(<PhaseBadge phase={4} variant="dot" />);
    const el = container.firstChild as HTMLElement;
    expect(el).toHaveAttribute('role', 'img');
    // Dot has a fixed square size (w-2.5 h-2.5) and rounded-full.
    expect(el.className).toContain('rounded-full');
    expect(el.className).toContain('w-2.5');
    expect(el.className).toContain('h-2.5');
  });

  it('passes through extra className', () => {
    const { container } = render(<PhaseBadge phase={1} className="ml-4" />);
    expect((container.firstChild as HTMLElement).className).toContain('ml-4');
  });
});
