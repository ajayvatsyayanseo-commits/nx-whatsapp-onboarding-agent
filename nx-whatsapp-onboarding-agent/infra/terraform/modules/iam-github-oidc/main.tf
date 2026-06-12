variable "name" { type = string }
variable "github_org" { type = string }
variable "github_repo" { type = string }
variable "github_oidc_thumbprint" { type = string }
variable "deploy_resource_arns" { type = list(string) }
variable "readonly_resource_arns" { type = list(string) }

locals {
  repo_subject = "repo:${var.github_org}/${var.github_repo}:*"
}

resource "aws_iam_openid_connect_provider" "github" {
  count           = var.github_org == "" || var.github_repo == "" ? 0 : 1
  url             = "https://token.actions.githubusercontent.com"
  client_id_list  = ["sts.amazonaws.com"]
  thumbprint_list = [var.github_oidc_thumbprint]
}

data "aws_iam_policy_document" "assume" {
  count = var.github_org == "" || var.github_repo == "" ? 0 : 1

  statement {
    effect  = "Allow"
    actions = ["sts:AssumeRoleWithWebIdentity"]

    principals {
      type        = "Federated"
      identifiers = [aws_iam_openid_connect_provider.github[0].arn]
    }

    condition {
      test     = "StringEquals"
      variable = "token.actions.githubusercontent.com:aud"
      values   = ["sts.amazonaws.com"]
    }

    condition {
      test     = "StringLike"
      variable = "token.actions.githubusercontent.com:sub"
      values   = [local.repo_subject]
    }
  }
}

resource "aws_iam_role" "plan" {
  count              = var.github_org == "" || var.github_repo == "" ? 0 : 1
  name               = "${var.name}-github-plan"
  assume_role_policy = data.aws_iam_policy_document.assume[0].json
}

resource "aws_iam_role_policy" "plan" {
  count = var.github_org == "" || var.github_repo == "" ? 0 : 1
  name  = "${var.name}-github-plan"
  role  = aws_iam_role.plan[0].id
  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect   = "Allow"
      Action   = ["ec2:Describe*", "ecs:Describe*", "ecs:List*", "ecr:Describe*", "rds:Describe*", "elasticache:Describe*", "sqs:Get*", "sqs:List*", "s3:List*", "cloudwatch:Describe*", "cloudwatch:Get*", "cloudwatch:List*", "iam:Get*", "iam:List*", "secretsmanager:DescribeSecret"]
      Resource = var.readonly_resource_arns
    }]
  })
}

resource "aws_iam_role" "deploy" {
  count              = var.github_org == "" || var.github_repo == "" ? 0 : 1
  name               = "${var.name}-github-deploy"
  assume_role_policy = data.aws_iam_policy_document.assume[0].json
}

resource "aws_iam_role_policy" "deploy" {
  count = var.github_org == "" || var.github_repo == "" ? 0 : 1
  name  = "${var.name}-github-deploy"
  role  = aws_iam_role.deploy[0].id
  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [
      {
        Effect = "Allow"
        Action = [
          "ecr:GetAuthorizationToken",
          "ecr:BatchCheckLayerAvailability",
          "ecr:CompleteLayerUpload",
          "ecr:InitiateLayerUpload",
          "ecr:PutImage",
          "ecr:UploadLayerPart",
          "ecs:Describe*",
          "ecs:List*",
          "ecs:RegisterTaskDefinition",
          "ecs:RunTask",
          "ecs:UpdateService",
          "ecs:TagResource",
          "iam:PassRole",
          "cloudwatch:*",
          "logs:*",
          "s3:GetObject",
          "s3:PutObject",
          "s3:ListBucket"
        ]
        Resource = var.deploy_resource_arns
      }
    ]
  })
}

output "plan_role_arn" {
  value = length(aws_iam_role.plan) == 0 ? "" : aws_iam_role.plan[0].arn
}

output "deploy_role_arn" {
  value = length(aws_iam_role.deploy) == 0 ? "" : aws_iam_role.deploy[0].arn
}
