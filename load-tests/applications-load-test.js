// нагрузочный тест
import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Counter } from 'k6/metrics';

const stepLoginGetVisits = new Counter('step_login_get_visits');
const stepLoginGetDur = new Trend('step_login_get_duration');
const stepLoginPostVisits = new Counter('step_login_post_visits');
const stepLoginPostDur = new Trend('step_login_post_duration');
const stepDashboardVisits = new Counter('step_dashboard_visits');
const stepDashboardDur = new Trend('step_dashboard_duration');
const stepApplicationsVisits = new Counter('step_applications_visits');
const stepApplicationsDur = new Trend('step_applications_duration');
const stepApplicationShowVisits = new Counter('step_application_show_visits');
const stepApplicationShowDur = new Trend('step_application_show_duration');

function recordStep(visitsMetric, durMetric, res) {
  visitsMetric.add(1);
  if (res && res.timings && typeof res.timings.duration === 'number') {
    durMetric.add(res.timings.duration);
  }
}

const BASE_URL = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const USER_POOL = [
  { email: 'Ivanov@mail.ru', password: '11111111' },
  { email: 'Petrov@mail.ru', password: '11111111' },
  { email: 'Sidorova@mail.ru', password: '11111111' },
  { email: 'Kozlov@mail.ru', password: '11111111' },
  { email: 'Volkov@mail.ru', password: '11111111' },
  { email: 'AntonovSV@mail.ru', password: '11111111' },
];
const QUICK = __ENV.K6_QUICK === '1';
const STRICT = __ENV.K6_STRICT === '1';

const fullScenarioOptions = {
  scenarios: {
    applications_load: {
      executor: 'ramping-vus',
      startVUs: 1,
          stages: [
            { duration: '2m', target: 50 },
            { duration: '10m', target: 50 },
            { duration: '5m', target: 50 },
          ],
      gracefulRampDown: '30s',
    },
  },
};

export const options = QUICK
  ? {
      scenarios: {
        smoke: {
          executor: 'constant-vus',
          vus: 5,
          duration: '30s',
        },
      },
      thresholds: {
        checks: ['rate>0.90'],
        http_req_failed: ['rate<0.05'],
      },
    }
  : STRICT
    ? {
        ...fullScenarioOptions,
        thresholds: {
          http_req_failed: ['rate<0.01'],
          http_req_duration: ['p(95)<1500'],
        },
      }
    : fullScenarioOptions;

const formHeaders = { 'Content-Type': 'application/x-www-form-urlencoded' };

const REPORT_ROWS = [
  {
    visitsKey: 'step_login_get_visits',
    durKey: 'step_login_get_duration',
    visits: 'Запросов страницы входа ',
    perf: 'Страница входа ',
  },
  {
    visitsKey: 'step_login_post_visits',
    durKey: 'step_login_post_duration',
    visits: 'Отправок формы входа (POST /login)',
    perf: 'Отправка формы входа (POST /login)',
  },
  {
    visitsKey: 'step_dashboard_visits',
    durKey: 'step_dashboard_duration',
    visits: 'Просмотров главной страницы',
    perf: 'Главная страница ',
  },
  {
    visitsKey: 'step_applications_visits',
    durKey: 'step_applications_duration',
    visits: 'Просмотров списка заявок',
    perf: 'Список заявок',
  },
  {
    visitsKey: 'step_application_show_visits',
    durKey: 'step_application_show_duration',
    visits: 'Просмотров карточки заявки',
    perf: 'Карточка заявки',
  },
];

function getCsrfToken(html) {
  let m = html.match(/name="_token"\s+value="([^"]+)"/);
  if (m) {
    return m[1];
  }
  m = html.match(/value="([^"]+)"\s+name="_token"/);
  return m ? m[1] : null;
}

function extractApplicationIds(html) {
  const ids = [];
  const seen = {};
  const re = /\/applications\/(\d+)(?:["\/?]|'|$)/g;
  let m;
  while ((m = re.exec(html)) !== null) {
    const id = m[1];
    if (!seen[id]) {
      seen[id] = true;
      ids.push(id);
    }
  }
  return ids;
}

function credentialsForVu() {
  if (__ENV.K6_EMAIL && __ENV.K6_PASSWORD) {
    return { email: __ENV.K6_EMAIL, password: __ENV.K6_PASSWORD };
  }
  return USER_POOL[__VU % USER_POOL.length];
}

function counterCount(metrics, key) {
  const m = metrics[key];
  return m && m.type === 'counter' && m.values ? m.values.count : 0;
}

function trendVals(metrics, key) {
  const m = metrics[key];
  return m && m.type === 'trend' && m.values ? m.values : null;
}

function fmtMs(ms) {
  if (ms === undefined || ms === null || Number.isNaN(ms)) {
    return 'н/д';
  }
  if (ms < 1000) {
    return `${ms.toFixed(2)}ms`;
  }
  return `${(ms / 1000).toFixed(2)} с (${ms.toFixed(0)}ms)`;
}

function pct(x) {
  if (x === undefined || x === null || Number.isNaN(x)) {
    return 'н/д';
  }
  return `${(x * 100).toFixed(2)}%`;
}

function buildConsoleReport(data) {
  const { metrics } = data;
  const lines = [];

  lines.push('');
  lines.push(' РЕЗУЛЬТАТЫ НАГРУЗОЧНОГО ТЕСТИРОВАНИЯ');
  lines.push('');

  lines.push(' СТАТИСТИКА ОБРАЩЕНИЙ К РАЗДЕЛАМ');
  for (const row of REPORT_ROWS) {
    lines.push(`    ${row.visits}: ${counterCount(metrics, row.visitsKey)}`);
  }
  lines.push('');

  lines.push('⏱ПРОИЗВОДИТЕЛЬНОСТЬ ');
  for (const row of REPORT_ROWS) {
    const n = counterCount(metrics, row.visitsKey);
    const v = trendVals(metrics, row.durKey);
    if (n > 0 && v) {
      lines.push(
        `    ${row.perf}: ${fmtMs(v.avg)} (p95: ${fmtMs(v['p(95)'])})`,
      );
    } else {
      lines.push(`    ${row.perf}: н/д`);
    }
  }
  lines.push('');

  const checks = metrics.checks && metrics.checks.values ? metrics.checks.values.rate : null;
  const failRate =
    metrics.http_req_failed && metrics.http_req_failed.values
      ? metrics.http_req_failed.values.rate
      : null;
  const okHttp = failRate !== null ? 1 - failRate : null;

  lines.push(' КАЧЕСТВО ОБСЛУЖИВАНИЯ');
  lines.push(`    Успешность проверок : ${pct(checks)}`);
  lines.push(`    Успешность HTTP-запросов : ${pct(okHttp)}`);
  lines.push('');

  const httpDur = metrics.http_req_duration && metrics.http_req_duration.values;
  const reqs = metrics.http_reqs && metrics.http_reqs.values;
  const iters = metrics.iterations && metrics.iterations.values;
  const vuMax = metrics.vus_max && metrics.vus_max.values;

  lines.push(' ОБЩАЯ СТАТИСТИКА СИСТЕМЫ');
  lines.push(`    Всего HTTP запросов: ${reqs ? reqs.count : 'н/д'}`);
  lines.push(`    Ошибок HTTP: ${pct(failRate)}`);
  lines.push(`    Среднее время запроса: ${httpDur ? fmtMs(httpDur.avg) : 'н/д'}`);
  lines.push(`    95-й перцентиль: ${httpDur ? fmtMs(httpDur['p(95)']) : 'н/д'}`);
  lines.push(`    Виртуальных пользователей (макс.): ${vuMax ? vuMax.max : 'н/д'}`);
  lines.push(`    Выполнено итераций: ${iters ? iters.count : 'н/д'}`);
  lines.push('');

  const p95 = httpDur ? httpDur['p(95)'] : null;
  const okTime = p95 !== null && p95 < 5000;
  const okErr = failRate !== null && failRate < 0.05;
  const okChecks = checks !== null && checks >= 0.95;
  const okStable = checks !== null && checks >= 0.9;

  lines.push('🔧  ДИАГНОСТИКА И РЕКОМЕНДАЦИИ');
  lines.push(
    `    ${okTime ? '✅' : '❌'} Время ответа (p95 < 5 с для типового стенда): ${okTime ? 'в пределах ориентира' : 'выше ориентира'}`,
  );
  lines.push(
    `    ${okErr ? '✅' : '❌'} Уровень ошибок HTTP (< 5%): ${okErr ? 'в норме' : 'повышен'}`,
  );
  lines.push(
    `    ${okChecks ? '✅' : '❌'} Доля успешных проверок (≥ 95%): ${okChecks ? 'в норме' : 'ниже ориентира'}`,
  );
  lines.push(
    `    ${okStable ? '✅' : '❌'} Стабильность сценария (вход → заявки): ${okStable ? 'приемлемо' : 'есть провалы'}`,
  );
  lines.push('');

  return lines.join('\n');
}

function login() {
  const creds = credentialsForVu();

  const resGet = http.get(`${BASE_URL}/login`);
  recordStep(stepLoginGetVisits, stepLoginGetDur, resGet);
  const okGet = check(resGet, {
    'login page 200': (r) => r.status === 200,
  });
  if (!okGet) {
    return false;
  }

  const token = getCsrfToken(resGet.body);
  if (!token) {
    return false;
  }

  const body = `_token=${encodeURIComponent(token)}&email=${encodeURIComponent(
    creds.email,
  )}&password=${encodeURIComponent(creds.password)}`;

  const resPost = http.post(`${BASE_URL}/login`, body, {
    headers: {
      ...formHeaders,
      Referer: `${BASE_URL}/login`,
      Origin: BASE_URL,
    },
    redirects: 10,
  });
  recordStep(stepLoginPostVisits, stepLoginPostDur, resPost);

  const loggedIn = check(resPost, {
    'login reaches dashboard': (r) =>
      r.status === 200 &&
      typeof r.url === 'string' &&
      r.url.includes('/dashboard'),
  });

  return loggedIn;
}

export default function () {
  if (!login()) {
    sleep(1);
    return;
  }

  const dash = http.get(`${BASE_URL}/dashboard`);
  recordStep(stepDashboardVisits, stepDashboardDur, dash);
  check(dash, {
    'dashboard 200': (r) => r.status === 200,
  });

  const apps = http.get(`${BASE_URL}/applications`);
  recordStep(stepApplicationsVisits, stepApplicationsDur, apps);
  check(apps, {
    'applications index 200': (r) => r.status === 200,
  });

  const ids = extractApplicationIds(apps.body);
  const pick = ids.length ? ids[__VU % ids.length] : __ENV.K6_APPLICATION_ID || '1';

  const show = http.get(`${BASE_URL}/applications/${pick}`);
  recordStep(stepApplicationShowVisits, stepApplicationShowDur, show);
  check(show, {
    'application show 200': (r) => r.status === 200,
  });

  sleep(QUICK ? 0.5 : 1);
}

export function handleSummary(data) {
  return {
    stdout: buildConsoleReport(data),
    'load-tests/k6-summary.json': JSON.stringify(data, null, 2),
  };
}
