# WebForm Agent


## Dev
Create .env
```
cp .env.sample .env
```
and add missing configuration.

Start service
```
docker-compose up
```

and open http://localhost:8888

To create test link with valid token for RTA form use:
http://localhost:8888/test_create_rta_link.php