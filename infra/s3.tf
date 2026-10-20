# Un bucket privado y versionado por entorno: el versionado es el respaldo de
# los archivos (doc 03 §6). La EC2 no se respalda porque no guarda estado.
resource "aws_s3_bucket" "files" {
  for_each = local.environments
  bucket   = "clinica-${each.key}-archivos-${local.account_id}"
}

resource "aws_s3_bucket_versioning" "files" {
  for_each = aws_s3_bucket.files
  bucket   = each.value.id

  versioning_configuration {
    status = "Enabled"
  }
}

resource "aws_s3_bucket_public_access_block" "files" {
  for_each                = aws_s3_bucket.files
  bucket                  = each.value.id
  block_public_acls       = true
  block_public_policy     = true
  ignore_public_acls      = true
  restrict_public_buckets = true
}

# Respaldo completo semanal de RDS: domingo 02:00 en Guatemala, 4 semanas.
resource "aws_backup_vault" "main" {
  name = "clinica"
}

resource "aws_backup_plan" "weekly" {
  name = "clinica-semanal"

  rule {
    rule_name                    = "semanal"
    target_vault_name            = aws_backup_vault.main.name
    schedule                     = "cron(0 2 ? * SUN *)"
    schedule_expression_timezone = "America/Guatemala"

    lifecycle {
      delete_after = 28
    }
  }
}

data "aws_iam_policy_document" "backup_trust" {
  statement {
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["backup.amazonaws.com"]
    }
  }
}

resource "aws_iam_role" "backup" {
  name               = "clinica-backup"
  assume_role_policy = data.aws_iam_policy_document.backup_trust.json
}

resource "aws_iam_role_policy_attachment" "backup" {
  role       = aws_iam_role.backup.name
  policy_arn = "arn:aws:iam::aws:policy/service-role/AWSBackupServiceRolePolicyForBackup"
}

resource "aws_backup_selection" "rds" {
  name         = "clinica-rds"
  plan_id      = aws_backup_plan.weekly.id
  iam_role_arn = aws_iam_role.backup.arn
  resources    = [aws_db_instance.main.arn]
}
