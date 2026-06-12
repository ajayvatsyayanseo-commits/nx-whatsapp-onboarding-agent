variable "app_name" {
  type = string
}

variable "aws_region" {
  type = string
}

variable "environment" {
  type = string
}

variable "vpc_id" {
  type    = string
  default = ""
}

variable "create_vpc" {
  type    = bool
  default = false
}

variable "private_subnet_ids" {
  type    = list(string)
  default = []
}

variable "public_subnet_ids" {
  type    = list(string)
  default = []
}

variable "domain_name" {
  type    = string
  default = ""
}

variable "certificate_arn" {
  type    = string
  default = ""
}

variable "ecr_repo_name" {
  type = string
}

variable "ecs_cluster_name" {
  type = string
}

variable "web_cpu" {
  type    = number
  default = 512
}

variable "web_memory" {
  type    = number
  default = 1024
}

variable "worker_cpu" {
  type    = number
  default = 512
}

variable "worker_memory" {
  type    = number
  default = 1024
}

variable "desired_count_web" {
  type    = number
  default = 2
}

variable "desired_count_worker" {
  type    = number
  default = 1
}

variable "aurora_engine_version" {
  type    = string
  default = "16.4"
}

variable "aurora_min_acu" {
  type    = number
  default = 0.5
}

variable "aurora_max_acu" {
  type    = number
  default = 4
}

variable "redis_node_type" {
  type    = string
  default = "cache.t4g.micro"
}

variable "sqs_visibility_timeout" {
  type    = number
  default = 120
}

variable "secrets_arns" {
  type    = map(string)
  default = {}
}

variable "media_bucket_name" {
  type = string
}

variable "analytics_bucket_name" {
  type = string
}

variable "log_retention_days" {
  type    = number
  default = 30
}

variable "container_image" {
  type    = string
  default = "public.ecr.aws/docker/library/php:8.2-fpm"
}

variable "environment_variables" {
  type        = map(string)
  default     = {}
  description = "Non-sensitive ECS environment variables."
}

variable "github_org" {
  type    = string
  default = ""
}

variable "github_repo" {
  type    = string
  default = ""
}

variable "github_oidc_thumbprint" {
  type    = string
  default = "6938fd4d98bab03faadb97b34396831e3780aea1"
}
