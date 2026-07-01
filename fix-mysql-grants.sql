-- Run this in DBeaver while connected as root (through your SSH tunnel).
-- Replace YOUR_ACTUAL_PASSWORD with the same password you put in db.config.php

-- Allow login when PHP connects via SSH tunnel (MySQL sees you as localhost on the server)
CREATE USER IF NOT EXISTS 'dbeaver_user'@'localhost' IDENTIFIED BY 'YOUR_ACTUAL_PASSWORD';
CREATE USER IF NOT EXISTS 'dbeaver_user'@'127.0.0.1' IDENTIFIED BY 'YOUR_ACTUAL_PASSWORD';

ALTER USER 'dbeaver_user'@'localhost' IDENTIFIED BY 'YOUR_ACTUAL_PASSWORD';
ALTER USER 'dbeaver_user'@'127.0.0.1' IDENTIFIED BY 'YOUR_ACTUAL_PASSWORD';

GRANT ALL PRIVILEGES ON myappdb.* TO 'dbeaver_user'@'localhost';
GRANT ALL PRIVILEGES ON myappdb.* TO 'dbeaver_user'@'127.0.0.1';

FLUSH PRIVILEGES;

-- Verify accounts exist:
SELECT user, host FROM mysql.user WHERE user = 'dbeaver_user';
