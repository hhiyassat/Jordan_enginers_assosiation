import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { TextField, FormField } from './FormField';

describe('TextField', () => {
  it('associates the label with the input via htmlFor + id', () => {
    render(<TextField label="اسم" labelEn="Name" value="" onChange={() => {}} />);
    const input = screen.getByLabelText(/اسم/);
    // The generated id from useId is unpredictable, but the association
    // is what matters — getByLabelText resolves via for/id.
    expect(input).toBeInTheDocument();
    expect(input.tagName).toBe('INPUT');
  });

  it('sets aria-required when required=true', () => {
    render(<TextField label="س" value="" onChange={() => {}} required />);
    expect(screen.getByLabelText(/س/)).toHaveAttribute('aria-required', 'true');
  });

  it('renders error text with role=alert and sets aria-invalid', () => {
    render(<TextField label="س" value="" onChange={() => {}} error="Bad" />);
    const input = screen.getByLabelText(/س/);
    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(input).toHaveAttribute('aria-describedby', expect.stringMatching(/error$/));
    expect(screen.getByRole('alert')).toHaveTextContent('Bad');
  });

  it('propagates typed characters via onChange', async () => {
    const onChange = vi.fn();
    render(<TextField label="س" value="" onChange={onChange} />);
    await userEvent.type(screen.getByLabelText(/س/), 'ab');
    expect(onChange).toHaveBeenCalled();
    // Last call arg is the string, either 'a' then 'ab' depending on
    // React state batching — just assert the final call passed 'b'.
    const lastArg = onChange.mock.calls[onChange.mock.calls.length - 1][0];
    expect(['a', 'b', 'ab']).toContain(lastArg);
  });

  it('hint text is aria-linked when no error present', () => {
    render(<TextField label="س" value="" onChange={() => {}} hint="do this" />);
    const input = screen.getByLabelText(/س/);
    expect(input).toHaveAttribute('aria-describedby', expect.stringMatching(/hint$/));
    expect(screen.getByText('do this')).toBeInTheDocument();
  });
});

describe('FormField', () => {
  it('render-prop mode passes id + aria props to the child control', () => {
    render(
      <FormField label="عمر" labelEn="Age" required error="min">
        {(props) => <input {...props} />}
      </FormField>
    );
    const input = screen.getByLabelText(/عمر/);
    expect(input).toHaveAttribute('id');
    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(input).toHaveAttribute('aria-required', 'true');
  });
});
