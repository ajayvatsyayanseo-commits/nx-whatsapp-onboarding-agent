locals {
  name = "${var.app_name}-${var.environment}"
}

module "network" {
  source             = "./modules/network"
  name               = local.name
  create_vpc         = var.create_vpc
  vpc_id             = var.vpc_id
  public_subnet_ids  = var.public_subnet_ids
  private_subnet_ids = var.private_subnet_ids
}

module "ecr" {
  source = "./modules/ecr"
  name   = var.ecr_repo_name
}

module "s3" {
  source                = "./modules/s3"
  media_bucket_name     = var.media_bucket_name
  analytics_bucket_name = var.analytics_bucket_name
}

module "sqs" {
  source             = "./modules/sqs"
  name               = local.name
  visibility_timeout = var.sqs_visibility_timeout
}

module "secrets" {
  source = "./modules/secrets"
  name   = local.name
}

module "iam" {
  source                = "./modules/iam"
  name                  = local.name
  secrets_arns          = merge(var.secrets_arns, module.secrets.secret_arns)
  media_bucket_name     = module.s3.media_bucket_name
  analytics_bucket_name = module.s3.analytics_bucket_name
}

module "aurora" {
  source              = "./modules/aurora"
  name                = local.name
  vpc_id              = module.network.vpc_id
  subnet_ids          = module.network.private_subnet_ids
  engine_version      = var.aurora_engine_version
  min_acu             = var.aurora_min_acu
  max_acu             = var.aurora_max_acu
  database_name       = "nxtutors"
  master_username     = "nxtutors_admin"
  allowed_cidr_blocks = []
  security_group_ids  = []
}

module "rds_proxy" {
  source             = "./modules/rds-proxy"
  name               = local.name
  vpc_id             = module.network.vpc_id
  subnet_ids         = module.network.private_subnet_ids
  db_cluster_arn     = module.aurora.cluster_arn
  db_cluster_id      = module.aurora.cluster_id
  db_secret_arn      = lookup(var.secrets_arns, "DB_PASSWORD", module.secrets.secret_arns["DB_PASSWORD"])
  security_group_ids = []
}

module "redis" {
  source          = "./modules/redis"
  name            = local.name
  vpc_id          = module.network.vpc_id
  subnet_ids      = module.network.private_subnet_ids
  node_type       = var.redis_node_type
  security_groups = []
}

module "alb" {
  source            = "./modules/alb"
  name              = local.name
  vpc_id            = module.network.vpc_id
  public_subnet_ids = module.network.public_subnet_ids
  domain_name       = var.domain_name
  certificate_arn   = var.certificate_arn
}

module "ecs_web" {
  source                = "./modules/ecs-web"
  name                  = "${local.name}-web"
  cluster_name          = var.ecs_cluster_name
  vpc_id                = module.network.vpc_id
  subnet_ids            = module.network.private_subnet_ids
  image                 = var.container_image
  cpu                   = var.web_cpu
  memory                = var.web_memory
  desired_count         = var.desired_count_web
  target_group_arn      = module.alb.target_group_arn
  container_port        = 8080
  log_retention_days    = var.log_retention_days
  execution_role_arn    = module.iam.execution_role_arn
  task_role_arn         = module.iam.task_role_arn
  environment_variables = var.environment_variables
  secrets_arns          = merge(var.secrets_arns, module.secrets.secret_arns)
  queue_url             = module.sqs.inbound_queue_url
  media_bucket_name     = module.s3.media_bucket_name
  analytics_bucket_name = module.s3.analytics_bucket_name

  depends_on = [module.ecs_web]
}

module "ecs_worker" {
  source                = "./modules/ecs-worker"
  name                  = "${local.name}-worker"
  cluster_name          = var.ecs_cluster_name
  vpc_id                = module.network.vpc_id
  subnet_ids            = module.network.private_subnet_ids
  image                 = var.container_image
  cpu                   = var.worker_cpu
  memory                = var.worker_memory
  desired_count         = var.desired_count_worker
  log_retention_days    = var.log_retention_days
  execution_role_arn    = module.iam.execution_role_arn
  task_role_arn         = module.iam.task_role_arn
  environment_variables = var.environment_variables
  secrets_arns          = merge(var.secrets_arns, module.secrets.secret_arns)
  queue_url             = module.sqs.inbound_queue_url
  media_bucket_name     = module.s3.media_bucket_name
  analytics_bucket_name = module.s3.analytics_bucket_name
}

module "cloudwatch" {
  source             = "./modules/cloudwatch"
  name               = local.name
  log_retention_days = var.log_retention_days
  queue_name         = module.sqs.inbound_queue_name
}

module "glue" {
  source           = "./modules/glue"
  name             = local.name
  analytics_bucket = module.s3.analytics_bucket_name
}

module "iam_github_oidc" {
  source                 = "./modules/iam-github-oidc"
  name                   = local.name
  github_org             = var.github_org
  github_repo            = var.github_repo
  github_oidc_thumbprint = var.github_oidc_thumbprint
  deploy_resource_arns   = ["*"]
  readonly_resource_arns = ["*"]
}
