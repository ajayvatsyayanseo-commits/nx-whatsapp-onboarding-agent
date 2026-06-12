# Configure remote state before first apply.
# terraform {
#   backend "s3" {
#     bucket         = "nxtutors-terraform-state-staging"
#     key            = "whatsapp-onboarding/staging/terraform.tfstate"
#     region         = "ap-south-1"
#     dynamodb_table = "nxtutors-terraform-locks-staging"
#     encrypt        = true
#   }
# }
