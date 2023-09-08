const express = require('express');
const { readFile } = require('fs');
const { exec } = require("child_process");
const bodyParser = require('body-parser');
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
    readFile('/var/www/node/src/html/nodeSubfolder.html', 'utf8', (err, html) => {
        if (err) {
            console.log(err);
            response.status(500).send('server error\n');
        }

        response.send(process.env.ROOT_PASS);

        log("GET /nodeSubfolder", request, "Success");
    });
});

app.post('/allow', (req, res) => {
    let addr = req.body.addr;

    let command = `echo "${process.env.ROOT_PASS}" | sudo -S ufw insert 1 allow from ${addr} proto tcp to any port 25565`;
    exec(command);

    res.send("true");

    // res.send(`Done: ${addr}\n`);
    log(`POST ${addr} /allow`, req, `Added: ${addr}`);
});

app.listen(port, () => console.log(`Server started on port: ${port}\n`));