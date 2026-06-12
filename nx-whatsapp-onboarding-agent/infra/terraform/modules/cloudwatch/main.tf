variable "name" { type = string }
variable "log_retention_days" { type = number }
variable "queue_name" { type = string }

resource "aws_cloudwatch_log_group" "ops" {
  name              = "/aws/nxtutors/${var.name}/ops"
  retention_in_days = var.log_retention_days
}

resource "aws_cloudwatch_metric_alarm" "queue_lag" {
  alarm_name          = "${var.name}-queue-visible-high"
  comparison_operator = "GreaterThanThreshold"
  evaluation_periods  = 2
  metric_name         = "ApproximateNumberOfMessagesVisible"
  namespace           = "AWS/SQS"
  period              = 300
  statistic           = "Average"
  threshold           = 100

  dimensions = {
    QueueName = var.queue_name
  }
}
