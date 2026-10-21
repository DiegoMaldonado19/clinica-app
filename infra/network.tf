# VPC minima: una subred publica para la EC2 y dos privadas, sin ruta a
# Internet ni NAT, que RDS exige en dos zonas aunque corra en una sola.

data "aws_availability_zones" "available" {
  state = "available"
}

resource "aws_vpc" "main" {
  cidr_block           = "10.20.0.0/16"
  enable_dns_hostnames = true
  tags                 = { Name = "clinica" }
}

resource "aws_internet_gateway" "main" {
  vpc_id = aws_vpc.main.id
}

resource "aws_subnet" "public" {
  vpc_id            = aws_vpc.main.id
  cidr_block        = "10.20.0.0/24"
  availability_zone = data.aws_availability_zones.available.names[0]
  tags              = { Name = "clinica-publica" }
}

resource "aws_route_table" "public" {
  vpc_id = aws_vpc.main.id

  route {
    cidr_block = "0.0.0.0/0"
    gateway_id = aws_internet_gateway.main.id
  }
}

resource "aws_route_table_association" "public" {
  subnet_id      = aws_subnet.public.id
  route_table_id = aws_route_table.public.id
}

resource "aws_subnet" "private" {
  count             = 2
  vpc_id            = aws_vpc.main.id
  cidr_block        = "10.20.${count.index + 10}.0/24"
  availability_zone = data.aws_availability_zones.available.names[count.index]
  tags              = { Name = "clinica-privada-${count.index}" }
}

resource "aws_db_subnet_group" "main" {
  name       = "clinica"
  subnet_ids = aws_subnet.private[*].id
}

# 22 para el pipeline (los runners de GitHub no tienen IP fija); 443, 8081 y
# 8082 son prod, stage y dev; el 80 solo redirige a https.
resource "aws_security_group" "web" {
  name   = "clinica-web"
  vpc_id = aws_vpc.main.id

  dynamic "ingress" {
    for_each = [22, 80, 443, 8081, 8082]

    content {
      from_port   = ingress.value
      to_port     = ingress.value
      protocol    = "tcp"
      cidr_blocks = ["0.0.0.0/0"]
    }
  }

  egress {
    from_port   = 0
    to_port     = 0
    protocol    = "-1"
    cidr_blocks = ["0.0.0.0/0"]
  }
}

# Sin reglas de salida: la base no inicia conexiones.
resource "aws_security_group" "db" {
  name   = "clinica-db"
  vpc_id = aws_vpc.main.id

  ingress {
    from_port       = 3306
    to_port         = 3306
    protocol        = "tcp"
    security_groups = [aws_security_group.web.id]
  }
}
