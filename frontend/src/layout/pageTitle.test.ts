import { describe, expect, it } from 'vitest';
import { pageTitleKeyFor } from './pageTitle';

/**
 * JORD-5 / JORD-38: pageTitleKeyFor moved from returning { ar, en }
 * to returning an i18n key. Pin the mapping so future route additions
 * don't silently point somewhere unexpected (which used to happen with
 * the string-return version too — worth locking).
 */
describe('pageTitleKeyFor', () => {
  it.each<[string, string]>([
    ['/dashboard',              'pageTitle.dashboard'],
    ['/services',               'pageTitle.services'],
    ['/services/CAT-1',         'pageTitle.services'],
    ['/apply/SRV-99',           'pageTitle.services'],
    ['/projects',               'pageTitle.projects'],
    ['/projects/42',            'pageTitle.projects'],
    ['/my-applications',        'pageTitle.myRequests'],
    ['/review/queue',           'pageTitle.review'],
    ['/review/17',              'pageTitle.review'],
    ['/admin',                  'pageTitle.admin'],
    ['/admin/services/new',     'pageTitle.newService'],
    ['/admin/services',         'pageTitle.servicesAdmin'],
    ['/admin/integration',      'pageTitle.integration'],
    ['/somewhere-unknown',      'pageTitle.home'],
  ])('maps %s → %s', (path, key) => {
    expect(pageTitleKeyFor(path)).toBe(key);
  });
});
