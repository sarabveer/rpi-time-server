# BeagleBone Black Time Server

Stratum 1 GNSS NTP Server running on a BeagleBone Black

## Equipment 

These are the parts I have used.

- [BeagleBone Black](https://beagleboard.org/black)
- [SparkFun ublox NEO-M9N](https://www.amazon.com/gp/product/B082YDZLL9)
  - I went a little overkill on this one, but this module supports GPS, GLONASS, BeiDou, and Galileo
  - This [Adafruit](https://www.adafruit.com/product/5440) module is a good alternative
- [Adafruit DS3231 RTC](https://www.adafruit.com/product/3013)
- Some cheap [Active GPS Antenna](https://www.amazon.com/gp/product/B07R7RC96G)
- uFL to SMA Adapter Pigtail ([Example](https://www.adafruit.com/product/851))
- Pin Headers for the GNSS module (Soldering required)
- Some Male-to-Female DuPont Cables

## Wiring

| BBB              | GNSS Module |
|------------------|-------------|
| 5V               | 5V          |
| GND              | GND         |
| P9_11 (UART4_TX) | RX          |
| P9_13 (UART4_RX) | TX          |
| P9_12 (GPIO 60)  | PPS         |

## Setup

1. [Install Debian Minimal on the BeagleBone Black](https://forum.beagleboard.org/t/debian-11-x-bullseye-monthly-snapshots/31280)

2. Install packages:

```bash
sudo apt update
sudo apt install git build-essential pps-tools gpsd chrony nginx php-fpm php-gd
```

3. Compile PPS device tree

  - Clone `bb.org-overlays` repo:
   
    ```bash
    git clone https://github.com/beagleboard/bb.org-overlays
    ```
  
  - Copy `DD-PPS-00A0.dts` into cloned `bb.org-overlays/src/arm/`.
  - Compile .dtbo and place in `/lib/firmware`:
  
    ```bash
    make src/arm/DD-PPS.dtbo
    sudo cp src/arm/DD-PPS.dtbo /lib/firmware
    ```

4. Edit `/boot/uEnv.txt`:

```bash
###Overide capes with eeprom
uboot_overlay_addr0=/lib/firmware/BB-I2C2-RTC-DS3231.dtbo
uboot_overlay_addr1=/lib/firmware/BB-UART4-00A0.dtbo
uboot_overlay_addr2=/lib/firmware/DD-PPS.dtbo
```

5. Reboot to apply changes.

6. Setup GPSd
  
  - Edit `/etc/default/gpsd`. Use file in this repo as reference.

  - Start GPSd:

    ```bash
    sudo systemctl start gpsd
    sudo systemctl enable gpsd
    systemctl status gpsd
    ```

  - Check using `gpsmon` and `cgps` commands.

6. Setup Chrony
  
  - Edit Chrony config files. Use file in this repo as reference.

  - Start GPSd:

    ```bash
    sudo systemctl start chrony
    systemctl status chrony
    ```

  - Check using `chronyc tracking`, `chronyc sources -v`, and `chronyc sourcestats -v` commands.

7. (Optional) Setup nginx + php to serve `/var/www/html`.

## Photos

![Alt text](/img/outside.jpeg?raw=true "Built Unit")
![Alt text](/img/inside.jpeg?raw=true "Inside Unit")

### Time Page

This is the html website included in this repo. In concept, it is similar to [time.is](https://time.is).

![Alt text](/img/time.png?raw=true "Current Time")

## References

My setup is based off the following guides, very thankful for them.

- https://austinsnerdythings.com/2021/04/19/microsecond-accurate-ntp-with-a-raspberry-pi-and-pps-gps/
- http://www.satsignal.eu/ntp/Raspberry-Pi-NTP.html
