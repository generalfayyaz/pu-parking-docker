PU PARKING SYSTEM - DOCKER SETUP



1\. PROJECT OVERVIEW

PU Parking is a PHP + MySQL web application for parking management: login, vehicle records, check-in / check-out, parking slots and reports.

Main feature used for verification: log in, check a vehicle in and out, and see the slot status update.

This repository packages the existing app with Docker so it can be run from a fresh clone.



2\. PREREQ

\- Docker Desktop (running) and Docker Compose

\- Git

\- Host port 8080 free

\- Tested with Docker 29.8.2 and Docker Compose v5.6.0 on Windows 11 (PowerShell). Replace with your own versions if different.



3\. CLONE AND CONFIGURE

Run these commands one by one in PowerShell:



&#x20;   git clone https://github.com/generalfayyaz/pu-parking-docker.git

&#x20;   cd pu-parking-docker

&#x20;   git checkout pu\_parking

&#x20;   copy .env.example .env



(Skip the git checkout line once the branch has been merged into main.)

Edit .env if you want different values. Never commit .env.



4\. ENVIRONMENT VARIABLES (see .env.example)

\- DB\_HOST: database host for the app. Must be db (the Compose service name), not localhost

\- DB\_PORT: database port (3306)

\- DB\_NAME: database name (parking\_db)

\- DB\_USER and DB\_PASS: app database credentials (must match MYSQL\_USER and MYSQL\_PASSWORD)

\- MYSQL\_ROOT\_PASSWORD: MySQL root password (dummy value in .env.example)

\- MYSQL\_DATABASE, MYSQL\_USER, MYSQL\_PASSWORD: MySQL container setup

\- APP\_PORT: host port for the app (8080)



5\. BUILD AND START



&#x20;   docker compose up --build -d



The first build downloads images and can take several minutes.



6\. URL AND PORTS

\- App URL: http://localhost:8080/parking_system/

\- Host port 8080 maps to container port 80 (app)

\- Database: container port 3306 (internal only, service name db)



7\. VERIFY IT WORKS

1\. Run docker compose ps. The app and db services should be running and healthy.

2\. Open http://localhost:8080/parking\_system/ in a browser. The login page should appear.

3\. Log in with admin / admin123 (demo user from parking\_db\_complete.sql). Check a vehicle in and out. The dashboard and slot status should update.

4\. Optional: run docker compose restart, reload the page. The data should still be there.



8\. LOGS AND STATUS



&#x20;   docker compose ps

&#x20;   docker compose logs

&#x20;   docker compose logs -f

&#x20;   docker compose logs db



9\. STOP AND CLEAN UP



&#x20;   docker compose down

&#x20;   docker compose down -v



down stops and removes the containers and keeps the database data.

down -v also deletes the database volume (the SQL file is imported again on the next start).



10\. TROUBLESHOOTING AND KNOWN LIMITATIONS

\- "getaddrinfo for db failed": the db service is not running or DB\_HOST is not db. Run docker compose ps and check .env.

\- Port already in use: stop whatever uses port 8080 or change APP\_PORT in .env.

\- Compose cannot find .env: copy .env.example to .env (section 3).

\- Database data does not change after editing parking\_db\_complete.sql: the SQL runs only on first start. Use docker compose down -v, then start again.

\- Docker Desktop not running: start it and wait for "Engine running".

\- "container name already in use": run docker rm -f pu-parking-app pu-parking-db and start again.

\- Limitations: demo users and dummy passwords are for development only. This setup is not production hardened.

