import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QuotaCard, type QuotaFacet } from './QuotaCard';

const OK_FACET: QuotaFacet = {
  quota_m2: 1000, used_m2: 200, remaining_m2: 800,
  percent_used: 20, projects_count: 2, unlimited: false,
};

const WARN_FACET: QuotaFacet = { ...OK_FACET, used_m2: 700, remaining_m2: 300, percent_used: 70 };
const DANGER_FACET: QuotaFacet = { ...OK_FACET, used_m2: 1000, remaining_m2: 0, percent_used: 100 };

describe('QuotaCard', () => {
  it('renders numeric progressbar with correct aria-value attrs', () => {
    render(<QuotaCard facet={OK_FACET} />);
    const bar = screen.getByRole('progressbar');
    expect(bar).toHaveAttribute('aria-valuenow', '20');
    expect(bar).toHaveAttribute('aria-valuemin', '0');
    expect(bar).toHaveAttribute('aria-valuemax', '100');
  });

  it('shows the unlimited copy when facet.unlimited is true', () => {
    render(<QuotaCard facet={{ ...OK_FACET, unlimited: true }} />);
    expect(screen.getByText('غير محدد')).toBeInTheDocument();
    expect(screen.getByText('Unlimited')).toBeInTheDocument();
    // No progressbar in unlimited variant.
    expect(screen.queryByRole('progressbar')).not.toBeInTheDocument();
  });

  it('shows the ceiling role=alert when at 100%', () => {
    render(<QuotaCard facet={DANGER_FACET} />);
    // The severity=danger renders a role=alert message under the bar.
    const alerts = screen.getAllByRole('alert');
    // Note: could be more than one alert-role element depending on how
    // React composes attributes; the salient one contains the ceiling copy.
    expect(alerts.some(el => el.textContent?.includes('السقف السنوي'))).toBe(true);
  });

  it('does not render danger alert below 90%', () => {
    render(<QuotaCard facet={WARN_FACET} />);
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('renders skeleton when loading', () => {
    const { container } = render(<QuotaCard facet={null} loading />);
    expect(container.querySelector('.animate-pulse')).toBeInTheDocument();
  });

  it('renders error banner and invokes onRetry on click', async () => {
    const onRetry = vi.fn();
    render(<QuotaCard facet={null} error="oh no" onRetry={onRetry} />);
    expect(screen.getByRole('alert')).toHaveTextContent('تعذّر تحميل الرصيد');
    await userEvent.click(screen.getByRole('button', { name: /إعادة المحاولة/ }));
    expect(onRetry).toHaveBeenCalledTimes(1);
  });

  it('renders title overrides when provided', () => {
    render(
      <QuotaCard
        facet={OK_FACET}
        titleAr="م. سارة"
        titleEn="Eng. Sara"
        year={2026}
      />
    );
    expect(screen.getByText('م. سارة')).toBeInTheDocument();
    expect(screen.getByText(/Eng\. Sara/)).toBeInTheDocument();
    expect(screen.getByText(/2026/)).toBeInTheDocument();
  });
});
