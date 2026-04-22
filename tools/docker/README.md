# LibreFunnels Local WordPress Rig

This Docker Compose stack runs a local WordPress + WooCommerce install with the working `librefunnels/` plugin directory bind-mounted into WordPress.

The stack uses MariaDB for the database because the WordPress CLI image ships with a MariaDB client. The `wpcli` service runs as UID/GID `33:33` so it can write to the same WordPress volume as the Apache WordPress container.

## Start

From the repository root:

```powershell
& "C:\Program Files\Docker\Docker\resources\bin\docker.exe" compose up -d
.\tools\docker\init-wordpress.ps1
```

Docker Desktop must have its Linux engine available. On Windows, that usually means WSL 2 is installed and Docker Desktop has finished starting before these commands run.

Then open:

- Site: http://localhost:8080
- Admin: http://localhost:8080/wp-admin
- Username: `admin`
- Password: `password`

## Useful Commands

```powershell
& "C:\Program Files\Docker\Docker\resources\bin\docker.exe" compose config
& "C:\Program Files\Docker\Docker\resources\bin\docker.exe" compose logs -f wordpress
& "C:\Program Files\Docker\Docker\resources\bin\docker.exe" compose down
```

Use `compose down -v` only when you intentionally want to delete the local WordPress database and uploaded files.
