import http from 'k6/http';
import { check } from 'k6';

export const options = { vus: 25, iterations: 250 };

export default function () {
  const payload = JSON.stringify({
    entry: [{ changes: [{ value: { messages: [{ id: 'wamid.duplicate.load', from: '919999999999', type: 'text', timestamp: `${Date.now() / 1000}`, text: { body: 'signup' } }] } }] }],
  });
  const res = http.post(`${__ENV.BASE_URL}/whatsapp/onboarding/webhook`, payload, { headers: { 'Content-Type': 'application/json' } });
  check(res, { 'dedupe path returns': (r) => r.status === 200 || r.status === 403 });
}
