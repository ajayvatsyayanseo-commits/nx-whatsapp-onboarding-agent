variable "name" { type = string }
variable "visibility_timeout" { type = number }

locals {
  queues = toset(["inbound", "outbound", "profile", "media", "analytics"])
}

resource "aws_sqs_queue" "dlq" {
  for_each = local.queues
  name     = "${var.name}-${each.key}-dlq"
}

resource "aws_sqs_queue" "queue" {
  for_each                   = local.queues
  name                       = "${var.name}-${each.key}"
  visibility_timeout_seconds = var.visibility_timeout
  message_retention_seconds  = 1209600
  redrive_policy = jsonencode({
    deadLetterTargetArn = aws_sqs_queue.dlq[each.key].arn
    maxReceiveCount     = 5
  })
}

output "inbound_queue_url" { value = aws_sqs_queue.queue["inbound"].url }
output "outbound_queue_url" { value = aws_sqs_queue.queue["outbound"].url }
output "profile_queue_url" { value = aws_sqs_queue.queue["profile"].url }
output "media_queue_url" { value = aws_sqs_queue.queue["media"].url }
output "analytics_queue_url" { value = aws_sqs_queue.queue["analytics"].url }
output "inbound_queue_name" { value = aws_sqs_queue.queue["inbound"].name }
