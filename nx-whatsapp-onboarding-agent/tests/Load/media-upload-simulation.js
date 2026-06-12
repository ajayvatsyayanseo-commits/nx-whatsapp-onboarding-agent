import http from 'k6/http';
import { check } from 'k6';

export const options = { vus: 10, duration: '30s' };

export default function () {
  const payload = JSON.stringify({
    entry: [{ changes: [{ value: { messages: [{ id: `wamid.media.${__VU}.${__ITER}`, from: '919999999999', type: 'image', timestamp: `${Date.now() / 1000}`, image: { id: 'media-id', mime_type: 'image/jpeg', file_size: 1024 } }] } }] }],
  });
  const res = http.post(`${__ENV.BASE_URL}/whatsapp/onboarding/webhook`, payload, { headers: { 'Content-Type': 'application/json' } });
  check(res, { 'media webhook returns': (r) => r.status === 200 || r.status === 403 });
}
