#!/bin/bash
sleep 15

# Instal·lar mòduls Node-RED si no estan
docker exec virko-server-nodered-1 npm install node-red-node-mysql node-red-dashboard

# ONLYOFFICE - permetre IPs privades
docker exec virko-server-onlyoffice-1 sed -i 's/"allowPrivateIPAddress": false/"allowPrivateIPAddress": true/g' /etc/onlyoffice/documentserver/default.json
docker exec virko-server-onlyoffice-1 sed -i 's/"allowMetaIPAddress": false/"allowMetaIPAddress": true/g' /etc/onlyoffice/documentserver/default.json
docker restart virko-server-onlyoffice-1
docker restart virko-server-nodered-1
