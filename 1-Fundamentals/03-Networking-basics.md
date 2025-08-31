# Networking Basics

## 1. Inspect IP of Container

```bash
docker inspect <container_id> | grep IPAddress
```

- Shows container IP.
- better to use Docker networks.

## 2. Creating a Custom Network

`docker network create mynet` -> Creates bridge network where containers can talk.

## 3. Run Containers in Same Network

```bash
docker run -d --name mydb --network mynet -e MYSQL_ROOT_PASSWORD=pass mysql:8
docker run -it --name myapp --network mynet ubuntu:20.04 bash
```

- MySQL + App

## 4. Test Connectivity

- Inside myapp

```bash
apt update && apt install -y iputils-ping mysql-client
ping mydb        # test DNS resolution inside Docker network
mysql -h mydb -u root -p   # connect to MySQL by container name
```

## 5. Port Mapping to Host

- MySQL on host port 3307:
  `docker run -d --name mydb -p 3307:3306 mysql:8` -> Access from host: mysql -h 127.0.0.1 -P 3307 -u root -p.
