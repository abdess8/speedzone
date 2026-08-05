import axios from 'axios';

const PERIOD_VALUES = [
  'today',
  'yesterday',
  'last_7_days',
  'last_30_days',
  'this_month',
  'last_month',
  'custom',
];

let inFlight = null;

/**
 * Fetch logistics dashboard data from the API.
 *
 * Switching periods quickly used to leave several requests racing, and the
 * slowest one won: the pending request is aborted before a new one starts.
 *
 * @param {{ period?: string, from?: string, to?: string }} params
 * @returns {Promise<object>}
 */
export async function fetchDashboard(params = {}) {
  inFlight?.abort();
  inFlight = new AbortController();

  const { data } = await axios.get('/api/dashboard', {
    params,
    signal: inFlight.signal,
  });

  return data?.data ?? data;
}

export function isCancelled(error) {
  return axios.isCancel(error);
}

export { PERIOD_VALUES };

export default {
  fetchDashboard,
  isCancelled,
  PERIOD_VALUES,
};
