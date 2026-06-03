#!/bin/bash
sleep 60

# Restaurar flows_cred.json al contenidor
docker cp /opt/virko-server/nodered_data/flows_cred.json virko-server-nodered-1:/data/flows_cred.json

# Instal·lar mòduls Node-RED
docker exec virko-server-nodered-1 npm install node-red-node-mysql node-red-dashboard

# ONLYOFFICE - permetre IPs privades
docker exec virko-server-onlyoffice-1 sed -i 's/"allowPrivateIPAddress": false/"allowPrivateIPAddress": true/g' /etc/onlyoffice/documentserver/default.json
docker exec virko-server-onlyoffice-1 sed -i 's/"allowMetaIPAddress": false/"allowMetaIPAddress": true/g' /etc/onlyoffice/documentserver/default.json
docker restart virko-server-onlyoffice-1
docker restart virko-server-nodered-1
