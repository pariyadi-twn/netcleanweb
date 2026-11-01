import http from 'k6/http';
import { check, sleep } from 'k6';
import { Rate } from 'k6/metrics';

const baseUrl = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const token = __ENV.API_TOKEN || '';
const tenant = __ENV.TENANT_ID || '';
const branch = __ENV.BRANCH_ID || '';
const location = __ENV.LOCATION_ID || '';
const from = __ENV.REPORT_FROM || '2026-11-01';
const to = __ENV.REPORT_TO || '2026-11-01';
const rate = Number(__ENV.RPS || 20);
const duration = __ENV.DURATION || '1m';
const vu = Number(__ENV.VUS || 50);
const businessError = new Rate('business_errors');

export const options = {
  scenarios: {
    pilot_read_mix: {
      executor: 'constant-arrival-rate',
      rate,
      timeUnit: '1s',
      duration,
      preAllocatedVUs: vu,
      maxVUs: Math.max(vu, rate * 2),
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.005'],
    http_req_duration: ['p(95)<300'],
  },
};

const headers = {
  Authorization: `Bearer ${token}`,
  'X-Tenant-ID': tenant,
  Accept: 'application/json',
};

export default function () {
  const paths = [
    `/api/v1/customers?q=a&per_page=25`,
    `/api/v1/reports/outlet?branch_id=${branch}&from=${from}&to=${to}`,
    `/api/v1/reports/receivables?branch_id=${branch}&as_of=${to}`,
  ];
  if (location) {
    paths.push(`/api/v1/production/tasks?location_id=${location}&compact=1`);
  }

  const path = paths[Math.floor(Math.random() * paths.length)];
  const response = http.get(`${baseUrl}${path}`, { headers, tags: { endpoint: path.split('?')[0] } });
  check(response, { 'authorized read': (r) => r.status === 200 });
  businessError.add(response.status >= 400 && response.status < 500);
  sleep(Math.random() * 0.05);
}
