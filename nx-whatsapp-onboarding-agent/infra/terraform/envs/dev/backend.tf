# Configure remote state before first apply.
# terraform {
#   backend "s3" {
#     bucket         = "nxtutors-terraform-state-dev"
#     key            = "whatsapp-onboarding/dev/terraform.tfstate"
#     region         = "ap-south-1"
#     dynamodb_table = "nxtutors-terraform-locks-dev"
#     encrypt        = true
#   }
# }
