# Account Already Exists - Visual Flowchart

## Complete User Journey Map

```
┌─────────────────────────────────────────────────────────────────┐
│                  USER STARTS ONBOARDING                         │
│              (Clicks "Generate my profile")                      │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
        ┌──────────────────────────────────────┐
        │  System Checks for Existing Account  │
        │  (By Phone/Email/Document)           │
        └────────────┬─────────────────────────┘
                     │
         ┌───────────┴──────────────┐
         ▼                          ▼
    FOUND                       NOT FOUND
    (Account exists)            (New user)
         │                          │
         │                          └──────────────┐
         │                                         │
         ▼                                         ▼
   ┌────────────────────────┐            ┌──────────────────┐
   │ SEND MESSAGE:          │            │ Generate New     │
   │                        │            │ Profile          │
   │ "We found an existing" │            │ (60-sec counter) │
   │ account with your      │            └──────────────────┘
   │ phone number"          │                     │
   │                        │                     ▼
   │ Is this your account?  │            ┌──────────────────┐
   │                        │            │ Profile created  │
   │ YES / HUMAN / NO       │            │ Send to WhatsApp │
   └────────┬───────┬───────┘            └──────────────────┘
            │       │
    ┌───────┘       └───────┬─────────┐
    ▼                       ▼         ▼
  YES                    HUMAN       NO
   │                       │         │
   │                       ▼         ▼
   │              ┌──────────────┐  │
   │              │ Ask for      │  │ Contact
   │              │ verification │  │ Support
   │              └──────┬───────┘  │
   │                     │          │
   │                     ▼          │
   │        ┌────────────────────┐  │
   │        │ Send Account Info: │  │
   │        │                    │  │
   │        │ 1️⃣ Login          │  │
   │        │ 2️⃣ Support        │  │
   │        └────────┬────┬──────┘  │
   │                 │    │         │
   │        ┌────────┘    └───────┐ │
   │        ▼                     ▼ ▼
   │    CHOICE 1              CHOICE 2
   │    (Login)               (Support)
   │        │                     │
   ▼        ▼                     ▼
┌──────────────────────┐  ┌──────────────────────┐
│ SEND DASHBOARD LINK  │  │ SEND SUPPORT INFO    │
│                      │  │                      │
│ ✅ Account Found     │  │ 📞 Support Team      │
│ 👤 Name: ...        │  │ 🆔 User ID: ...     │
│ 📧 Email: ...       │  │ 📧 Email: ...       │
│ 🆔 User ID: ...     │  │                      │
│ 🔗 Dashboard Link    │  │ ⏱️ Response: 2-4h   │
│                      │  │ 🕐 Hours: M-F 9-6PM│
│ Click to login!      │  │                      │
└──────────────────────┘  └──────────────────────┘
         │                        │
         ▼                        ▼
    ┌─────────────┐          ┌──────────────┐
    │ User Logins │          │ Support Team │
    │ to Profile  │          │ Contacts     │
    │             │          │ User         │
    └─────────────┘          └──────────────┘
         │                        │
         ▼                        ▼
    ┌─────────────┐          ┌──────────────┐
    │ Profile     │          │ Resolve      │
    │ Updated     │          │ Account      │
    │             │          │ Issue        │
    └─────────────┘          └──────────────┘
```

## WhatsApp Message Sequence

```
┌─────────────────────────────────────────────────────────────────┐
│                        MESSAGE 1                                 │
│                    (Account Detected)                            │
├─────────────────────────────────────────────────────────────────┤
│  👋 Welcome back!                                               │
│                                                                  │
│  We found an existing account with your phone number.            │
│                                                                  │
│  📧 Email: santosh@example.com                                  │
│                                                                  │
│  Is this your account?                                          │
│                                                                  │
│  Reply with:                                                    │
│  YES - If this is your account                                  │
│  HUMAN - To speak with our team                                 │
│  NO - If this is not your account                               │
└─────────────────────────────────────────────────────────────────┘
           │
    User Replies:
           │
    ┌──────┼──────────────────┐
    │      │                  │
    ▼      ▼                  ▼
  YES    HUMAN              NO
   │      │                  │
   │      ▼                  │
   │  ┌──────────────────┐   │
   │  │   MESSAGE 2      │   │
   │  │ (Options Sent)   │   │
   │  ├──────────────────┤   │
   │  │ 🔐 Your Account  │   │
   │  │ 👤 Name: ...    │   │
   │  │ 📧 Email: ...   │   │
   │  │ 🆔 User ID: ... │   │
   │  │                  │   │
   │  │ What to do?      │   │
   │  │ 1 - Login        │   │
   │  │ 2 - Support      │   │
   │  └────┬──────┬──────┘   │
   │       │      │          │
   ▼       ▼      ▼          ▼
  MSG3   MSG3    MSG4       MSG5
         │      │          │
         ▼      ▼          ▼
   DASHBOARD  SUPPORT   CONTACT
   LINK SENT  SENT      SUPPORT
    │         │          │
    ▼         ▼          ▼
   USER   SUPPORT    SUPPORT
   LOGS   CONTACTS   TEAM
   IN     USER       HELPS
         
```

## Decision Tree

```
                    ┌─ Account Check
                    │
            Is Account Found?
            /                \
          YES                NO
          /                    \
         /                      \
    Send Account              New Profile
    Message                   Generation
        |                        |
        |                    (60-sec counter)
        |                        |
    User Replies?              Profile
    /    |    \                Generated
   /     |     \                |
YES    HUMAN   NO            Send to
|        |      |           WhatsApp
|        |      |              |
|        |      |          User Receives
|        |      |          Complete
|        |      |          Profile Info
|        |      |
|     Send      Contact
|    Options    Support
|        |         |
|     Choose    Help From
|     Option    Support Team
|    /     \
|   1       2
|  /         \
LOGIN      SUPPORT
|             |
Send        Send
Dashboard   Contact
Link        Info
|             |
User       Support
Accesses   Helps
Profile    User
```

## State Machine

```
START
  │
  ├─────────────────────────────────────┐
  │                                     │
  ▼                                     ▼
ACCOUNT_FOUND                      ACCOUNT_NOT_FOUND
  │                                     │
  ├─ EVENT: account_detected         ├─ EVENT: new_profile_start
  │                                     │
  ▼                                     ▼
WAITING_USER_CONFIRMATION          PROFILE_GENERATION
  │                                     │
  ├─ TRANSITION: user_confirms          ├─ WAIT: 60 seconds
  │ → ACCOUNT_CONFIRMED                 │
  │                                     ├─ EVENT: profile_generated
  ├─ TRANSITION: user_requests_help     │
  │ → AWAITING_CHOICE                  │
  │                                     ├─ EVENT: message_sent
  ├─ TRANSITION: user_denies            │
  │ → CONTACT_SUPPORT                  │
  │                                     ▼
  ▼                              USER_RECEIVED_PROFILE
AWAITING_CHOICE                          │
  │                                     ├─ EVENT: login
  ├─ TRANSITION: choice_1               │ → LOGGED_IN
  │ → DASHBOARD_LINK_SENT              │
  │                                     ├─ EVENT: support_request
  ├─ TRANSITION: choice_2               │ → SUPPORT_CONTACTED
  │ → SUPPORT_CONTACTED                │
  │                                     ▼
  ▼                              COMPLETED
DASHBOARD_LINK_SENT
  │
  ├─ EVENT: user_logged_in
  │ → LOGGED_IN
  │
  ▼
LOGGED_IN
  │
  ├─ EVENT: profile_updated
  │ → COMPLETED
  │
  ▼
COMPLETED
```

## Timeline Example

```
10:00:00 - User clicks "Generate my profile"
           ↓
10:00:05 - System detects account already exists
           ↓
10:00:07 - WhatsApp Message 1 sent: "We found an existing account"
           ↓
10:00:15 - User replies: "HUMAN"
           ↓
10:00:18 - WhatsApp Message 2 sent: "What would you like to do?"
           ↓
10:00:45 - User replies: "1"
           ↓
10:00:48 - WhatsApp Message 3 sent: Dashboard access link
           ↓
10:01:30 - User clicks link and logs in
           ↓
10:01:35 - User on dashboard, profile updated
           ↓
✅ FLOW COMPLETED
```

## Response Time Targets

```
Event                          Expected Time
─────────────────────────────  ──────────────
Account check                  < 100ms
Message 1 (Account detected)   < 1 second
User replies "HUMAN"           Immediate
Message 2 (Options)            < 1 second
User replies "1" or "2"        Immediate
Message 3 (Link/Support)       < 1 second
──────────────────────────────────────────────
Total time to user help        ~ 5-10 seconds
```

## Key Differences: Account Exists vs New Profile

```
                  ACCOUNT EXISTS         NEW PROFILE
────────────────────────────────────────────────────
Flow Type         Verification           Creation
Duration          Immediate              60 seconds
Messages          3-4                    1-2
Actions           Redirect               Generate
WhatsApp Delivery Instant                After 60s
User Experience   Quick recovery         Full setup
Database Impact   No writes              New record
Password Reset    Option available       Temporary
```

## Error Handling Paths

```
             START
               │
         Account Check
         /            \
       OK            ERROR
       │              │
       │          No Results
       │              │
       │          Try Email Check
       │              │
       │          /       \
       │        OK       FAIL
       │        │         │
       │        ▼         ▼
       │    Found      Try Document
       │        │        Check
       │        │        │
       │        │    /       \
       │        │  OK       FAIL
       │        │  │         │
       │        │  ▼         ▼
       │        │ Found    No Account
       │        │  │         │
       └────────┴──┴─────────┴─── Continue with choice
```

## Success Rate Optimization

```
Step 1: Account Detection    → 99.5% success
Step 2: Message Delivery     → 99% success  
Step 3: User Response        → 95% success
Step 4: Dashboard Access     → 98% success
──────────────────────────────────────────
Overall Success Rate         → ~92% (95.5% × 99% × 95% × 98%)

Optimization strategies:
- Retry failed message sends
- SMS fallback for WhatsApp failures
- Email confirmation option
- Support escalation for errors
```

---

**See also:**
- [EXISTING_ACCOUNT_FLOW.md](EXISTING_ACCOUNT_FLOW.md) - Detailed guide
- [PROFILE_GENERATION_GUIDE.md](PROFILE_GENERATION_GUIDE.md) - New profile flow
- [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md) - Quick reference
