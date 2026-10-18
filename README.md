PU Parking System - Docker Setup

1. Overview
PU Parking is a PHP and MySQL web application. This project uses Docker to simplify application setup and provide a consistent environment.

2. Requirements
- Docker Desktop
- Docker Compose
- Git

Tested with Docker 29.8.2 and Docker Compose v5.6.0 on Windows.

3. Run the Application

Clone the repository:

bash
git clone https://github.com/generalfayyaz/pu-parking-docker.git
cd pu-parking-docker


Build and start the containers:

bash
docker compose up --build -d

Check container status:

bash
docker compose ps

Open the application:

http://localhost:8080/parking_system/

Make sure the port and URL match the configuration in compose.yaml.

4. Configuration
Check .env.example for required environment variables. Keep real passwords and secrets private. The application should connect to MySQL using the database service name defined in compose.yaml, not localhost.

5. Verification
- V-01: Confirm the containers are running using docker compose ps.
- V-02: Open the application URL and confirm the page loads.
- V-03: Test a core parking feature and confirm it works.
- V-04: Check logs using docker compose logs for startup or database errors.

Save screenshots and record the actual results for the Week 6 report.

6. Stop the Application

bash
docker compose down

Database data stored in a volume may remain after stopping the containers.

7. Troubleshooting
- Database connection error: Check the database status, credentials, and service name.
- Port conflict: Stop the process using the host port or change the host port mapping.
- Application unavailable: Check docker compose ps, review logs, and verify the URL.

8. Handover and Deliverables
A reviewer outside the implementation group should test the project from a clean clone using this README. Record the environment, test results, issues, and fixes.

Submit the Docker configuration, README, verification evidence, report PDF, handover evidence, demo video (maximum 20 minutes), and individual contribution statements.

Do not commit real secrets or unnecessary generated files.
