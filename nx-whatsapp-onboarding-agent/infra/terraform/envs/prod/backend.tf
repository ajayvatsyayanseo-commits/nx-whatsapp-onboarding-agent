# Configure remote state before first apply.
# terraform {
#   backend "s3" {
#     bucket         = "nxtutors-terraform-state-prod"
#     key            = "whatsapp-onboarding/prod/terraform.tfstate"
#     region         = "ap-south-1"
#     dynamodb_table = "nxtutors-terraform-locks-prod"
#     encrypt        = true
#   }
# }
