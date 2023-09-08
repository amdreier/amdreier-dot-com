const express = require('express');
const { readFile } = require('fs');
const { exec } = require("child_process");
const bodyParser = require('body-parser');
const e = require('express');
require('dotenv').config()
const app = express();
const port = 3000;

function log(type, request, response) {
    console.log(`IP: ${request.header('x-forwarded-for')}`);
    console.log(`Request: ${type}`);
    console.log(`Response: ${response}`);
    console.log("");
};

app.use(bodyParser.json());
app.use(bodyParser.urlencoded({extended: false}));


app.get('/', (request, response) => {
    readFile('/var/www/node/src/html/nodeTest.html', 'utf8', (err, html) => {
        if (err) {
            console.log(err);
            response.status(500).send('server error\n');
        }

        response.send(html);

        log("GET /", request, "Success");
    });
});

app.get('/nodeSubfolder', (request, response) => {
    readFile('/var/www/node/src/html/nodeSubfolder.html', 'utf8', (err, html) => {
        if (err) {
            console.log(err);
            response.status(500).send('server error\n');
        }

        response.send(html);

        log("GET /nodeSubfolder", request, "Success");
    });
});

app.get('/creds', (request, response) => {
    let req_ip = request.socket.remoteAddress;

    if (req_ip == '::ffff:10.0.0.40') {
        response.send(process.env.ROOT_PASS);
        log("GET /creds", request, "Allowed");
    } else {
        log(`GET /creds from ${req_ip}`, request, "Denied");
    }
});

app.post('/allow', (req, res) => {
    let addr = req.body.addr;
    let user = req.body.user;
    let req_ip = req.socket.remoteAddress;

    if (req_ip == '::ffff:10.0.0.40') {

        let command = `echo "${process.env.ROOT_PASS}" | sudo -S ufw insert 1 allow from ${addr} proto tcp to any port 25565 comment '${user}'`;
        exec(command);

        res.send("true");

        log(`POST ${addr}, ${user} /allow`, req, `Added: ${addr}, ${user}`);
    } else {
        log(`POST ${addr}, ${user} /allow from ${req_ip}`, req, `Denied`);
    }
});

app.listen(port, () => console.log(`Server started on port: ${port}\n`));