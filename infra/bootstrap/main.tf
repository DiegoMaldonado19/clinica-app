# Se aplica una sola vez, desde la maquina del autor y con credenciales de
# administrador. Crea lo que infra/ necesita antes de existir: el bucket de su
# estado y el rol que GitHub Actions asume por OIDC, sin llaves de larga vida.

terraform {
  required_version = "1.16.6"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "6.69.1"
    }
  }
}

provider "aws" {
  region = "us-east-1"

  default_tags {
    tags = { Project = "clinica" }
  }
}

data "aws_caller_identity" "current" {}

locals {
  account_id          = data.aws_caller_identity.current.account_id
  repository_owner    = "DiegoMaldonado19"
  repository_owner_id = "80069863"
  repository_name     = "clinica-app"
  repository_id       = "1338087801"
}

resource "aws_s3_bucket" "state" {
  bucket = "clinica-tfstate-${local.account_id}"
}

resource "aws_s3_bucket_versioning" "state" {
  bucket = aws_s3_bucket.state.id

  versioning_configuration {
    status = "Enabled"
  }
}

resource "aws_s3_bucket_public_access_block" "state" {
  bucket                  = aws_s3_bucket.state.id
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

resource "aws_iam_openid_connect_provider" "github" {
  url            = "https://token.actions.githubusercontent.com"
  client_id_list = ["sts.amazonaws.com"]
}

data "aws_iam_policy_document" "infra_trust" {
  statement {
    actions = ["sts:AssumeRoleWithWebIdentity"]

    principals {
      type        = "Federated"
      identifiers = [aws_iam_openid_connect_provider.github.arn]
    }

    condition {
      test     = "StringEquals"
      variable = "token.actions.githubusercontent.com:aud"
      values   = ["sts.amazonaws.com"]
    }

    # GitHub repos creados despues del 2026-07-15 incluyen IDs inmutables en `sub`.
    # Solo el Environment `infra`, que exige aprobacion y corre desde main.
    condition {
      test     = "StringEquals"
      variable = "token.actions.githubusercontent.com:sub"
      values   = ["repo:${local.repository_owner}@${local.repository_owner_id}/${local.repository_name}@${local.repository_id}:environment:infra"]
    }
  }
}

resource "aws_iam_role" "infra" {
  name               = "clinica-infra"
  assume_role_policy = data.aws_iam_policy_document.infra_trust.json
}

# PowerUserAccess no incluye IAM. El IAM que Terraform necesita se limita a los
# roles y perfiles `clinica-*`.
resource "aws_iam_role_policy_attachment" "infra_services" {
  role       = aws_iam_role.infra.name
  policy_arn = "arn:aws:iam::aws:policy/PowerUserAccess"
}

data "aws_iam_policy_document" "infra_iam" {
  statement {
    actions = ["iam:*"]
    resources = [
      "arn:aws:iam::${local.account_id}:role/clinica-*",
      "arn:aws:iam::${local.account_id}:instance-profile/clinica-*",
    ]
  }
}

resource "aws_iam_role_policy" "infra_iam" {
  name   = "clinica-iam"
  role   = aws_iam_role.infra.id
  policy = data.aws_iam_policy_document.infra_iam.json
}

output "state_bucket" {
  description = "Secret TF_STATE_BUCKET del Environment infra"
  value       = aws_s3_bucket.state.bucket
}

output "infra_role_arn" {
  description = "Secret AWS_ROLE_ARN del Environment infra"
  value       = aws_iam_role.infra.arn
}
