import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  vus: 50,
  duration: '1m',
};

export default function () {
  const payload = JSON.stringify({
    entry: [{ changes: [{ value: { messages: [{ id: `wamid.${__VU}.${__ITER}`, from: '919999999999', type: 'text', timestamp: `${Date.now() / 1000}`, text: { body: 'signup' } }] } }] }],
  });
  const res = http.post(`${__ENV.BASE_URL}/whatsapp/onboarding/webhook`, payload, { headers: { 'Content-Type': 'application/json' } });
  check(res, { 'accepted quickly': (r) => r.status === 200 || r.status === 403 });
  sleep(1);
}
