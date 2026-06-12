variable "name" { type = string }
variable "vpc_id" { type = string }
variable "subnet_ids" { type = list(string) }
variable "node_type" { type = string }
variable "security_groups" { type = list(string) }

resource "aws_security_group" "this" {
  name        = "${var.name}-redis"
  description = "Redis access for onboarding"
  vpc_id      = var.vpc_id
}

resource "aws_elasticache_subnet_group" "this" {
  name       = "${var.name}-redis"
  subnet_ids = var.subnet_ids
}

resource "aws_elasticache_replication_group" "this" {
  replication_group_id       = "${var.name}-redis"
  description                = "NXtutors onboarding Redis"
  engine                     = "redis"
  node_type                  = var.node_type
  num_cache_clusters         = 1
  automatic_failover_enabled = false
  subnet_group_name          = aws_elasticache_subnet_group.this.name
  security_group_ids         = concat([aws_security_group.this.id], var.security_groups)
  at_rest_encryption_enabled = true
  transit_encryption_enabled = true
}

output "endpoint" {
  value = aws_elasticache_replication_group.this.primary_endpoint_address
}
