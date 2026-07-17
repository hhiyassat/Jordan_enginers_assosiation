import React, { useId } from 'react';

/**
 * FormField — NFR-004
 *
 * Wraps a form control with a proper <label htmlFor>, optional bilingual
 * label suffix, aria-describedby for hints/errors, aria-invalid on error,
 * and aria-required on required fields. Using this instead of raw <input>
 * guarantees every form field is keyboard + screen-reader accessible.
 *
 * Two usage modes:
 *   1) Render prop:  <FormField label..><input id={id} ... /></FormField>
 *      (Passes the generated id + aria-* props to your input.)
 *   2) Simple string input via <TextField /> below.
 */
export interface FieldRenderProps {
  id: string;
  'aria-describedby': string | undefined;
  'aria-invalid': boolean | undefined;
  'aria-required': boolean | undefined;
}

interface FormFieldProps {
  /** Arabic label (primary). */
  label: string;
  /** English label (secondary, shown in small text after AR). */
  labelEn?: string;
  /** Helper text under the field (id linked via aria-describedby). */
  hint?: string;
  /** Error text (id linked, styled red, sets aria-invalid). */
  error?: string;
  required?: boolean;
  children: (props: FieldRenderProps) => React.ReactNode;
}

export function FormField({
  label, labelEn, hint, error, required = false, children,
}: FormFieldProps) {
  const rawId = useId();
  const id     = `f-${rawId}`;
  const hintId = hint  ? `${id}-hint`  : undefined;
  const errId  = error ? `${id}-error` : undefined;
  const describedBy = [hintId, errId].filter(Boolean).join(' ') || undefined;

  return (
    <div>
      <label htmlFor={id} className="block text-sm font-bold text-jea-text mb-1.5">
        <span lang="ar">{label}</span>
        {labelEn && (
          <span className="text-jea-muted font-normal text-xs" lang="en" dir="ltr"> · {labelEn}</span>
        )}
        {required && (
          <span className="text-jea-danger mx-1" aria-hidden="true">*</span>
        )}
      </label>
      {children({
        id,
        'aria-describedby': describedBy,
        'aria-invalid': error ? true : undefined,
        'aria-required': required || undefined,
      })}
      {hint && !error && (
        <p id={hintId} className="text-[10px] text-jea-muted mt-1">{hint}</p>
      )}
      {error && (
        <p id={errId} role="alert" className="text-xs text-jea-danger mt-1">
          {error}
        </p>
      )}
    </div>
  );
}

/* ── Convenience: text input version ────────────────────────────────── */

interface TextFieldProps extends FormFieldProps {
  value: string;
  onChange: (v: string) => void;
  type?: string;
  placeholder?: string;
  autoComplete?: string;
  /** Optional decorative icon rendered inside the input (start position). */
  startIcon?: React.ReactNode;
  /** Optional trigger rendered inside the input (end position). */
  endAdornment?: React.ReactNode;
}

export function TextField({
  value, onChange, type = 'text', placeholder, autoComplete,
  startIcon, endAdornment, ...fieldProps
}: TextFieldProps) {
  return (
    <FormField {...fieldProps}>
      {props => (
        <div className="relative">
          {startIcon && (
            <span
              className="absolute right-3 top-1/2 -translate-y-1/2 text-jea-muted pointer-events-none"
              aria-hidden="true"
            >
              {startIcon}
            </span>
          )}
          <input
            {...props}
            type={type}
            value={value}
            onChange={e => onChange(e.target.value)}
            placeholder={placeholder}
            autoComplete={autoComplete}
            className={[
              'w-full border rounded-xl py-3 text-sm outline-none bg-white transition-all',
              'placeholder:text-[#A0BCCC]',
              'focus:border-jea-primary focus:ring-2 focus:ring-jea-primary/20',
              props['aria-invalid'] ? 'border-jea-danger' : 'border-jea-border',
              startIcon    ? 'pr-10' : 'pr-4',
              endAdornment ? 'pl-10' : 'pl-4',
            ].join(' ')}
          />
          {endAdornment && (
            <span className="absolute left-3 top-1/2 -translate-y-1/2 text-jea-muted">
              {endAdornment}
            </span>
          )}
        </div>
      )}
    </FormField>
  );
}
