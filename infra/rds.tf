resource "aws_db_parameter_group" "main" {
  name   = "clinica-mariadb114"
  family = "mariadb11.4"

  # Las migraciones crean triggers y RDS tiene el binlog activo por los respaldos.
  parameter {
    name  = "log_bin_trust_function_creators"
    value = "1"
  }

  parameter {
    name  = "require_secure_transport"
    value = "1"
  }
}

# Una base y un usuario por entorno; los crea el despliegue (scripts/deploy.sh).
# Los respaldos automaticos dan PITR de 5 minutos durante 7 dias (doc 03 §6).
resource "aws_db_instance" "main" {
  identifier                 = "clinica"
  engine                     = "mariadb"
  engine_version             = "11.4.13"
  auto_minor_version_upgrade = false
  instance_class             = "db.t4g.micro"
  allocated_storage          = 20
  storage_type               = "gp3"
  storage_encrypted          = true

  username            = "clinica_admin"
  password_wo         = var.db_admin_password
  password_wo_version = 1

  db_subnet_group_name   = aws_db_subnet_group.main.name
  vpc_security_group_ids = [aws_security_group.db.id]
  parameter_group_name   = aws_db_parameter_group.main.name
  publicly_accessible    = false

  # 01:00 y domingo 03:00 en Guatemala.
  backup_retention_period   = 7
  backup_window             = "07:00-07:30"
  maintenance_window        = "sun:09:00-sun:09:30"
  deletion_protection       = true
  final_snapshot_identifier = "clinica-final"
}
