variable "name" { type = string }
variable "cluster_name" { type = string }
variable "vpc_id" { type = string }
variable "subnet_ids" { type = list(string) }
variable "image" { type = string }
variable "cpu" { type = number }
variable "memory" { type = number }
variable "desired_count" { type = number }
variable "log_retention_days" { type = number }
variable "execution_role_arn" { type = string }
variable "task_role_arn" { type = string }
variable "environment_variables" { type = map(string) }
variable "secrets_arns" { type = map(string) }
variable "queue_url" { type = string }
variable "media_bucket_name" { type = string }
variable "analytics_bucket_name" { type = string }

locals {
  env = merge(var.environment_variables, {
    WHATSAPP_ONBOARDING_SQS_QUEUE_URL    = var.queue_url
    AWS_S3_MEDIA_BUCKET                  = var.media_bucket_name
    WHATSAPP_ONBOARDING_ANALYTICS_BUCKET = var.analytics_bucket_name
  })
}

data "aws_region" "current" {}

resource "aws_cloudwatch_log_group" "this" {
  name              = "/ecs/${var.name}"
  retention_in_days = var.log_retention_days
}

resource "aws_security_group" "this" {
  name        = "${var.name}-sg"
  description = "ECS worker service"
  vpc_id      = var.vpc_id

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
}

resource "aws_ecs_task_definition" "this" {
  family                   = var.name
  requires_compatibilities = ["FARGATE"]
  network_mode             = "awsvpc"
  cpu                      = tostring(var.cpu)
  memory                   = tostring(var.memory)
  execution_role_arn       = var.execution_role_arn
  task_role_arn            = var.task_role_arn

  container_definitions = jsonencode([{
    name        = "app"
    image       = var.image
    essential   = true
    command     = ["worker"]
    environment = [for key, value in local.env : { name = key, value = value }]
    secrets     = [for key, arn in var.secrets_arns : { name = key, valueFrom = arn }]
    logConfiguration = {
      logDriver = "awslogs"
      options = {
        awslogs-group         = aws_cloudwatch_log_group.this.name
        awslogs-region        = data.aws_region.current.name
        awslogs-stream-prefix = "worker"
      }
    }
  }])
}

resource "aws_ecs_service" "this" {
  name            = var.name
  cluster         = var.cluster_name
  task_definition = aws_ecs_task_definition.this.arn
  desired_count   = var.desired_count
  launch_type     = "FARGATE"

  network_configuration {
    subnets          = var.subnet_ids
    security_groups  = [aws_security_group.this.id]
    assign_public_ip = false
  }
}

output "service_name" { value = aws_ecs_service.this.name }
output "task_family" { value = aws_ecs_task_definition.this.family }
output "security_group_id" { value = aws_security_group.this.id }
