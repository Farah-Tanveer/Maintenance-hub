# Maintenance Hub

An online booking platform for home and office maintenance services, built with plain PHP, MySQL and Apache on Ubuntu (WSL). No frameworks.

## Features (version 1)

1. Accounts and roles: customer, worker and admin
2. Service catalog with packages priced by property type (home or office) and size (small, medium, large)
3. Booking with a live price preview and free time slots
4. Customer bookings page with status tracking and cancel
5. Admin tools: create workers, assign bookings, edit the catalog
6. Worker jobs page with progress updates (confirmed, in progress, completed)
7. Customer reviews and ratings after a completed job

## Scope

Not included in version 1, on purpose:

1. Online payments
2. Email or SMS notifications
3. Password reset
4. Worker skills, profiles and availability calendars
5. Photo uploads and chat

Still to do for version 1: admin reports page, Apache setup, UI polish with a 404 page, final testing.

## Security

1. Passwords are stored as hashes with password_hash
2. Prepared statements for every query that uses user input
3. CSRF tokens on every form that changes data
4. Output escaped to prevent XSS
5. Role checks on every protected page, and ownership checks on bookings and jobs
6. Database credentials kept out of git

## Setup

You need PHP 8 with the mysqli extension, and MySQL.

1. Clone the repo and go into the folder

        git clone git@github.com:Farah-Tanveer/Maintenance-hub.git
        cd Maintenance-hub

2. Create the database and a user (run inside sudo mysql, choose your own password)

        CREATE DATABASE maintenance_hub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE USER 'hub_user'@'localhost' IDENTIFIED BY 'your_password';
        GRANT ALL PRIVILEGES ON maintenance_hub.* TO 'hub_user'@'localhost';
        FLUSH PRIVILEGES;

3. Copy the config file and put your password in it

        cp app/config/database.example.php app/config/database.php
        nano app/config/database.php

4. Load the tables, the starter data and the demo accounts

        mysql -u hub_user -p maintenance_hub < database/schema.sql
        mysql --default-character-set=utf8mb4 -u hub_user -p maintenance_hub < database/seed.sql
        php database/seed-users.php

5. Start the test server and open http://localhost:8000

        php -S localhost:8000 -t public

## Running on Apache (optional)

The PHP test server is enough for development. To run the site on Apache instead:

1. Let Apache pass through your home folder and read the config file

        chmod o+x ~
        sudo chgrp www-data app/config/database.php
        chmod 640 app/config/database.php

2. Create /etc/apache2/sites-available/maintenancehub.conf with this content (change the paths to your own)

        <VirtualHost *:80>
            ServerName maintenancehub.local
            DocumentRoot /home/YOUR_USER/projects/maintenance-hub/public
            <Directory /home/YOUR_USER/projects/maintenance-hub/public>
                Options -Indexes
                AllowOverride None
                Require all granted
            </Directory>
        </VirtualHost>

3. Enable the site and reload Apache

        sudo a2ensite maintenancehub
        sudo apache2ctl configtest
        sudo service apache2 reload

4. Add this line to the Windows hosts file (PowerShell as Administrator), then open http://maintenancehub.local

        Add-Content -Path C:\Windows\System32\drivers\etc\hosts -Value "127.0.0.1 maintenancehub.local"

Only the public folder is served, so the app and database folders cannot be opened from a browser.

With WSL's default networking, the Linux address can change after a restart. If the site stops loading, run hostname -I in Ubuntu and put the new address on the maintenancehub.local line of the Windows hosts file (edit it in Notepad as Administrator).

## Demo logins

For local demos only. Delete database/seed-users.php before any real deployment.

| Role | Email | Password |
|---|---|---|
| Admin | admin@maintenancehub.local | Admin@12345 |
| Worker | worker@maintenancehub.local | Worker@12345 |
| Customer | customer@maintenancehub.local | Customer@12345 |

## Project structure

    public/      Pages the browser can open, plus assets (CSS, JS, images)
    app/         Config and shared PHP code, kept outside the public folder
    database/    schema.sql, seed.sql and the demo user script

## License

See the LICENSE file.
