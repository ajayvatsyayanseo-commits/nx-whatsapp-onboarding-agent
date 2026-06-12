variable "name" { type = string }
variable "vpc_id" { type = string }
variable "subnet_ids" { type = list(string) }
variable "engine_version" { type = string }
variable "min_acu" { type = number }
variable "max_acu" { type = number }
variable "database_name" { type = string }
variable "master_username" { type = string }
variable "allowed_cidr_blocks" { type = list(string) }
variable "security_group_ids" { type = list(string) }

resource "aws_security_group" "this" {
  name        = "${var.name}-aurora"
  description = "Aurora PostgreSQL access for NXtutors onboarding"
  vpc_id      = var.vpc_id
}

resource "aws_db_subnet_group" "this" {
  name       = "${var.name}-aurora"
  subnet_ids = var.subnet_ids
}

resource "aws_rds_cluster" "this" {
  cluster_identifier          = "${var.name}-aurora"
  engine                      = "aurora-postgresql"
  engine_mode                 = "provisioned"
  engine_version              = var.engine_version
  database_name               = var.database_name
  master_username             = var.master_username
  manage_master_user_password = true
  db_subnet_group_name        = aws_db_subnet_group.this.name
  vpc_security_group_ids      = concat([aws_security_group.this.id], var.security_group_ids)
  storage_encrypted           = true
  backup_retention_period     = 7
  deletion_protection         = true
  apply_immediately           = false

  serverlessv2_scaling_configuration {
    min_capacity = var.min_acu
    max_capacity = var.max_acu
  }
}

resource "aws_rds_cluster_instance" "this" {
  identifier         = "${var.name}-aurora-1"
  cluster_identifier = aws_rds_cluster.this.id
  instance_class     = "db.serverless"
  engine             = aws_rds_cluster.this.engine
  engine_version     = aws_rds_cluster.this.engine_version
}

output "cluster_arn" { value = aws_rds_cluster.this.arn }
output "cluster_id" { value = aws_rds_cluster.this.id }
output "endpoint" { value = aws_rds_cluster.this.endpoint }
