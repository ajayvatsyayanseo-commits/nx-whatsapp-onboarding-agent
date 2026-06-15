# NXtutors Website Integration Guide

This guide is for the current NXtutors Laravel 12 website.

Current website facts:

- Database is MySQL.
- Account table is `register`.
- Login is email + password.
- Login URL is `https://www.nxtutors.com/login`.
- Student dashboard is `https://www.nxtutors.com/user/dashboard`.
- Tutor dashboard is `https://www.nxtutors.com/teacher/dashboard`.
- `register.status` uses `t`.
- `register.otp_status` uses `t`.
- Student `join_as` is `student`.
- Tutor `join_as` is `teacher`.
- Tutor `user_type` defaults to `Individual`.
- `c_password` is not used for login and the WhatsApp agent must not store plaintext there.
- Website WhatsApp number is `917836034313`.

## A. General Chat On WhatsApp Button

Use:

```text
https://wa.me/917836034313?text=Hey%20NXtutors%2C%20I%20want%20to%20signup
```

## B. Student Signup Button

Use:

```text
https://wa.me/917836034313?text=Hey%20NXtutors%2C%20I%20want%20to%20signup%20as%20a%20Student
```

## C. Tutor Signup Button

Use:

```text
https://wa.me/917836034313?text=Hey%20NXtutors%2C%20I%20want%20to%20signup%20as%20a%20Tutor
```

## D. New Tutor Partner Form

Required website changes:

- Add proper `name` or `id` attributes to inputs.
- Require the terms checkbox.
- Terms link must go to `/terms-conditions`, not `#`.
- Privacy link should go to `/privacy-policy`.
- On `Apply on WhatsApp`, build an encoded `wa.me` URL with field values.
- Do not rely on the frontend terms checkbox as legal acceptance for account creation. The WhatsApp agent will still show Terms/Privacy and require `I AGREE`.

Example JavaScript:

```js
function applyTutorPartnerOnWhatsApp() {
  const name = document.querySelector('[name="name"]').value.trim();
  const subjects = document.querySelector('[name="subjects"]').value.trim();
  const classes = document.querySelector('[name="classes"]').value.trim();
  const experience = document.querySelector('[name="experience"]').value.trim();
  const location = document.querySelector('[name="location"]').value.trim();
  const mode = document.querySelector('[name="preferred_mode"]').value.trim();
  const rate = document.querySelector('[name="hourly_rate"]').value.trim();
  const availability = document.querySelector('[name="availability"]').value.trim();
  const phone = document.querySelector('[name="whatsapp_number"]').value.trim();
  const terms = document.querySelector('[name="terms"]').checked;

  if (!terms) {
    alert('Please accept NXtutors terms before continuing.');
    return;
  }

  const message = [
    'Hey NXtutors, I want to signup as a Tutor Partner.',
    `Name: ${name}`,
    `Subjects: ${subjects}`,
    `Classes: ${classes}`,
    `Experience: ${experience}`,
    `Location: ${location}`,
    `Preferred mode: ${mode}`,
    `Hourly rate: ${rate}`,
    `Availability: ${availability}`,
    `WhatsApp number: ${phone}`,
  ].join('\n');

  window.open(`https://wa.me/917836034313?text=${encodeURIComponent(message)}`, '_blank');
}
```

## E. Single Course Page WhatsApp Number

Replace hardcoded WhatsApp numbers with a setting, for example:

```php
config('services.nxtutors.whatsapp_number', '917836034313')
```

## F. Login Behavior

The website currently logs in with:

```text
register.email + password
```

So the WhatsApp agent sends:

```text
Login page
Login email
Temporary password
Dashboard URL
```

Do not tell users to login with phone unless the website developer adds phone login support and sets:

```env
NXTUTORS_LOGIN_IDENTIFIER=phone
```

or:

```env
NXTUTORS_LOGIN_IDENTIFIER=both
```

## G. Recommended Website Backend Fixes

Ask the website developer to:

- Stop storing plaintext `c_password` in old website signup code.
- Keep WhatsApp-agent-created `c_password` as `null` or empty string.
- Add force-password-reset logic after WhatsApp signup.
- Add phone login only if the business wants phone login.
- Add unique indexes after duplicate cleanup.
- Fix the old `user_id = last row + 1` race condition.
- Ensure uploaded files stored in `public/storage/user` are served safely.

## H. Agent Environment Values For Current Website

```env
DB_CONNECTION=mysql
QUEUE_CONNECTION=database
CACHE_STORE=file
NXTUTORS_LEGACY_WEBSITE_MODE=true
NXTUTORS_LOGIN_IDENTIFIER=email
NXTUTORS_STUDENT_JOIN_AS=student
NXTUTORS_TUTOR_JOIN_AS=teacher
NXTUTORS_STUDENT_USER_TYPE=student
NXTUTORS_TUTOR_USER_TYPE=Individual
NXTUTORS_STATUS_ACTIVE_VALUE=t
NXTUTORS_OTP_VERIFIED_VALUE=t
NXTUTORS_USER_ID_MODE=legacy_numeric
NXTUTORS_STORE_C_PASSWORD=false
WHATSAPP_CREATE_REAL_PROFILE=false
WHATSAPP_PHONE_NUMBER=917836034313
WHATSAPP_ONBOARDING_LOGIN_URL=https://www.nxtutors.com/login
STUDENT_DASHBOARD_URL=https://www.nxtutors.com/user/dashboard
TUTOR_DASHBOARD_URL=https://www.nxtutors.com/teacher/dashboard
TERMS_STUDENT_URL=https://www.nxtutors.com/terms-conditions
TERMS_TUTOR_URL=https://www.nxtutors.com/terms-conditions
PRIVACY_STUDENT_URL=https://www.nxtutors.com/privacy-policy
PRIVACY_TUTOR_URL=https://www.nxtutors.com/privacy-policy
MEDIA_STORAGE_DRIVER=legacy_public_user
WHATSAPP_ONBOARDING_LOCAL_MEDIA_PATH=storage/user
WHATSAPP_ONBOARDING_MEDIA_DB_VALUE=filename_only
```
