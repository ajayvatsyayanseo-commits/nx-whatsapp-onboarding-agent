output "alb_dns_name" { value = module.alb.alb_dns_name }
output "webhook_url" { value = module.alb.webhook_url }
output "ecr_repository_url" { value = module.ecr.repository_url }
output "ecs_cluster_name" { value = var.ecs_cluster_name }
output "web_service_name" { value = module.ecs_web.service_name }
output "worker_service_name" { value = module.ecs_worker.service_name }
output "aurora_endpoint_proxy" { value = module.rds_proxy.endpoint }
output "redis_endpoint" { value = module.redis.endpoint }
output "queue_urls" {
  value = {
    inbound   = module.sqs.inbound_queue_url
    outbound  = module.sqs.outbound_queue_url
    profile   = module.sqs.profile_queue_url
    media     = module.sqs.media_queue_url
    analytics = module.sqs.analytics_queue_url
  }
}
output "github_plan_role_arn" { value = module.iam_github_oidc.plan_role_arn }
output "github_deploy_role_arn" { value = module.iam_github_oidc.deploy_role_arn }
