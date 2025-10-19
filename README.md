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
sudo apt install pps-tools gpsd chrony nginx php-fpm php-gd
```

2. Edit `/boot/firmware/config.txt`

- Uncomment `dtparam=i2c_arm=on`

- Add the following under `[all]`:

  ```bash
  # Overclock
  arm_freq=1000
  core_freq=500
  sdram_freq=500
  over_voltage=2
  # Serial, RTC, PPS
  enable_uart=1
  dtoverlay=i2c-rtc,ds3231
  dtoverlay=pps-gpio,gpiopin=18
  nohz=off
  ```

3. Edit `/boot/firmware/cmdline.txt`, remove `console=serial0,115200`

4. Reboot to apply changes.

5. Setup GPSd

- Edit `/etc/default/gpsd`. Use file in this repo as reference.

- Edit systemd service to start after chrony: `sudo systemctl edit gpsd`

  Add:
  ```
  [Unit]
  After=chrony.service
  ```

- Start GPSd:

  ```bash
  sudo systemctl disable --now serial-getty@ttyAMA0.service
  sudo systemctl mask serial-getty@ttyAMA0.service
  sudo systemctl enable --now gpsd
  systemctl status gpsd
  ```

- Check using `gpsmon` and `cgps` commands.

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
