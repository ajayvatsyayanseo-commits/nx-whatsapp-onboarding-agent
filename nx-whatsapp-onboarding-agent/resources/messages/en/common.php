<?php

declare(strict_types=1);

return [
    'welcome_choose_role' => "Welcome to NXtutors. Please choose signup type:\n1. As a Student\n2. As a Tutor",
    'signup_hint' => 'Welcome to NXtutors. Reply signup to start your student or tutor registration.',
    'signup_disabled' => 'WhatsApp signup is currently unavailable. Please use the NXtutors website or contact support.',
    'role_disabled' => 'This signup type is currently unavailable on WhatsApp. Our team can help you continue.',
    'reply_help' => 'Please reply with the requested detail, or type HELP for support.',
    'review_title' => 'Here is your profile summary:',
    'review_footer' => 'Sensitive details are masked.',
    'review_confirm_prompt' => 'Reply CONFIRM to continue, or EDIT field_name.',
    'current_step_help' => 'Current step: :step. You can reply BACK, REVIEW, CANCEL, or HUMAN.',
    'cancelled' => 'Your NXtutors signup has been cancelled. Reply signup any time to start again.',
    'restart_confirm' => 'Restart will clear this draft. Reply CONFIRM to restart, or any other message to continue.',
    'restart_not_confirmed' => 'Restart was not confirmed. You can continue from the current step.',
    'restarted' => "Okay, let's restart your signup. Please choose:\n1. As a Student\n2. As a Tutor",
    'skip_not_allowed' => 'This field is required, so SKIP is not available here.',
    'back_not_available' => 'There is no previous field to go back to.',
    'edit_unknown' => 'I could not find that field. Try REVIEW and then reply EDIT field_name.',
    'duplicate_conflict' => 'This :field already appears to be linked to an NXtutors account. Our team will help you safely.',
    'duplicate_phone_login_help' => 'An NXtutors account already exists with this WhatsApp number. Please login here: https://www.nxtutors.com/login. If you need help, reply HUMAN.',
    'duplicate_email_try_again' => 'That email is already linked to an NXtutors account. Please share another email.',
    'duplicate_document_review' => "This document needs manual review by NXtutors team. I'm creating a support ticket.",
    'profile_create_trouble' => 'We are having trouble finishing signup. Our team will help you soon.',
    'terms_prompt' => "Please read NXtutors Terms and Privacy Policy before account creation:\n\nTerms: :url\nPrivacy: :privacy_url\n\nReply I AGREE to continue.",
    'otp_prompt' => 'We sent your NXtutors verification OTP. It expires soon. Do not share it with anyone.',
    'handoff' => "I'm connecting you with the NXtutors team. Your ticket ID is :ticket_id.",
    'stopped' => 'You are unsubscribed from non-transactional WhatsApp messages. Signup transaction messages may still be sent when you request them.',
    'signup_complete' => "Your NXtutors account is ready.\nLogin page: :login_url\nLogin email: :email\nTemporary password: :password\nDashboard after login: :dashboard\n\nFirst login, then dashboard will open.\nPlease change your password after login.\n\n:checklist",
    'signup_complete_tutor_pending' => "Your NXtutors tutor account is ready.\nLogin page: :login_url\nLogin email: :email\nTemporary password: :password\nTutor dashboard after login: :dashboard\n\nFirst login, then dashboard will open.\nPlease complete your tutor profile/documents after login.\n\n:checklist",
];
