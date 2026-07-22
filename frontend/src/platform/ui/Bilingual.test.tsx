import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Bilingual } from './Bilingual';

describe('Bilingual', () => {
  it('renders both AR and EN spans with correct lang attrs (inline variant)', () => {
    const { container } = render(<Bilingual ar="مرحبا" en="Hello" />);
    // ar
    const ar = container.querySelector('[lang="ar"]');
    expect(ar).toBeInTheDocument();
    expect(ar).toHaveTextContent('مرحبا');
    // en
    const en = container.querySelector('[lang="en"]');
    expect(en).toBeInTheDocument();
    expect(en).toHaveTextContent('Hello');
    expect(en).toHaveAttribute('dir', 'ltr');
  });

  it('renders as the requested element via the `as` prop', () => {
    const { container } = render(<Bilingual as="h1" ar="عنوان" en="Title" />);
    expect(container.querySelector('h1')).toBeInTheDocument();
  });

  it('stacked variant places AR and EN in separate block spans', () => {
    const { container } = render(<Bilingual variant="stacked" ar="س" en="S" />);
    const blocks = container.querySelectorAll('.block');
    expect(blocks.length).toBe(2);
  });

  it('pill variant wraps EN in parentheses', () => {
    render(<Bilingual variant="pill" ar="مرحبا" en="Hello" />);
    // The parenthesised EN text is broken across nodes, so we search
    // globally for both fragments.
    expect(screen.getByText('مرحبا')).toBeInTheDocument();
    expect(screen.getByText(/\(Hello\)/)).toBeInTheDocument();
  });
});
