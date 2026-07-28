import { AppProviders } from './providers/AppProviders';
import { AppRoutes } from './router';

/**
 * App — pure composition root (JORD-25 / Task 3 FSD migration).
 *
 * All providers are now in AppProviders. This file just wires them together.
 */
export default function App(): JSX.Element {
  return (
    <AppProviders>
      <AppRoutes />
    </AppProviders>
  );
}
