# Account Already Exists Flow - Complete Guide

## Overview

When a user tries to create a new profile but an account already exists with their phone number/email, the system detects this and guides them through a special flow to access their existing account.

## Flow Diagram

```
User Attempts Profile Generation
    ↓
System Checks if Account Already Exists
    ├─ YES → Existing Account Detected
    │   ↓
    │   Send Message: "We found an existing account"
    │   Ask: "Is this your account?"
    │   ↓
    │   User Replies:
    │   ├─ "YES" → Show Account Details & Dashboard Link
    │   ├─ "HUMAN" → Ask for Verification → Show Dashboard Options
    │   └─ "NO" → Contact Support
    │
    └─ NO → Proceed with New Profile Generation
```

## Detailed User Interactions

### Step 1: Account Already Exists Detected

**Message sent to user:**
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

### Step 2a: User Replies "YES"

**Message sent to user:**
```
✅ Account Found!

👤 Name: Santosh Sharma
📧 Email: santosh@example.com
🆔 User ID: TUTOR_XXX_123

Your dashboard link:
https://dashboard.nxtutors.com/?login_token=...

Click to login and manage your profile!
```

### Step 2b: User Replies "HUMAN" (or any human handoff request)

**Message sent to user:**
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

#### Option 1: User Replies "1" (Login to Dashboard)

**Message sent to user:**
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

#### Option 2: User Replies "2" (Contact Support)

**Message sent to user:**
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

### Step 2c: User Replies "NO"

**Message sent to user:**
```
We understand! 

If you need to create a new account with a different phone number or email, please start the onboarding process again.

📞 For questions, contact our support team:
support@nxtutors.com

We're here to help!
```

## API Endpoints

### 1. Check for Existing Account

**Endpoint:** `POST /api/nx-whatsapp-onboarding/account/check-existing`

**Request:**
```json
{
  "conversation_id": 123
}
```

**Response (Account Exists):**
```json
{
  "found": true,
  "user_id": "TUTOR_XXX_123",
  "email": "santosh@example.com",
  "phone": "+91XXXXXXXXXX",
  "name": "Santosh Sharma",
  "role": "tutor",
  "message_sent": true
}
```

**Response (No Account):**
```json
{
  "found": false,
  "error": "Account not found"
}
```

### 2. Handle Human Request

**Endpoint:** `POST /api/nx-whatsapp-onboarding/account/handle-human`

**Request:**
```json
{
  "conversation_id": 123,
  "user_input": "human"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Sent dashboard options",
  "user_id": "TUTOR_XXX_123"
}
```

### 3. Handle Dashboard Choice

**Endpoint:** `POST /api/nx-whatsapp-onboarding/account/dashboard-choice`

**Request:**
```json
{
  "conversation_id": 123,
  "choice": "1"
}
```

**Response (Choice 1 - Login):**
```json
{
  "success": true,
  "action": "dashboard_link_sent",
  "dashboard_link": "https://dashboard.nxtutors.com/?login_token=...",
  "user_id": "TUTOR_XXX_123"
}
```

**Response (Choice 2 - Support):**
```json
{
  "success": true,
  "action": "support_message_sent",
  "user_id": "TUTOR_XXX_123"
}
```

## Implementation Details

### Services Created

**ExistingAccountService** (`src/Profile/Services/ExistingAccountService.php`)

**Methods:**
- `handleExistingAccount(OnboardingConversation $conversation): array` - Detects and sends initial message
- `handleHumanRequest(OnboardingConversation $conversation, string $userInput): array` - Handles human handoff
- `handleDashboardChoice(OnboardingConversation $conversation, string $choice): array` - Processes option selection

**Private Methods:**
- `sendExistingAccountMessage()` - Sends "account found" message
- `sendDashboardOptions()` - Sends "choose option" message
- `sendDashboardLink()` - Sends dashboard link with magic login token
- `sendSupportMessage()` - Sends support contact information

### Controllers Created

**ExistingAccountController** (`src/Profile/Controllers/ExistingAccountController.php`)

**Methods:**
- `checkExisting(Request $request): JsonResponse` - API endpoint to check for existing account
- `handleHumanRequest(Request $request): JsonResponse` - API endpoint for human handoff
- `handleDashboardChoice(Request $request): JsonResponse` - API endpoint for dashboard choice

## Integration with WhatsApp Agent

The system uses:
- **MessageSenderInterface** for WhatsApp delivery
- **RegisterRepository** to find existing accounts
- **DashboardLinkService** for magic login tokens
- **OnboardingConversation** model for user context

All messages are logged with `event_type` for tracking:
- `existing_account_detected` - Initial detection
- `dashboard_options_sent` - Options provided
- `dashboard_link_sent` - Link delivered
- `support_message_sent` - Support info delivered

## Duplicate Detection

The system checks for existing accounts by:

1. **Phone Number** - Primary identifier
2. **Email Address** - Secondary check
3. **Document Number** - Tertiary check (for tutors)

If any match is found, the existing account flow is triggered.

## Testing

### Test Scenario 1: Account Already Exists

```bash
# 1. Check if account exists
curl -X POST http://localhost:8000/api/nx-whatsapp-onboarding/account/check-existing \
  -H "Content-Type: application/json" \
  -d '{"conversation_id": 1}'

# Expected: Account found with details

# 2. Handle human request
curl -X POST http://localhost:8000/api/nx-whatsapp-onboarding/account/handle-human \
  -H "Content-Type: application/json" \
  -d '{
    "conversation_id": 1,
    "user_input": "human"
  }'

# Expected: Dashboard options sent

# 3. Handle choice (Option 1 - Login)
curl -X POST http://localhost:8000/api/nx-whatsapp-onboarding/account/dashboard-choice \
  -H "Content-Type: application/json" \
  -d '{
    "conversation_id": 1,
    "choice": "1"
  }'

# Expected: Dashboard link sent
```

### Test Scenario 2: Account Does Not Exist

```bash
curl -X POST http://localhost:8000/api/nx-whatsapp-onboarding/account/check-existing \
  -H "Content-Type: application/json" \
  -d '{"conversation_id": 999}'

# Expected: Account not found - proceed with new profile creation
```

## Database Queries

### Find Existing Accounts

```sql
-- Find by phone number
SELECT * FROM register WHERE phone = '+91XXXXXXXXXX';

-- Find by email
SELECT * FROM register WHERE email = 'user@example.com';

-- Find by document number
SELECT * FROM register WHERE document_number = 'XXXXX12345';

-- Find all profiles for a user
SELECT * FROM register WHERE user_id = 'TUTOR_XXX_123';
```

### Track Account Detection Events

```sql
-- View all account detection messages
SELECT * FROM onboarding_events 
WHERE event_type = 'existing_account_detected'
ORDER BY created_at DESC;

-- View all dashboard options sent
SELECT * FROM onboarding_events 
WHERE event_type = 'dashboard_options_sent'
ORDER BY created_at DESC;

-- View all support requests
SELECT * FROM onboarding_events 
WHERE event_type = 'support_message_sent'
ORDER BY created_at DESC;
```

## Error Handling

### Account Not Found During Human Request

If the system can't find the account during human request:
```
I couldn't find your account. Please reply with:

📧 Your email address
OR
📱 Your user ID

This will help me locate your account.
```

### Invalid Dashboard Choice

If user enters invalid option:
```
Please reply with:

1 - Login to Dashboard
2 - Contact Support
```

## Security Features

✅ **Magic Login Tokens** - No password sent in WhatsApp  
✅ **Phone Verification** - Uses WhatsApp phone as primary identifier  
✅ **Account Confirmation** - User confirms it's their account  
✅ **Audit Logging** - All interactions logged in database  
✅ **Rate Limiting** - Prevents abuse  
✅ **PII Masking** - Personal data masked in logs  

## Monitoring

### View Recent Account Detections

```bash
# Check logs
tail -f storage/logs/laravel.log

# Query database
sqlite3 database.sqlite "
  SELECT 
    oe.created_at,
    oe.wa_phone,
    oe.event_type,
    oc.role
  FROM onboarding_events oe
  JOIN onboarding_conversations oc ON oe.wa_phone = oc.wa_phone
  WHERE oe.event_type LIKE 'existing%' OR oe.event_type LIKE 'dashboard%'
  ORDER BY oe.created_at DESC
  LIMIT 20;
"
```

## Related Files

- `src/Profile/Repositories/RegisterRepository.php` - Find existing accounts
- `src/Profile/Services/DashboardLinkService.php` - Generate magic login links
- `src/WhatsApp/Services/MetaMessageSender.php` - Send WhatsApp messages
- `routes/api.php` - API endpoints (updated)

## Flow Summary

| User Input | System Action | Message Sent |
|------------|---------------|--------------|
| Account exists + clicks form | Detect existing account | "We found an existing account" |
| "YES" | Verify account | Dashboard link |
| "HUMAN" | Ask for help | "What would you like to do?" |
| "1" (Login) | Send link | Dashboard access link |
| "2" (Support) | Route to support | Support contact info |
| "NO" | Decline | Contact support info |

## Benefits

✅ **User Experience** - Users with existing accounts aren't forced to create duplicates  
✅ **Data Integrity** - Prevents duplicate profiles  
✅ **Support** - Reduces support tickets for "account creation failed"  
✅ **Compliance** - One profile per user maintained  
✅ **Security** - Verification step prevents unauthorized access  
✅ **Recovery** - Helps users recover forgotten account access  

## Next Steps

1. Deploy the code
2. Test with existing accounts
3. Monitor webhook events
4. Adjust messaging as needed
5. Train support team on new flow

Refer to `PROFILE_GENERATION_GUIDE.md` for related features.
