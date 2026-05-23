import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';

const BASE_URL = (__ENV.BASE_URL || 'http://localhost/api').replace(/\/$/, '');
const RATE = Number(__ENV.RATE || 5);
const DURATION = __ENV.DURATION || '30s';
const PRE_ALLOCATED_VUS = Number(__ENV.PRE_ALLOCATED_VUS || 5);
const MAX_VUS = Number(__ENV.MAX_VUS || 20);
const BULK_RECIPIENTS = Number(__ENV.BULK_RECIPIENTS || 10);
const THINK_TIME_SECONDS = Number(__ENV.THINK_TIME_SECONDS || 0.2);
const REPORT_DIR = (__ENV.REPORT_DIR || 'reports/load').replace(/\/$/, '');
const REPORT_RUN_ID = __ENV.REPORT_RUN_ID || new Date().toISOString().replace(/[:.]/g, '-');
const REPORT_BASENAME = `${REPORT_DIR}/notifications-${REPORT_RUN_ID}`;

const createdNotifications = new Counter('created_notifications');
const callbackFailures = new Rate('delivery_callback_failures');
const createLatency = new Trend('create_notification_latency', true);
const bulkLatency = new Trend('bulk_notification_latency', true);

export const options = {
  scenarios: {
    api_load: {
      executor: 'constant-arrival-rate',
      rate: RATE,
      timeUnit: '1s',
      duration: DURATION,
      preAllocatedVUs: PRE_ALLOCATED_VUS,
      maxVUs: MAX_VUS,
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<750', 'p(99)<1500'],
    create_notification_latency: ['p(95)<750'],
    bulk_notification_latency: ['p(95)<1500'],
    delivery_callback_failures: ['rate<0.02'],
  },
};

export default function () {
  const roll = Math.random();

  if (roll < 0.7) {
    createSingleNotification();
  } else if (roll < 0.9) {
    createBulkNotifications();
  } else {
    readSubscriberHistory();
  }

  sleep(THINK_TIME_SECONDS);
}

export function handleSummary(data) {
  const summary = JSON.stringify(data, null, 2);

  return {
    stdout: summaryText(data),
    [`${REPORT_BASENAME}.json`]: summary,
    [`${REPORT_BASENAME}.html`]: summaryHtml(data),
    [`${REPORT_DIR}/latest.json`]: summary,
    [`${REPORT_DIR}/latest.html`]: summaryHtml(data),
  };
}

function createSingleNotification() {
  group('create single notification', () => {
    const requestId = uniqueId('single');
    const recipient = `${requestId}@load.local`;
    const payload = JSON.stringify({
      idempotency_key: requestId,
      subscriber_id: recipient,
      channel: Math.random() < 0.75 ? 'email' : 'sms',
      priority: Math.random() < 0.2 ? 'transactional' : 'marketing',
      recipient,
      subject: 'Load test notification',
      body: 'Notification created by k6 load test.',
      payload: {
        source: 'k6',
        iteration: __ITER,
      },
    });

    const response = http.post(`${BASE_URL}/notifications`, payload, jsonParams(requestId));

    createLatency.add(response.timings.duration);

    const accepted = check(response, {
      'single notification accepted': (res) => res.status === 202 || res.status === 200,
      'single response has id': (res) => notificationId(res) !== null,
    });

    if (!accepted) {
      return;
    }

    createdNotifications.add(1);

    const id = notificationId(response);
    if (id !== null && Math.random() < 0.25) {
      confirmDelivery(id, requestId);
    }
  });
}

function createBulkNotifications() {
  group('create bulk notifications', () => {
    const requestId = uniqueId('bulk');
    const recipients = [];

    for (let i = 0; i < BULK_RECIPIENTS; i += 1) {
      recipients.push(`${requestId}-${i}@load.local`);
    }

    const payload = JSON.stringify({
      idempotency_key: requestId,
      channel: 'email',
      priority: Math.random() < 0.2 ? 'transactional' : 'marketing',
      subject: 'Load test campaign',
      body: 'Bulk notification created by k6 load test.',
      recipients,
    });

    const response = http.post(`${BASE_URL}/notifications/bulk`, payload, jsonParams(requestId));

    bulkLatency.add(response.timings.duration);

    check(response, {
      'bulk notifications accepted': (res) => res.status === 202,
      'bulk response has all records': (res) => {
        const data = responseData(res);

        return Array.isArray(data) && data.length === BULK_RECIPIENTS;
      },
    });
  });
}

function readSubscriberHistory() {
  group('read subscriber history', () => {
    const subscriber = `history-${__VU % 20}@load.local`;
    const response = http.get(`${BASE_URL}/subscribers/${encodeURIComponent(subscriber)}/notifications`, {
      tags: { endpoint: 'subscriber_history' },
    });

    check(response, {
      'history returned': (res) => res.status === 200,
      'history response has array': (res) => Array.isArray(responseData(res)),
    });
  });
}

function confirmDelivery(notificationIdValue, requestId) {
  const response = http.post(
    `${BASE_URL}/notifications/${notificationIdValue}/delivery-status`,
    JSON.stringify({ status: Math.random() < 0.95 ? 'delivered' : 'dropped' }),
    jsonParams(`${requestId}-delivery`),
  );

  const failed = !check(response, {
    'delivery callback accepted': (res) => res.status === 200,
  });

  callbackFailures.add(failed);
}

function jsonParams(traceId) {
  return {
    headers: {
      'Content-Type': 'application/json',
      'X-Trace-Id': `k6-${traceId}`,
    },
  };
}

function uniqueId(prefix) {
  return `${prefix}-${Date.now()}-${__VU}-${__ITER}-${randomInt(100000, 999999)}`;
}

function randomInt(min, max) {
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

function notificationId(response) {
  const data = responseData(response);

  if (data !== null && typeof data === 'object' && typeof data.id === 'string') {
    return data.id;
  }

  return null;
}

function responseData(response) {
  try {
    const body = response.json();

    return body.data;
  } catch (error) {
    return null;
  }
}

function summaryText(data) {
  const metrics = data.metrics;
  const duration = trendValue(metrics.http_req_duration, 'p(95)');
  const failed = rateValue(metrics.http_req_failed);
  const requests = countValue(metrics.http_reqs);
  const checks = rateValue(metrics.checks);

  return [
    '',
    'k6 load test summary',
    `  requests: ${requests}`,
    `  http failed: ${formatPercent(failed)}`,
    `  checks passed: ${formatPercent(checks)}`,
    `  http p95: ${formatMs(duration)}`,
    `  html report: ${REPORT_BASENAME}.html`,
    `  json report: ${REPORT_BASENAME}.json`,
    '',
  ].join('\n');
}

function summaryHtml(data) {
  const metrics = data.metrics;
  const rows = [
    metricRow('HTTP requests', countValue(metrics.http_reqs)),
    metricRow('HTTP failures', formatPercent(rateValue(metrics.http_req_failed))),
    metricRow('Checks passed', formatPercent(rateValue(metrics.checks))),
    metricRow('HTTP duration avg', formatMs(trendValue(metrics.http_req_duration, 'avg'))),
    metricRow('HTTP duration p95', formatMs(trendValue(metrics.http_req_duration, 'p(95)'))),
    metricRow('HTTP duration p99', formatMs(trendValue(metrics.http_req_duration, 'p(99)'))),
    metricRow('Single create p95', formatMs(trendValue(metrics.create_notification_latency, 'p(95)'))),
    metricRow('Bulk create p95', formatMs(trendValue(metrics.bulk_notification_latency, 'p(95)'))),
    metricRow('Created notifications', countValue(metrics.created_notifications)),
    metricRow('Callback failures', formatPercent(rateValue(metrics.delivery_callback_failures))),
  ].join('');

  const checks = collectChecks(data.root_group)
    .map((checkData) => metricRow(escapeHtml(checkData.path || checkData.name), formatPercent(checkData.passes / (checkData.passes + checkData.fails))))
    .join('');

  return `<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>k6 notifications report ${escapeHtml(REPORT_RUN_ID)}</title>
  <style>
    body { margin: 0; font-family: Arial, sans-serif; color: #172033; background: #f5f7fb; }
    main { max-width: 960px; margin: 0 auto; padding: 32px 20px; }
    h1 { margin: 0 0 8px; font-size: 28px; }
    h2 { margin: 32px 0 12px; font-size: 18px; }
    .meta { color: #5e6a7d; margin-bottom: 24px; }
    table { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid #dfe5ef; }
    th, td { padding: 12px 14px; border-bottom: 1px solid #edf1f7; text-align: left; }
    th { background: #eef3f9; font-weight: 700; }
    tr:last-child td { border-bottom: 0; }
    code { background: #e9eef6; padding: 2px 5px; border-radius: 4px; }
  </style>
</head>
<body>
  <main>
    <h1>k6 notifications report</h1>
    <div class="meta">
      Run id: <code>${escapeHtml(REPORT_RUN_ID)}</code><br>
      Base URL: <code>${escapeHtml(BASE_URL)}</code><br>
      Profile: <code>${RATE}/s for ${escapeHtml(DURATION)}</code>
    </div>

    <h2>Metrics</h2>
    <table>
      <thead><tr><th>Metric</th><th>Value</th></tr></thead>
      <tbody>${rows}</tbody>
    </table>

    <h2>Checks</h2>
    <table>
      <thead><tr><th>Check</th><th>Passed</th></tr></thead>
      <tbody>${checks || metricRow('No checks collected', '-')}</tbody>
    </table>
  </main>
</body>
</html>`;
}

function collectChecks(group) {
  const checks = [...(group?.checks || [])];

  for (const child of group?.groups || []) {
    checks.push(...collectChecks(child));
  }

  return checks;
}

function metricRow(name, value) {
  return `<tr><td>${name}</td><td>${value}</td></tr>`;
}

function trendValue(metric, key) {
  return metric?.values?.[key] ?? null;
}

function rateValue(metric) {
  return metric?.values?.rate ?? null;
}

function countValue(metric) {
  return metric?.values?.count ?? 0;
}

function formatMs(value) {
  return value === null ? '-' : `${value.toFixed(2)} ms`;
}

function formatPercent(value) {
  return value === null || Number.isNaN(value) ? '-' : `${(value * 100).toFixed(2)}%`;
}

function escapeHtml(value) {
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
