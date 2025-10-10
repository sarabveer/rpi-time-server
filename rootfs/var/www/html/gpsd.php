<?php

# @MASTER@
# @GENERATED@

# Copyright 2006 Chris Kuethe <chris.kuethe@gmail.com>
#
# This file is Copyright 2009 The GPSD project
# SPDX-License-Identifier: BSD-2-clause

# This code validates with https://validator.w3.org/
# Keep it valid.

global $head, $blurb, $title, $showmap, $autorefresh, $footer, $gmap_key;
global $server, $advertise, $port, $open, $swap_ew, $testmode;
global $colors;
$testmode = 1; # leave this set to 1

# Public script parameters:
#   host: host name or address where GPSd runs. Default: from config file
#   port: port of GPSd. Default: from config file
#   op=view: show just the skyview image instead of the whole HTML page
#     sz=small: used with op=view, display a small (240x240px) skyview
#   op=json: respond with the GPSd POLL JSON structure
#     jsonp=prefix: used with op=json, wrap the POLL JSON in parentheses
#                   and prepend prefix

# To draw the skyview, make sure that GD is installed and configured with PHP

# If you're running PHP with the Suhosin patch (like the Debian PHP5 package),
# it may be necessary to increase the value of the
# suhosin.get.max_value_length parameter to 2048. The imgdata parameter used
# for displaying the skyview is longer than the default 512 allowed by Suhosin.
# Debian has the config file at /etc/php5/conf.d/suhosin.ini.

# If you use the OPenLayers code you will likely want to server
# their JS locally.

# this script shouldn't take more than a few seconds to run
set_time_limit(3);
ini_set('max_execution_time', 3);

if (!file_exists("gpsd_config.inc"))
        write_config();

require_once("gpsd_config.inc");

# sample data
$resp = <<<EOF
{"class":"POLL","time":"2010-04-05T21:27:54.84Z","active":1,
 "tpv":[{"class":"TPV","tag":"MID41","device":"/dev/ttyUSB0",
           "time":1270517264.240,"ept":0.005,"lat":40.035093060,
           "lon":-75.519748733,"alt":31.1,"track":99.4319,
           "speed":0.123,"mode":3}],
 "sky":[{"class":"SKY","tag":"MID41","device":"/dev/ttyUSB0",
              "time":"2010-04-05T21:27:44.84Z","hdop":9.20,"vdop":12.1,
              "satellites":[{"PRN":16,"el":55,"az":42,"ss":36,"used":true},
                            {"PRN":19,"el":25,"az":177,"ss":0,"used":false},
                            {"PRN":7,"el":13,"az":295,"ss":0,"used":false},
                            {"PRN":6,"el":56,"az":135,"ss":32,"used":true},
                            {"PRN":13,"el":47,"az":304,"ss":0,"used":false},
                            {"PRN":23,"el":66,"az":259,"ss":40,"used":true},
                            {"PRN":20,"el":7,"az":226,"ss":0,"used":false},
                            {"PRN":3,"el":52,"az":163,"ss":32,"used":true},
                            {"PRN":31,"el":16,"az":102,"ss":0,"used":false}
                           ]
             }
            ]
}
EOF;



# if we're passing in a query, let's unpack and use it
$op = isset($_GET['op']) ? $_GET['op'] : '';
if (isset($_GET['imgdata']) && $op == 'view'){
    $resp = base64_decode($_GET['imgdata']);
    if ($resp){
        gen_image($resp);
        exit(0);
    }
} else {
    if (isset($_GET['host']))
            if (!preg_match('/[^a-zA-Z0-9\.-]/', $_GET['host']))
                    $server = $_GET['host'];

    if (isset($_GET['port']))
            if (!preg_match('/\D/', $_GET['port']) &&
                ($port > 0) && ($port < 65536))
                    $port = $_GET['port'];

    if ($testmode){
        $have_sky = false;
        $have_tpv = false;
        $skyresp = "";
        $tpvresp = "";
        $sock = @fsockopen($server, $port, $errno, $errstr, 2);
        @fwrite($sock, "?WATCH={\"enable\":true,\"json\":true}\n");
        // Start the loop to start reading from gpsd.
        usleep(1000);
        for($tries = 0; $tries < 100; $tries++){
                $resp = @fread($sock, 10000); # SKY can be pretty big
                if (preg_match('/{"class":"SKY".+}/i', $resp, $m)){
                        $skyresp = $m[0];
                        $have_sky = true;
                }
                elseif (preg_match('/{"class":"TPV".+}/i', $resp, $m)){
                        $tpvresp = $m[0];
                        $have_tpv = true;
                }
                if ($have_sky && $have_tpv) {
                        break;
                }
        }
        @fclose($sock);
        if (!$resp)
            $resp = '{"class":"ERROR","message":"no response from GPS daemon"}';
    }
}
# fake a partial ?POLL response
$resp = '{"class":"POLL","tpv":[' . $tpvresp . '],"sky":[' . $skyresp . ']}';

# ensure all satellites keys exist, for clean logs.
function sat_clean($sat) {
    $skeys = array('az', 'el', 'gnssid', 'health', 'PRN', 'ss', 'svid', 'used');
    foreach($skeys as $key) {
        if (!array_key_exists($key, $sat)) {
            $sat[$key] = 'n/a';
        }
    }
    return $sat;
}

# format $arr[$field], adding $unit, optionally add $errkey/$errunit
# otherwise return 'n/a' or 'n/a</td><td>'
function field_str($arr, $key, $unit, $errkey = '', $errunit = '') {
    if (!array_key_exists($key, $arr)) {
        if ($errkey) {
            return 'n/a</td><td>&nbsp;';
        } else {
            return 'n/a';
        }
    }
    if ('' == $errunit) {
        $errunit = $unit;
    }
    $ret = strval($arr[$key]);
    if ('' != $unit) {
        $ret .= " " . $unit;
        if ($errkey) {
            $ret .= "</td><td>&nbsp;" ;
            if (array_key_exists($errkey, $arr)) {
                $ret .= "&plusmn;" . strval($arr[$errkey]) .
                        "&nbsp;" . $errunit;
            }
        }
    }
    return $ret;
}


if ($op == 'view')
        gen_image($resp);
else if ($op == 'json')
        write_json($resp);
else
        write_html($resp);

exit(0);

function colorsetup($im){
        global $colors;

        $C['white']     = imageColorAllocate($im, 255, 255, 255);
        $C['ltgray']    = imageColorAllocate($im, 191, 191, 191);
        $C['mdgray']    = imageColorAllocate($im, 127, 127, 127);
        $C['dkgray']    = imageColorAllocate($im, 63, 63, 63);
        $C['black']     = imageColorAllocate($im, 0, 0, 0);
        $C['red']       = imageColorAllocate($im, 236, 50, 31);
        $C['brightgreen'] = imageColorAllocate($im, 0, 255, 0);
        $C['darkgreen'] = imageColorAllocate($im, 0, 192, 0);
        $C['blue']      = imageColorAllocate($im, 0, 0, 255);
        $C['cyan']      = imageColorAllocate($im, 0, 255, 255);
        $C['magenta']   = imageColorAllocate($im, 255, 0, 255);
        $C['yellow']    = imageColorAllocate($im, 255, 255, 0);
        $C['burntyellow']    = imageColorAllocate($im, 199, 163, 23);
        $C['orange']    = imageColorAllocate($im, 255, 128, 0);
        $colors = $C;

        return $C;
}

function legend($im, $sz, $C){
        $r = 30;
        $fn = 5;
        $x = $sz - (4*$r+7) - 2;
        $y = $sz - $r - 3;

        imageFilledRectangle($im, $x, $y, $x + 4*$r + 7, $y + $r +1,
                             $C['dkgray']);
        imageRectangle($im, $x+0*$r+1, $y+1, $x + 1*$r + 0, $y + $r,
                       $C['red']);
        imageRectangle($im, $x+1*$r+2, $y+1, $x + 2*$r + 2, $y + $r,
                       $C['burntyellow']);
        imageRectangle($im, $x+2*$r+4, $y+1, $x + 3*$r + 4, $y + $r,
                       $C['darkgreen']);
        imageRectangle($im, $x+4*$r+6, $y+1, $x + 3*$r + 6, $y + $r,
                       $C['brightgreen']);
        imageString($im, $fn, $x+3+0*$r, $y+$r/3, "<30", $C['red']);
        imageString($im, $fn, $x+5+1*$r, $y+$r/3, "30+", $C['burntyellow']);
        imageString($im, $fn, $x+7+2*$r, $y+$r/3, "35+", $C['darkgreen']);
        imageString($im, $fn, $x+9+3*$r, $y+$r/3, "40+", $C['brightgreen']);
}

function radial($angle, $sz){
        #turn into radians
        $angle = deg2rad($angle);

        # determine length of radius
        $r = $sz * 0.5 * 0.95;

        # and convert length/azimuth to cartesian
        $x0 = sprintf("%d", (($sz * 0.5) - ($r * cos($angle))));
        $y0 = sprintf("%d", (($sz * 0.5) - ($r * sin($angle))));
        $x1 = sprintf("%d", (($sz * 0.5) + ($r * cos($angle))));
        $y1 = sprintf("%d", (($sz * 0.5) + ($r * sin($angle))));

        return array($x0, $y0, $x1, $y1);
}

function azel2xy($az, $el, $sz){
        global $swap_ew;
        #rotate coords... 90deg E = 180deg trig
        $az += 270;

        #turn into radians
        $az = deg2rad($az);

        # determine length of radius
        $r = $sz * 0.5 * 0.95;
        $r -= ($r * ($el/90));

        # and convert length/azimuth to cartesian
        $x = sprintf("%d", (($sz * 0.5) + ($r * cos($az))));
        $y = sprintf("%d", (($sz * 0.5) + ($r * sin($az))));
        if ($swap_ew != 0)
                $x = $sz - $x;

        return array($x, $y);
}

function imageCircle($im, $x, $y, $r, $color, $filled){
    $t = $r / 2;

    if ($filled) {
        imageFilledArc($im, $x, $y, $r, $r, 0, 360, $color, 0);
    } else {
        imageArc($im, $x, $y, $r, $r, 0, 360, $color);
    }
}

function imageDiamond($im, $x, $y, $r, $color, $filled){
    $t = $r / 2;

    $vx = array($x + $t, $y, $x, $y + $t, $x - $t, $y, $x, $y - $t,
                $x + $t, $y);

    if ($filled) {
        imageFilledPolygon($im, $vx, 5, $color);
    } else {
        imagepolygon($im, $vx, 5, $color);
    }
}

function imageSquare($im, $x, $y, $r, $color, $filled){
    global $colors;

    $t = $r / 2;
    $vx = array($x + $t, $y + $t,
                $x + $t, $y - $t,
                $x - $t, $y - $t,
                $x - $t, $y + $t,
                $x + $t, $y + $t);
    if ($filled) {
        imageFilledPolygon($im, $vx, 5, $color);
    } else {
        imagepolygon($im, $vx, 5, $color);
    }
}

# Triangle pointing down
function imageTriangleD($im, $x, $y, $r, $color, $filled){
    $t = $r / 2;

    $vx = array($x, $y + $t,
                $x + $t, $y - $t,
                $x - $t, $y - $t,
                $x, $y + $t);

    if ($filled) {
        imageFilledPolygon($im, $vx, 4, $color);
    } else {
        imagepolygon($im, $vx, 4, $color);
    }
}

# Triangle pointing up
function imageTriangleU($im, $x, $y, $r, $color, $filled){
    $t = $r / 2;

    $vx = array($x, $y - $t,
                $x - $t, $y + $t,
                $x + $t, $y + $t,
                $x, $y - $t);

    if ($filled) {
        imageFilledPolygon($im, $vx, 4, $color);
    } else {
        imagepolygon($im, $vx, 4, $color);
    }
}

function splot($im, $sz, $C, $e){
        # ensure all $e keys exist, for clean logs.
        $keys = array('PRN', 'az', 'el', 'gnssid', 'ss');
        foreach($keys as $key) {
            if (!array_key_exists($key, $e)) {
                return;
            }
            if (!is_numeric($e[$key])) {
                return;
            }
        }
        if (!array_key_exists('used', $e)) {
            return;
        }
        if (!array_key_exists('health', $e) ||
            !is_numeric($e['health'])) {
            $e['health'] = 0;
        }

        # validate ranges
        if ((0 >= $e['PRN']) || (0 > $e['az']) || (0 > $e['el']) ||
            (0 > $e['ss'])) {
                return;
        }

        $color = $C['brightgreen'];
        if ($e['ss'] < 40)
                $color = $C['darkgreen'];
        if ($e['ss'] < 35)
                $color = $C['burntyellow'];
        if ($e['ss'] < 30)
                $color = $C['red'];
        if ($e['el']<10)
                $color = $C['blue'];
        if ($e['ss'] < 10)
                $color = $C['black'];

        list($x, $y) = azel2xy($e['az'], $e['el'], $sz);

        $r = 12;
        if (isset($_GET['sz']) && ($_GET['sz'] == 'small'))
                $r = 8;

        imageString($im, 3, $x+4, $y+4, $e['PRN'], $C['black']);
        imagesetthickness($im, 2);
        switch ($e['gnssid']) {
        case 0:
            # GPS
            # FALLTHROUGH
        case 5:
            # QZSS
            imageCircle($im, $x, $y, $r, $color, $e['used']);
            break;
        case 1:
            # SBAS
            # FALLTHROUGH
        case 4:
            # IMES
            # FALLTHROUGH
        default:
            imageDiamond($im, $x, $y, $r, $color, $e['used']);
            break;
        case 2:
            # Galileo
            imageTriangleU($im, $x, $y, $r, $color, $e['used']);
            break;
        case 3:
            # BeiDou
            imageTriangleD($im, $x, $y, $r, $color, $e['used']);
            break;
        case 6:
            # GLONASS
            imageSquare($im, $x, $y, $r, $color, $e['used']);
            break;
        }
}

function elevation($im, $sz, $C, $a){
        $b = 90 - $a;
        $a = $sz * 0.95 * ($a/180);
        imageArc($im, $sz/2, $sz/2, $a*2, $a*2, 0, 360, $C['ltgray']);
        $x = $sz/2 - 16;
        $y = $sz/2 - $a;
        imageString($im, 2, $x, $y, $b, $C['ltgray']);
}

function skyview($im, $sz, $C){
        global $swap_ew;
        $a = 90; $a = $sz * 0.95 * ($a/180);
        imageFilledArc($im, $sz/2, $sz/2, $a*2, $a*2, 0, 360, $C['mdgray'], 0);
        imageArc($im, $sz/2, $sz/2, $a*2, $a*2, 0, 360, $C['black']);
        $x = $sz/2 - 16; $y = $sz/2 - $a;
        imageString($im, 2, $x, $y, "0", $C['ltgray']);

        $a = 85; $a = $sz * 0.95 * ($a/180);
        imageFilledArc($im, $sz/2, $sz/2, $a*2, $a*2, 0, 360, $C['white'], 0);
        imageArc($im, $sz/2, $sz/2, $a*2, $a*2, 0, 360, $C['ltgray']);
        imageString($im, 1, $sz/2 - 6, $sz+$a, '5', $C['black']);
        $x = $sz/2 - 16; $y = $sz/2 - $a;
        imageString($im, 2, $x, $y, "5", $C['ltgray']);

        for($i = 0; $i < 180; $i += 15){
                list($x0, $y0, $x1, $y1) = radial($i, $sz);
                imageLine($im, $x0, $y0, $x1, $y1, $C['ltgray']);
        }

        for($i = 15; $i < 90; $i += 15)
                elevation($im, $sz, $C, $i);

        $x = $sz/2 - 16; $y = $sz/2 - 8;
        /* imageString($im, 2, $x, $y, "90", $C['ltgray']); */

        imageString($im, 4, $sz/2 + 4, 2        , 'N', $C['black']);
        imageString($im, 4, $sz/2 + 4, $sz - 16 , 'S', $C['black']);
        if ($swap_ew != 0){
                imageString($im, 4, 4        , $sz/2 + 4, 'E', $C['black']);
                imageString($im, 4, $sz - 10 , $sz/2 + 4, 'W', $C['black']);
        } else {
                imageString($im, 4, 4        , $sz/2 + 4, 'W', $C['black']);
                imageString($im, 4, $sz - 10 , $sz/2 + 4, 'E', $C['black']);
        }
}

function gen_image($resp){
        $sz = 600;
        if (isset($_GET['sz']) && ($_GET['sz'] == 'small'))
                $sz = 240;

        $GPS = json_decode($resp, true);
        if ($GPS['class'] != "POLL"){
                die("json_decode error: $resp");
        }

        $im = imageCreate($sz, $sz);
        $C = colorsetup($im);
        skyview($im, $sz, $C);
        if (240 < $sz)
            legend($im, $sz, $C);

        if (array_key_exists('sky', $GPS) &&
            array_key_exists(0, $GPS['sky']) &&
            array_key_exists('satellites', $GPS['sky'][0])) {
            for($i = 0; $i < count($GPS['sky'][0]['satellites']); $i++){
                $sat = sat_clean($GPS['sky'][0]['satellites'][$i]);
                splot($im, $sz, $C, $sat);
            }
        }

        header("Content-type: image/png");
        imagePNG($im);
        imageDestroy($im);
}

function dfix($x, $y, $z){
        if ($x < 0){
                $x = sprintf("%f %s", -1 * $x, $z);
        } else {
                $x = sprintf("%f %s", $x, $y);
        }
        return $x;
}

# compare sats for sort.  used at top.
function sat_cmp($a, $b) {
    if ($b['used'] != $a['used']) {
        # used Y before used N
        return $b['used'] - $a['used'];
    }
    return $a['PRN'] - $b['PRN'];
}

function write_html($resp) {
        global $sock, $errstr, $errno, $server, $port, $head, $body, $open;
        global $blurb, $title, $autorefresh, $showmap, $gmap_key, $footer;
        global $testmode, $advertise;

        $GPS = json_decode($resp, true);
        if ($GPS['class'] != 'POLL'){
                die("json_decode error: $resp");
        }

        header("Content-type: text/html; charset=UTF-8");

        global $lat, $lon;
        # make sure some things exist
        if (!array_key_exists('sky', $GPS)) {
            $GPS['sky'] = array();
        }
        if (!array_key_exists(0, $GPS['sky'])) {
            $GPS['sky'][0] = array();
        }
        if (!array_key_exists('satellites', $GPS['sky'][0])) {
            $GPS['sky'][0]['satellites'] = array();
        }
        if (!array_key_exists('tpv', $GPS)) {
            $GPS['tpv'] = array();
        }
        if (!array_key_exists(0, $GPS['tpv'])) {
            $GPS['tpv'][0] = array();
        }
        if (!array_key_exists('lat', $GPS['tpv'][0]) ||
            !array_key_exists('lon', $GPS['tpv'][0])) {
            $GPS['tpv'][0]['lat'] = 0.0;
            $GPS['tpv'][0]['lon'] = 0.0;
        }
        if (!array_key_exists('mode', $GPS['tpv'][0])) {
            $GPS['tpv'][0]['mode'] = 0;
        }
        if (!array_key_exists('time', $GPS['tpv'][0])) {
            $GPS['tpv'][0]['time'] = 0;
        }

        $lat = (float)$GPS['tpv'][0]['lat'];
        $lon = (float)$GPS['tpv'][0]['lon'];

        $x = $server; $y = $port;
        $imgdata = base64_encode($resp);
        $server = $x; $port = $y;

        if ($autorefresh > 0)
            $autorefresh = "<meta http-equiv='Refresh' content='$autorefresh'>";
        else
            $autorefresh = '';

        $map_head = $map_body = $map_code = '';
        if ($showmap == 1) {
                $map_head = gen_gmap_head();
                $map_body = 'onload="Load()" onunload="GUnload()"';
                $map_code = gen_map_code();
        } else if ($showmap == 2) {
                $map_head = gen_osm_head();
                $map_body = '';
                $map_code = gen_osmmap_code();
        }
        $part_header = <<<EOF
<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
{$head}
{$map_head}
<title>{$title} - GPSD Test Station {$lat}, {$lon}</title>
{$autorefresh}
<style>
.blink {
    color: #dc322f;
    animation: blinking 1.5s linear infinite;
}

@keyframes blinking{
    0%{
      opacity: 0;
    }
    50%{
      opacity: 0.7;
    }
    100%{
      opacity: 0;
    }
}

.warning {
    color: #dc322f;
}

.fixed {
    font-family: mono-space;
}

.caption {
    text-align: left;
    margin: 1ex 1em 1ex 1em; /* top right bottom left */
}

.administrivia {
    font-size: small;
    font-family: verdana, sans-serif;
}

body.light {
    background-color: #fdf6e3;
    color: #657b83;
}
body.dark {
    background-color: #002b36;
    color: #839496;
}

td:nth-child(1) {
    text-align: right;
    font-weight: bold;
}
</style>
</head>

<body {$body} {$map_body}>
<div id="main" style="clear:both">
<div>
{$blurb}
<br>
</div>
EOF;

        if (0 == strlen($advertise))
                $advertise = $server;

        if ($testmode && !$sock)
                $part_sky = "";
        else
                $part_sky = <<<EOF
<!-- -->

<div style="width:600px;float:right;margin:0 0 1ex 1em;">
<img src="?op=view&amp;imgdata={$imgdata}"
width="600" height="600" alt="Skyview">
<br style="clear:both">
<p class="caption">A filled shape means the satellite was used in
the last fix.<br>
Green-yellow-red colors indicate signal to noise ratio in dBHz<br>
<span style="color:green;">green=best</span>,
<span style="color:#c7a317;">yellow=fair</span>,
and <span style="color:#dc322f">red=worst</span>.<br>
Circles are GPS and QZSS satellites.<br>
Diamonds indicate augmentation (SBAS, WAAS, etc.) satellites.<br>
Triangles pointing up are Galileo satellites.<br>
Triangles pointing down are BeiDou satellites.<br>
Squares are GLONASS satellites.<br>
</p>
{$map_code}
</div>
EOF;

        if ($open)
            $part3 = <<<EOF
<!-- -->

<div>To get real-time information, connect to
<span class="fixed">telnet://{$advertise}:{$port}/</span> and type "?POLL;"
or "?WATCH={"enable":true,"raw":true}".<br>
Use a different server:<br>
<form method=GET action="{$_SERVER['SCRIPT_NAME']}">
<input name="host" value="{$advertise}">:
<input name="port" value="{$port}" size="5" maxlength="5">
<input type=submit value="Get Position"><input type=reset></form>
<br>
</div>
EOF;
        else
            $part3 = '';

        if ($testmode && !$sock)
            $part_tpv_sky = <<<EOF
<div style="clear:both">The gpsd instance that this page monitors is
not running.</div>
EOF;
        else {
            $fix = $GPS['tpv'][0];
            $sky = $GPS['sky'][0];
            $sats = Array();
            if (array_key_exists('satellites', $sky)) {
                $sats = $sky['satellites'];
            }

            $fixtype = array('Unknown' => 0, 'No Fix' => 1, '2D Fix' => 2,
                             '3D Fix' => 3);
            $type = array_search($fix['mode'], $fixtype);

            $nsv = count($sats);
            $ts = $fix['time'];
            $sat = '';

            # gnssid to gnss abbreviation
            $gnss = array(0 => 'GP', 1 => 'SB', 2 => 'GA', 3 => 'BD',
                          4 => 'IM', 5 => 'QZ', 6 => 'GL');

            # sort sats
            usort($sats, "sat_cmp");

            $sats_used = 0;
            foreach($sats as $s) {
                $s = sat_clean($s);
                if (array_key_exists($s['gnssid'], $gnss)) {
                    $s['gnssid'] = $gnss[$s['gnssid']];
                } else {
                    $s['gnssid'] = '  ';
                }
                if ($s['used']) {
                    $sats_used += 1;
                }
                if (2 == (int)$s['health']) {
                    $used = $s['used'] ? 'uY&nbsp;' : 'uN&nbsp; ';
                } else {
                    $used = $s['used'] ? '&nbsp;Y&nbsp;' : '&nbsp;N&nbsp; ';
                }
                $sat .= sprintf(
                        "\t<tr style='text-align:right'><td>%s</td>" .
                        "<td>%d</td><td>%d&nbsp;</td><td>%d&nbsp;</td>" .
                        "<td>%d</td><td>%s</td></tr>\n",
                        $s['gnssid'] . $s['svid'],
                        $s['PRN'], $s['el'], $s['az'], $s['ss'],
                        $used
                );
            };


            # ensure all $fix keys exist, for clean logs.
            $fixkeys = array('lat', 'leapseconds', 'lon');
            foreach($fixkeys as $key) {
                if (!array_key_exists($key, $fix)) {
                    $fix[$key] = 'n/a';
                }
            }

            # ensure all $sky keys exist, for clean logs.
            $skykeys = array('gdop', 'hdop', 'pdop','tdop', 'vdop',
                             'xdop', 'ydop');
            foreach($skykeys as $key) {
                if (!array_key_exists($key, $sky)) {
                    $sky[$key] = 'n/a';
                }
            }

            $lat = field_str($fix, 'lat', '&deg;', 'epx', 'm');
            $lon = field_str($fix, 'lon', '&deg;', 'epy', 'm');
            $altHAE = field_str($fix, 'altHAE', 'm', 'epv');
            $altMSL = field_str($fix, 'altMSL', 'm');
            $geoidSep = field_str($fix, 'geoidSep', 'm');
            $speed = field_str($fix, 'speed', 'm/s', 'eps');
            $climb = field_str($fix, 'climb', 'm/s', 'epc');
            $velN = field_str($fix, 'velN', 'm/s');
            $velE = field_str($fix, 'velE', 'm/s');
            $velD = field_str($fix, 'velD', 'm/s');

            $track = field_str($fix, 'track', '&deg;', 'epd');
            $magtrack = field_str($fix, 'magtrack', '&deg;');
            $magvar = field_str($fix, 'magvar', '&deg;');

            $ecefx = field_str($fix, 'ecefx', 'm');
            $ecefy = field_str($fix, 'ecefy', 'm');
            $ecefz = field_str($fix, 'ecefz', 'm', 'ecefpAcc');
            $ecefvx = field_str($fix, 'ecefvx', 'm/s');
            $ecefvy = field_str($fix, 'ecefvy', 'm/s');
            $ecefvz = field_str($fix, 'ecefvz', 'm/s', 'ecefvAcc');

            $epc = field_str($fix, 'epc', 'm/s');
            $eph = field_str($fix, 'eph', 'm');
            $eps = field_str($fix, 'eps', 'm/s');
            $ept = field_str($fix, 'ept', 's');
            $epx = field_str($fix, 'epx', 'm');
            $epy = field_str($fix, 'epy', 'm');
            $epv = field_str($fix, 'epv', 'm');
            $sep = field_str($fix, 'sep', 'm');
            $ecefpAcc = field_str($fix, 'ecefpAcc', 'm');
            $ecefvAcc = field_str($fix, 'ecefvAcc', 'm');
            $sep = field_str($fix, 'sep', 'm');
            if (!array_key_exists('status', $fix)) {
                $fix['status'] = 1;
            }
            switch($fix['status']) {
            case 0:
                $status = 'No Fix';
                break;
            case 1:
                $status = 'Normal Fix';
                break;
            case 2:
                $status = 'DGPS Fix';
                break;
            case 3:
                $status = 'RTK Fixed Fix';
                break;
            case 4:
                $status = 'RTK Float Fix';
                break;
            case 5:
                $status = 'Dead Reckoning';
                break;
            case 6:
                $status = 'GNSS+DR Fix';
                break;
            case 7:
                $status = 'Surveyed-In Fix';
                break;
            case 8:
                $status = 'Simulated Fix';
                break;
            default:
                $status = 'Unknown Fix Type';
                break;
            }

            $part_tpv_sky = <<<EOF
<!-- -->
<div style="float:left;margin:0 0 1ex 1em;">
    <table style="border-width:1px;border-style:solid;text-align:center;">
        <tr><th colspan="3" style="text-align:center">Fix Data</th></tr>
        <tr><td>Fix Type</td><td>{$type}</td><td>&nbsp;</td></tr>
        <tr><td>Fix Status</td><td>{$status}</td><td>&nbsp;</td></tr>
        <tr><th colspan="3" style="text-align:center">Time</th></tr>
        <tr><td>UTC</td><td colspan="2">{$ts}&nbsp;</td></tr>
        <tr><td>Leap Seconds</td><td>{$fix['leapseconds']}</td><td>&nbsp;</td></tr>
        <tr><th colspan="3" style="text-align:center">Position</th></tr>
        <tr><td>Latitude</td><td>{$lat}</td></tr>
        <tr><td>Longitude</td><td>{$lon}</td></tr>
        <tr title="Height Above Ellipsoid. Typically WGS84">
          <td>Altitude HAE</td><td>{$altHAE}</td></tr>
        <tr title="Height Above Mean Sea Level">
          <td>Altitude MSL</td><td>{$altMSL}</td><td>&nbsp;</td></tr>
        <tr title="HAE - MSL"><td>Geoid Separation</td><td>{$geoidSep}</td><td>&nbsp;</td></tr>
        <tr><th colspan="3" style="text-align:center">Velocity</th></tr>
        <tr title="Horizontal Velocity"><td>Speed</td><td>{$speed}</td></tr>
        <tr title="Vertical Velocity"><td>Climb</td><td>{$climb}</td></tr>
        <tr title="Velocity North"><td>velN</td><td>{$velN}</td><td>&nbsp;</td></tr>
        <tr title="Velocity East"><td>velE</td><td>{$velE}</td><td>&nbsp;</td></tr>
        <tr title="Velocity Down"><td>velD</td><td>{$velD}</td><td>&nbsp;</td></tr>
        <tr><td>Track True</td><td>{$track}</td></tr>
        <tr><td>Track Magnetic</td><td>{$magtrack}</td><td>&nbsp;</td></tr>
        <tr><td>Magnetic Variation</td><td>{$magvar}</td><td>&nbsp;</td></tr>
    </table>
</div>
<div style="float:left;margin:0 0 1ex 1em;">
    <table style="border-width:1px;border-style:solid;text-align:center;">
        <tr title="Dimentionless Ratios">
            <th colspan="2" style="text-align:center">Dilution of Precision
            </th></tr>
        <tr title="Geometric Dilution Of Precision"><td>GDOP
            </td><td>{$sky['gdop']}</td></tr>
        <tr title="Horizontal (2D) Dilution Of Precision"><td>HDOP</td>
            <td>{$sky['hdop']}</td></tr>
        <tr title="Position (3D) Dilution Of Precision"><td>PDOP
            </td><td>{$sky['pdop']}</td></tr>
        <tr title="Time Dilution Of Precision"><td>TDOP</td>
            <td>{$sky['tdop']}</td></tr>
        <tr title="X (Longitude) Dilution Of Precision"><td>XDOP
            </td><td>{$sky['xdop']}</td></tr>
        <tr title="Y (Latitude) Dilution Of Precision"><td>YDOP
            </td><td>{$sky['ydop']}</td></tr>
        <tr title="Velocity Dilution Of Precision"><td>VDOP</td>
            <td>{$sky['vdop']}</td></tr>
    </table>
</div>
<div style="float:left;margin:0 0 1ex 1em;">
    <table style="border-width:1px;border-style:solid;text-align:center;">
        <tr title="Unknown/undefined uncertainty">
            <th colspan="2" style="text-align:center">Error Estimates</th></tr>
        <tr title="Estimated Precision of Climb"><td>epc</td>
            <td>{$epc}</td></tr>
        <tr title="Estimated Precision 2D"><td>eph</td>
            <td>{$eph}</td></tr>
        <tr title="Estimated Precision Speed"><td>eps</td>
          <td>{$eps}</td></tr>
        <tr title="Estimated Precision Time"><td>ept</td><td>{$ept}</td></tr>
        <tr title="Estimated Precision X (Longitude)"><td>epx</td>
          <td>{$epx}</td></tr>
        <tr title="Estimated Precision Y (Latitude)"><td>epy</td>
          <td>{$epy}</td></tr>
        <tr title="Estimated Precision Vertical"><td>epv</td>
          <td>{$epv}</td></tr>
        <tr title="Spherical Error Probability (epe)"><td>sep</td>
          <td>{$sep}</td></tr>
        <tr title="Estimated ECEF Position Accuracy"><td>ecef pAcc</td>
          <td>{$ecefpAcc}</td></tr>
        <tr title="Estimated ECEF Velocity Accuracy"><td>ecef vAcc</td>
          <td>{$ecefvAcc}</td></tr>
    </table>
</div>

<div style="float:right;margin:0 0 1ex 1em;">
    <table style="border-width:1px;border-style:solid">
        <tr><th colspan="6" style="text-align:center">Satellites</th></tr>
        <tr><td colspan="6" style="text-align:center"
            >Seen {$nsv}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Used {$sats_used}
</td></tr>
        <tr><th></th><th>PRN</th>
            <th>Elv</th><th>Azm</th>
            <th>SNR</th><th>Used</th>
        </tr>
$sat    </table>
</div>

<div style="float:left;margin:0 0 1ex 1em;">
    <table style="border-width:1px;border-style:solid;text-align:center;">
        <tr><th colspan="5" style="text-align:center"
>Earth Centered Earth Fixed (ECEF)</th></tr>
        <tr><th></th><th>X</th><th>Y</th><th>Z</th><th>Acc</th></tr>
        <tr><td>Position</td>
            <td>{$ecefx}</td><td>{$ecefy}</td> <td>{$ecefz}</td></tr>
        <tr><td>Velocity</td>
            <td>{$ecefvx}</td><td>{$ecefvy}</td> <td>{$ecefvz}</td></tr>
    </table>
</div>
<!-- raw response:
{$resp}
-->
EOF;
        }

        $part_footer = <<<EOF

</div>  <!-- end div main -->
<div style="clear:both">
<br>
<hr>
{$footer}
<p class="administrivia">This script is distributed by the
<a href="@WEBSITE@">GPSD project</a>.</p>
</div>

</body>
</html>
EOF;

print $part_header . $part_sky . $part3 . $part_tpv_sky . $part_footer;

}

function write_json($resp){
        header('Content-Type: text/javascript');
        if (isset($_GET['jsonp']))
                print "{$_GET['jsonp']}({$resp})";
        else
                print $resp;
}

function write_config(){
        $f = fopen("gpsd_config.inc", "a");
        if (!$f)
                die("can't generate prototype config file. try running this script as root in DOCUMENT_ROOT");

        $buf = <<<EOB
<?PHP
\$title = 'My GPS Server';
\$server = 'localhost';
#\$advertise = 'localhost';
\$port = 2947;
\$autorefresh = 0; # number of seconds after which to refresh
# set to 1 if you want to have a google map,
# set it to 2 if you want a map based on opemstreetmap/openlayers (osm)
\$showmap = 0;
\$gmap_key = 'GetYourOwnGoogleKey'; # your google API key goes here
\$swap_ew = 0; # set to 1 for upward facing view (nonstandard)
\$open = 0; # set to 1 to show the form to change the GPSd server

## You can read the header, footer and blurb from a file...
# \$head = file_get_contents('/path/to/header.inc');
# \$body = file_get_contents('/path/to/body.inc');
# \$footer = file_get_contents('/path/to/footer.hinc');
# \$blurb = file_get_contents('/path/to/blurb.inc');

## ... or you can just define them here
\$head = '';
\$body = '';
\$footer = '';
\$blurb = <<<EOT
This is a
<a href="@WEBSITE@">gpsd</a>
server <span class="blink">located someplace</span>.

The hardware is a
<span class="blink">hardware description and link</span>.

This machine is maintained by
<a href="mailto:you@example.com">Your Name Goes Here</a>.<br>
EOT;

?>

EOB;
        fwrite($f, $buf);
        fclose($f);
}

function gen_gmap_head() {
    global $gmap_key;
    return <<<EOT
<script src="//maps.googleapis.com/maps/api/js?sensor=false"/>

<script>
 <!-- note that the google map API is commented out, it requires
an API KEY, and costs money to use!  As of March 2020 more info
here: https://developers.google.com/maps/gmp-get-started   -->

 <!--
    function Load() {
      var map = new google.maps.Map(
        document.getElementById('map'), {
          center: new google.maps.LatLng({$GLOBALS['lat']}, {$GLOBALS['lon']}),
          zoom: 13,
          mapTypeId: google.maps.MapTypeId.ROADMAP
      });

      var marker = new google.maps.Marker({
          position: new google.maps.LatLng({$GLOBALS['lat']}, {$GLOBALS['lon']}),
          map: map
      });

    }
    google.maps.event.addDomListener(window, 'load', initialize);

 -->

</script>
EOT;
}

# example code from: https://openlayers.org/en/latest/doc/quickstart.html
function gen_osm_head() {
    global $GPS;
    return <<< EOT

<link rel="stylesheet"
 href="https://cdn.jsdelivr.net/gh/openlayers/openlayers.github.io@master/en/v6.2.1/css/ol.css"
 type="text/css">
<script
 src="https://cdn.jsdelivr.net/gh/openlayers/openlayers.github.io@master/en/v6.2.1/build/ol.js"></script>

EOT;
}

function gen_osmmap_code() {
    return <<<EOT

<br>
    <div id="map" style="height:400px;"></div>
    <noscript>
        <span class='warning'>Sorry: you must enable javascript to view our
maps.</span><br>
    </noscript>

<script>
  var LonLat = ol.proj.fromLonLat([{$GLOBALS['lon']}, {$GLOBALS['lat']}])

  var stroke = new ol.style.Stroke({color: 'red', width: 2});

  var feature = new ol.Feature(new ol.geom.Point(LonLat))
  var x = new ol.style.Style({
    image: new ol.style.RegularShape({
      stroke: stroke,
      points: 4,
      radius: 10,
      radius2: 0,
      angle: 0.785397   // Pi / 4
      })
  })
  feature.setStyle(x)
  var source = new ol.source.Vector({
      features: [feature]
  });

  var vectorLayer = new ol.layer.Vector({
    source: source
  });

  var map = new ol.Map({
    target: 'map',
    layers: [
      new ol.layer.Tile({
        source: new ol.source.OSM()
      }),
      vectorLayer
    ],
    view: new ol.View({
      center: LonLat,
      zoom: 6
    })
  });

</script>

EOT;
}

function gen_map_code() {
    return <<<EOT

<br>
<div id="map"
  style="width:550px;height:400px;border:1px;border-style:solid;float:left;">
    Loading...
    <noscript>
        <span class='warning'>Sorry: you must enable javascript to view our
maps.</span><br>
    </noscript>
</div>

EOT;
}

?>
