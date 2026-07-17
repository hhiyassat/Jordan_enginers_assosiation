import React from 'react';

/**
 * Bilingual — NFR-003
 *
 * Every user-visible label must carry both Arabic and English. Using this
 * primitive (rather than raw JSX) makes the AR+EN pairing mandatory: TS
 * forbids constructing it with only one language.
 *
 * Variants:
 *   inline (default) — AR + EN on one line: "الخدمات · Services"
 *   stacked          — AR big, EN smaller/muted underneath (JEA hero pattern)
 *   pill             — small chip with AR primary and EN in parentheses
 *
 * The `as` prop lets callers pick the semantic element (h1/h2/span/etc.).
 */
type Variant = 'inline' | 'stacked' | 'pill';

type ElementTag = 'h1' | 'h2' | 'h3' | 'h4' | 'p' | 'span' | 'div' | 'label';

interface BilingualProps {
  ar: string;
  en: string;
  variant?: Variant;
  as?: ElementTag;
  className?: string;
  /** Extra classes for the English line (stacked variant only) */
  enClassName?: string;
  /** Extra classes for the Arabic line */
  arClassName?: string;
}

export function Bilingual({
  ar,
  en,
  variant = 'inline',
  as: Tag = 'span',
  className = '',
  enClassName = '',
  arClassName = '',
}: BilingualProps) {
  if (variant === 'stacked') {
    return (
      <Tag className={className}>
        <span className={`block ${arClassName}`} lang="ar">{ar}</span>
        <span className={`block text-white/50 text-xs mt-0.5 ${enClassName}`} lang="en" dir="ltr">{en}</span>
      </Tag>
    );
  }
  if (variant === 'pill') {
    return (
      <Tag className={className}>
        <span lang="ar">{ar}</span>
        <span className="opacity-60 mx-1" lang="en" dir="ltr">({en})</span>
      </Tag>
    );
  }
  // inline
  return (
    <Tag className={className}>
      <span lang="ar">{ar}</span>
      <span className="opacity-60 mx-1" aria-hidden="true">·</span>
      <span lang="en" dir="ltr">{en}</span>
    </Tag>
  );
}
