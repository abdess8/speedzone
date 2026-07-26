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

/**
 * Fetch logistics dashboard data from the API.
 *
 * @param {{ period?: string, from?: string, to?: string }} params
 * @returns {Promise<object>}
 */
export async function fetchDashboard(params = {}) {
  const { data } = await axios.get('/api/dashboard', { params });
  return data?.data ?? data;
}

export { PERIOD_VALUES };

export default {
  fetchDashboard,
  PERIOD_VALUES,
};
