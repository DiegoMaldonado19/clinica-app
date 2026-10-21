#!/bin/bash
# Primer arranque de la EC2: Docker y Compose con version fija, bitacoras de
# contenedor rotadas y swap. Lo demas lo hace el pipeline por SSH.
set -euo pipefail

DOCKER=5:29.9.1-1~ubuntu.24.04~noble
CONTAINERD=2.4.2-2~ubuntu.24.04~noble
COMPOSE=5.6.0-1~ubuntu.24.04~noble

# Sin rotacion, los logs de 15 contenedores terminan llenando el disco.
mkdir -p /etc/docker
echo '{"log-driver": "json-file", "log-opts": {"max-size": "10m", "max-file": "5"}}' > /etc/docker/daemon.json

install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
echo "deb [arch=amd64 signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu noble stable" \
  > /etc/apt/sources.list.d/docker.list
apt-get update
apt-get install -y "docker-ce=$DOCKER" "docker-ce-cli=$DOCKER" "containerd.io=$CONTAINERD" "docker-compose-plugin=$COMPOSE"
apt-mark hold docker-ce docker-ce-cli containerd.io docker-compose-plugin
usermod -aG docker ubuntu

# Tres entornos en 4 GB de RAM: el swap evita que un pico mate un contenedor.
fallocate -l 2G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
