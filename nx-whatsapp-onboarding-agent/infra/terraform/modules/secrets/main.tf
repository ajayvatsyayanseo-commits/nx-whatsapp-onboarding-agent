variable "name" { type = string }

locals {
  secret_names = toset([
    "APP_KEY",
    "DB_HOST",
    "DB_NAME",
    "DB_USERNAME",
    "DB_PASSWORD",
    "META_WHATSAPP_ACCESS_TOKEN",
    "META_WHATSAPP_APP_SECRET",
    "META_WHATSAPP_VERIFY_TOKEN",
    "META_WHATSAPP_PHONE_NUMBER_ID",
    "TERMS_STUDENT_URL",
    "TERMS_TUTOR_URL",
    "STUDENT_DASHBOARD_URL",
    "TUTOR_DASHBOARD_URL",
    "DASHBOARD_SIGNING_KEY",
    "S3_MEDIA_BUCKET",
    "S3_ANALYTICS_BUCKET"
  ])
}

resource "aws_secretsmanager_secret" "this" {
  for_each                = local.secret_names
  name                    = "${var.name}/${each.key}"
  recovery_window_in_days = 7
}

output "secret_arns" {
  value = { for key, secret in aws_secretsmanager_secret.this : key => secret.arn }
}
