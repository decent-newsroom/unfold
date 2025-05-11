
# 🕒 Cron Job Container

This folder contains the Docker configuration to run scheduled Symfony commands via cron inside a separate container.

- Run Symfony console commands periodically using a cron schedule (e.g. every 6 hours)
- Decouple scheduled jobs from the main PHP/FPM container
- Easily manage and test cron execution in a Dockerized Symfony project

---

## Build & Run

1. **Build the cron image**  
   From the project root:
   ```bash
   docker-compose build cron
   ```

2. **Start the cron container**  
   ```bash
   docker-compose up -d cron
   ```

---

## Cron Schedule

The default cron schedule is set to run **every 6 hours**:

```cron
0 */6 * * * root /run_commands.sh >> /var/log/cron.log 2>&1
```

To customize the schedule, edit the `crontab` file and rebuild the container.

---

## Testing & Debugging

### Manually test the command runner

You can run the script manually to check behavior without waiting for the cron trigger:

```bash
docker-compose exec cron /run_commands.sh
```

### Check the cron output log

```bash
docker-compose exec cron tail -f /var/log/cron.log
```

### Shell into the cron container

```bash
docker-compose exec cron bash
```

Once inside, you can:
- Check crontab entries: `crontab -l`
- Manually trigger cron: `cron` or `cron -f` (in another session)

---

## Customization

- **Add/Remove Symfony Commands:**  
  Edit `run_commands.sh` to include the commands you want to run.

- **Change Schedule:**  
  Edit `crontab` using standard cron syntax.

- **Logging:**  
  Logs are sent to `/var/log/cron.log` inside the container.

---

## Rebuilding After Changes

If you modify the `crontab` or `run_commands.sh`, make sure to rebuild:

```bash
docker-compose build cron
docker-compose up -d cron
```

---

## Notes

- Symfony project source is mounted at `/var/www/html` via volume.
- Make sure your commands do **not rely on services** (like `php-fpm`) that are not running in this container.
