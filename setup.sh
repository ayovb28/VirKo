#!/bin/bash
sleep 15
docker exec virko-server-onlyoffice-1 sed -i 's/"allowPrivateIPAddress": false/"allowPrivateIPAddress": true/g' /etc/onlyoffice/documentserver/default.json
docker exec virko-server-onlyoffice-1 sed -i 's/"allowMetaIPAddress": false/"allowMetaIPAddress": true/g' /etc/onlyoffice/documentserver/default.json
docker restart virko-server-onlyoffice-1
