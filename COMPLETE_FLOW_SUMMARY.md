# Complete Onboarding Flow - All Scenarios

## What Happens When User Clicks "Generate my profile"

### Scenario 1: NEW USER (No Account Exists)

```
User: Clicks "Generate my profile"
         ↓
System: Checks for existing account
         ↓
System: ✅ No account found - Proceed with new profile
         ↓
API Response: 
{
  "success": true,
  "message": "Profile generation started. You will receive details on WhatsApp in 60 seconds.",
  "counter_duration": 60
}
         ↓
Frontend: Shows 60-second countdown timer
         ↓
[After 60 seconds in background]
System: Generates profile
System: Creates login credentials
System: Generates dashboard link
System: Sends WhatsApp message:

✅ Profile Generated Successfully!

👤 Name: Santosh Sharma
📧 Email: santosh@example.com
📱 Phone: +91XXXXXXXXXX
🆔 User ID: TUTOR_XXX_123
🔐 Temporary Password: SecurePass123!

📚 Subjects: Maths for class 8-10th
💼 Experience: 5 years
📍 City: Gurgaon

🔗 Dashboard Link: https://dashboard.nxtutors.com/?login_token=...

⚠️ Please change your password after login for security.

         ↓
User: Receives WhatsApp message with all profile details
         ↓
✅ Flow Completed - New profile created!
```

---

### Scenario 2: ACCOUNT ALREADY EXISTS - User Confirms

```
User: Clicks "Generate my profile"
         ↓
System: Checks for existing account
         ↓
System: ⚠️ Account found! Send message:

👋 Welcome back!

We found an existing account with your phone number.

📧 Email: santosh@example.com

Is this your account?

Reply with:
YES - If this is your account
HUMAN - To speak with our team
NO - If this is not your account

         ↓
User: Replies "YES" ← User confirms it's their account
         ↓
System: Sends message:

✅ Account Found!

👤 Name: Santosh Sharma
📧 Email: santosh@example.com
🆔 User ID: TUTOR_XXX_123

Your dashboard link:
https://dashboard.nxtutors.com/?login_token=...

Click to login and manage your profile!

         ↓
User: Clicks dashboard link
         ↓
User: Logged in to their profile
         ↓
✅ Flow Completed - User accessed existing account!
```

---

### Scenario 3: ACCOUNT ALREADY EXISTS - User Requests Human Help

```
User: Clicks "Generate my profile"
         ↓
System: Checks for existing account
         ↓
System: ⚠️ Account found! Sends initial message
         ↓
User: Replies "HUMAN" ← User wants to speak with someone
         ↓
System: Sends message:

🔐 Your Account Details

👤 Name: Santosh Sharma
📧 Email: santosh@example.com
🆔 User ID: TUTOR_XXX_123

What would you like to do?

Reply with:
1 - 🔗 Login to Dashboard
2 - 📞 Contact Support Team

         ↓
User: Replies "1" (Login to Dashboard)
         ↓
System: Sends message:

✅ Dashboard Access

🔗 Your Dashboard Link:
https://dashboard.nxtutors.com/?login_token=...

📌 Instructions:
1️⃣ Click the link above
2️⃣ You'll be automatically logged in
3️⃣ Complete your profile setup

❓ Need help?
Reply with 'SUPPORT' to chat with our team.

         ↓
User: Clicks link and logs in
         ↓
✅ Flow Completed - User logged into dashboard!

---OR---

User: Replies "2" (Contact Support Team)
         ↓
System: Sends message:

📞 Support Team Available

Thank you for contacting us!

🆔 Your User ID: TUTOR_XXX_123
📧 Email: santosh@example.com
👤 Name: Santosh Sharma

Our support team will help you shortly.

📧 You can also email us:
support@nxtutors.com

⏱️ Response Time: Usually within 2-4 hours
🕐 Hours: Monday-Friday, 9AM-6PM

         ↓
Support Team: Reviews message and contacts user
         ↓
User: Receives help from support team
         ↓
✅ Flow Completed - User routed to support!
```

---

### Scenario 4: ACCOUNT ALREADY EXISTS - User Says NO

```
User: Clicks "Generate my profile"
         ↓
System: Checks for existing account
         ↓
System: ⚠️ Account found! Sends message:

👋 Welcome back!

We found an existing account with your phone number.
...
Is this your account?

Reply with:
YES - If this is your account
HUMAN - To speak with our team
NO - If this is not your account

         ↓
User: Replies "NO" ← This is not my account
         ↓
System: Sends message:

We understand!

If you need to create a new account with a different 
phone number or email, please start the onboarding 
process again.

📞 For questions, contact our support team:
support@nxtutors.com

We're here to help!

         ↓
User: Starts onboarding again with different phone/email
         ↓
✅ Flow Completed - User can create new account!
```

---

## Decision Tree Summary

| Scenario | User Action | System Response | Final Outcome |
|----------|-------------|-----------------|---------------|
| **NEW USER** | Generate Profile | 60-sec counter → Profile created → WhatsApp with details | ✅ Profile Ready |
| **EXISTS - YES** | Confirm Account | Account details + Dashboard link | ✅ User Logged In |
| **EXISTS - HUMAN + 1** | Request Help + Login | Account details → Dashboard link | ✅ User Logged In |
| **EXISTS - HUMAN + 2** | Request Help + Support | Support team routed | ✅ Support Contacted |
| **EXISTS - NO** | Deny Account | Direct to support | ✅ Can Create New |

---

## API Endpoints Summary

### For New Profile Generation

```bash
# Request
POST /api/nx-whatsapp-onboarding/profile/generate
{
  "conversation_id": 123,
  "role": "tutor"
}

# Response
{
  "success": true,
  "message": "Profile generation started...",
  "counter_duration": 60
}
```

### For Existing Account Check

```bash
# Request
POST /api/nx-whatsapp-onboarding/account/check-existing
{
  "conversation_id": 123
}

# Response (Account Exists)
{
  "found": true,
  "user_id": "TUTOR_XXX_123",
  "email": "santosh@example.com",
  "phone": "+91XXXXXXXXXX",
  "name": "Santosh Sharma",
  "message_sent": true
}

# Response (No Account)
{
  "found": false,
  "error": "Account not found"
}
```

### For Human Handoff

```bash
# Request
POST /api/nx-whatsapp-onboarding/account/handle-human
{
  "conversation_id": 123,
  "user_input": "human"
}

# Response
{
  "success": true,
  "message": "Sent dashboard options",
  "user_id": "TUTOR_XXX_123"
}
```

### For Dashboard Choice

```bash
# Request
POST /api/nx-whatsapp-onboarding/account/dashboard-choice
{
  "conversation_id": 123,
  "choice": "1"
}

# Response (Choice 1)
{
  "success": true,
  "action": "dashboard_link_sent",
  "dashboard_link": "https://dashboard.nxtutors.com/?login_token=...",
  "user_id": "TUTOR_XXX_123"
}

# Response (Choice 2)
{
  "success": true,
  "action": "support_message_sent",
  "user_id": "TUTOR_XXX_123"
}
```

---

## WhatsApp Messages Reference

### Message 1: Initial Detection (Account Exists)
```
👋 Welcome back!

We found an existing account with your phone number.

📧 Email: santosh@example.com

Is this your account?

Reply with:
YES - If this is your account
HUMAN - To speak with our team
NO - If this is not your account
```

### Message 2: Options After "HUMAN"
```
🔐 Your Account Details

👤 Name: Santosh Sharma
📧 Email: santosh@example.com
🆔 User ID: TUTOR_XXX_123

What would you like to do?

Reply with:
1 - 🔗 Login to Dashboard
2 - 📞 Contact Support Team
```

### Message 3a: Dashboard Link (Option 1)
```
✅ Dashboard Access

🔗 Your Dashboard Link:
https://dashboard.nxtutors.com/?login_token=...

📌 Instructions:
1️⃣ Click the link above
2️⃣ You'll be automatically logged in
3️⃣ Complete your profile setup

❓ Need help?
Reply with 'SUPPORT' to chat with our team.
```

### Message 3b: Support Info (Option 2)
```
📞 Support Team Available

Thank you for contacting us!

🆔 Your User ID: TUTOR_XXX_123
📧 Email: santosh@example.com
👤 Name: Santosh Sharma

Our support team will help you shortly.

📧 You can also email us:
support@nxtutors.com

⏱️ Response Time: Usually within 2-4 hours
🕐 Hours: Monday-Friday, 9AM-6PM
```

### Message 4: New Profile (After 60 seconds, New User)
```
✅ Profile Generated Successfully!

👤 Name: Santosh Sharma
📧 Email: santosh@example.com
📱 Phone: +91XXXXXXXXXX
🆔 User ID: TUTOR_XXX_123
🔐 Temporary Password: SecurePass123!

📚 Subjects: Maths for class 8-10th
💼 Experience: 5 years
📍 City: Gurgaon

🔗 Dashboard Link: https://dashboard.nxtutors.com/?login_token=...

⚠️ Please change your password after login for security.
```

---

## Time to Resolution

| Scenario | Steps | Time | Notes |
|----------|-------|------|-------|
| New Profile | 1. Click → 2. Wait 60s → 3. Receive | ~65 sec | Async generation |
| Confirm YES | 1. Click → 2. Reply YES → 3. Receive link | ~5 sec | Instant response |
| Human Handoff | 1. Click → 2. Reply HUMAN → 3. Choose → 4. Receive | ~10 sec | Multiple messages |
| Support Request | 1. Click → 2. Reply HUMAN+2 → 3. Support contacts | Variable | Human follow-up |

---

## Database Events Logged

```
event_type: 'existing_account_detected'      → Initial detection
event_type: 'dashboard_options_sent'         → Options message
event_type: 'dashboard_link_sent'            → Link sent
event_type: 'support_message_sent'           → Support info sent
event_type: 'profile_generated'              → Profile created
```

Check database:
```sql
SELECT * FROM onboarding_events 
WHERE wa_phone = '+91XXXXXXXXXX'
ORDER BY created_at DESC;
```

---

## Error Scenarios

### Account Not Found After "HUMAN"
```
User replies "HUMAN"
    ↓
System: Can't find account
    ↓
System: Sends message:

I couldn't find your account. Please reply with:

📧 Your email address
OR
📱 Your user ID

This will help me locate your account.
```

### Invalid Dashboard Choice
```
User replies: "hello"
    ↓
System: Invalid choice
    ↓
System: Sends message:

Please reply with:

1 - Login to Dashboard
2 - Contact Support
```

### WhatsApp Delivery Fails
```
System: Retries up to 3 times with exponential backoff
        ↓
        If still fails: Message logged in database
        ↓
        Admin notification sent
        ↓
        Can be manually resent
```

---

## Files Created

```
Backend Services:
├── src/Profile/Services/ProfileGenerationService.php
├── src/Profile/Services/ExistingAccountService.php
├── src/Profile/Controllers/ProfileGenerationController.php
└── src/Profile/Controllers/ExistingAccountController.php

Frontend:
└── resources/views/profile_generation.html

Routes:
├── routes/api.php (updated)
└── routes/web.php (created)

Queue Jobs:
└── src/Queue/Jobs/SendProfileGeneratedMessageJob.php

Documentation:
├── PROFILE_GENERATION_GUIDE.md
├── EXISTING_ACCOUNT_FLOW.md
├── ACCOUNT_EXISTS_FLOWCHART.md
└── COMPLETE_FLOW_SUMMARY.md (this file)
```

---

## Testing Checklist

- [ ] New profile generation (60-sec counter)
- [ ] Account exists → YES → Dashboard link
- [ ] Account exists → HUMAN → Options → 1 → Dashboard
- [ ] Account exists → HUMAN → Options → 2 → Support
- [ ] Account exists → NO → Support info
- [ ] WhatsApp messages received correctly
- [ ] Dashboard links work with magic tokens
- [ ] Temporary passwords function
- [ ] Error messages display properly
- [ ] Events logged in database

---

## Next Steps

1. Deploy code to production
2. Start queue worker: `php artisan queue:work whatsapp-notifications`
3. Test all scenarios
4. Monitor WhatsApp delivery
5. Review user feedback
6. Train support team on new flows

---

## Related Documentation

- [PROFILE_GENERATION_GUIDE.md](PROFILE_GENERATION_GUIDE.md) - New profile flow details
- [EXISTING_ACCOUNT_FLOW.md](EXISTING_ACCOUNT_FLOW.md) - Account exists flow details
- [ACCOUNT_EXISTS_FLOWCHART.md](ACCOUNT_EXISTS_FLOWCHART.md) - Visual diagrams
- [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md) - Technical overview
