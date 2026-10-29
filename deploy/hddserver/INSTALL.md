# Installing the ROP Registry on a clinic PC (HDDServer)

The registry runs on HDDServer's nginx + PHP, from a Git checkout in
`D:\HddServer\sites\rop`, and is reached at `http://localhost` on the PC itself
and at `http://<the PC's IP>` from other PCs on the network.

Files in this folder:

| File | Where it goes |
|---|---|
| `nginx.conf` | `D:\HddServer\conf\nginx.conf` |
| `workers.json` | `D:\HddServer\conf\workers.json` (empty: the registry needs no workers) |
| `cron.json` | `D:\HddServer\conf\cron.json` (empty: the registry needs no scheduled jobs) |
| `deploy.json` | The `rop` deployment profile, opened by `Open-Profiles.cmd` |
| `env.server.example` | Content of `D:\HddServer\sites\rop\.env` |

## What you need

- The HDDServer installer, `HddServerSetup-1.0.5.exe` or later (earlier builds
  stop on the second `Install.cmd` run, can finish without an application key,
  and cannot turn MariaDB off).
- Node.js LTS installer (https://nodejs.org). The registry's frontend build needs
  real Node; HDDServer's bundled Bun is not enough.
- GitHub admin access to `HassanDomeDenea/rop-registry` (to add a deploy key).
- A **Full** backup zip of the current registry, on a USB stick.
- Internet on the clinic PC during installation (GitHub, Composer, npm).

## On the current registry PC

1. **Push the latest code.** The clinic PC installs and updates from GitHub
   `main`, so everything you want there must be committed and pushed.
2. **Make a Full backup.** Backups page → Create backup → Full (or
   `php artisan registry:backup`). Copy the newest `rop-full-*.zip` from the
   backup folder to the USB stick, together with this `deploy/hddserver` folder.

## On the clinic PC

3. **Install HDDServer and Node.js.** Run `HddServerSetup-<version>.exe`
   (default folder `D:\HddServer`), then the Node.js LTS installer with its
   defaults. Open a new terminal afterwards so `php`, `composer`, `git`, `node`
   and `npm` are on PATH.

4. **Swap in the registry's server config.** Exit HDDServer from the tray icon.
   In `D:\HddServer\conf`, keep copies of `nginx.conf`, `workers.json` and
   `cron.json` (e.g. rename to `*.clinic`), then copy the three files from this
   folder there. The registry uses SQLite, not MariaDB, so also open
   `D:\HddServer\conf\settings.json` and set `"mariaDb": false` (add the line if
   it is missing). Start HDDServer again; the dashboard should show only nginx
   and PHP, both running (the site itself is not installed yet).

5. **Create the deployment profile.** Run `D:\HddServer\deployment\Setup.cmd`:
   - Profile: `rop`
   - Repository: `HassanDomeDenea/rop-registry`
   - Branch: `main`
   - Folder: `D:\HddServer\sites\rop`

   It opens a page with two QR codes. Add the shown public key on GitHub
   (repository → Settings → Deploy keys → Add), name it after this PC, and leave
   **Allow write access** unchecked. Back in the console press Enter; it checks
   that the PC can read the repository.

6. **Set the profile.** Run `Open-Profiles.cmd`, open `rop\deploy.json` and
   replace its content with this folder's `deploy.json`. If HDDServer is not in
   `D:\HddServer`, change `applicationDirectory` to match.

7. **Install the registry.**
   1. Run `Install.cmd` and choose `rop`. It clones the repository, creates
      `.env` and stops on purpose ("Edit .env ... then run Install.cmd again").
   2. Replace the content of `D:\HddServer\sites\rop\.env` with
      `env.server.example` and fill it in:
      - `APP_URL`: the address other PCs will use, e.g. `http://192.168.1.50`
        (see "Addresses" below; it does not have to be exact).
      - `ADMIN_EMAIL`, `ADMIN_PASSWORD`: copy from the old PC's `.env`.
      - `CLINIC_*`: the letterhead, copy from the old PC's `.env`.
      - `BACKUP_PATH`: a folder on another drive, e.g. `E:\ROP-Backups`.
      - Keep the empty `APP_KEY=` line; the install fills it in.
   3. Make sure HDDServer is running, then run `Install.cmd` again. It installs
      the PHP and frontend dependencies, generates the key, builds the frontend,
      creates the database, creates the admin account, caches the config and
      checks `http://127.0.0.1/up`. It ends with "Deployment complete".

8. **Open it.** Browse to `http://localhost` and sign in with `ADMIN_EMAIL` /
   `ADMIN_PASSWORD`.

9. **Bring over the data.** Copy the backup zip to the PC, then in a terminal:

   ```bat
   cd /d D:\HddServer\sites\rop
   php artisan registry:restore "E:\path\to\rop-full-YYYYMMDD-HHMMSS.zip" --force
   ```

   (or Backups page → Upload a backup → Restore). The restore replaces
   everything, including the sign-in account: from now on, sign in with the
   account and password from the old PC. Check the patient count and open a few
   attachments.

10. **Open it to the network.**
    - Give the PC a fixed address: a DHCP reservation in the router, or a static
      IP in Windows.
    - Set the network to **Private** in Windows network settings.
    - Make sure nothing else uses port 80: `netstat -ano | findstr :80` should
      only list nginx.
    - From another PC open `http://<the PC's IP>/`. HDDServer's installer already
      allowed nginx through the Windows firewall.

11. **Camera inbox (if used).** Set the camera's export folder on the Camera
    inbox page (or `CAPTURE_FOLDER` in `.env`). For the virtual printer, set
    `D:\HddServer\sites\rop\capture-images.bat` as the program it runs after
    printing; it uses HDDServer's `php` automatically.

## Updating later

On the development PC, commit and push to `main`. On the clinic PC run
`D:\HddServer\deployment\Update.cmd` and choose `rop`. It puts the registry in
maintenance mode, makes a full backup (`registry:backup`), pulls, installs
dependencies, rebuilds the frontend, migrates, caches and brings the site back.
An unchanged branch is a no-op.

## Good to know

- **Addresses.** Pages, links and sign-in follow whatever address is typed in
  the browser, so `localhost`, the Ethernet IP, a Wi-Fi IP or the PC's name all
  work at once, and a changed IP needs no `.env` change. Each address keeps its
  own sign-in, so a browser that switches address signs in once more.
  `APP_URL` is only used for things the registry does not rely on (e-mail links,
  passkeys, the public disk).
- **Editing `.env` later.** The config is cached, so after any change run
  `php artisan optimize` in `D:\HddServer\sites\rop`.
- **One request at a time.** HDDServer runs a single PHP process. Fine for a
  few users; many simultaneous users will feel it.
- **Plain HTTP** is fine on the clinic network; the registry uses no browser
  features that require HTTPS.
- **Don't edit files in `D:\HddServer\sites\rop`.** Updates refuse a checkout
  with local changes; change the code on the development PC and push.
- **Backups need no cron job.** When a signed-in user opens a page, the
  registry checks (at most once an hour) whether the newest backup is older than
  24 hours, and if so makes a database-only backup right after answering. It
  keeps the last 30 (`BACKUP_KEEP`). Days with nobody using it get no backup,
  but nothing changed on those days either. Attachments are only in **Full**
  backups, so make one now and then (Backups page, or
  `php artisan registry:backup`) and keep a copy off the PC.

## Troubleshooting

| Symptom | Fix |
|---|---|
| `Install.cmd` stopped with "Edit .env ... then run Install.cmd again" | Expected on the first run; do step 7.2 and run it again. |
| Install failed part-way | Read the log path it prints, fix the cause, run `Install.cmd` again. |
| Update failed | The site stays in maintenance mode. Read the log, fix, then run `Resume.cmd` (or `Update.cmd` again). |
| "Application health check failed" | HDDServer was not running or nginx is not using this folder's `nginx.conf`. Start it, then run `Resume.cmd`. |
| Error 500, log says "No application encryption key" | `.env` had no `APP_KEY=` line. Add an empty `APP_KEY=` line, then run `php artisan optimize:clear`, `php artisan key:generate --force`, `php artisan optimize`. |
| "Server is starting..." when reloading any page other than the home page | nginx is using an old `nginx.conf` without the `fastcgi_buffer_size` lines (its `logs\nginx\error.log` says "upstream sent too big header"). Copy this folder's `nginx.conf` to `D:\HddServer\conf` and restart HDDServer. |
| A change in `.env` has no effect | Run `php artisan optimize`. |
| Other PCs can't connect | Network set to Private? Right IP? Port 80 free? Try `http://<IP>/health`, which should answer `ok`. |
| Error details | `D:\HddServer\sites\rop\storage\logs\laravel-*.log`, and HDDServer's `logs\nginx\error.log`. Deployment logs are under `%LOCALAPPDATA%\HDDServer\deployments\...\rop\logs`. |

## Testing in a throw-away Windows

A Hyper-V VM or Windows Sandbox works for a dry run. Windows Sandbox has only
a C: drive: install HDDServer to `C:\HddServer` and set `applicationDirectory`
to `C:\\HddServer\\sites\\rop`. Remove the test PC's deploy key on GitHub when
done.
