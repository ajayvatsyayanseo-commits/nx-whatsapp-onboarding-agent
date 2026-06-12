variable "name" { type = string }
variable "analytics_bucket" { type = string }

resource "aws_glue_catalog_database" "this" {
  name = replace("${var.name}_onboarding", "-", "_")
}

resource "aws_glue_crawler" "events" {
  name          = "${var.name}-onboarding-events"
  database_name = aws_glue_catalog_database.this.name
  role          = aws_iam_role.glue.arn

  s3_target {
    path = "s3://${var.analytics_bucket}/nxtutors/onboarding_events/"
  }
}

resource "aws_iam_role" "glue" {
  name = "${var.name}-glue"
  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect    = "Allow"
      Principal = { Service = "glue.amazonaws.com" }
      Action    = "sts:AssumeRole"
    }]
  })
}

resource "aws_iam_role_policy" "glue" {
  name = "${var.name}-glue"
  role = aws_iam_role.glue.id
  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect   = "Allow"
      Action   = ["s3:GetObject", "s3:ListBucket", "s3:PutObject", "glue:*", "logs:*"]
      Resource = "*"
    }]
  })
}
