import React from 'react';

/**
 * Button — NFR-004
 *
 * Accessible, focus-ringed button primitive. Always renders a real <button>
 * (never a div-as-button), inherits keyboard support and role automatically.
 * The `variant` prop covers the JEA design language; add new variants here
 * so future features stay visually consistent.
 */
type Variant = 'primary' | 'secondary' | 'ghost' | 'danger' | 'white';
type Size = 'sm' | 'md' | 'lg';

interface ButtonProps extends Omit<React.ButtonHTMLAttributes<HTMLButtonElement>, 'className'> {
  variant?: Variant;
  size?: Size;
  className?: string;
  /** Loading spinner replaces children when true; button auto-disables. */
  loading?: boolean;
  /** Icon rendered before children (or after, in RTL). */
  icon?: React.ReactNode;
}

const VARIANT_CLASSES: Record<Variant, string> = {
  primary:   'bg-jea-primary text-white hover:bg-jea-hover active:bg-jea-topbarDeep shadow-sm shadow-jea-primary/20',
  secondary: 'bg-jea-accent text-jea-primary hover:bg-jea-accent2',
  ghost:     'bg-transparent text-jea-primary hover:bg-jea-accent',
  danger:    'bg-red-500 text-white hover:bg-red-600',
  white:     'bg-white text-jea-primary hover:bg-jea-accent shadow-sm',
};

const SIZE_CLASSES: Record<Size, string> = {
  sm: 'text-xs px-3 py-1.5 rounded-lg gap-1.5',
  md: 'text-sm px-4 py-2 rounded-xl gap-2',
  lg: 'text-base px-5 py-3 rounded-xl gap-2',
};

export function Button({
  variant = 'primary',
  size = 'md',
  className = '',
  loading = false,
  icon,
  disabled,
  type = 'button',
  children,
  ...rest
}: ButtonProps) {
  const isDisabled = disabled || loading;
  return (
    <button
      type={type}
      disabled={isDisabled}
      aria-busy={loading || undefined}
      className={[
        'inline-flex items-center justify-center font-bold transition-colors',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-jea-primary/40 focus-visible:ring-offset-2',
        'disabled:opacity-60 disabled:cursor-not-allowed',
        VARIANT_CLASSES[variant],
        SIZE_CLASSES[size],
        className,
      ].join(' ')}
      {...rest}
    >
      {loading ? (
        <svg className="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24" aria-hidden="true">
          <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
          <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
        </svg>
      ) : (
        <>
          {icon && <span aria-hidden="true">{icon}</span>}
          {children}
        </>
      )}
    </button>
  );
}
