<?php
/**
 * moOde audio player (C) 2014 Tim Curtis
 * http://moodeaudio.org
 *
 * This Program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3, or (at your option)
 * any later version.
 *
 * This Program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/sql.php';

// Configure network interfaces
function cfgNetIfaces() {
	$dbh = sqlConnect();

	// Write interfaces file
	$fp = fopen('/etc/network/interfaces', 'w');
	$data  = "#########################################\n";
	$data .= "# This file is automatically generated by\n";
	$data .= "# the player Network configuration page. \n";
	$data .= "#########################################\n\n";
	$data  .= "# interfaces(5) file used by ifup(8) and ifdown(8)\n\n";
	$data  .= "# Please note that this file is written to be used with dhcpcd\n";
	$data  .= "# For static IP, consult /etc/dhcpcd.conf and 'man dhcpcd.conf'\n\n";
	$data  .= "# Include files from /etc/network/interfaces.d:\n";
	$data  .= "source-directory /etc/network/interfaces.d\n";
	fwrite($fp, $data);
	fclose($fp);

	// Write dhcpcd.conf
	// eth0
	$fp = fopen('/etc/dhcpcd.conf', 'w');
	$data  = "#########################################\n";
	$data .= "# This file is automatically generated by\n";
	$data .= "# the player Network configuration page. \n";
	$data .= "#########################################\n\n";
	$data .= "hostname\n";
	$data .= "clientid\n";
	$data .= "persistent\n";
	$data .= "option rapid_commit\n";
	$data .= "option domain_name_servers, domain_name, domain_search, host_name\n";
	$data .= "option classless_static_routes\n";
	$data .= "option ntp_servers\n";
	$data .= "option interface_mtu\n";
	$data .= "require dhcp_server_identifier\n";
	$data .= "slaac private\n";

	// Read network config: [0] = eth0, [1] = wlan0, [2] = apd0
	$cfgNetwork = sqlQuery('SELECT * FROM cfg_network', $dbh);

	if ($cfgNetwork[0]['method'] == 'static') {
		// eth0 static
		$data .= "interface eth0\n";
		$data .= 'static ip_address=' . $cfgNetwork[0]['ipaddr'] . '/' . $cfgNetwork[0]['netmask'] . "\n";
		$data .= 'static routers=' . $cfgNetwork[0]['gateway'] . "\n";
		$data .= 'static domain_name_servers=' . $cfgNetwork[0]['pridns'] . ' ' . $cfgNetwork[0]['secdns'] . "\n";
	}
	if ($cfgNetwork[1]['method'] == 'static') {
		// wlan0 static
		$data .= "interface wlan0\n";
		$data .= 'static ip_address=' . $cfgNetwork[1]['ipaddr'] . '/' . $cfgNetwork[1]['netmask'] . "\n";
		$data .= 'static routers=' . $cfgNetwork[1]['gateway'] . "\n";
		$data .= 'static domain_name_servers=' . $cfgNetwork[1]['pridns'] . ' ' . $cfgNetwork[1]['secdns'] . "\n";
	}
	if ($cfgNetwork[1]['wlanssid'] == 'None (activates AP mode)') {
		// wlan0 AP mode
		$data .= "#AP mode\n";
		$data .= "interface wlan0\n";
		$data .= "static ip_address=172.24.1.1/24\n";
		$data .= "nohook wpa_supplicant";
	} else {
		// No AP mode
		$data .= "#AP mode\n";
		$data .= "#interface wlan0\n";
		$data .= "#static ip_address=172.24.1.1/24\n";
		$data .= "#nohook wpa_supplicant";
	}
	fwrite($fp, $data);
	fclose($fp);

	// Write wpa_supplicant.conf
	$fp = fopen('/etc/wpa_supplicant/wpa_supplicant.conf', 'w');
	$data  = "#########################################\n";
	$data .= "# This file is automatically generated by\n";
	$data .= "# the player Network configuration page. \n";
	$data .= "#########################################\n\n";
	$data .= 'country=' . $cfgNetwork[1]['wlan_country'] . "\n";
	$data .= "ctrl_interface=DIR=/var/run/wpa_supplicant GROUP=netdev\n";
	$data .= "update_config=1\n\n";
	if ($cfgNetwork[1]['wlanssid'] != 'None (activates AP mode)') {
		// Primary SSID: first block and highest priority
		$data .= "network={\n";
		$data .= 'ssid=' . '"' . $cfgNetwork[1]['wlanssid'] . '"' . "\n";
		$data .= 'priority=100' . "\n";
		$data .= "scan_ssid=1\n"; // Scan even if SSID is hidden
		if ($cfgNetwork[1]['wlansec'] == 'wpa') {
			// WPA/WPA2 Personal
			$data .= 'psk=' . $cfgNetwork[1]['wlan_psk'] . "\n";
		} else if ($cfgNetwork[1]['wlansec'] == 'wpa23') {
			// WPA3 Personal Transition Mode
			$data .= 'psk=' . $cfgNetwork[1]['wlan_psk'] . "\n";
			$data .= 'key_mgmt=WPA-PSK-SHA256' . "\n";
			$data .= 'ieee80211w=2' . "\n";
		} else if ($cfgNetwork[1]['wlansec'] == 'wpa3') {
			// WPA3 Personal
			// TBD
		} else {
			// No security
			$data .= "key_mgmt=NONE\n";
		}
		$data .= "}\n";

		// Add saved SSID's
		$cfgSsid = sqlQuery("SELECT * FROM cfg_ssid WHERE ssid != '" . SQLite3::escapeString($cfgNetwork[1]['wlanssid']) . "'", $dbh);
		foreach($cfgSsid as $row) {
			$data .= "network={\n";
			$data .= 'ssid=' . '"' . $row['ssid'] . '"' . "\n";
			$data .= 'priority=10' . "\n";
			$data .= "scan_ssid=1\n"; // Scan even if SSID is hidden
			if ($row['sec'] == 'wpa') {
				// WPA/WPA2 Personal
				$data .= 'psk=' . $cfgNetwork[1]['wlan_psk'] . "\n";
			} else if ($row['sec'] == 'wpa23') {
				// WPA3 Personal Transition Mode
				$data .= 'psk=' . $cfgNetwork[1]['wlan_psk'] . "\n";
				$data .= 'key_mgmt=WPA-PSK-SHA256' . "\n";
				$data .= 'ieee80211w=2' . "\n";
			} else if ($row['sec'] == 'wpa3') {
				// WPA3 Personal
				// TBD
			} else {
				// No security
				$data .= "key_mgmt=NONE\n";
			}
			$data .= "}\n";
		}

	}
	fwrite($fp, $data);
	fclose($fp);

	// Set regulatory domain
	sysCmd('iw reg set "' . $cfgNetwork[1]['wlan_country'] . '" >/dev/null 2>&1');

	// TODO: Enhance rule set to enable general purpose hotspot.
	// Write /etc/nftables.conf
	$fp = fopen('/etc/nftables.conf', 'w');
	$data  = "#########################################\n";
	$data .= "# This file is automatically generated by\n";
	$data .= "# the player Network configuration page. \n";
	$data .= "#########################################\n\n";
	$data .= '#!/usr/sbin/nft -f

flush ruleset

table ip filter {
        # Allow all packets inbound
        chain IMPUT {
                type filter hook input priority 0; policy accept;
        }
        # Forwad packets from WLAN to LAN, and LAN to WLAN if WLAN initiated the connection
        chain FORWARD {
                type filter hook forward priority 0; policy accept;
                iifname "wlan0" oifname "eth0" accept
                iifname "eth0" oifname "wlan0" ct state established accept
                iifname "eth0" oifname "wlan0" ct state related accept
                iifname "eth0" oifname "wlan0" drop
        }
        # Allow all packets outbound
        chain OUTPUT {
                type filter hook output priority 100; policy accept;
        }
}

table ip nat {
        # Accept all packets for prerouting
        chain PREROUTING {
            type nat hook prerouting priority 0; policy accept;
        }
        # Accept and masquerade all packets for postrouting to outbound LAN
        chain POSTROUTING {
            type nat hook postrouting priority 100; policy accept;
            oifname "eth0" masquerade

}';

	fwrite($fp, $data);
	fclose($fp);

	// Configure packet forwarding for AP Router mode
	if ($cfgNetwork[2]['wlan_router'] == 'On') {
		sysCmd('sed -i "s/^#net.ipv4.ip_forward/net.ipv4.ip_forward/" /etc/sysctl.conf');
	} else {
		sysCmd('sed -i "s/^net.ipv4.ip_forward/#net.ipv4.ip_forward/" /etc/sysctl.conf');
	}
}

// Configure hostapd conf
function cfgHostApd() {
	// Read network config: [0] = eth0, [1] = wlan0, [2] = apd0
	$cfgNetwork = sqlQuery('SELECT * FROM cfg_network', sqlConnect());

	$file = '/etc/hostapd/hostapd.conf';
	$fp = fopen($file, 'w');

	$data  = "#########################################\n";
	$data .= "# This file is automatically generated by\n";
	$data .= "# the player Network configuration page. \n";
	$data .= "#########################################\n\n";

	$data .= "# Interface and driver\n";
	$data .= "interface=wlan0\n";
	$data .= "driver=nl80211\n\n";

	$data .= "# Wireless settings\n";
	$data .= "ssid=" . $cfgNetwork[2]['wlanssid'] . "\n";
	$data .= "hw_mode=g\n";
	$data .= "channel=" . $cfgNetwork[2]['wlan_channel'] . "\n\n";

	$data .= "# Security settings\n";
	$data .= "macaddr_acl=0\n";
	$data .= "auth_algs=1\n";
	$data .= "ignore_broadcast_ssid=0\n";
	$data .= "wpa=2\n";
	$data .= "wpa_key_mgmt=WPA-PSK\n";
	$data .= 'wpa_psk=' . $cfgNetwork[2]['wlan_psk'] . "\n";
	$data .= "rsn_pairwise=CCMP\n";

	fwrite($fp, $data);
	fclose($fp);
}

function activateApMode() {
	sysCmd('sed -i "/AP mode/,/$p/ d" /etc/dhcpcd.conf');
	sysCmd('sed -i "$ a#AP mode\ninterface wlan0\nstatic ip_address=172.24.1.1/24\nnohook wpa_supplicant" /etc/dhcpcd.conf');
	sysCmd('systemctl daemon-reload');
	sysCmd('systemctl restart dhcpcd');
	sysCmd('systemctl start hostapd');
	sysCmd('systemctl start dnsmasq');
}

function resetApMode() {
	sysCmd('sed -i "/AP mode/,/$p/ d" /etc/dhcpcd.conf');
	sysCmd('sed -i "$ a#AP mode\n#interface wlan0\n#static ip_address=172.24.1.1/24\n#nohook wpa_supplicant" /etc/dhcpcd.conf');
}

// Wait up to timeout seconds for IP address to be assigned to the interface
function checkForIpAddr($iface, $timeoutSecs, $sleepTime = 2) {
	$maxLoops = $timeoutSecs / $sleepTime;
	for ($i = 0; $i < $maxLoops; $i++) {
		$ipAddr = sysCmd('ip addr list ' . $iface . " | grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
		if (!empty($ipAddr[0])) {
			break;
		} else {
			workerLog('worker: ' . $iface .' check '. $i . ' for IP address');
			sleep($sleepTime);
		}
	}

	return $ipAddr;
}

function getHostIp() {
	$eth0ip = '';
	$wlan0ip = '';

	// Check both interfaces
	$eth0 = sysCmd('ip addr list | grep eth0');
	if (!empty($eth0)) {
		$eth0ip = sysCmd("ip addr list eth0 | grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
	}
	$wlan0 = sysCmd('ip addr list | grep wlan0');
	if (!empty($wlan0)) {
		$wlan0ip = sysCmd("ip addr list wlan0 | grep \"inet \" |cut -d' ' -f6|cut -d/ -f1");
	}

	// TODO: IF AP mode active set $hostip = 172.24.1.1
	// Use Ethernet address if present
	if (!empty($eth0ip[0])) {
		$hostIp = $eth0ip[0];
	} else if (!empty($wlan0ip[0])) {
		$hostIp = $wlan0ip[0];
	} else {
		$hostIp = '127.0.0.1';
	}

	return $hostIp;
}

function genWpaPSK($ssid, $passphrase) {
	$fh = fopen('/tmp/passphrase', 'w');
	fwrite($fh, $passphrase . "\n");
	fclose($fh);

	$result = sysCmd('wpa_passphrase "' . $ssid . '" < /tmp/passphrase');
	sysCmd('rm /tmp/passphrase');

	$psk = explode('=', $result[4]);
	return $psk[1];
}

// Pi integrated WiFi adapter enable/disable
function ctlWifi($ctl) {
	$cmd = $ctl == '0' ? 'sed -i /disable-wifi/c\dtoverlay=disable-wifi ' . '/boot/config.txt' :
		'sed -i /disable-wifi/c\#dtoverlay=disable-wifi ' . '/boot/config.txt';
	sysCmd($cmd);
}

// Pi integrated Bluetooth adapter enable/disable
function ctlBt($ctl) {
	$cmd = $ctl == '0' ? 'sed -i /disable-bt/c\dtoverlay=disable-bt ' . '/boot/config.txt' :
		'sed -i /disable-bt/c\#dtoverlay=disable-bt ' . '/boot/config.txt';
	sysCmd($cmd);
}
