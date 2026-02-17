# neighbor

The neighbor plugin enables the collection of interface and routing neighborships, which can then be used to generate a dynamic map of your network. 

Interface neighbors can be discovered using:

* CDP or LLDP
* IP Subnet information - i.e. interfaces in the same subnet are neighbors
* Interface descriptions using regular expressions

Routing protocol neighbors:
* OSPF
* BGP
* IS-IS

The collection of the various types of neighbor can be controlled both at a global level in the settings, or at a host level.

## Rules

In order to create a map of your network, a rule set needs to be defined which determines which devices you are interested in and the type of neighbor. Anyone familiar with the Automate plugin functionality should be comfortable with the interface as the code for the Neighbor plugin borrows heavily from this existing code base.

The "Neighbor Rules" link is found in the Automation menu group on the left of the console.

### Rule Example

In order to draw a map of a Core network, we could use the following example criteria to limit the hosts:
```
	h.description CONTAINS "-pe1"
OR 	h.description CONTAINS "-p1"
```
To limit the interfaces, we can select those with a certain description, using CDP, and having an IP address:
```
	xdp.interface_alias	begins with	CORE:
AND	xdp.type		matches		cdp
AND	xdp.neighbor_interface_ip	is not empty
```

To check that the rules are working correctly, the "Show Matching Devices" and "Show Matching Objects" links may be used.

## Installation

This plugin was developed on the v1.x versions of Cacti and is therefore unlikely to work correctly on the older 0.8.x versions.

The simplest installation is to untar the source code into your Cacti plugins source directory (e.g. /usr/share/cacti/plugins/).

Alternatively you may clone the github source directly into the plugins directory with the command:
```
cd /usr/share/cacti/plugins
git clone https://github.com/ahouston/plugin_neighbor.git neighbor
```
Please note that the directory must be called 'neighbor'.

## Bugs and Feature Enhancements
   
Bug and feature enhancements for the neighbor plugin are handled in GitHub.
All reasonable feature requests will be entertained!

## ChangeLog

--- 0.1 ---
* Preliminary Commit
