<?php

#$CSK: gpsd.php,v 1.39 2006/11/21 22:31:10 ckuethe Exp $

# Copyright (c) 2006 Chris Kuethe <chris.kuethe@gmail.com>
#
# Permission to use, copy, modify, and distribute this software for any
# purpose with or without fee is hereby granted, provided that the above
# copyright notice and this permission notice appear in all copies.
#
# THE SOFTWARE IS PROVIDED "AS IS" AND THE AUTHOR DISCLAIMS ALL WARRANTIES
# WITH REGARD TO THIS SOFTWARE INCLUDING ALL IMPLIED WARRANTIES OF
# MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR
# ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES
# WHATSOEVER RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN
# ACTION OF CONTRACT, NEGLIGENCE OR OTHER TORTIOUS ACTION, ARISING OUT OF
# OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.

global $head, $blurb, $title, $googlemap, $autorefresh, $footer, $gmap_key;
global $GPS, $server, $advertise, $port, $magic, $swap_ew, $magic;
$magic = 1; # leave this set to 1

$mytitle = 'My GPS Server';
$myserver = '127.0.0.1';
$myadvertise = '127.0.0.1';
$myport = 2947;
$myautorefresh = 0; # number of seconds after which to refresh
$mygooglemap = 0; # set to 1 if you want to have a google map
$mygmap_key = 'GetYourOwnGoogleKey'; # your google API key goes here
$myswap_ew = 0; # set to 1 if you don't understand projections





# sample data
$resp = 'GPSD,S=2,P=53.527167 -113.530168,A=704.542,M=3,Q=10 1.77 0.80 0.66 0.61 1.87,Y=MID9 1158081774.000000 12:25 24 70 42 1:4 13 282 36 1:23 87 196 48 1:6 9 28 29 1:16 54 102 47 1:20 34 190 45 1:2 12 319 36 1:13 52 292 46 1:24 12 265 0 0:1 8 112 41 1:27 16 247 40 1:122 23 213 31 0:';

# if we're passing in a query, let's unpack and use it
if (isset($_GET['imgdata']) && isset($_GET['op']) && ($_GET['op'] == 'view')){
	$resp = base64_decode($_GET['imgdata']);
	if ($resp){
		gen_image($resp);
		exit(0);
	}
} else {
 
	if (isset($myserver))
		if (!preg_match('/[^a-zA-Z0-9\.-]/', $myserver))
			$server = $myserver;
			
	if (isset($myport))
		if (!preg_match('/\D/', $myport) && ($myport>0) && ($myport<65536))
			$port = $myport; 
         

	if ($magic){
		$sock = @fsockopen($server, $port, $errno, $errstr, 2);
		@fwrite($sock, "J=1,W=1\n");	# watcher mode and buffering
		$z = 0;
		do {
			$resp = @fread($sock, 384);
			$z++;
		} while ((strncmp($resp, 'GPSD,O=', 7) || (
			strncmp($resp, 'GPSD,O=', 7) == 0 &&
			strlen($resp) <= 8)) &&
			$z < 6);
		@fwrite($sock, "SPAMQY\n");	# Query what we actually want
		# the O report doesn't give us satellite usage or DOP
		$resp = @fread($sock, 384);
		@fclose($sock);
        
	}
}

if (isset($_GET['op']) && ($_GET['op'] == 'view')){
	//gen_image($resp);
	echo "ops..";
} else {
	
	parse_pvt($resp);
	//write_html($resp);
}

exit(0);

function clearstate(){
	global $GPS;

	$GPS['loc'] = '';
	$GPS['alt'] = 'Unavailable';
	$GPS['lat'] = 'Unavailable';
	$GPS['lon'] = 'Unavailable';
	$GPS['sat'] = 'Unavailable';
	$GPS['hdop'] = 'Unavailable';
	$GPS['dgps'] = 'Unavailable';
	$GPS['fix'] = 'Unavailable';
	$GPS['gt'] = '';
	$GPS['lt'] = '';
}

function dfix($x, $y, $z){
	if ($x < 0){
		$x = sprintf("%f %s", -1 * $x, $z);
	} else {
		$x = sprintf("%f %s", $x, $y);
	}
	return $x;
}

function parse_pvt($resp){
	global $GPS, $magic; $myalt; $mylat; $mylon; $mylatlon;
    	
	clearstate();
          
	if (strlen($resp)){
	
		$GPS['fix']  = 'No';
		if (preg_match('/M=(\d),/', $resp, $m)){
			switch ($m[1]){
			case 2:
				$GPS['fix']  = '2D';
				break;
			case 3:
				$GPS['fix']  = '3D';
				break;
			case 4:
				$GPS['fix']  = '3D (PPS)';
				break;
			default:
				$GPS['fix']  = "No";
			}
		}

		if (preg_match('/S=(\d),/', $resp, $m)){
			$GPS['fix'] .= ' (';
			if ($m[1] != 2){
				$GPS['fix'] .= 'not ';
			}
			$GPS['fix'] .= 'DGPS corrected)';
		}

		if (preg_match('/A=([0-9\.-]+),/', $resp, $m)){
			$GPS['alt'] = ($m[1] . ' m');
			$myalt = $GPS['alt']; 
		}

		if (preg_match('/P=([0-9\.-]+) ([0-9\.-]+),/',
		    $resp, $m)){
			$GPS['lat'] = $m[1]; $GPS['lon'] = $m[2];
            $mylat = $GPS['lat']; $mylon = $GPS['lon'];
		}

		if (preg_match('/Q=(\d+) ([0-9\.]+) ([0-9\.]+) ([0-9\.]+) ([0-9\.]+)/', $resp, $m)){
			$GPS['sat']  = $m[1]; $GPS['gdop'] = $m[2];
			$GPS['hdop'] = $m[3]; $GPS['vdop'] = $m[4];
		}

		if ($GPS['lat'] != 'Unavailable' &&
		    $GPS['lon'] != 'Unavailable'){
			$GPS['lat'] = dfix($GPS['lat'], 'N', 'S');
			$GPS['lon'] = dfix($GPS['lon'], 'E', 'W');

			$GPS['loc'] = sprintf('at %s / %s',$GPS['lat'], $GPS['lon']);
			
			//echo $GPS['loc'];
			
			$mylatlon = sprintf('%s,%s', $mylat, $mylon);
            echo $mylatlon;
			//printf('%s,%s', $mylat, $mylon);
			
		}

		if (preg_match('/^No/', $GPS['fix'])){
			clearstate();
		}
	} else
		$GPS['loc'] = '';

	$GPS['gt'] = time();
	$GPS['lt'] = date("r", $GPS['gt']);
	$GPS['gt'] = gmdate("r", $GPS['gt']);
}
?>
