variable "name" { type = string }
variable "vpc_id" { type = string }
variable "subnet_ids" { type = list(string) }
variable "db_cluster_arn" { type = string }
variable "db_cluster_id" { type = string }
variable "db_secret_arn" { type = string }
variable "security_group_ids" { type = list(string) }

resource "aws_security_group" "this" {
  name        = "${var.name}-rds-proxy"
  description = "RDS Proxy access"
  vpc_id      = var.vpc_id
}

resource "aws_iam_role" "this" {
  name = "${var.name}-rds-proxy"
  assume_role_policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect    = "Allow"
      Principal = { Service = "rds.amazonaws.com" }
      Action    = "sts:AssumeRole"
    }]
  })
}

resource "aws_iam_role_policy" "this" {
  name = "${var.name}-rds-proxy-secrets"
  role = aws_iam_role.this.id
  policy = jsonencode({
    Version = "2012-10-17"
    Statement = [{
      Effect   = "Allow"
      Action   = ["secretsmanager:GetSecretValue"]
      Resource = var.db_secret_arn
    }]
  })
}

resource "aws_db_proxy" "this" {
  name                   = "${var.name}-proxy"
  debug_logging          = false
  engine_family          = "POSTGRESQL"
  idle_client_timeout    = 1800
  require_tls            = true
  role_arn               = aws_iam_role.this.arn
  vpc_security_group_ids = concat([aws_security_group.this.id], var.security_group_ids)
  vpc_subnet_ids         = var.subnet_ids

  auth {
    auth_scheme = "SECRETS"
    secret_arn  = var.db_secret_arn
    iam_auth    = "DISABLED"
  }
}

resource "aws_db_proxy_default_target_group" "this" {
  db_proxy_name = aws_db_proxy.this.name

  connection_pool_config {
    max_connections_percent = 80
  }
}

resource "aws_db_proxy_target" "this" {
  db_cluster_identifier = var.db_cluster_id
  db_proxy_name         = aws_db_proxy.this.name
  target_group_name     = aws_db_proxy_default_target_group.this.name
}

output "endpoint" { value = aws_db_proxy.this.endpoint }
