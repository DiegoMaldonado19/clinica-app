# Ubuntu 24.04 fijada por nombre: una imagen nueva de Canonical no reemplaza la instancia.
data "aws_ami" "ubuntu" {
  owners = ["099720109477"]

  filter {
    name   = "name"
    values = ["ubuntu/images/hvm-ssd-gp3/ubuntu-noble-24.04-amd64-server-20261004"]
  }
}

resource "aws_key_pair" "deploy" {
  key_name   = "clinica"
  public_key = var.ssh_public_key
}

data "aws_iam_policy_document" "ec2_trust" {
  statement {
    actions = ["sts:AssumeRole"]

    principals {
      type        = "Service"
      identifiers = ["ec2.amazonaws.com"]
    }
  }
}

resource "aws_iam_role" "ec2" {
  name               = "clinica-ec2"
  assume_role_policy = data.aws_iam_policy_document.ec2_trust.json
}

# Doc 05 §2.2: objetos de negocio y nada mas. Los tres entornos comparten la
# instancia y por eso el rol; aislarlos por IAM exige una instancia por entorno.
data "aws_iam_policy_document" "ec2_files" {
  statement {
    actions   = ["s3:GetObject", "s3:PutObject", "s3:DeleteObject"]
    resources = [for bucket in aws_s3_bucket.files : "${bucket.arn}/*"]
  }
}

resource "aws_iam_role_policy" "ec2_files" {
  name   = "clinica-archivos"
  role   = aws_iam_role.ec2.id
  policy = data.aws_iam_policy_document.ec2_files.json
}

resource "aws_iam_instance_profile" "ec2" {
  name = "clinica-ec2"
  role = aws_iam_role.ec2.name
}

resource "aws_instance" "app" {
  ami                    = data.aws_ami.ubuntu.id
  instance_type          = "t3.medium"
  subnet_id              = aws_subnet.public.id
  vpc_security_group_ids = [aws_security_group.web.id]
  key_name               = aws_key_pair.deploy.key_name
  iam_instance_profile   = aws_iam_instance_profile.ec2.name
  user_data              = file("${path.module}/user-data.sh")

  # IMDSv2 con un salto mas: los contenedores llegan al rol a traves del puente de Docker.
  metadata_options {
    http_tokens                 = "required"
    http_put_response_hop_limit = 2
  }

  root_block_device {
    volume_type = "gp3"
    volume_size = 30
    encrypted   = true
  }

  tags = { Name = "clinica" }
}

resource "aws_eip" "app" {
  instance = aws_instance.app.id
  domain   = "vpc"
}
