# Una EC2 con los tres entornos (dev, stage, prod), una RDS con una base por
# entorno y un bucket privado por entorno. El estado vive en el bucket que crea
# infra/bootstrap: `terraform init -backend-config="bucket=<state_bucket>"`.

terraform {
  required_version = "1.16.6"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "6.69.1"
    }
  }

  backend "s3" {
    key          = "clinica/terraform.tfstate"
    region       = "us-east-1"
    encrypt      = true
    use_lockfile = true
  }
}

provider "aws" {
  region = "us-east-1"

  default_tags {
    tags = { Project = "clinica" }
  }
}

variable "db_admin_password" {
  description = "Usuario maestro de RDS. Write-only: no queda en el estado"
  type        = string
  sensitive   = true
  ephemeral   = true
}

variable "ssh_public_key" {
  description = "Llave publica con la que el pipeline entra a la EC2"
  type        = string
}

variable "alert_email" {
  description = "Correo que recibe la alarma de presupuesto (RNF-46)"
  type        = string
}

locals {
  environments = toset(["dev", "stage", "prod"])
  account_id   = data.aws_caller_identity.current.account_id
}

data "aws_caller_identity" "current" {}

# RNF-45 y RNF-46: techo de USD 85 al mes, con aviso al 80 % gastado.
resource "aws_budgets_budget" "monthly" {
  name         = "clinica-mensual"
  budget_type  = "COST"
  limit_amount = "85"
  limit_unit   = "USD"
  time_unit    = "MONTHLY"

  notification {
    comparison_operator        = "GREATER_THAN"
    threshold                  = 80
    threshold_type             = "PERCENTAGE"
    notification_type          = "ACTUAL"
    subscriber_email_addresses = [var.alert_email]
  }
}

output "ec2_host" {
  description = "Secret EC2_HOST de los Environments dev, stage y prod; IP del registro de DuckDNS"
  value       = aws_eip.app.public_ip
}

output "db_host" {
  description = "Secret DB_HOST de los Environments dev, stage y prod"
  value       = aws_db_instance.main.address
}

output "buckets" {
  description = "Secret AWS_BUCKET de cada Environment"
  value       = { for env, bucket in aws_s3_bucket.files : env => bucket.bucket }
}
