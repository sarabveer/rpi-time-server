# Raspberry Pi Time Server

Stratum 1 GNSS NTP Server running on a Raspberry Pi 2

## Equipment

These are the parts I have used.

- [Raspberry Pi 2](https://www.raspberrypi.com/products/raspberry-pi-2-model-b/)
- [SparkFun ublox NEO-M9N](https://www.amazon.com/gp/product/B082YDZLL9)
  - This [Adafruit](https://www.adafruit.com/product/5440) module is a good alternative
- [Adafruit DS3231 RTC](https://www.adafruit.com/product/3013)
- Some cheap [Active GPS Antenna](https://www.amazon.com/gp/product/B07R7RC96G)
- uFL to SMA Adapter Pigtail ([Example](https://www.adafruit.com/product/851))
- Pin Headers for the GNSS module (Soldering required)
- Some Male-to-Female DuPont Cables

Previously the [BeagleBone Black](https://beagleboard.org/black) SBC was used, check the `beaglebone` branch for more information.

## Setup

1. Install packages:

```bash
sudo apt update
sudo apt install pps-tools chrony nginx php-fpm php-gd
```

On trixie, build GPSd 3.27.x from the forky source package. Trixie currently
ships GPSd 3.25, while forky/sid have GPSd 3.27.x.

Fetch the Debian 13/trixie archive signing key first. Forky source metadata is
signed by Debian's archive key, not Raspberry Pi OS's archive key.

```bash
sudo dpkg -r gpsd-build-deps
```

```bash
sudo apt install ca-certificates wget
sudo install -d -m 0755 /usr/share/keyrings
sudo wget -O /usr/share/keyrings/debian-archive-trixie-automatic.asc https://ftp-master.debian.org/keys/archive-key-13.asc

sudo tee /etc/apt/sources.list.d/debian-forky.sources >/dev/null <<'EOF'
Types: deb-src
URIs: http://deb.debian.org/debian
Suites: forky
Components: main
Signed-By: /usr/share/keyrings/debian-archive-trixie-automatic.asc
EOF

sudo apt update
sudo apt install build-essential scons pkgconf python3 python-is-python3 python3-serial libncurses-dev libusb-1.0-0-dev libsystemd-dev libudev-dev
apt source gpsd/forky
cd gpsd-3.27.*

scons -c
scons prefix=/usr systemd=yes manbuild=no qt=no dbus_export=no
sudo scons prefix=/usr systemd=yes manbuild=no qt=no dbus_export=no install
sudo scons prefix=/usr systemd=yes manbuild=no qt=no dbus_export=no udev-install

if ! getent passwd gpsd >/dev/null; then
  sudo adduser --system --no-create-home --home /run/gpsd --ingroup dialout gpsd
fi
sudo adduser gpsd dialout
gpsd -V
```

2. Edit `/boot/firmware/config.txt`

- Uncomment `dtparam=i2c_arm=on`

- Add the following under `[all]`:

  ```bash
  enable_uart=1
  dtoverlay=i2c-rtc,ds3231
  dtoverlay=pps-gpio,gpiopin=18
  ```

3. Edit `/boot/firmware/cmdline.txt`, remove `console=serial0,115200`, add `nohz=off`.

4. Reboot to apply changes.

5. Setup GPSd

- Edit `/etc/default/gpsd`. Use file in this repo as reference.

- Edit systemd service to start after chrony: `sudo systemctl edit gpsd`

  Add:
  ```
  [Unit]
  Wants=chrony.service
  After=chrony.service
  ```

- Start GPSd:

  ```bash
  sudo systemctl disable --now serial-getty@ttyAMA0.service
  sudo systemctl mask serial-getty@ttyAMA0.service
  sudo systemctl enable --now gpsd
  systemctl status gpsd
  ```

- Check using the `cgps` command.

6. Setup Chrony

- Edit Chrony config files. Use file in this repo as reference.

- Start Chrony:

  ```bash
  sudo systemctl enable chrony
  sudo systemctl restart chrony gpsd
  systemctl status chrony
  ```

- Check using `chronyc tracking`, `chronyc sources -v`, and `chronyc sourcestats -v` commands.

7. (Optional) Setup nginx + php to serve webpage: `/etc/nginx/` & `/var/www/html`.

### Webpage

The following webpages are included in this repo.

#### index.html

Shows current time + offset, similar to [time.is](https://time.is) (Requires `serverDate.js.php`).

#### chrony.php

Quick page that shows the output of the following commands: `chronyc sources`, `chronyc sourcestats`, `chronyc tracking`.

## References

My setup is based off the following guides.

- https://austinsnerdythings.com/2021/04/19/microsecond-accurate-ntp-with-a-raspberry-pi-and-pps-gps/
- https://robrobinette.com/pi_GPS_PPS_Time_Server.htm
- http://www.satsignal.eu/ntp/Raspberry-Pi-NTP.html
- https://conorrobinson.ie/raspberry-pi-ntp-server-part-6/
